<?php

declare(strict_types=1);

namespace FancyCrud\Testing;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The four hooks {@see CrudTestBuilder} relies on, with defaults for an app that
 * authenticates through a Laravel guard (session / actingAs). Use it on the base
 * test case the CRUD files are bound to and implement `crudUser()`:
 *
 * ```php
 * abstract class CrudTestCase extends \Orchestra\Testbench\TestCase
 * {
 *     use InteractsWithCrud;
 *
 *     protected function crudUser(?array $permissions = null): Authenticatable
 *     {
 *         // null => a user allowed to do everything, array => exactly these permissions
 *         return User::factory()->withPermissions($permissions)->create();
 *     }
 * }
 * ```
 *
 * Token based apps (Sanctum, JWT) override `crudHeaders()`,
 * `crudUnauthedHeaders()` and `crudRestrictedHeaders()` instead.
 */
trait InteractsWithCrud
{
    /** Headers sent by every generated request. */
    public array $headers = ['Accept' => 'application/json'];

    /**
     * An authenticatable user. `null` means "may do everything"; an array is the
     * exact list of permissions (e.g. `['index posts']`) the user holds.
     */
    abstract protected function crudUser(?array $permissions = null): Authenticatable;

    /** Call from `setUp()` (after `parent::setUp()`) to sign the main user in. */
    protected function setUpCrud(): void
    {
        $this->headers = $this->crudHeaders();
    }

    /** Headers of the fully-privileged user; signs it in on the default guard. */
    protected function crudHeaders(): array
    {
        $this->actingAs($this->crudUser());

        return ['Accept' => 'application/json'];
    }

    /** Headers of a request with no credentials at all. */
    public function crudUnauthedHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Accept' => 'application/json'];
    }

    /** Headers of a user holding only `$permissions`. */
    public function crudRestrictedHeaders(array $permissions = []): array
    {
        $this->actingAs($this->crudUser($permissions));

        return ['Accept' => 'application/json'];
    }

    /**
     * Run `$callback` where setup data belongs (e.g. inside a tenant). The default
     * just runs it; override when the app needs an environment opened first.
     */
    public function crudSeed(Closure $callback): mixed
    {
        return $callback($this);
    }
}
