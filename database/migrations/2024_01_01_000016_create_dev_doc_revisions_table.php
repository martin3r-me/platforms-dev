<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_doc_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dev_doc_page_id')->constrained('dev_doc_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('change_summary')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['dev_doc_page_id', 'version'], 'dev_doc_revisions_page_version_unique');
            $table->index('dev_doc_page_id', 'dev_doc_revisions_page_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_doc_revisions');
    }
};
