<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert dev_boards.type from a fixed enum ('feature','bug','custom')
     * to a plain string so new board types (e.g. 'inbox') can be added without
     * DB-specific ALTER ENUM statements. Same swap-column pattern as the
     * story_points -> string conversion.
     */
    public function up(): void
    {
        Schema::table('dev_boards', function (Blueprint $table) {
            $table->string('type_new', 20)->default('custom')->after('type');
        });

        DB::statement('UPDATE dev_boards SET type_new = type');

        Schema::table('dev_boards', function (Blueprint $table) {
            $table->dropIndex('dev_boards_package_type_idx');
            $table->dropColumn('type');
        });

        Schema::table('dev_boards', function (Blueprint $table) {
            $table->renameColumn('type_new', 'type');
        });

        Schema::table('dev_boards', function (Blueprint $table) {
            $table->index(['dev_package_id', 'type'], 'dev_boards_package_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dev_boards', function (Blueprint $table) {
            $table->enum('type_old', ['feature', 'bug', 'custom'])->default('custom')->after('type');
        });

        // Preserve known values; collapse any newer types (e.g. 'inbox') to 'custom'.
        DB::statement("UPDATE dev_boards SET type_old = type WHERE type IN ('feature', 'bug', 'custom')");
        DB::statement("UPDATE dev_boards SET type_old = 'custom' WHERE type NOT IN ('feature', 'bug', 'custom')");

        Schema::table('dev_boards', function (Blueprint $table) {
            $table->dropIndex('dev_boards_package_type_idx');
            $table->dropColumn('type');
        });

        Schema::table('dev_boards', function (Blueprint $table) {
            $table->renameColumn('type_old', 'type');
        });

        Schema::table('dev_boards', function (Blueprint $table) {
            $table->index(['dev_package_id', 'type'], 'dev_boards_package_type_idx');
        });
    }
};
