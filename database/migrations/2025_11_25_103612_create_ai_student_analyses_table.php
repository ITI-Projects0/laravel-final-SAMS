<?php

use App\Models\Center;
use App\Models\User;
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
        Schema::create('ai_student_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Center::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class,'student_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class,'requested_by')->constrained()->cascadeOnDelete();
            $table->text('summary');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_student_analyses');
    }
};
