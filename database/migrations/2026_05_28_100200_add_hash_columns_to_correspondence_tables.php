<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondence_threads', function (Blueprint $table) {
            $table->char('subject_hash', 64)->nullable()->index()->after('subject');
        });

        Schema::table('correspondence_items', function (Blueprint $table) {
            $table->char('sender_name_hash', 64)->nullable()->index()->after('sender_name');
            $table->char('sender_email_hash', 64)->nullable()->index()->after('sender_email');
            $table->char('recipient_name_hash', 64)->nullable()->index()->after('recipient_name');
            $table->char('recipient_email_hash', 64)->nullable()->index()->after('recipient_email');
            $table->char('body_text_hash', 64)->nullable()->after('body_text');
            $table->char('body_html_hash', 64)->nullable()->after('body_html');
            $table->char('metadata_hash', 64)->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_threads', function (Blueprint $table) {
            $table->dropColumn('subject_hash');
        });

        Schema::table('correspondence_items', function (Blueprint $table) {
            $table->dropColumn([
                'sender_name_hash',
                'sender_email_hash',
                'recipient_name_hash',
                'recipient_email_hash',
                'body_text_hash',
                'body_html_hash',
                'metadata_hash',
            ]);
        });
    }
};
