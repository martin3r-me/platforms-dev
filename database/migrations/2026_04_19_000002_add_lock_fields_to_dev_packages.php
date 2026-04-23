<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->foreignId('locked_by_user_id')->nullable()->after('user_in_charge_id')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by_user_id');
            $table->string('lock_reason')->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('dev_packages', function (Blueprint $table) {
            $table->dropForeign(['locked_by_user_id']);
            $table->dropColumn(['locked_by_user_id', 'locked_at', 'lock_reason']);
        });
    }
};
