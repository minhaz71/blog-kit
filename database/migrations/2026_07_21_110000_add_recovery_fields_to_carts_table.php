<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abandoned-cart recovery fields (all additive & nullable — safe on a live
 * store with existing carts):
 *   - email/customer_name/phone: captured at checkout so GUESTS can be
 *     re-engaged (the cart otherwise only knows a session id).
 *   - abandoned_at: the anchor the reminder cadence is measured from; reset
 *     whenever the shopper touches the cart again.
 *   - reminder_stage / last_reminder_at: how far through the 30min→1day→7day→
 *     1month sequence a cart has progressed.
 *   - recovered_at / order_id: set when an abandoned cart later converts, so
 *     the dashboard can show recovered orders and revenue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('email')->nullable()->after('session_id')->index();
            $table->string('customer_name')->nullable()->after('email');
            $table->string('phone')->nullable()->after('customer_name');
            $table->timestamp('abandoned_at')->nullable()->after('abandoned_email_sent_at');
            $table->unsignedTinyInteger('reminder_stage')->default(0)->after('abandoned_at');
            $table->timestamp('last_reminder_at')->nullable()->after('reminder_stage');
            $table->timestamp('recovered_at')->nullable()->after('last_reminder_at');
            $table->foreignId('order_id')->nullable()->after('recovered_at')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn([
                'email', 'customer_name', 'phone', 'abandoned_at',
                'reminder_stage', 'last_reminder_at', 'recovered_at',
            ]);
        });
    }
};
