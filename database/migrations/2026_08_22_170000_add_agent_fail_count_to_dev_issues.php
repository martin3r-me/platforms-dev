<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fehlversuch-Zähler für den Agenten-Loop: verhindert die Endlosschleife, in der ein
 * wiederholt scheiterndes Issue sofort neu geclaimt wird (fail() entsperrte es bislang).
 *  - agent_fail_count: Anzahl aufeinanderfolgender Fehlschläge. Bei jedem fail() +1,
 *    bei erfolgreichem complete zurück auf 0. Nach N (Controller) wird das Issue geparkt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->unsignedInteger('agent_fail_count')->default(0)->after('agent_summary');
        });
    }

    public function down(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->dropColumn('agent_fail_count');
        });
    }
};
