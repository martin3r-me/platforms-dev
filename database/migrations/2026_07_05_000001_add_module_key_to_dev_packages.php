<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loose, explicit mapping of a dev package to a platform module. Lets the
     * feature-request tab resolve "the package of the module I'm in" without
     * name guessing. Nullable + unique per team (MySQL ignores NULLs), so a
     * module maps to at most one package.
     */
    public function up(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->string('module_key')->nullable()->after('name');
            $table->unique(['team_id', 'module_key'], 'dev_packages_team_module_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->dropUnique('dev_packages_team_module_key_unique');
            $table->dropColumn('module_key');
        });
    }
};
