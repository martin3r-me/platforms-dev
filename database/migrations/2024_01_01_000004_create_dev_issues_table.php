<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('dev_board_id')->constrained('dev_boards')->onDelete('cascade');
            $table->foreignId('dev_board_slot_id')->nullable()->constrained('dev_board_slots')->onDelete('set null');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->json('labels')->nullable();
            $table->foreignId('user_in_charge_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('slot_order')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dev_board_id', 'status'], 'dev_issues_board_status_idx');
            $table->index('dev_board_slot_id', 'dev_issues_slot_idx');
            $table->index('user_in_charge_id', 'dev_issues_assignee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_issues');
    }
};
