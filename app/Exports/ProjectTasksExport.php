<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class ProjectTasksExport implements FromCollection, WithHeadings
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * Return data for export
     */
    public function collection(): Collection
    {
        return collect($this->rows);
    }

    /**
     * Add headings from array keys
     */
    public function headings(): array
    {
        if (empty($this->rows)) {
            return [];
        }

        return array_keys($this->rows[0]);
    }
}
