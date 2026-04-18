<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->foreignId('user_in_charge_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->dropForeign(['user_in_charge_id']);
            $table->dropColumn('user_in_charge_id');
        });
    }
};
