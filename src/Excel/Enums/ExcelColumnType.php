<?php

namespace FancyCrud\Excel\Enums;

use FancyCrud\Contracts\BaseEnumTrait;
use Carbon\Carbon;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Throwable;

/**
 * The type of an exported/imported cell. Drives three things: how the value is
 * cast on the way out, how the cell is formatted in the sheet, and how the raw
 * cell is cast back plus validated on the way in.
 */
enum ExcelColumnType: string
{
    use BaseEnumTrait;

    case TEXT = 'text';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';

    public const DATE_FORMAT = 'Y-m-d';
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Cast a model value into the value written to the cell. Dates stay
     * DateTimeInterface here; the export turns them into Excel serials.
     */
    public function castForExport(mixed $value): DateTimeInterface|bool|float|int|string|null
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        return match ($this) {
            self::TEXT => is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE),
            self::INTEGER => is_numeric($value) ? (int)$value : (string)$value,
            self::DECIMAL => is_numeric($value) ? (float)$value : (string)$value,
            self::BOOLEAN => (bool)$value,
            self::DATE, self::DATETIME => $this->toDate($value) ?? (string)$value,
        };
    }

    /**
     * Number format applied to the whole column, so Excel renders dates as dates
     * instead of as the serial number underneath.
     */
    public function numberFormat(): ?string
    {
        return match ($this) {
            self::DATE => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            self::DATETIME => 'yyyy-mm-dd hh:mm:ss',
            default => null,
        };
    }

    /**
     * Cast a raw cell coming from a spreadsheet into the value the validator and
     * the model expect. Anything unparsable is returned untouched so the
     * validation rules produce the error message instead of this method guessing.
     */
    public function castForImport(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($this === self::DATE ? self::DATE_FORMAT : self::DATETIME_FORMAT);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (is_null($value) || $value === '') {
            return null;
        }

        return match ($this) {
            self::TEXT => (string)$value,
            self::INTEGER => is_numeric($value) ? (int)$value : $value,
            self::DECIMAL => is_numeric($value) ? (float)$value : $value,
            self::BOOLEAN => $this->toBoolean($value),
            self::DATE => $this->toDate($value)?->format(self::DATE_FORMAT) ?? $value,
            self::DATETIME => $this->toDate($value)?->format(self::DATETIME_FORMAT) ?? $value,
        };
    }

    /**
     * Validation rules implied by the type. Merged with the header's own rules.
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::TEXT => ['string'],
            self::INTEGER => ['integer'],
            self::DECIMAL => ['numeric'],
            self::BOOLEAN => ['boolean'],
            self::DATE, self::DATETIME => ['date'],
        };
    }

    /**
     * A sample value for the downloadable template, so the customer can see the
     * expected shape of each column.
     */
    public function exampleValue(): string
    {
        return match ($this) {
            self::TEXT => 'text',
            self::INTEGER => '1',
            self::DECIMAL => '1.5',
            self::BOOLEAN => '1',
            self::DATE => now()->format(self::DATE_FORMAT),
            self::DATETIME => now()->format(self::DATETIME_FORMAT),
        };
    }

    private function toBoolean(mixed $value): mixed
    {
        $normalized = is_string($value) ? mb_strtolower($value) : $value;

        return match (true) {
            in_array($normalized, [true, 1, '1', 'true', 'yes', 'y', 'نعم'], true) => true,
            in_array($normalized, [false, 0, '0', 'false', 'no', 'n', 'لا'], true) => false,
            default => $value,
        };
    }

    private function toDate(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        // A real date cell read without formatting comes back as an Excel serial.
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float)$value);
            } catch (Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
