<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Services\Email\EmailService;
use App\Support\AbandonedCartFlow;
use App\Support\StoreBranding;
use Illuminate\Console\Command;

/**
 * Sends the editable abandoned-cart reminder sequence (Admin → Abandoned cart
 * settings), e.g. 30min → 1day → 7day → 1month, then the cart exits the flow.
 *
 * Covers GUEST carts (email captured at checkout) as well as logged-in users.
 * The cadence is anchored on `abandoned_at` (= last real cart activity); a
 * shopper returning to their cart bumps `updated_at`, which re-anchors and
 * restarts the sequence. Progress is saved WITHOUT touching `updated_at` so a
 * reminder never looks like renewed activity.
 *
 * Idempotent and safe to run every few minutes from the scheduler.
 */
class AbandonedCartEmailCommand extends Command
{
    protected $signature = 'email:abandoned-cart {--hours= : Deprecated — the cadence now comes from Abandoned cart settings}';

    protected $description = 'Email customers who abandoned a cart, following the configured reminder sequence.';

    public function handle(EmailService $email): int
    {
        if (! AbandonedCartFlow::enabled()) {
            $this->info('Abandoned-cart reminders are disabled.');

            return self::SUCCESS;
        }

        $stages = AbandonedCartFlow::stages();
        if ($stages === []) {
            $this->info('No reminder stages configured.');

            return self::SUCCESS;
        }

        $stageCount = count($stages);
        $now = now();
        $sent = 0;

        Cart::query()
            ->where('status', 'active')
            ->where('reminder_stage', '<', $stageCount)
            ->where('updated_at', '<', $now->copy()->subMinutes($stages[0]['minutes']))
            ->where(fn ($q) => $q->whereNotNull('email')->orWhereHas('user'))
            ->whereHas('items')
            ->with(['user', 'items.product.images', 'items.variation'])
            ->chunkById(50, function ($carts) use ($email, $stages, $stageCount, $now, &$sent) {
                foreach ($carts as $cart) {
                    $to = $cart->recipientEmail();
                    if (! $to) {
                        continue;
                    }

                    // Re-anchor if the shopper touched the cart since we last
                    // anchored (they came back, then drifted off again).
                    if (! $cart->abandoned_at || $cart->updated_at->gt($cart->abandoned_at)) {
                        $cart->abandoned_at = $cart->updated_at;
                        $cart->reminder_stage = 0;
                    }

                    $stageIndex = (int) $cart->reminder_stage;
                    $elapsed = $cart->abandoned_at->diffInMinutes($now);

                    if ($stageIndex >= $stageCount || $elapsed < $stages[$stageIndex]['minutes']) {
                        $this->persist($cart);

                        continue;
                    }

                    $cartUrl = $cart->recoveryUrl();

                    $ok = $email->send(
                        $stages[$stageIndex]['template'],
                        $to,
                        [
                            'customer_name' => $cart->recipientName(),
                            'store_name' => StoreBranding::name(),
                            'cart_url' => $cartUrl,
                            'item_count' => $cart->itemCount(),
                        ],
                        // Cart context (NOT an order): the email shows the saved
                        // cart + a "Complete your order" button, no order chrome.
                        [
                            'audience' => 'cart',
                            'cart_url' => $cartUrl,
                            'subtotal' => price_format($cart->subtotal()),
                            'items' => $cart->items->map(fn ($it) => [
                                'name' => $it->displayName(),
                                'qty' => $it->qty,
                                'total' => price_format($it->lineTotal()),
                                'options' => null,
                                'image' => $it->product?->featuredImageUrl(),
                            ])->all(),
                        ],
                    );

                    // Only advance the sequence when the email actually went
                    // out. If the template is inactive/missing or the mailer
                    // fails, leave the cart on this stage to retry next run —
                    // never silently burn through the whole sequence sending
                    // nothing. (Persist any re-anchor done above regardless.)
                    if (! $ok) {
                        $this->warn("Stage {$stageIndex} email (template '{$stages[$stageIndex]['template']}') did not send for cart {$cart->id}; will retry.");
                        $this->persist($cart);

                        continue;
                    }

                    $cart->reminder_stage = $stageIndex + 1;
                    $cart->last_reminder_at = $now;
                    $cart->abandoned_email_sent_at = $cart->abandoned_email_sent_at ?? $now;
                    $this->persist($cart);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} abandoned-cart reminder(s).");

        return self::SUCCESS;
    }

    /** Save recovery progress WITHOUT bumping updated_at (the abandonment anchor). */
    protected function persist(Cart $cart): void
    {
        $cart->timestamps = false;
        $cart->save();
        $cart->timestamps = true;
    }
}
