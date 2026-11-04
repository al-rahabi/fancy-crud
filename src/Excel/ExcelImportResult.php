<?php

namespace FancyCrud\Excel;

/**
 * The outcome of an import: how many rows were written, and — per failed row —
 * its line number in the sheet with the messages that rejected it. An import
 * never stops on a bad row, so both sides of this object can be non-empty.
 */
class ExcelImportResult
{
    protected int $imported = 0;
    protected int $skipped = 0;
    protected array $errors = [];

    public function addImported(int $count = 1): static
    {
        $this->imported += $count;
        return $this;
    }

    public function addSkipped(int $count = 1): static
    {
        $this->skipped += $count;
        return $this;
    }

    /**
     * @param int $line 1-based line number as seen in the spreadsheet
     * @param array<string, string[]> $messages attribute => messages
     */
    public function addError(int $line, array $messages): static
    {
        $this->errors[] = ['line' => $line, 'messages' => $messages];
        return $this;
    }

    public function getImported(): int
    {
        return $this->imported;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFailed(): int
    {
        return count($this->errors);
    }

    public function isFullySuccessful(): bool
    {
        return $this->errors === [];
    }

    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'failed' => $this->getFailed(),
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }
}
