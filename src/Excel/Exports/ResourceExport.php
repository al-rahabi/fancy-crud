<?php

namespace FancyCrud\Excel\Exports;

use FancyCrud\Excel\ExcelHeader;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Turns a query plus a list of {@see ExcelHeader}s into a sheet.
 *
 * `FromQuery` makes Excel read the query with `chunk()`, so the export is bounded
 * by the chunk size rather than by the number of rows.
 */
class ResourceExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithStyles, WithCustomChunkSize, ShouldAutoSize
{
    protected Builder $builder;

    /** @var ExcelHeader[] */
    protected array $headers;

    protected int $chunkSize;

    /**
     * @param ExcelHeader[] $headers
     */
    public function __construct(Builder $builder, array $headers, int $chunkSize = 500)
    {
        $this->builder = $builder;
        $this->headers = array_values($headers);
        $this->chunkSize = max(1, $chunkSize);
    }

    public function query(): Builder
    {
        return $this->builder;
    }

    public function headings(): array
    {
        return array_map(fn(ExcelHeader $header) => $header->getLabel(), $this->headers);
    }

    /**
     * @param Model $row
     */
    public function map($row): array
    {
        return array_map(
            fn(ExcelHeader $header) => $this->toCellValue($header->exportValue($row)),
            $this->headers
        );
    }

    /**
     * Column letter => number format, for the types that need one.
     */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->headers as $index => $header) {
            if ($format = $header->numberFormat()) {
                $formats[Coordinate::stringFromColumnIndex($index + 1)] = $format;
            }
        }

        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * A DateTime has to become an Excel serial to land in a real date cell; the
     * column format from `columnFormats()` renders it.
     */
    protected function toCellValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? ExcelDate::PHPToExcel($value) : $value;
    }
}
