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
        Schema::table('groups', function (Blueprint $table) {
            $table->json('schedule_days')->nullable()->after('is_active');
            $table->time('schedule_time')->nullable()->after('schedule_days');
            $table->integer('sessions_count')->nullable()->after('schedule_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['schedule_days', 'schedule_time', 'sessions_count']);
        });
    }
};
