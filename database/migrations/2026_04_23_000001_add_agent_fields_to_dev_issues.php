<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->timestamp('agent_locked_at')->nullable()->after('due_date');
            $table->string('agent_locked_by', 100)->nullable()->after('agent_locked_at');
            $table->string('agent_branch', 255)->nullable()->after('agent_locked_by');
            $table->timestamp('agent_completed_at')->nullable()->after('agent_branch');
            $table->text('agent_summary')->nullable()->after('agent_completed_at');

            $table->index(['status', 'agent_locked_at'], 'dev_issues_agent_lock_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->dropIndex('dev_issues_agent_lock_idx');
            $table->dropColumn([
                'agent_locked_at',
                'agent_locked_by',
                'agent_branch',
                'agent_completed_at',
                'agent_summary',
            ]);
        });
    }
};
