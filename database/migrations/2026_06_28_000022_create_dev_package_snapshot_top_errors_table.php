<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dev_package_snapshot_top_errors')) {
            return;
        }

        Schema::create('dev_package_snapshot_top_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('dev_package_snapshots', 'id', 'dpste_snap_fk')
                ->cascadeOnDelete();
            $table->foreignId('error_occurrence_id')->nullable()
                ->constrained('dev_error_occurrences', 'id', 'dpste_err_fk')
                ->nullOnDelete();

            // Denorm — bleibt erhalten auch wenn Occurrence spaeter geloescht
            $table->string('exception_class', 255)->nullable();
            $table->string('message_excerpt', 500)->nullable();
            $table->unsignedInteger('occurrence_count')->default(0);
            $table->string('status', 16)->nullable();          // open|acknowledged
            $table->dateTime('first_seen_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();

            $table->unsignedTinyInteger('rank');

            $table->timestamps();

            $table->index(['snapshot_id', 'rank'], 'dpste_snap_rank_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_package_snapshot_top_errors');
    }
};
