<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Remover classroom_id como foreign key e o campo apply_to_all_classrooms
            if (Schema::hasColumn('events', 'classroom_id')) {
                try {
                    $table->dropForeign(['classroom_id']);
                } catch (\Exception $e) {
                    // Foreign key may not exist
                }
            }

            if (Schema::hasColumn('events', 'classroom_id')) {
                $table->dropColumn('classroom_id');
            }

            if (Schema::hasColumn('events', 'apply_to_all_classrooms')) {
                $table->dropColumn('apply_to_all_classrooms');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('classroom_id')->after('id');
            $table->boolean('apply_to_all_classrooms')->default(false)->after('classroom_id');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
        });
    }
};
