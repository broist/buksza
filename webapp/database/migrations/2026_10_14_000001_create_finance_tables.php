<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7);
            $table->unsignedInteger('position');
            $table->boolean('active')->default(true);
            $table->boolean('carryover')->default(true);
            $table->json('subcategories');
            $table->bigInteger('default_amount')->default(0);
            $table->timestamps();
        });
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner')->default('Isti');
            $table->string('kind')->default('bank');
            $table->string('mode')->default('detailed');
            $table->bigInteger('opening_balance')->default(0);
            $table->date('opening_date');
            $table->timestamps();
        });
        Schema::create('year_settings', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->bigInteger('opening_amount')->default(0);
            $table->string('savings_rate')->default('10');
            $table->timestamps();
        });
        Schema::create('monthly_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->boolean('is_override')->default(false);
            $table->unique(['year', 'month', 'category_id']);
            $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_id')->unique();
            $table->date('date')->index();
            $table->string('type');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('subcategory')->nullable();
            $table->string('source')->nullable();
            $table->bigInteger('amount');
            $table->text('note')->nullable();
            $table->boolean('business')->default(false);
            $table->boolean('informational')->default(false);
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->unsignedBigInteger('investment_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('daily_notes', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['daily_notes', 'transactions', 'monthly_plans', 'year_settings', 'accounts', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
