<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\User;
use App\Services\Ai\CategoryWriter;
use App\Services\Ai\LlmClient;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Writes a category's description with AI in its OWN process, launched
 * detached by the "Write with AI" action (BackgroundProcess::artisan). This
 * keeps the multi-minute LLM work off the web request — the admin dashboard
 * stays fully responsive instead of a worker (and its DB-backed session)
 * being held for the whole call. Progress + result are published to cache
 * (CategoryWriter::setStatus) and, when database notifications are enabled,
 * pushed to the initiating user's bell.
 */
class WriteCategoryContent extends Command
{
    protected $signature = 'category:write
        {category : Category id}
        {--provider=anthropic}
        {--model=}
        {--reviewer= : Reviewer provider (empty = automated checks only)}
        {--reviewer-model=}
        {--passes=1}
        {--notes=}
        {--user= : Admin id to notify on completion}';

    protected $description = 'Write a category description with AI (background worker for the admin action).';

    public function handle(): int
    {
        @set_time_limit(0);

        $category = Category::find((int) $this->argument('category'));

        if (! $category) {
            $this->error('Category not found.');

            return self::FAILURE;
        }

        CategoryWriter::setStatus($category->id, 'running', 'AI is writing this category…');

        try {
            $reviewer = $this->option('reviewer')
                ? LlmClient::for((string) $this->option('reviewer'), ($this->option('reviewer-model') ?: null))->withContext('category')
                : null;

            $result = CategoryWriter::forProvider((string) $this->option('provider'), $this->option('model') ?: null)
                ->write($category, (string) $this->option('notes'), (int) $this->option('passes'), $reviewer);

            $applied = CategoryWriter::apply($category, $result['output']);

            $message = trim(implode(' ', array_filter([
                'Content, short description and SEO meta updated.',
                $applied['faqs_written'] ? 'FAQs created.' : 'Existing FAQs kept.',
                $result['issues'] !== [] ? 'Review notes: '.implode('; ', array_slice($result['issues'], 0, 2)) : null,
            ])));

            CategoryWriter::setStatus($category->id, 'done', $message);
            $this->notifyUser('success', "Category “{$category->name}” written ✓", $message);
            $this->info('Done: '.$message);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error("category:write failed for #{$category->id} ({$category->name}): ".$e->getMessage());
            CategoryWriter::setStatus($category->id, 'failed', $e->getMessage());
            $this->notifyUser('danger', "AI writing failed for “{$category->name}”", mb_substr($e->getMessage(), 0, 200));
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function notifyUser(string $type, string $title, string $body): void
    {
        $userId = (int) $this->option('user');

        if ($userId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->{$type}()
            ->sendToDatabase($user);
    }
}
