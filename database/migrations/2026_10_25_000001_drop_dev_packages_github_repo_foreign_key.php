<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt den harten FK dev_packages.github_repo_id -> Integrations-Tabelle auf bestehenden
 * Instanzen. Idempotent: droppt nur, wenn ein FK auf der Spalte existiert; Spalte und Daten
 * bleiben, ein Index bleibt erhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dev_packages') || ! Schema::hasColumn('dev_packages', 'github_repo_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'dev_packages')
            ->where('COLUMN_NAME', 'github_repo_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        if ($constraints->isEmpty()) {
            return;
        }

        Schema::table('dev_packages', function (Blueprint $table) use ($constraints) {
            foreach ($constraints as $name) {
                $table->dropForeign($name);
            }
        });

        // Der Index, den MySQL/MariaDB für den FK angelegt hat, bleibt bestehen (gleicher Name wie ->index()).
    }

    public function down(): void
    {
        // Bewusst leer: es gibt keinen FK mehr, der wiederhergestellt werden müsste.
    }
};
