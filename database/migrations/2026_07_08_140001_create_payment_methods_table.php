<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-defined offline payment methods (cash on delivery, card on
        // delivery, bank transfer, …). Each has a customer-facing name, an
        // instructions "message box", and an optional named surcharge.
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();               // stable machine key (orders reference this)
            $table->string('name');                         // customer-facing title, e.g. "Card on Delivery"
            $table->string('description')->nullable();      // one-line blurb under the title
            $table->text('instructions')->nullable();       // message box shown at checkout / thank-you
            $table->decimal('fee_fixed', 10, 2)->default(0);   // flat surcharge
            $table->decimal('fee_percent', 5, 2)->default(0);  // % of subtotal surcharge
            $table->string('fee_label')->nullable();        // charge name, e.g. "Card payment charge"
            $table->string('mark_as')->default('processing'); // order status after placing (offline flow)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Persist the payment surcharge on the order so it shows as its own
        // named line on the summary, thank-you page, and invoice.
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('payment_fee', 10, 2)->default(0)->after('tax_total');
            $table->string('payment_fee_label')->nullable()->after('payment_fee');
        });

        // Seed the two methods this store needs out of the box.
        DB::table('payment_methods')->insert([
            [
                'key' => 'cash_on_delivery',
                'name' => 'Cash on Delivery',
                'description' => 'Pay with cash when your order arrives.',
                'instructions' => 'Pay via cash on delivery — please have the exact amount ready for the courier.',
                'fee_fixed' => 0, 'fee_percent' => 0, 'fee_label' => null,
                'mark_as' => 'processing', 'is_active' => true, 'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'key' => 'card_on_delivery',
                'name' => 'Card on Delivery',
                'description' => 'Pay by card to the courier on delivery.',
                'instructions' => 'Pay via card on delivery — the courier will bring a card machine to your door.',
                'fee_fixed' => 0, 'fee_percent' => 0, 'fee_label' => 'Card payment charge',
                'mark_as' => 'processing', 'is_active' => true, 'sort_order' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_fee', 'payment_fee_label']);
        });
        Schema::dropIfExists('payment_methods');
    }
};
