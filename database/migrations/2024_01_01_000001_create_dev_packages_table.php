<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('github_repo_full_name')->nullable();
            $table->foreignId('github_repo_id')->nullable()->constrained('integrations_github_repositories')->onDelete('set null');
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->string('icon')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status'], 'dev_packages_team_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_packages');
    }
};
