<?php

namespace App\Console\Commands;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Services\Ai\LlmClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiDiagnose extends Command
{
    protected $signature = 'ai:diagnose {--batch= : Batch ID to inspect in detail} {--live : Send a real test request to each configured provider}';

    protected $description = 'Step-by-step AI publisher workflow check — report printed, saved to storage/app/, and logged to storage/logs/ai.log';

    protected array $report = [];

    public function handle(): int
    {
        $this->section('1. API keys (Settings → AI settings)');
        $configured = [];
        foreach (array_keys(AiImportBatch::PROVIDERS) as $provider) {
            $key = trim((string) setting("ai.{$provider}_api_key"));
            $model = (string) setting("ai.{$provider}_model") ?: LlmClient::defaultModel($provider);
            $configured[$provider] = $key !== '';
            $this->line_(($key !== '' ? '✅' : '⚪')." {$provider}: ".($key !== '' ? 'key saved ('.mb_substr($key, 0, 6).'…), model: '.$model : 'NO KEY — this provider cannot be used'));
        }

        $this->section('2. Endpoint reachability'.($this->option('live') ? ' (live ping)' : ' (skipped — pass --live to send real test requests)'));
        if ($this->option('live')) {
            foreach ($configured as $provider => $hasKey) {
                if (! $hasKey) {
                    continue;
                }
                [$ok, $message] = LlmClient::for($provider)->withContext('healthcheck')->healthCheck();
                $this->line_(($ok ? '✅' : '❌')." {$provider}: {$message}");
            }
        }

        $this->section('2b. Google Drive images'.($this->option('live') ? ' (live check)' : ' (config only — pass --live to call the Drive API)'));
        $driveKey = trim((string) setting('ai.google_drive_api_key'));
        $this->line_(($driveKey !== '' ? '✅' : '⚪').' Drive API key: '.($driveKey !== '' ? 'saved ('.mb_substr($driveKey, 0, 6).'…)' : 'NOT SET — folder image matching is disabled'));

        if ($this->option('live') && $driveKey !== '') {
            $folder = AiImportBatch::whereNotNull('drive_folder')->where('drive_folder', '!=', '')->latest()->value('drive_folder');
            [$ok, $message] = \App\Services\Ai\DriveImageFetcher::healthCheck($folder);
            $this->line_(($ok ? '✅' : '❌').' '.$message.($folder ? ' (folder from the most recent batch)' : ''));
        }

        $this->section('3. Queue (this is what actually runs the writing — a WORKER, not a cron)');
        $driver = config('queue.default');
        $this->line_("Queue driver: {$driver}");

        if ($driver === 'sync') {
            $this->line_('⚠️ sync driver: jobs run instantly in the same request — fine for testing, blocks the browser for long batches.');
        } elseif ($driver === 'database') {
            try {
                $pending = DB::table('jobs')->count();
                $oldest = DB::table('jobs')->whereNull('reserved_at')->orderBy('created_at')->value('created_at');
                $oldestAge = $oldest ? now()->getTimestamp() - (int) $oldest : null;
                $this->line_("Pending jobs in queue: {$pending}");

                if ($pending > 0 && $oldestAge !== null && $oldestAge > 30) {
                    $this->line_('❌ Jobs are stuck '.gmdate('i\m s\s', $oldestAge).' — NO QUEUE WORKER IS RUNNING.');
                    $this->line_('   Fix (dev):        php artisan queue:work');
                    $this->line_('   Fix (all-in-one): composer dev   (serves + queue + logs + vite together)');
                    $this->line_('   Fix (production): run "php artisan queue:work --tries=3" under Supervisor/systemd.');
                    $this->line_('   Note: the Laravel SCHEDULER cron (* * * * * php artisan schedule:run) does NOT process the queue.');
                } elseif ($pending > 0) {
                    $this->line_('✅ Jobs are being picked up (a worker appears active).');
                } else {
                    $this->line_('✅ Queue is empty — nothing waiting.');
                }
            } catch (\Throwable $e) {
                $this->line_('❌ Cannot read jobs table: '.$e->getMessage());
            }

            $failed = DB::table('failed_jobs')->count();
            $this->line_($failed > 0 ? "⚠️ {$failed} failed job(s) — inspect with: php artisan queue:failed" : '✅ No failed jobs.');
        }

        $this->section('4. Batches (writer AND reviewer keys are both required per batch)');
        foreach (AiImportBatch::latest()->limit(5)->get() as $batch) {
            $this->line_("#{$batch->id} \"{$batch->name}\" [{$batch->provider}] status={$batch->status} items={$batch->done_items}/{$batch->total_items} failed={$batch->failed_items}"
                .($batch->error ? " error=\"{$batch->error}\"" : ''));

            // The most common two-model misconfiguration: writer key saved,
            // reviewer provider's key missing → every review pass degrades
            // to automated checks only.
            $reviewer = $batch->reviewer_provider ?: 'openai';

            if (trim((string) setting("ai.{$reviewer}_api_key")) === '') {
                $this->line_("   ❌ reviewer [{$reviewer}]: NO API KEY — reviews fall back to automated checks only. Add the key in Settings → AI settings.");
            } elseif ($reviewer !== $batch->provider) {
                $this->line_("   ✅ reviewer [{$reviewer}]: key saved.");
            }
        }

        if ($batchId = $this->option('batch')) {
            $batch = AiImportBatch::find($batchId);

            if ($batch === null) {
                $this->line_("❌ Batch {$batchId} not found.");
            } else {
                $this->section("5. Batch #{$batch->id} deep dive");
                $this->line_('CSV exists: '.(Storage::disk('local')->exists($batch->csv_path) ? '✅ '.$batch->csv_path : '❌ MISSING '.$batch->csv_path));

                foreach ($batch->items()->orderBy('id')->get() as $item) {
                    $this->line_("  item #{$item->id} \"".($item->row['name'] ?? '?')."\" status={$item->status} passes={$item->passes_done}"
                        .($item->error ? "\n    ↳ ERROR: {$item->error}" : ''));
                }

                $this->line_('');
                $this->line_('Recent activity:');
                foreach (AiActivityLog::where('batch_id', $batch->id)->latest('id')->limit(15)->get()->reverse() as $log) {
                    $this->line_("  [{$log->created_at->format('H:i:s')}] [{$log->level}] [{$log->stage}] {$log->message}");
                }
            }
        }

        $this->section('Done');
        $path = 'ai-diagnostic-report-'.now()->format('Y-m-d-His').'.txt';
        Storage::disk('local')->put($path, implode("\n", $this->report));
        Log::channel('ai')->info('ai:diagnose report', ['report' => implode("\n", $this->report)]);
        $this->info("Report saved: storage/app/{$path}");
        $this->info('Full request/error history: storage/logs/ai.log — send either file when reporting a problem.');

        return self::SUCCESS;
    }

    protected function section(string $title): void
    {
        $this->line_('');
        $this->line_('━━ '.$title);
    }

    protected function line_(string $text): void
    {
        $this->report[] = $text;
        $this->line($text);
    }
}
