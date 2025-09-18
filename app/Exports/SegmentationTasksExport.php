<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * One row per SEGMENT (task metadata repeated).
 * Columns: task info + segment start/end + label info + is_custom.
 */
class SegmentationTasksExport implements FromCollection, WithHeadings
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        if (empty($this->rows)) {
            return [];
        }
        return array_keys($this->rows[0]);
    }
}
