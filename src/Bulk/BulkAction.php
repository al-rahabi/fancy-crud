<?php

namespace FancyCrud\Bulk;

use Illuminate\Support\Str;
use LogicException;

/**
 * An operation applied to many records at once (`POST {resource}/bulk`).
 *
 * The way it runs is decided by the interface the subclass implements:
 * {@see HandlesByQuery} (one statement), {@see HandlesByJob} (queued, per record)
 * or {@see HandlesBySync} (immediate, per record). If several are implemented the
 * order of preference is query → job → sync; override `mode()` to change that.
 *
 * ```php
 * class PublishBulkAction extends BulkAction implements HandlesByQuery
 * {
 *     public function rules(): array
 *     {
 *         return ['status' => ['required', 'in:published,archived']];
 *     }
 *
 *     public function byQuery(Builder $query, array $extra): ?int
 *     {
 *         return $query->update(['status' => $extra['status']]);
 *     }
 * }
 * ```
 */
abstract class BulkAction
{
    public const string MODE_QUERY = 'query';

    public const string MODE_JOB = 'job';

    public const string MODE_SYNC = 'sync';

    /**
     * The value of the request's `action` key. `PublishPostsBulkAction` → `publish_posts`.
     */
    public function name(): string
    {
        $base = class_basename(static::class);

        return Str::snake(Str::beforeLast($base, 'BulkAction') ?: $base);
    }

    public function label(): string
    {
        return Str::headline($this->name());
    }

    /**
     * Policy ability checked against the resource's model before running.
     */
    public function ability(): string
    {
        return 'update';
    }

    /**
     * Validation rules of the optional/required `extra` data, keyed by extra key.
     * Mark a key `required` to make it mandatory.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Records processed per chunk by the per-record modes.
     */
    public function chunkSize(): int
    {
        return 200;
    }

    /**
     * Queue connection / name of the job (null = application defaults).
     */
    public function connection(): ?string
    {
        return null;
    }

    public function queue(): ?string
    {
        return null;
    }

    /**
     * `query`, `job` or `sync`, from the interfaces the action implements.
     */
    public function mode(): string
    {
        return match (true) {
            $this instanceof HandlesByQuery => self::MODE_QUERY,
            $this instanceof HandlesByJob => self::MODE_JOB,
            $this instanceof HandlesBySync => self::MODE_SYNC,
            default => throw new LogicException(static::class.' must implement HandlesByQuery, HandlesByJob or HandlesBySync.'),
        };
    }

    /**
     * What the client receives in the index `meta` to build the bulk menu.
     */
    public function describe(): array
    {
        $extra = [];
        foreach ($this->rules() as $key => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;
            $strings = array_values(array_filter($rules, 'is_string'));
            $extra[$key] = ['required' => in_array('required', $strings, true), 'rules' => $strings];
        }

        return [
            'name' => $this->name(),
            'label' => $this->label(),
            'mode' => $this->mode(),
            'extra' => $extra,
        ];
    }
}
