<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    /**
     * Exceptions that are normal request outcomes, not application bugs —
     * logging them would bury the real errors in noise.
     */
    protected const IGNORED = [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    ];

    public static function shouldRecord(Throwable $e): bool
    {
        foreach (self::IGNORED as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        // 4xx HTTP exceptions are client problems, not server bugs.
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return false;
        }

        return true;
    }

    /**
     * Persist an exception for the admin error log. Deduped by fingerprint:
     * a repeat of the same error bumps occurrences + last_seen_at and
     * re-opens it if it was marked resolved. Wrapped so a logging failure
     * can never cascade into another error.
     */
    public static function record(Throwable $e, ?Request $request = null): void
    {
        if (! self::shouldRecord($e)) {
            return;
        }

        try {
            $request ??= request();
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $normalized = preg_replace('/\d+/', '#', $e->getMessage()); // digits vary; group them
            $fingerprint = md5(get_class($e).'|'.$e->getFile().'|'.$e->getLine().'|'.$normalized);

            $existing = static::where('fingerprint', $fingerprint)->first();

            if ($existing) {
                $existing->forceFill([
                    'occurrences' => $existing->occurrences + 1,
                    'last_seen_at' => now(),
                    'resolved' => false, // it happened again — reopen
                    'message' => mb_substr($e->getMessage(), 0, 2000),
                    'url' => $request?->fullUrl(),
                ])->saveQuietly();

                return;
            }

            static::create([
                'fingerprint' => $fingerprint,
                'level' => 'error',
                'exception_class' => get_class($e),
                'message' => mb_substr($e->getMessage(), 0, 2000),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'status_code' => $status,
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'user_id' => auth()->id(),
                'ip' => $request?->ip(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 20000),
                'occurrences' => 1,
                'last_seen_at' => now(),
            ]);
        } catch (Throwable) {
            // Never let error logging throw — that would recurse forever.
        }
    }

    public function shortClass(): string
    {
        return class_basename($this->exception_class ?? 'Error');
    }
}
