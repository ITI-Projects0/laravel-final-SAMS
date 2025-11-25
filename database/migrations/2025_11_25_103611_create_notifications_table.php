<?php

use App\Models\Center;
use App\Models\Group;
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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Center::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class,'sender_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class,'recipient_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class,'related_student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Group::class,'related_group_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['low_performance', 'attendance', 'general'])->default('general');
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
