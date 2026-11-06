<?php

namespace FancyCrud\Excel\Exports;

use FancyCrud\Excel\ExcelHeader;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The blank sheet handed to the customer: the labelled columns of every
 * importable header plus one example row showing the expected shape.
 */
class TemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /** @var ExcelHeader[] */
    protected array $headers;

    /**
     * @param  ExcelHeader[]  $headers
     */
    public function __construct(array $headers)
    {
        $this->headers = array_values($headers);
    }

    public function headings(): array
    {
        return array_map(fn (ExcelHeader $header) => $header->getLabel(), $this->headers);
    }

    public function array(): array
    {
        return [
            array_map(fn (ExcelHeader $header) => $header->exampleValue(), $this->headers),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
