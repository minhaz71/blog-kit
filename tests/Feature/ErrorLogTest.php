<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_real_exception(): void
    {
        ErrorLog::record(new \RuntimeException('Database went away'));

        $this->assertDatabaseCount('error_logs', 1);
        $log = ErrorLog::first();
        $this->assertSame('Database went away', $log->message);
        $this->assertSame(\RuntimeException::class, $log->exception_class);
        $this->assertSame(500, $log->status_code);
        $this->assertSame(1, $log->occurrences);
        $this->assertNotNull($log->trace);
    }

    public function test_repeat_errors_dedupe_into_one_row_with_a_counter(): void
    {
        // Same class+file+line, digits differ → normalized to one fingerprint.
        for ($i = 0; $i < 3; $i++) {
            ErrorLog::record(new \RuntimeException("Timeout after {$i}00ms"));
        }

        $this->assertDatabaseCount('error_logs', 1);
        $this->assertSame(3, ErrorLog::first()->occurrences);
    }

    public function test_a_recurrence_reopens_a_resolved_error(): void
    {
        // One exception instance recorded twice = same file+line = same
        // fingerprint (as a real recurring bug throws from one spot).
        $e = new \RuntimeException('Flaky thing');
        ErrorLog::record($e);
        ErrorLog::first()->update(['resolved' => true]);

        ErrorLog::record($e);

        $this->assertFalse(ErrorLog::first()->resolved);
        $this->assertSame(2, ErrorLog::first()->occurrences);
    }

    public function test_noise_exceptions_are_not_recorded(): void
    {
        ErrorLog::record(new NotFoundHttpException('missing'));       // 404
        ErrorLog::record(new AuthenticationException('unauthenticated'));
        ErrorLog::record(\Illuminate\Validation\ValidationException::withMessages(['x' => 'y']));

        $this->assertDatabaseCount('error_logs', 0);
    }

    public function test_guests_get_the_friendly_page_not_a_stack_trace(): void
    {
        config(['app.debug' => false]);

        // Two-segment path so the single-segment /{page:slug} catch-all
        // doesn't swallow it into a 404.
        \Illuminate\Support\Facades\Route::get('/__boom/now', fn () => throw new \RuntimeException('secret internal detail xyz'))
            ->middleware('web');

        $res = $this->get('/__boom/now');

        $res->assertStatus(500);
        $res->assertSee('Something went wrong');
        $res->assertDontSee('secret internal detail xyz'); // never leak the message
        $res->assertDontSee('RuntimeException');            // never leak the class

        // …but it WAS captured for the admin log.
        $this->assertDatabaseHas('error_logs', ['message' => 'secret internal detail xyz']);
    }
}
