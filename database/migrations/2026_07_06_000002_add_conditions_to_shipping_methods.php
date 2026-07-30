<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('class_costs');
            // conditions structure:
            //   min_qty, max_qty
            //   min_weight_kg, max_weight_kg
            //   min_subtotal, max_subtotal          (secondary to min_order_amount which is the primary threshold)
            //   allowed_shipping_class_slugs        (only match when cart contains one of these)
            //   allowed_customer_roles              (guest, customer, wholesale, ...)
            //   day_of_week                         (e.g. [1,2,3,4,5] mon-fri)
            //   time_start / time_end               ('HH:MM' in the store timezone)
            //   allowed_postcodes / blocked_postcodes (exact or prefix match with a trailing *)
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn('conditions');
        });
    }
};
