<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow groups to remain when a teacher is removed.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->foreignId('teacher_id')->nullable()->change();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('teacher_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Revert to the original non-nullable constraint and cascade delete.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable(false)->change();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('teacher_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
