<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            $table->boolean('insights_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('reminder_settings', fn (Blueprint $table) => $table->dropColumn('insights_enabled'));
    }
};
