<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->unsignedSmallInteger('story_points')->nullable()->after('priority');
            $table->json('acceptance_criteria')->nullable()->after('labels');
        });
    }

    public function down(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->dropColumn(['story_points', 'acceptance_criteria']);
        });
    }
};
