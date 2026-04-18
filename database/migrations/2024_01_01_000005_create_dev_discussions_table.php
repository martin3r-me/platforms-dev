<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_discussions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('dev_package_id')->constrained('dev_packages')->onDelete('cascade');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dev_package_id', 'is_pinned'], 'dev_discussions_package_pinned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_discussions');
    }
};
