<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warten-auf-Antwort-Zustand für Rückfragen (statt Zurück-Delegieren):
 *  - agent_waiting_at: gesetzt = Issue wartet auf eine Antwort im Kontext-Thread.
 *    Owner bleibt der Worker; der Claim überspringt es, solange keine Antwort da ist.
 *  - agent_session_id: die Claude-Session, die bei Antwort per --resume fortgesetzt
 *    wird → kein Neuaufsetzen, kein Ping-Pong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->timestamp('agent_waiting_at')->nullable()->after('agent_summary');
            $table->string('agent_session_id', 255)->nullable()->after('agent_waiting_at');
            $table->index('agent_waiting_at', 'dev_issues_agent_waiting_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->dropIndex('dev_issues_agent_waiting_idx');
            $table->dropColumn(['agent_waiting_at', 'agent_session_id']);
        });
    }
};
