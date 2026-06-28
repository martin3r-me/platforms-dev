<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Health\Support\HealthSnapshotSchema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dev_package_snapshots')) {
            return;
        }

        Schema::create('dev_package_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('dev_package_id')
                ->constrained('dev_packages', 'id', 'dps_pkg_fk')
                ->cascadeOnDelete();

            HealthSnapshotSchema::columns($table);

            // ── Issue-Counts (gesamt) ──
            $table->unsignedInteger('issues_total')->default(0);
            $table->unsignedInteger('issues_open')->default(0);
            $table->unsignedInteger('issues_done')->default(0);
            $table->unsignedInteger('issues_overdue')->default(0);
            $table->unsignedInteger('issues_high_priority_open')->default(0);

            // ── Bugs (Issues auf bug-Type-Boards) ──
            $table->unsignedInteger('bugs_total')->default(0);
            $table->unsignedInteger('bugs_open')->default(0);
            $table->unsignedInteger('bugs_done')->default(0);

            // ── Features (Issues auf feature-Type-Boards) ──
            $table->unsignedInteger('features_total')->default(0);
            $table->unsignedInteger('features_open')->default(0);
            $table->unsignedInteger('features_done')->default(0);

            // ── Story Points ──
            $table->unsignedInteger('story_points_total')->default(0);
            $table->unsignedInteger('story_points_open')->default(0);
            $table->unsignedInteger('story_points_done')->default(0);

            // ── Production-Errors ──
            $table->unsignedInteger('errors_open')->default(0);
            $table->unsignedInteger('errors_acknowledged')->default(0);
            $table->unsignedInteger('errors_total_hits')->default(0);    // sum(occurrence_count) ueber open+acknowledged
            $table->unsignedInteger('errors_seen_today')->default(0);    // Anzahl Errors mit last_seen_at >= startOfDay
            $table->dateTime('latest_error_seen_at')->nullable();

            // ── Boards ──
            $table->unsignedSmallInteger('boards_count')->default(0);
            $table->boolean('has_bug_board')->default(false);
            $table->boolean('has_feature_board')->default(false);

            // ── Doku ──
            $table->unsignedInteger('doc_pages_count')->default(0);
            $table->unsignedInteger('doc_pages_stale')->default(0);     // updated_at < 90 days ago
            $table->unsignedInteger('doc_pages_published')->default(0);

            // ── Workload ──
            $table->unsignedInteger('active_users_count')->default(0);
            $table->unsignedInteger('unassigned_open_issues')->default(0);

            $table->timestamps();

            $table->unique(['dev_package_id', 'taken_on'], 'dps_pkg_day_uniq');
            $table->index(['dev_package_id', 'taken_at'], 'dps_pkg_taken_idx');
            $table->index(['team_id', 'taken_at'], 'dps_team_taken_idx');
            $table->index('taken_at', 'dps_taken_idx');
        });

        Schema::table('dev_package_snapshots', function (Blueprint $table) {
            $table->foreign('prev_snapshot_id', 'dps_prev_fk')
                ->references('id')->on('dev_package_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dev_package_snapshots', function (Blueprint $table) {
            $table->dropForeign('dps_prev_fk');
        });
        Schema::dropIfExists('dev_package_snapshots');
    }
};
