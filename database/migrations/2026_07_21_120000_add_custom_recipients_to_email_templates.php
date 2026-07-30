<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets each email template route to extra fixed addresses (e.g. a warehouse or
 * accounts inbox) beyond the built-in customer/admin recipient — comma
 * separated. Additive & nullable: existing templates are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('custom_recipients')->nullable()->after('recipient');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('custom_recipients');
        });
    }
};
