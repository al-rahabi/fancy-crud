# excel — Import / Export

Source: `src/Excel/`. Built on `maatwebsite/excel`. Enabled per controller by `protected ?string $excel = XExcel::class;`.

## Pieces
| Class | Role |
|-------|------|
| `BaseExcel` | The **sheet definition** for a resource: headers, validation, row persistence, lifecycle hooks. HTTP-free (usable from console/tests) |
| `ExcelHeader` | One column: key, label, source, type, rules, import/export behaviour |
| `Enums\ExcelColumnType` | `TEXT INTEGER DECIMAL BOOLEAN DATE DATETIME` — export casting, number format, import casting, validation rule, example value |
| `Enums\ExcelColumnSource` | `COLUMN RELATION QUERY CALCULATED` — where an export value comes from |
| `Contracts\Exportable` / `Importable` | What the managers need from the controller (implemented by the traits) |
| `Traits\ExcelHeadersTrait` | `excel()` (instance built once; RuntimeException if `$excel` null), `authorizeExcel()`, `getHeaders()`, `excelFileName()` |
| `Traits\ExportTrait` | `export()`, `exportQuery()`, `prepareExport()`, `exportFileName()`, `exportChunkSize()` |
| `Traits\ImportTrait` | `import()`, `importTemplate()`, `prepareImport()`, `importRules()`, `importAdditionalData()`, `importChunkSize()`, `importRowLimit()`, `storeExcelRow()`; props `$importExtensions` (xlsx,csv,ods), `$importMaxSize` (20480 KB) |
| `Managers\ExcelExportManager` | Fluent builder: `forController`, `headers`, `query`, `fileName`, `chunk`, `download`, `downloadTemplate`, `store($path,$disk)`, `export()`, `templateExport()` |
| `Managers\ExcelImportManager` | `forController`, `chunk`, `limit`, `import($file,$readerType,$disk): ExcelImportResult` |
| `Exports\ResourceExport` | `FromQuery` + headings/mapping/formats/bold header/auto-size/custom chunk |
| `Exports\TemplateExport` | `FromArray`: header row + example row |
| `Imports\ResourceImport` | `OnEachRow` + `WithChunkReading` + `WithLimit`; validation per row, transaction per row |
| `ExcelImportResult` | counters + errors; `toArray()` → `{imported, failed, skipped, errors}` |

## `BaseExcel` API
Required: `headers(): ExcelHeader[]`. Config: `$model`, `$exportChunkSize` (500), `$importChunkSize` (500), `$importRowLimit` (5000, null = unlimited).
Hooks: `requestRules()` (extra upload fields; available later through `context($key)`), `withHeadingRow(array)` (adapt to file-dependent columns),
`beforeExport(Builder)`, `beforeImport()`, `afterImport(ExcelImportResult)`, `rules()` (default: each importable header's `validationRules()`),
`additionalData(array $row)`, `create(array $data): Model` (override for multi-table rows).
Helpers: `lookup($key, Closure $resolver, $name)` (name→id map loaded once), `splitList($cell)` (splits on `|` or `,`),
`unknownNamesRule($messageKey)` (reject rows whose `importUsing` returned null).

## `ExcelHeader` API
Factories: `column($key,$label)`, `relation('a.b',$label)` (load the relation in the repository/export query to avoid N+1),
`query($key,$label,Closure $query)` (`withCount`/`selectSub`), `calculated($key,$label,Closure $using)`, or `make()`.
Fluent: `type()`, `attribute()` (payload key on import, e.g. `subject_id`), `using()`, `queryUsing()`, `importUsing(fn($value,$row))`,
`rules([])` (replaces auto rules), `options([])` (adds `Rule::in`, shown in template), `required()`, `exportable(bool)`, `importable(bool)`, `example()`.
Default rules: `required|nullable` + type rule + `in:options`. Label defaults to `Str::headline(key)`.

## Column types
- Export: dates stay DateTime then become Excel serials with a column number format; booleans cast to bool; numbers cast if numeric.
- Import: booleans accept `true/yes/y/نعم/1` and `false/no/n/لا/0`; dates normalised to `Y-m-d` / `Y-m-d H:i:s` (Excel serials supported).

## Behavioural notes
- Import matches columns by label first then key, case-insensitively; unmatched sheet columns are ignored; no match at all ⇒ HTTP 400.
- A bad row never stops the run; line numbers are the real sheet rows.
- Controller method `createForExcel(array $data): Model` (optional, not on the interface) takes over row creation.
- Export query is forced to order by primary key when it has no orders.
- `ResourceImport::MAX_ROWS = 100000` applies when the sheet gives no limit.
