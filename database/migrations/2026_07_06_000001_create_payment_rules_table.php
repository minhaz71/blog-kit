<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('payment_method')->index();  // stripe, paypal, cod, bank_transfer, or '*' for any
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(10);

            // Availability filters
            $table->json('allowed_countries')->nullable();
            $table->json('blocked_countries')->nullable();
            $table->json('allowed_cities')->nullable();
            $table->json('blocked_cities')->nullable();
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->decimal('max_order_amount', 12, 2)->nullable();

            // Shipping-method interaction
            $table->json('allowed_shipping_methods')->nullable();  // IDs; null = any
            $table->json('blocked_shipping_methods')->nullable();

            // Adjustments — positive fee added to total, negative discount subtracted
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);  // applied to subtotal
            $table->boolean('free_shipping')->default(false);

            // Cart eligibility
            $table->boolean('first_order_only')->default(false);
            $table->boolean('disallow_coupons')->default(false);

            $table->string('customer_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_rules');
    }
};
