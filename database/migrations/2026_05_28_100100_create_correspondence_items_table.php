<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_items', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('correspondence_threads')->cascadeOnDelete();
            $table->enum('type', ['email', 'letter']);
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->text('sender_name')->nullable();
            $table->text('sender_email')->nullable();
            $table->text('recipient_name')->nullable();
            $table->text('recipient_email')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->text('metadata')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('provider_id', 255)->nullable();
            $table->date('correspondence_date')->nullable();
            $table->boolean('is_read')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'provider', 'provider_id'], 'corr_items_team_provider_unique');
            $table->index(['team_id', 'thread_id', 'correspondence_date']);
            $table->index(['team_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_items');
    }
};
