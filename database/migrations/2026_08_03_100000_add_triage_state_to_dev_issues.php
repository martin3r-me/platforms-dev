<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reife-Zustand (Triage-Stufe): triage_done_at gesetzt = ein Triage-Worker hat das Issue auf
 * Story-Points + Inhalt geprüft und für die Bearbeitung freigegeben. NULL = noch ungeprüft.
 * Der Developer-Claim gated darauf NUR, wenn der Worker require_triage sendet — sonst egal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('dev_issues', 'triage_done_at')) {
                $table->timestamp('triage_done_at')->nullable()->after('agent_session_id');
                $table->index('triage_done_at', 'dev_issues_triage_done_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->dropIndex('dev_issues_triage_done_idx');
            $table->dropColumn('triage_done_at');
        });
    }
};
