<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Bewusst ein No-op: github_repo_id ist seit 000001 eine lose gekoppelte Spalte (nullable + Index)
 * ohne Foreign Key auf Tabellen des Integrations-Moduls. Der Dateiname bleibt bestehen, damit
 * bereits gelaufene Instanzen (migrations-Tabelle) konsistent bleiben. Der Bestands-FK wird in
 * 2026_10_25_000001_drop_dev_packages_github_repo_foreign_key entfernt.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
