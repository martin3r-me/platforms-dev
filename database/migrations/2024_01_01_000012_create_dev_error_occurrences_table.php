<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dev_error_occurrences')) {
            return;
        }

        Schema::create('dev_error_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dev_package_id')
                ->constrained('dev_packages')
                ->cascadeOnDelete();
            $table->foreignId('dev_issue_id')
                ->nullable()
                ->constrained('dev_issues')
                ->nullOnDelete();
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->string('error_hash', 64);
            $table->string('exception_class')->nullable();
            $table->text('message')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->integer('http_code')->nullable();

            $table->integer('occurrence_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->json('sample_data')->nullable();
            $table->string('status')->default('open');

            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('error_hash');
            $table->index('status');
            $table->index('http_code');
            $table->index('first_seen_at');
            $table->index('last_seen_at');
            $table->index(['dev_package_id', 'error_hash', 'status'], 'dev_err_occ_pkg_hash_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_error_occurrences');
    }
};
