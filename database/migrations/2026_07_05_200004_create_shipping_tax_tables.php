<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('countries')->nullable(); // ["US","CA"] — null = rest of world
            $table->json('states')->nullable();
            $table->json('cities')->nullable();
            $table->json('postcodes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // flat_rate, free_shipping, local_pickup, weight_based, value_based
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('min_order_amount', 12, 2)->nullable(); // free shipping threshold / value tier
            $table->json('weight_tiers')->nullable(); // [{"up_to_kg":1,"cost":5},...]
            $table->json('class_costs')->nullable(); // {"shipping_class_slug": cost}
            $table->string('delivery_estimate')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country', 2)->nullable()->index();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('tax_class')->default('standard');
            $table->decimal('rate', 8, 4); // percent
            $table->boolean('applies_to_shipping')->default(false);
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
    }
};
