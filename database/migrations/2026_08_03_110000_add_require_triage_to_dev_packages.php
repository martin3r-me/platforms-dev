<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Triage-Pflicht als Eigenschaft der QUELLE (Package), nicht des Workers: verlangt ein
 * Package Triage, führt der Developer-Worker nur triagierte Issues (triage_done_at) aus.
 * Default false → strukturierter interner Eingang braucht standardmäßig keine Triage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('dev_packages', 'require_triage')) {
                $table->boolean('require_triage')->default(false)->after('agent_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->dropColumn('require_triage');
        });
    }
};
