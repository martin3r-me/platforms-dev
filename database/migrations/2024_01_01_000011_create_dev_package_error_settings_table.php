<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dev_package_error_settings')) {
            return;
        }

        Schema::create('dev_package_error_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dev_package_id')
                ->unique()
                ->constrained('dev_packages')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->boolean('enabled')->default(false);
            $table->boolean('capture_console_errors')->default(false);
            $table->json('capture_codes')->nullable();
            $table->json('priority_mapping')->nullable();
            $table->integer('dedupe_window_hours')->default(24);
            $table->boolean('auto_create_issue')->default(true);
            $table->boolean('include_stack_trace')->default(true);
            $table->integer('stack_trace_limit')->default(50);

            $table->timestamps();

            $table->index('team_id');
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_package_error_settings');
    }
};
