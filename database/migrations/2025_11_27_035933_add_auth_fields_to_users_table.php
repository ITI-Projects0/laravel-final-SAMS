<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_code')->nullable()->after('password');
            $table->boolean('is_data_complete')->default(false)->after('activation_code');
            $table->string('google_id')->nullable()->after('is_data_complete');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activation_code', 'is_data_complete', 'google_id']);
        });
    }
};
