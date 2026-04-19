<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert story_points from unsignedSmallInteger to string (enum-backed T-shirt sizes).
     * Maps existing numeric values to the closest Fibonacci-based size.
     */
    public function up(): void
    {
        // Step 1: Add temporary column
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->string('story_points_new', 10)->nullable()->after('story_points');
        });

        // Step 2: Map existing integer values to T-shirt sizes
        // Fibonacci: XS=1, S=2, M=3, L=5, XL=8, XXL=13
        $mapping = [
            [0, 1, 'xs'],
            [2, 2, 's'],
            [3, 3, 'm'],
            [4, 6, 'l'],
            [7, 10, 'xl'],
            [11, 100, 'xxl'],
        ];

        foreach ($mapping as [$min, $max, $size]) {
            DB::table('dev_issues')
                ->whereNotNull('story_points')
                ->whereBetween('story_points', [$min, $max])
                ->update(['story_points_new' => $size]);
        }

        // Step 3: Drop old column, rename new
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->dropColumn('story_points');
        });

        Schema::table('dev_issues', function (Blueprint $table) {
            $table->renameColumn('story_points_new', 'story_points');
        });
    }

    public function down(): void
    {
        Schema::table('dev_issues', function (Blueprint $table) {
            $table->unsignedSmallInteger('story_points_old')->nullable()->after('story_points');
        });

        $mapping = ['xs' => 1, 's' => 2, 'm' => 3, 'l' => 5, 'xl' => 8, 'xxl' => 13];

        foreach ($mapping as $size => $points) {
            DB::table('dev_issues')
                ->where('story_points', $size)
                ->update(['story_points_old' => $points]);
        }

        Schema::table('dev_issues', function (Blueprint $table) {
            $table->dropColumn('story_points');
        });

        Schema::table('dev_issues', function (Blueprint $table) {
            $table->renameColumn('story_points_old', 'story_points');
        });
    }
};
