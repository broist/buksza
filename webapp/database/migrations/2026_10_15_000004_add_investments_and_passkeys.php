<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('series', 100)->nullable();
            $table->string('beneficiary', 60)->default('Örs');
            $table->date('start_date');
            $table->bigInteger('opening_amount')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->restrictOnDelete();
            $table->date('effective_date');
            $table->bigInteger('amount');
            $table->timestamps();
            $table->unique(['investment_id', 'effective_date']);
        });
        Schema::create('investment_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->bigInteger('amount');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['investment_id', 'date']);
        });
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->text('credential_id');
            $table->string('credential_hash', 64)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['passkeys', 'investment_values', 'investment_plans', 'investments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
