<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AudioFile\ImportLinksRequest;
use App\Http\Requests\Admin\AudioFile\IndexAudioFilesRequest;
use App\Http\Requests\Admin\AudioFile\StoreAudioFilesRequest;
use App\Http\Requests\Admin\AudioFile\UpdateAudioFileRequest;
use App\Models\AudioFile;
use App\Models\Project;
use App\Services\AudioFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class AudioFileController extends Controller
{
    public function __construct(private AudioFileService $service)
    {
    }

    public function index(IndexAudioFilesRequest $request, Project $project)
    {
        $filters = $request->filters();

        $query = AudioFile::with('uploader')
            ->forProject($project->id)
            ->search($filters['q'] ?? null)
            ->when($filters['uploader'] ?? null, fn($q, $v) => $q->where('uploaded_by', $v))
            ->when($filters['mime'] ?? null, fn($q, $v) => $q->where('mime_type', 'like', "%$v%"))
            ->when($filters['date_from'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when(isset($filters['size_min']), fn($q) => $q->where('file_size', '>=', (int) $filters['size_min']))
            ->when(isset($filters['size_max']), fn($q) => $q->where('file_size', '<=', (int) $filters['size_max']))
            ->when(isset($filters['dur_min']), fn($q) => $q->where('duration', '>=', (float) $filters['dur_min']))
            ->when(isset($filters['dur_max']), fn($q) => $q->where('duration', '<=', (float) $filters['dur_max']));

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $pg = $query->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        // SAME SHAPE AS PROJECTS: data + links + meta
        $audioFiles = [
            'data' => $pg->through(function (AudioFile $f) {
                return [
                    'id' => $f->id,
                    'original_filename' => $f->original_filename,
                    'stored_filename' => $f->stored_filename,
                    'file_path' => $f->file_path,
                    'file_size' => $f->file_size,
                    'mime_type' => $f->mime_type,
                    'duration' => $f->duration,
                    'url' => method_exists($f, 'getUrlAttribute')
                        ? $f->url
                        : ($f->file_path ? \Storage::disk('s3')->url($f->file_path) : null),
                    'created_at' => $f->created_at->toDateTimeString(),
                    'uploader' => $f->uploader ? [
                        'id' => $f->uploader->id,
                        'name' => trim(($f->uploader->first_name ?? '') . ' ' . ($f->uploader->last_name ?? '')) ?: $f->uploader->email,
                        'email' => $f->uploader->email,
                    ] : null,
                ];
            })->items(),
            'links' => $pg->linkCollection(),
            'meta' => [
                'current_page' => $pg->currentPage(),
                'last_page' => $pg->lastPage(),
                'per_page' => $pg->perPage(),
                'total' => $pg->total(),
                'from' => $pg->firstItem(),
                'to' => $pg->lastItem(),
            ],
        ];

        // Uploader list for filter (unchanged)
        $uploaders = AudioFile::forProject($project->id)
            ->join('users', 'users.id', '=', 'audio_files.uploaded_by')
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
            ->distinct()->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email,
                'email' => $u->email,
            ]);

        return Inertia::render('Admin/AudioFiles/Index', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'audioFiles' => $audioFiles, // ← important
            'filters' => [
                'q' => $filters['q'] ?? null,
                'uploader' => $filters['uploader'] ?? null,
                'mime' => $filters['mime'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'size_min' => $filters['size_min'] ?? null,
                'size_max' => $filters['size_max'] ?? null,
                'dur_min' => $filters['dur_min'] ?? null,
                'dur_max' => $filters['dur_max'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'uploaders' => $uploaders,
        ]);
    }


    public function store(Request $request, Project $project)
    {
        // dd($request->all(), $request->file('files'));
    
        $files = $request->file('files');
        $created = $this->service->uploadMany($project, $files, $request->user());
    
        return back()->with('success', count($created) . ' audio file(s) uploaded.');
    }
    

    public function import(ImportLinksRequest $request, Project $project)
    {
        $links = [];

        // 1) CSV/XLSX file path
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // If Excel is installed, parse properly
            if (class_exists(Excel::class)) {
                $rows = Excel::toArray(new class implements ToArray {
                    public function array(array $array)
                    {
                        return $array;
                    }
                }, $file);

                // get first sheet
                $sheet = $rows[0] ?? [];
                foreach ($sheet as $row) {
                    // Accept first non-empty cell or "url" column if header present
                    if (is_array($row)) {
                        $candidate = $row['url'] ?? ($row[0] ?? null);
                        if ($candidate)
                            $links[] = $candidate;
                    }
                }
            } else {
                // Fallback: naive CSV
                $fh = fopen($file->getRealPath(), 'r');
                while (($row = fgetcsv($fh)) !== false) {
                    if (!empty($row[0]))
                        $links[] = $row[0];
                }
                fclose($fh);
            }
        }

        // 2) Or pasted links (newline-separated)
        if ($request->filled('links')) {
            $pasted = preg_split('/\r\n|\r|\n/', trim($request->input('links')));
            foreach ($pasted as $ln) {
                $ln = trim($ln);
                if ($ln !== '')
                    $links[] = $ln;
            }
        }

        $links = array_values(array_unique($links));
        if (empty($links)) {
            return back()->with('error', 'No links provided.');
        }

        $created = $this->service->importS3Links($project, $links, $request->user());

        return back()->with('success', count($created) . ' audio file(s) imported.');
    }

    public function update(UpdateAudioFileRequest $request, Project $project, AudioFile $audioFile)
    {
        abort_unless($audioFile->project_id === $project->id, 404);

        $audioFile->update($request->validated());

        return back()->with('success', 'Audio file updated.');
    }

    public function destroy(Request $request, Project $project, AudioFile $audioFile)
    {
        abort_unless($audioFile->project_id === $project->id, 404);

        // optional: guard if there are tasks referencing this file
        if ($audioFile->tasks()->exists()) {
            return back()->with('error', 'Cannot delete: audio file is used by tasks.');
        }

        // delete from S3 then DB
        if ($audioFile->file_path && Storage::disk('s3')->exists($audioFile->file_path)) {
            Storage::disk('s3')->delete($audioFile->file_path);
        }
        $audioFile->delete();

        return back()->with('success', 'Audio file deleted.');
    }

    public function bulkDestroy(Request $request, Project $project)
    {
        $ids = $request->input('ids', []);
        if (empty($ids))
            return back()->with('error', 'No items selected.');

        $files = AudioFile::whereIn('id', $ids)->where('project_id', $project->id)->get();

        foreach ($files as $audioFile) {
            if ($audioFile->tasks()->exists())
                continue; // skip files in use
            if ($audioFile->file_path && Storage::disk('s3')->exists($audioFile->file_path)) {
                Storage::disk('s3')->delete($audioFile->file_path);
            }
            $audioFile->delete();
        }

        return back()->with('success', 'Selected audio files deleted where possible.');
    }
}
