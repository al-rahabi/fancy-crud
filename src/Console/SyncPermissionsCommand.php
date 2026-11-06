<?php

declare(strict_types=1);

namespace FancyCrud\Console;

use FancyCrud\Permissions\PermissionSyncer;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'fancy-crud:permissions {--guard=* : Only these guards (default: all configured)}';

    protected $description = 'Create the "{action} {resource}" permissions and roles declared per guard in config/fancy-crud.php';

    public function handle(PermissionSyncer $syncer): int
    {
        $only = array_filter((array) $this->option('guard')) ?: null;

        $result = $syncer->sync(null, $only);

        if ($result === []) {
            $this->warn('No guards found in fancy-crud.permissions.guards.');

            return self::SUCCESS;
        }

        foreach ($result as $guard => $permissions) {
            $this->info("[{$guard}] synced {$permissions->count()} permissions.");
        }

        return self::SUCCESS;
    }
}
