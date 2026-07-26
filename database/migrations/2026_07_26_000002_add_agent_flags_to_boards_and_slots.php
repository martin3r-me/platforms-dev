<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_boards', function (Blueprint $table) {
            // Per-Board opt-in: darf der Worker Issues dieses Boards ziehen?
            $table->boolean('agent_enabled')->default(false)->after('status');
        });

        Schema::table('dev_board_slots', function (Blueprint $table) {
            // Rolle in der Worker-Pipeline: ready|working|human|done (null = rein menschlich).
            $table->string('agent_role', 20)->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('dev_boards', function (Blueprint $table) {
            $table->dropColumn('agent_enabled');
        });
        Schema::table('dev_board_slots', function (Blueprint $table) {
            $table->dropColumn('agent_role');
        });
    }
};
