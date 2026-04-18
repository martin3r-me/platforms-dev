<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_package_error_settings', function (Blueprint $table) {
            $table->string('ingest_token', 64)->nullable()->unique()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('dev_package_error_settings', function (Blueprint $table) {
            $table->dropColumn('ingest_token');
        });
    }
};
