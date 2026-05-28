<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_threads', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->text('subject');
            $table->string('subject_normalized', 255);
            $table->enum('status', ['inbox', 'assigned', 'archived'])->default('inbox');
            $table->string('ms365_conversation_id', 255)->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('latest_item_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status', 'latest_item_at']);
            $table->index(['team_id', 'ms365_conversation_id']);
            $table->index(['team_id', 'subject_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_threads');
    }
};
