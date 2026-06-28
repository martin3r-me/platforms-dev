<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dev_package_snapshot_people')) {
            return;
        }

        Schema::create('dev_package_snapshot_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('dev_package_snapshots', 'id', 'dpspp_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users', 'id', 'dpspp_user_fk')
                ->nullOnDelete();

            $table->string('user_name', 255);

            $table->unsignedInteger('open_issues')->default(0);
            $table->unsignedInteger('done_issues')->default(0);
            $table->unsignedInteger('open_bugs')->default(0);
            $table->unsignedInteger('open_features')->default(0);
            $table->unsignedInteger('overdue_issues')->default(0);
            $table->unsignedInteger('sp_open')->default(0);
            $table->unsignedInteger('sp_done')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'snapshot_id'], 'dpspp_user_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_package_snapshot_people');
    }
};
