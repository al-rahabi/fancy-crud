<?php

declare(strict_types=1);

namespace FancyCrud\Testing;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Declarative generator for the standard CRUD endpoint test-suite of a
 * {@see \FancyCrud\Contracts\CrudController}.
 *
 * A single `crudTest('posts')->model(Post::class)-> ... ->run()` call at the top
 * of a Pest test file expands into one `test()` per requested verb. Pest resolves
 * the owning test file from the outermost backtrace frame, so the generated tests
 * bind to whatever base class `pest()->extend(...)->in(...)` maps for the calling
 * file's directory.
 *
 * The closures run bound to that test case, so it must use
 * {@see InteractsWithCrud} (or provide the same four hooks: `$headers`,
 * `crudSeed()`, `crudUnauthedHeaders()`, `crudRestrictedHeaders()`).
 *
 * @phpstan-type PayloadSource array<string, mixed>|Closure
 */
class CrudTestBuilder
{
    /** Verbs enabled by default when ->only() is never called. */
    private const DEFAULT_VERBS = ['index', 'store', 'show', 'update', 'destroy'];

    private const ALL_VERBS = ['index', 'store', 'show', 'update', 'patch', 'destroy', 'export', 'template', 'import'];

    // Properties are public: the generated test closures are bound to the test
    // case, not to this class, so they can only reach public members of $cfg.

    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public string $model;

    public string $baseUrl = '/api';

    /** @var list<string> */
    public array $verbs = self::DEFAULT_VERBS;

    /** Factory override: fn ($testCase): Model — runs inside crudSeed(). */
    public ?Closure $record = null;

    /** @var PayloadSource */
    public array|Closure $createPayload = [];

    /** @var PayloadSource|null */
    public array|Closure|null $updatePayload = null;

    /** @var PayloadSource|null */
    public array|Closure|null $patchPayload = null;

    /** @var PayloadSource */
    public array|Closure $invalidPayload = [];

    /** @var array<int|string, mixed>|Closure|null */
    public array|Closure|null $jsonStructure = null;

    /** Extra assertion: fn (TestResponse $response, ?Model $model): void */
    public ?Closure $extraAssertions = null;

    /** Column => value pairs expected in the DB row after store (skipped when empty). */
    public array $createdInDb = [];

    public bool $softDeletes = false;

    public string $missingId = '999999999';

    /**
     * Rows of the CSV uploaded by the `import` verb, the first row being the
     * column labels (or keys) of the sheet. Rows after the first are data rows.
     *
     * @var list<list<string|int|float|null>>
     */
    public array $importRows = [];

    /** Extra form fields sent with the import (the sheet's `requestRules()`). */
    public array|Closure $importFields = [];

    /**
     * Non-null enables the "role without permission → 403" test (the string is a
     * readable note of the ability being denied — the test uses a user holding no
     * permissions at all). Null skips it, e.g. for controllers that do not
     * authorize.
     */
    public ?string $restrictedPermission = null;

    public function __construct(public readonly string $resource)
    {
        $this->restrictedPermission = "index {$resource}";
    }

