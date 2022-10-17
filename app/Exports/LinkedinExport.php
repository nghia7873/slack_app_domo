<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class LinkedinExport implements FromArray
{
    public $array;

    public function __construct($array)
    {
        $this->array = $array;
    }

    public function array(): array
    {
        return $this->array;
    }
}
