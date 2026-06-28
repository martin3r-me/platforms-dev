<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dev_package_snapshot_top_issues')) {
            return;
        }

        Schema::create('dev_package_snapshot_top_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('dev_package_snapshots', 'id', 'dpsti_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('issue_id')->nullable()
                ->constrained('dev_issues', 'id', 'dpsti_issue_fk')
                ->nullOnDelete();

            $table->uuid('issue_uuid')->nullable();
            $table->string('issue_title', 500);
            $table->string('board_type', 16)->nullable();        // feature|bug|custom
            $table->string('board_name', 255)->nullable();
            $table->string('priority', 16)->nullable();
            $table->string('story_points', 8)->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->boolean('is_done')->default(false);

            $table->foreignId('user_in_charge_id')->nullable()
                ->constrained('users', 'id', 'dpsti_user_fk')
                ->nullOnDelete();
            $table->string('user_in_charge_name', 255)->nullable();

            $table->unsignedTinyInteger('rank');

            $table->timestamps();

            $table->index(['snapshot_id', 'rank'], 'dpsti_snap_rank_idx');
            $table->index(['issue_id', 'snapshot_id'], 'dpsti_issue_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_package_snapshot_top_issues');
    }
};
