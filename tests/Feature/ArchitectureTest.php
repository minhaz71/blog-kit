<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Architecture tests — enforce codebase-wide rules by scanning source, so
 * regressions (a stray dd(), env() outside config, a model reaching for
 * the network) fail CI instead of shipping. Pure file inspection: no DB,
 * no boot.
 */
class ArchitectureTest extends TestCase
{
    /** @return array<string, string> path => contents for app/*.php */
    protected function appFiles(string $subdir = ''): array
    {
        $root = dirname(__DIR__, 2).'/app'.($subdir ? '/'.$subdir : '');
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $out[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $out;
    }

    protected function rel(string $path): string
    {
        return str_replace(dirname(__DIR__, 2).'/', '', $path);
    }

    public function test_no_debug_statements_in_application_code(): void
    {
        $offenders = [];
        // Word-boundary calls to debug helpers that must never ship.
        $pattern = '/(?<![\w>$])(dd|dump|var_dump|print_r|ray|dump_die|vd)\s*\(/';

        foreach ($this->appFiles() as $path => $code) {
            if (preg_match($pattern, $code, $m)) {
                $offenders[] = $this->rel($path).' → '.$m[1].'()';
            }
        }

        $this->assertSame([], $offenders, "Debug statements found:\n".implode("\n", $offenders));
    }

    public function test_env_is_only_read_in_config_or_whitelisted_safety_files(): void
    {
        // env() outside config returns null once config is cached; the only
        // deliberate exceptions are the destructive-op safety guards, which
        // WANT to fail safe (null → guard stays on).
        $allowed = ['app/Support/DatabaseSafetyGuard.php', 'app/Support/Preflight.php'];
        $offenders = [];

        foreach ($this->appFiles() as $path => $code) {
            $rel = $this->rel($path);
            if (in_array($rel, $allowed, true)) {
                continue;
            }
            if (preg_match('/(?<![\w>$])env\s*\(/', $code)) {
                $offenders[] = $rel;
            }
        }

        $this->assertSame([], $offenders,
            "env() should only appear in config/ or the safety guards. Use config() instead:\n".implode("\n", $offenders));
    }

    public function test_models_do_not_make_http_calls(): void
    {
        // Network I/O belongs in services, not Eloquent models.
        $offenders = [];
        foreach ($this->appFiles('Models') as $path => $code) {
            if (preg_match('/Facades\\\\Http|(?<![\w])Http::/', $code)) {
                $offenders[] = $this->rel($path);
            }
        }

        $this->assertSame([], $offenders,
            "Models must not call the HTTP client (move network calls to a service):\n".implode("\n", $offenders));
    }

    public function test_models_do_not_depend_on_controllers(): void
    {
        $offenders = [];
        foreach ($this->appFiles('Models') as $path => $code) {
            if (preg_match('/App\\\\Http\\\\Controllers\\\\/', $code)) {
                $offenders[] = $this->rel($path);
            }
        }

        $this->assertSame([], $offenders,
            "Models must not reference controllers (dependency direction):\n".implode("\n", $offenders));
    }

    public function test_queued_jobs_implement_should_queue(): void
    {
        $offenders = [];
        foreach ($this->appFiles('Jobs') as $path => $code) {
            if (! str_contains($code, 'ShouldQueue')) {
                $offenders[] = $this->rel($path);
            }
        }

        $this->assertSame([], $offenders,
            "Every job in app/Jobs must implement ShouldQueue:\n".implode("\n", $offenders));
    }

    public function test_controllers_extend_the_base_controller(): void
    {
        $offenders = [];
        foreach ($this->appFiles('Http/Controllers') as $path => $code) {
            $name = basename($path, '.php');
            if ($name === 'Controller') {
                continue;
            }
            // Either extends the base Controller, or another controller.
            if (! preg_match('/extends\s+\w*Controller/', $code)) {
                $offenders[] = $this->rel($path);
            }
        }

        $this->assertSame([], $offenders,
            "Controllers must extend a base Controller:\n".implode("\n", $offenders));
    }
}
