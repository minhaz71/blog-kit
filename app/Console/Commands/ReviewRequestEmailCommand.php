<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Email\EmailService;
use Illuminate\Console\Command;

class ReviewRequestEmailCommand extends Command
{
    protected $signature = 'email:review-request {--days=7 : Days after completion to send request}';

    protected $description = 'Email customers of completed orders asking for a product review.';

    public function handle(EmailService $email): int
    {
        $days = (int) $this->option('days');
        $target = now()->subDays($days)->startOfDay();

        $sent = 0;

        Order::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', $target->toDateString())
            ->chunkById(50, function ($orders) use ($email, &$sent) {
                foreach ($orders as $order) {
                    $email->sendOrderEmail('review_request', $order);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} review-request emails.");

        return self::SUCCESS;
    }
}
