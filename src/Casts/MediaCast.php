<?php

namespace FancyCrud\Casts;

use GuzzleHttp\Psr7\MimeType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Casts a stored path (or array of paths) into {url, path, size, extension,
 * mime_type} on read, via the assetUrl() helper (src/helpers.php).
 * On write, accepts a plain path string, or an array of paths/media arrays,
 * and drops any array entry that doesn't exist on the `fancy-crud.media_disk` disk.
 */
class MediaCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (empty($value)) {
            return null;
        }

        if (str($value)->isJson()) {
            $value = json_decode($value, true);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->format($item), $value);
        }

        return $this->format($value);
    }

    public function format($path): array
    {
        $disk = $this->disk();
        $fileExists = $disk->exists($path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return [
            'url' => assetUrl($path),
            'path' => $path,
            'size' => $fileExists ? round($disk->size($path) / 1024) : 0,
            'extension' => $extension,
            'mime_type' => $fileExists ? MimeType::fromExtension($extension) : 'unknown',
        ];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_array($value)) {
            $result = [];
            $disk = $this->disk();

            foreach ($value as $item) {
                $path = $item['path'] ?? (is_string($item) ? $item : '');

                if (empty($path) || ! $disk->exists($path)) {
                    continue;
                }

                $result[] = $path;
            }

            return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return null;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(config('fancy-crud.media_disk', 'public'));
    }
}
