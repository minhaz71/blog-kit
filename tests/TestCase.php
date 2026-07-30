<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety guard: refuse to run the suite against a real database.
     *
     * `RefreshDatabase` (and friends) run `migrate:fresh`, which DROPS EVERY
     * TABLE. If the app's config has been cached (e.g. after `php artisan
     * optimize`), Laravel ignores phpunit.xml's sqlite/:memory: env overrides
     * and uses the cached connection instead — which can be the real local
     * MySQL. That once wiped the local dev database.
     *
     * This runs inside setUpTraits(), which the parent invokes AFTER the app is
     * booted but BEFORE the database-refreshing traits execute — so it aborts
     * before a single migrate:fresh can touch a non-test database. (An earlier
     * version checked in setUp() after parent::setUp(), which was too late.)
     */
    protected function setUpTraits(): array
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $isSafe = $connection === 'sqlite'
            && in_array($database, [':memory:', ''], true);

        if (! $isSafe) {
            throw new \RuntimeException(
                "REFUSING TO RUN TESTS against connection [{$connection}] db [{$database}]. "
                .'Tests must use sqlite :memory:. Config is probably cached — run '
                .'`php artisan config:clear` before testing.'
            );
        }

        return parent::setUpTraits();
    }
}
