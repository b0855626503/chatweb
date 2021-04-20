<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMapping;
use Yajra\DataTables\Exports\DataTablesCollectionExport;

class UsersExport extends DataTablesCollectionExport implements WithMapping
{
    public function headings(): array
    {
        return [
            'Date Regis',
            'Firstname',
            'LastName',
            'Line ID',
            'Mobile',
        ];
    }

    public function map($row): array
    {
        return [
            $row['date_regis'],
            $row['firstname'],
            $row['lastname'],
            $row['line_id'],
            $row['tel'],
        ];
    }
}
