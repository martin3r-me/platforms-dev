<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_discussion_replies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('dev_discussion_id')->constrained('dev_discussions')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('dev_discussion_replies')->onDelete('cascade');
            $table->longText('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index('dev_discussion_id', 'dev_discussion_replies_discussion_idx');
            $table->index('parent_id', 'dev_discussion_replies_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_discussion_replies');
    }
};
