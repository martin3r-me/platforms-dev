<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dev_package_snapshot_boards')) {
            return;
        }

        Schema::create('dev_package_snapshot_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('dev_package_snapshots', 'id', 'dpsb_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('board_id')->nullable()
                ->constrained('dev_boards', 'id', 'dpsb_board_fk')
                ->nullOnDelete();

            $table->string('board_name', 255);
            $table->string('board_type', 16);    // feature|bug|custom

            $table->unsignedInteger('issues_open')->default(0);
            $table->unsignedInteger('issues_done')->default(0);
            $table->unsignedInteger('issues_total')->default(0);

            $table->timestamps();

            $table->index(['board_id', 'snapshot_id'], 'dpsb_board_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_package_snapshot_boards');
    }
};
