<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_boards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('dev_package_id')->constrained('dev_packages')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['feature', 'bug', 'custom'])->default('custom');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dev_package_id', 'type'], 'dev_boards_package_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_boards');
    }
};
