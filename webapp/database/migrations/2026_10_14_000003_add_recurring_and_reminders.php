<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('data');
            $table->timestamps();
        });
        Schema::create('recurring_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('recurring_templates')->restrictOnDelete();
            $table->date('effective_date');
            $table->json('data');
            $table->timestamps();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->constrained('recurring_templates')->restrictOnDelete();
            $table->date('occurrence_date')->nullable();
            $table->unique(['template_id', 'occurrence_date']);
        });
        Schema::create('month_observations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->boolean('complete')->default(false);
            $table->timestamps();
            $table->unique(['year', 'month']);
        });
        Schema::create('reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('daily_enabled')->default(true);
            $table->string('daily_time', 5)->default('19:00');
            $table->boolean('anna_enabled')->default(true);
            $table->string('anna_time', 5)->default('19:00');
            $table->timestamps();
        });
        Schema::create('push_credentials', function (Blueprint $table) {
            $table->id();
            $table->text('public_key');
            $table->text('private_key');
            $table->timestamps();
        });
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('endpoint_hash', 64)->unique();
            $table->text('subscription');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_status')->nullable();
            $table->timestamps();
        });
        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('push_devices')->cascadeOnDelete();
            $table->date('date');
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'date', 'kind']);
        });
        Schema::create('scheduler_heartbeats', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['template_id', 'occurrence_date']);
            $table->dropColumn(['template_id', 'occurrence_date']);
        });
        foreach (['scheduler_heartbeats', 'push_deliveries', 'push_devices', 'push_credentials', 'reminder_settings', 'month_observations', 'recurring_versions', 'recurring_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
