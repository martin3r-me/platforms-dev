<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            // Drop old FK to integrations_github_repositories
            if (Schema::hasColumn('dev_packages', 'github_repo_id')) {
                $table->dropForeign(['github_repo_id']);
                $table->dropColumn('github_repo_id');
            }
        });

        Schema::table('dev_packages', function (Blueprint $table) {
            // New FK to integration_github_repos
            $table->foreignId('github_repo_id')
                ->nullable()
                ->after('github_repo_full_name')
                ->constrained('integration_github_repos')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->dropForeign(['github_repo_id']);
            $table->dropColumn('github_repo_id');
        });

        Schema::table('dev_packages', function (Blueprint $table) {
            $table->foreignId('github_repo_id')
                ->nullable()
                ->after('github_repo_full_name')
                ->constrained('integrations_github_repositories')
                ->onDelete('set null');
        });
    }
};
