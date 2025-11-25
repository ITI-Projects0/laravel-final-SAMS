<?php

use App\Models\Lesson;
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
        Schema::create('lesson_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Lesson::class)->constrained()->cascadeOnDelete();
            $table->enum('type', ['video', 'file', 'link'])->default('video');
            $table->string('title');
            $table->string('url');
            $table->timestamps();
            $table->unique(['lesson_id', 'type', 'url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_resources');
    }
};
