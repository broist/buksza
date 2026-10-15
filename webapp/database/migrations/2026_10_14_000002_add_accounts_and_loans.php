<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('bank')->nullable();
            $table->text('role')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('funding_category_id')->nullable()->constrained('categories')->restrictOnDelete();
        });
        Schema::create('account_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('effective_date');
            $table->string('mode');
            $table->foreignId('funding_category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['account_id', 'effective_date']);
        });
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lender')->nullable();
            $table->bigInteger('original_amount')->nullable();
            $table->bigInteger('outstanding_amount')->nullable();
            $table->date('balance_date')->nullable();
            $table->string('annual_rate')->nullable();
            $table->text('rate_terms')->nullable();
            $table->bigInteger('installment')->default(0);
            $table->unsignedTinyInteger('due_day')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('loan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->date('effective_date');
            $table->json('data');
            $table->timestamps();
        });
        Schema::create('account_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->bigInteger('amount');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'date']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('family_type')->nullable();
            $table->string('person')->nullable();
            $table->bigInteger('fee_amount')->default(0);
            $table->foreignId('fee_category_id')->nullable()->constrained('categories')->restrictOnDelete();
        });
        // Fresh installations need the default categories before funding category FKs.
        (new DatabaseSeeder)->run();
        $defaults = [
            [2, 'CIB – Anna', 'Anna', 'snapshot', 'detailed', 'CIB', null, null],
            [3, 'Raiffeisen – családi', 'közös', 'bank', 'detailed', 'Raiffeisen', null, 1],
            [4, 'Revolut – Isti', 'Isti', 'bank', 'pocket', 'Revolut', null, 21],
            [5, 'CIB alszámla – Isti', 'Isti', 'subaccount', 'detailed', 'CIB', 1, null],
            [6, 'SZÉP-kártya – Isti', 'Isti', 'szep', 'detailed', null, null, null],
            [7, 'Egészségpénztár – Isti', 'Isti', 'health', 'detailed', null, null, null],
            [8, 'MÁK babakötvény – Örs', 'Örs', 'child', 'detailed', 'MÁK', null, null],
        ];
        foreach ($defaults as [$id, $name, $owner, $kind, $mode, $bank, $parent, $category]) {
            DB::table('accounts')->insertOrIgnore(['id' => $id, 'name' => $name, 'owner' => $owner, 'kind' => $kind, 'mode' => $mode, 'bank' => $bank, 'parent_id' => $parent, 'funding_category_id' => $category, 'opening_balance' => 0, 'opening_date' => now()->startOfYear()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (DB::table('accounts')->get() as $account) {
            DB::table('account_modes')->insert(['account_id' => $account->id, 'effective_date' => '2000-01-01', 'mode' => $account->mode, 'funding_category_id' => $account->funding_category_id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn(['family_type', 'person', 'fee_amount', 'fee_category_id']));
        foreach (['account_snapshots', 'loan_versions', 'loans', 'account_modes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('accounts', fn (Blueprint $table) => $table->dropColumn(['bank', 'role', 'active', 'parent_id', 'funding_category_id']));
    }
};
