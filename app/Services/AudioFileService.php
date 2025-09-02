<?php

namespace App\Services;

use App\Models\AudioFile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AudioFileService
{
    /**
     * Upload multiple local files to S3 -> create AudioFile rows
     */
    public function uploadMany(Project $project, array $files, User $uploader): array
    {
        $created = [];
        foreach ($files as $file) {
            /** @var UploadedFile $file */
    
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = time() . '_' . Str::random(10) . '.' . $ext;
            $dir = "projects/{$project->id}/audio";
            $storedPath = Storage::disk('s3')->putFileAs($dir, $file, $filename);
            $size = $file->getSize();
            $mime = $file->getClientMimeType();
            $duration = $this->probeDurationFromS3($storedPath);
    
            $created[] = AudioFile::create([
                'project_id'        => $project->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename'   => $filename,
                'file_path'         => $storedPath,
                'file_size'         => $size,
                'mime_type'         => $mime,
                'duration'          => $duration,
                'metadata'          => null,
                'uploaded_by'       => $uploader->id,
            ]);
        }
    
        return $created;
    }
    
    

    /**
     * Import S3 links (CSV/XLSX/textarea), supports links already in our bucket or remote S3.
     * If link points to our bucket, we only register it. Otherwise we copy it into our bucket.
     */
    public function importS3Links(Project $project, array $links, User $uploader): array
    {
        $created = [];
    
        $bucket = config('filesystems.disks.s3.bucket');
        $bucketUrl = rtrim(config('filesystems.disks.s3.url') ?? '', '/');
    
        foreach ($links as $raw) {
            $url = trim($raw);
            if ($url === '') continue;
    
            $basename = basename(parse_url($url, PHP_URL_PATH));
            $ext = Str::afterLast($basename, '.') ?: 'bin';
            $storedName = Str::uuid() . '.' . $ext;
            $dest = "projects/{$project->id}/audio/{$storedName}";
    
            // Is it already in OUR bucket? (robust host check)
            $host = parse_url($url, PHP_URL_HOST);
            $path = ltrim((string)parse_url($url, PHP_URL_PATH), '/');
            $isOurBucket = $bucket && $host && str_contains($host, "{$bucket}.s3.");
    
            if ($bucketUrl && Str::startsWith($url, $bucketUrl)) {
                $isOurBucket = true;
            }
    
            try {
                if ($isOurBucket) {
                    // Just register existing object (no copy)
                    $key = $path; // e.g. "projects/1/audio/xyz.mp3"
                    $size = \Storage::disk('s3')->exists($key) ? \Storage::disk('s3')->size($key) : null;
                    $mime = \Storage::disk('s3')->exists($key) ? \Storage::disk('s3')->mimeType($key) : null;
                    $duration = $this->probeDurationFromS3($key);
    
                    $created[] = AudioFile::create([
                        'project_id'        => $project->id,
                        'original_filename' => $basename,
                        'stored_filename'   => $basename,
                        'file_path'         => $key,
                        'file_size'         => $size,
                        'mime_type'         => $mime,
                        'duration'          => $duration,
                        'metadata'          => null,
                        'uploaded_by'       => $uploader->id,
                    ]);
                } else {
                    // Stream download to temp file, then upload to S3 (works even if allow_url_fopen=Off)
                    $tmp = tempnam(sys_get_temp_dir(), 's3i_');
                    if (!$tmp) throw new \RuntimeException('Temp file failed');
    
                    $response = Http::accept('*/*')->sink($tmp)->get($url);
                    if (!$response->successful()) {
                        @unlink($tmp);
                        throw new \RuntimeException("HTTP {$response->status()} for $url");
                    }
    
                    \Storage::disk('s3')->put($dest, fopen($tmp, 'r'));
                    @unlink($tmp);
    
                    $size = \Storage::disk('s3')->size($dest);
                    $mime = \Storage::disk('s3')->mimeType($dest);
                    $duration = $this->probeDurationFromS3($dest);
    
                    $created[] = AudioFile::create([
                        'project_id'        => $project->id,
                        'original_filename' => $basename,
                        'stored_filename'   => $storedName,
                        'file_path'         => $dest,
                        'file_size'         => $size,
                        'mime_type'         => $mime,
                        'duration'          => $duration,
                        'metadata'          => null,
                        'uploaded_by'       => $uploader->id,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning('Import S3 link failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }
    
        return $created;
    }

    /**
     * Best-effort duration probe using getID3 (if installed).
     * Reads a small temp stream locally to analyze.
     */
    private function probeDurationFromS3(string $key): ?float
    {
        try {
            if (!class_exists(\getID3::class)) return null;

            $tmp = tempnam(sys_get_temp_dir(), 'aud_');
            $stream = Storage::disk('s3')->readStream($key);
            if (!$stream || !$tmp) return null;

            $out = fopen($tmp, 'w');
            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            $getID3 = new \getID3();
            $data = $getID3->analyze($tmp);
            @unlink($tmp);

            $sec = Arr::get($data, 'playtime_seconds');
            return $sec ? round((float)$sec, 2) : null;
        } catch (\Throwable $e) {
            Log::debug('probeDurationFromS3 failed: '.$e->getMessage());
            return null;
        }
    }
}
