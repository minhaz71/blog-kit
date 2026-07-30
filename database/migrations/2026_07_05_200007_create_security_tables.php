<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->index();
            $table->string('ip_address', 45)->index();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('successful')->default(false);
            $table->boolean('is_admin_area')->default(false);
            $table->timestamps();
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason')->nullable();
            $table->timestamp('blocked_until')->nullable(); // null = permanent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('firewall_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('country', 2)->nullable();
            $table->string('url', 1000);
            $table->string('method', 10);
            $table->string('user_agent', 500)->nullable();
            $table->string('rule'); // sqli, xss, traversal, bad_bot, sensitive_file, scanner_path, blocked_ip, blocked_country, rate_limit
            $table->text('matched_payload')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // created, updated, deleted, login, settings_changed...
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('file_scan_results', function (Blueprint $table) {
            $table->id();
            $table->string('path', 1000);
            $table->string('file_hash', 64)->nullable();
            $table->string('issue'); // eval_usage, base64_abuse, shell_exec, obfuscated, php_in_uploads, modified
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->text('snippet')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('scanned_at');
            $table->timestamps();
        });

        Schema::create('file_hashes', function (Blueprint $table) {
            $table->id();
            $table->string('path', 700)->unique();
            $table->string('file_hash', 64);
            $table->timestamp('file_modified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to_email');
            $table->string('subject');
            $table->string('template_key')->nullable();
            $table->string('status')->default('sent'); // sent, failed
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('file_hashes');
        Schema::dropIfExists('file_scan_results');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('firewall_logs');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('login_attempts');
    }
};
