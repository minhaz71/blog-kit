<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Builds the manifest.json embedded in every backup archive. The manifest
 * is what makes a backup PORTABLE and SAFE to restore: it records exactly
 * what environment produced it (PHP / Laravel / ShopKit / DB versions, the
 * ran-migrations list, row counts, checksums) so BackupCompatibility can
 * verify a target machine BEFORE anything is overwritten.
 */
class BackupManifest
{
    /** Bump when the manifest structure changes incompatibly. */
    public const FORMAT = 1;

    /** PHP extensions ShopKit needs at runtime — presence is recorded and re-checked on restore. */
    public const REQUIRED_EXTENSIONS = ['pdo_mysql', 'zip', 'mbstring', 'openssl', 'curl', 'gd', 'json'];

    /** @param  array{database?: ?string, storage_public?: bool, ai_imports?: bool}  $includes */
    public static function generate(string $type, array $includes = []): array
    {
        $sqlPath = $includes['database'] ?? null;

        return [
            'format' => self::FORMAT,
            'generator' => 'blogkit backup:run',
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'app' => [
                'name' => (string) config('app.name'),
                'blogkit_version' => \App\Support\Version::core(),
                'url' => (string) config('app.url'),
                'environment' => app()->environment(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'extensions' => collect(self::REQUIRED_EXTENSIONS)
                    ->mapWithKeys(fn ($ext) => [$ext => extension_loaded($ext)])
                    ->all(),
            ],
            'laravel' => app()->version(),
            'database' => [
                'driver' => (string) config('database.default'),
                'server_version' => self::serverVersion(),
                'tables' => self::tableCount(),
                'migrations' => self::ranMigrations(),
            ],
            'counts' => self::counts(),
            // Fingerprint only — never the key itself. A different APP_KEY on
            // the target means encrypted columns won't decrypt after restore.
            'app_key_fingerprint' => substr(sha1((string) config('app.key')), 0, 12),
            'includes' => [
                'database' => $sqlPath !== null,
                'storage_public' => (bool) ($includes['storage_public'] ?? false),
                'ai_imports' => (bool) ($includes['ai_imports'] ?? false),
            ],
            'checksums' => [
                'database.sql' => $sqlPath && is_file($sqlPath) ? hash_file('sha256', $sqlPath) : null,
            ],
        ];
    }

    protected static function serverVersion(): ?string
    {
        try {
            return (string) DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function tableCount(): ?int
    {
        try {
            return count(\Illuminate\Support\Facades\Schema::getTables());
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, string> */
    protected static function ranMigrations(): array
    {
        try {
            return DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, int|null> */
    protected static function counts(): array
    {
        $tables = ['products', 'users', 'orders', 'settings', 'ai_import_batches', 'ai_usage_logs', 'product_templates', 'pages', 'posts'];
        $out = [];

        foreach ($tables as $table) {
            try {
                $out[$table] = (int) DB::table($table)->count();
            } catch (\Throwable) {
                $out[$table] = null;
            }
        }

        return $out;
    }
}
