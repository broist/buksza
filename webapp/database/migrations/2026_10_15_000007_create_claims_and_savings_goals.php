<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_positions', function (Blueprint $table) {
            $table->id();
            $table->string('kind'); // receivable, payable, reserve, project
            $table->string('name');
            $table->date('start_date');
            $table->unsignedBigInteger('opening_amount')->default(0);
            $table->unsignedBigInteger('target_amount')->default(0);
            $table->unsignedBigInteger('monthly_income')->default(0);
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('position_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('finance_positions');
            $table->foreignId('transaction_id')->unique()->constrained('transactions');
            $table->timestamps();
        });
        Schema::create('goal_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_id')->unique();
            $table->foreignId('from_id')->constrained('finance_positions');
            $table->foreignId('to_id')->constrained('finance_positions');
            $table->date('date');
            $table->unsignedBigInteger('amount');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_transfers');
        Schema::dropIfExists('position_transactions');
        Schema::dropIfExists('finance_positions');
    }
};
