<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every time a customer opens the signed invoice link (from the order
     * email or their account) we record one row here, so the store can see
     * how many recipients actually downloaded their PDF invoice and from
     * where. The `token` is the per-order signature hash embedded in the link.
     */
    public function up(): void
    {
        Schema::create('invoice_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->index();      // signature hash carried in the link
            $table->string('source', 20)->default('email'); // email | account | admin
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('downloaded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_downloads');
    }
};