    public static function make(string $resource): self
    {
        return new self($resource);
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /** Route prefix without the resource slug, e.g. /api, /api/v1/admin. */
    public function baseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    /** @param list<string> $verbs index|store|show|update|patch|destroy|export|template|import */
    public function only(array $verbs): self
    {
        $this->verbs = array_values($verbs);

        return $this;
    }

    /** @param list<string> $verbs */
    public function except(array $verbs): self
    {
        $this->verbs = array_values(array_diff($this->verbs, $verbs));

        return $this;
    }

    /** Every verb, Excel ones included. */
    public function all(): self
    {
        $this->verbs = self::ALL_VERBS;

        return $this;
    }

    /** fn ($testCase): Model — runs inside crudSeed(). */
    public function record(Closure $factory): self
    {
        $this->record = $factory;

        return $this;
    }

    /** @param PayloadSource $payload array, or fn ($testCase): array */
    public function createPayload(array|Closure $payload): self
    {
        $this->createPayload = $payload;

        return $this;
    }

    /** @param PayloadSource $payload Defaults to the create payload when omitted. */
    public function updatePayload(array|Closure $payload): self
    {
        $this->updatePayload = $payload;

        return $this;
    }

    /** @param PayloadSource $payload Defaults to the update payload when omitted. */
    public function patchPayload(array|Closure $payload): self
    {
        $this->patchPayload = $payload;

        return $this;
    }

    /** @param PayloadSource $payload Body expected to fail validation (422). Default: []. */
    public function invalidPayload(array|Closure $payload): self
    {
        $this->invalidPayload = $payload;

        return $this;
    }

    /**
     * JSON structure asserted under `data` for show/store/update and under
     * `data.*` for index. Array (assertJsonStructure syntax) or fn ($testCase): array.
     *
     * @param  array<int|string, mixed>|Closure  $structure
     */
    public function jsonStructure(array|Closure $structure): self
    {
        $this->jsonStructure = $structure;

        return $this;
    }

    /** Column => value pairs asserted with assertDatabaseHas after store. */
    public function assertCreated(array $columns): self
    {
        $this->createdInDb = $columns;

        return $this;
    }

    /** fn (TestResponse $response, ?Model $model): void — run on every 2xx verb. */
    public function assertUsing(Closure $callback): self
    {
        $this->extraAssertions = $callback;

        return $this;
    }

    public function softDeletes(bool $value = true): self
    {
        $this->softDeletes = $value;

        return $this;
    }

    public function missingId(string $id): self
    {
        $this->missingId = $id;

        return $this;
    }

    /**
     * Rows of the sheet the `import` verb uploads (as CSV). The first row holds
     * the column labels, every other row is imported.
     *
     * @param list<list<string|int|float|null>> $rows
     */
    public function importRows(array $rows, array|Closure $fields = []): self
    {
        $this->importRows = $rows;
        $this->importFields = $fields;

        return $this;
    }

    /** Permission string handed to the restricted user in the 403 test (or null to skip). */
    public function restrictedPermission(?string $permission): self
    {
        $this->restrictedPermission = $permission;

        return $this;
    }

    // -----------------------------------------------------------------
    //  Expansion
    // -----------------------------------------------------------------

    public function run(): void
    {
        $has = fn (string $verb) => in_array($verb, $this->verbs, true);

        if ($has('index')) {
            $this->testIndex();
        }
        if ($has('store')) {
            $this->testStore();
            $this->testStoreValidation();
        }
        if ($has('show')) {
            $this->testShow();
            $this->testShowMissing();
        }
        if ($has('update')) {
            $this->testUpdate();
            $this->testUpdateMissing();
        }
        if ($has('patch')) {
            $this->testPatch();
        }
        if ($has('destroy')) {
            $this->testDestroy();
            $this->testDestroyMissing();
        }
        if ($has('export')) {
            $this->testExport();
        }
        if ($has('template')) {
            $this->testTemplate();
        }
        if ($has('import')) {
            $this->testImport();
            $this->testImportWithoutFile();
        }

        $this->testUnauthenticated();

        if ($this->restrictedPermission !== null) {
            $this->testForbidden();
        }
    }

    // -----------------------------------------------------------------
    //  Individual cases
    // -----------------------------------------------------------------

    private function testIndex(): void
    {
        $cfg = $this;
        test("{$this->resource}: index returns the standard envelope (200)", function () use ($cfg) {
            $response = $this->withHeaders($this->headers)->getJson($cfg->url());
            $response->assertOk();
            $cfg->assertEnvelope($response);
            $structure = $cfg->resolveStructure($this);
            if ($structure !== null) {
                $response->assertJsonStructure(['data' => ['*' => $structure]]);
            }
            $cfg->runExtra($response, null);
        });
    }

    private function testStore(): void
    {
        $cfg = $this;
        test("{$this->resource}: store creates a record (201)", function () use ($cfg) {
            $payload = $cfg->payload($cfg->createPayload, $this);
            $response = $this->withHeaders($this->headers)->postJson($cfg->url(), $payload);
            $response->assertStatus(201);
            $cfg->assertEnvelope($response);
            $structure = $cfg->resolveStructure($this);
            if ($structure !== null) {
                $response->assertJsonStructure(['data' => $structure]);
            }
            if ($cfg->createdInDb !== []) {
                $this->crudSeed(fn () => $this->assertDatabaseHas((new ($cfg->model))->getTable(), $cfg->createdInDb));
            }
            $cfg->runExtra($response, null);
        });
    }

    private function testStoreValidation(): void
    {
        $cfg = $this;
        test("{$this->resource}: store rejects an invalid body (422)", function () use ($cfg) {
            $payload = $cfg->payload($cfg->invalidPayload, $this);
            $response = $this->withHeaders($this->headers)->postJson($cfg->url(), $payload);
            $response->assertStatus(422);
        });
    }

    private function testShow(): void
    {
        $cfg = $this;
        test("{$this->resource}: show returns a record (200)", function () use ($cfg) {
            $model = $cfg->seedRecord($this);
            $response = $this->withHeaders($this->headers)->getJson($cfg->url('/'.$model->getKey()));
            $response->assertOk();
            $cfg->assertEnvelope($response);
            $structure = $cfg->resolveStructure($this);
            if ($structure !== null) {
                $response->assertJsonStructure(['data' => $structure]);
            }
            $cfg->runExtra($response, $model);
        });
    }

    private function testShowMissing(): void
    {
        $cfg = $this;
        test("{$this->resource}: show of a missing id returns 404", function () use ($cfg) {
            $this->withHeaders($this->headers)
                ->getJson($cfg->url('/'.$cfg->missingId))
                ->assertStatus(404);
        });
    }

    private function testUpdate(): void
    {
        $cfg = $this;
        test("{$this->resource}: update modifies a record (200)", function () use ($cfg) {
            $model = $cfg->seedRecord($this);
            $payload = $cfg->payload($cfg->updatePayload ?? $cfg->createPayload, $this);
            $response = $this->withHeaders($this->headers)->putJson($cfg->url('/'.$model->getKey()), $payload);
            $response->assertOk();
            $cfg->assertEnvelope($response);
            $cfg->runExtra($response, $model);
        });
    }

    private function testUpdateMissing(): void
    {
        $cfg = $this;
        test("{$this->resource}: update of a missing id returns 404", function () use ($cfg) {
            $payload = $cfg->payload($cfg->updatePayload ?? $cfg->createPayload, $this);
            $this->withHeaders($this->headers)
                ->putJson($cfg->url('/'.$cfg->missingId), $payload)
                ->assertStatus(404);
        });
    }

    private function testPatch(): void
    {
        $cfg = $this;
        test("{$this->resource}: patch partially updates a record (200)", function () use ($cfg) {
            $model = $cfg->seedRecord($this);
            $payload = $cfg->payload($cfg->patchPayload ?? $cfg->updatePayload ?? $cfg->createPayload, $this);
            $response = $this->withHeaders($this->headers)->patchJson($cfg->url('/'.$model->getKey()), $payload);
            $response->assertOk();
            $cfg->assertEnvelope($response);
            $cfg->runExtra($response, $model);
        });
    }

    private function testDestroy(): void
    {
        $cfg = $this;
        test("{$this->resource}: destroy removes a record (200)", function () use ($cfg) {
            $model = $cfg->seedRecord($this);
            $response = $this->withHeaders($this->headers)->deleteJson($cfg->url('/'.$model->getKey()));
            $response->assertOk();

            if (! $cfg->softDeletes) {
                $this->crudSeed(function () use ($cfg, $model) {
                    expect((new ($cfg->model))->newQuery()->find($model->getKey()))->toBeNull();
                });
            }
        });
    }

    private function testDestroyMissing(): void
    {
        $cfg = $this;
        test("{$this->resource}: destroy of a missing id returns 404", function () use ($cfg) {
            $this->withHeaders($this->headers)
                ->deleteJson($cfg->url('/'.$cfg->missingId))
                ->assertStatus(404);
        });
    }

    private function testExport(): void
    {
        $cfg = $this;
        test("{$this->resource}: export downloads an .xlsx (200)", function () use ($cfg) {
            $cfg->seedRecord($this);
            $response = $this->withHeaders($this->headers)->get($cfg->url('/export'));
            $response->assertOk();
            expect($response->headers->get('content-disposition'))->toContain('.xlsx');
        });
    }

    private function testTemplate(): void
    {
        $cfg = $this;
        test("{$this->resource}: import template downloads an .xlsx (200)", function () use ($cfg) {
            $response = $this->withHeaders($this->headers)->get($cfg->url('/import/template'));
            $response->assertOk();
            expect($response->headers->get('content-disposition'))->toContain('.xlsx');
        });
    }

    private function testImport(): void
    {
        $cfg = $this;
        test("{$this->resource}: import creates the rows of the sheet (200)", function () use ($cfg) {
            $rows = $cfg->importRows;
            expect(count($rows))->toBeGreaterThan(1, 'importRows() needs a heading row plus data rows');

            $fields = $cfg->importFields instanceof Closure ? ($cfg->importFields)($this) : $cfg->importFields;
            $response = $this->withHeaders($this->headers)
                ->post($cfg->url('/import'), $fields + ['file' => $cfg->csvUpload($rows)], ['Accept' => 'application/json']);

            $response->assertOk();
            $cfg->assertEnvelope($response);
            expect($response->json('data.imported'))->toBe(count($rows) - 1)
                ->and($response->json('data.failed'))->toBe(0);
            $cfg->runExtra($response, null);
        });
    }

    private function testImportWithoutFile(): void
    {
        $cfg = $this;
        test("{$this->resource}: import without a file is rejected (422)", function () use ($cfg) {
            $this->withHeaders($this->headers)
                ->postJson($cfg->url('/import'), [])
                ->assertStatus(422);
        });
    }

    private function testUnauthenticated(): void
    {
        $cfg = $this;
        test("{$this->resource}: unauthenticated request is rejected (401)", function () use ($cfg) {
            $this->withHeaders($this->crudUnauthedHeaders())
                ->getJson($cfg->url())
                ->assertStatus(401);
        });
    }

    private function testForbidden(): void
    {
        $cfg = $this;
        test("{$this->resource}: a user without permission is forbidden (403)", function () use ($cfg) {
            // A user that holds no permission at all.
            $headers = $this->crudRestrictedHeaders([]);

            // Hit a verb that reaches the policy check. show/destroy of a missing
            // id short-circuits to 404 before authorization, so seed a real
            // record for show, and fall back to index for read-only resources.
            if (in_array('store', $cfg->verbs, true)) {
                $response = $this->withHeaders($headers)
                    ->postJson($cfg->url(), $cfg->payload($cfg->createPayload, $this));
            } elseif (in_array('show', $cfg->verbs, true)) {
                $model = $cfg->seedRecord($this);
                $response = $this->withHeaders($headers)->getJson($cfg->url('/'.$model->getKey()));
            } else {
                $response = $this->withHeaders($headers)->getJson($cfg->url());
            }

            $response->assertStatus(403);
        });
    }

    // -----------------------------------------------------------------
    //  Shared helpers (public so the generated closures can reach them)
    // -----------------------------------------------------------------

    public function url(string $suffix = ''): string
    {
        return "{$this->baseUrl}/{$this->resource}{$suffix}";
    }

    public function assertEnvelope(TestResponse $response): void
    {
        $response->assertJsonStructure(['data', 'status', 'message', 'code', 'meta']);
    }

    /** @param array<string, mixed>|Closure $source */
    public function payload(array|Closure $source, object $testCase): array
    {
        return $source instanceof Closure ? (array) $source($testCase) : $source;
    }

    /** @return array<int|string, mixed>|null */
    public function resolveStructure(object $testCase): ?array
    {
        if ($this->jsonStructure === null) {
            return null;
        }

        return $this->jsonStructure instanceof Closure
            ? (array) ($this->jsonStructure)($testCase)
            : $this->jsonStructure;
    }

    public function seedRecord(object $testCase): object
    {
        $factory = $this->record ?? fn () => ($this->model)::factory()->create();

        return $testCase->crudSeed($factory);
    }

    public function runExtra(TestResponse $response, ?object $model): void
    {
        if ($this->extraAssertions !== null) {
            ($this->extraAssertions)($response, $model);
        }
    }

    /** @param list<list<string|int|float|null>> $rows */
    public function csvUpload(array $rows): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }
}
