<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            // Opt-in flag: whether the autonomous worker may pick up issues for
            // this package at all. Default false — a package is only in scope
            // once it has been explicitly released for the agent.
            $table->boolean('agent_enabled')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->dropColumn('agent_enabled');
        });
    }
};
