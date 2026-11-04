<?php

if (! function_exists('assetUrl')) {
    /**
     * Public URL for a stored media path (e.g. a FancyCrud\Casts\MediaCast
     * field), resolved through the disk configured at fancy-crud.media_disk.
     * Define your own assetUrl() before this file loads to override it
     * (e.g. for tenant-aware storage).
     */
    function assetUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk(config('fancy-crud.media_disk', 'public'))->url($path);
    }
}