<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_boards', function (Blueprint $table) {
            $table->string('status')->default('active')->after('order');
            $table->index(['dev_package_id', 'status'], 'dev_boards_package_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dev_boards', function (Blueprint $table) {
            $table->dropIndex('dev_boards_package_status_idx');
            $table->dropColumn('status');
        });
    }
};
