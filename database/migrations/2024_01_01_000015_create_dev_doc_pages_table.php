<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_doc_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dev_package_id')->constrained('dev_packages')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('slug');
            $table->longText('content')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['dev_package_id', 'slug'], 'dev_doc_pages_package_slug_unique');
            $table->index(['dev_package_id', 'type'], 'dev_doc_pages_package_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_doc_pages');
    }
};
