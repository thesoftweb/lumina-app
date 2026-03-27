<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('student_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });

        // Populate customer_id from student relationship
        DB::statement('
            UPDATE enrollments e
            JOIN students s ON e.student_id = s.id
            SET e.customer_id = s.customer_id
            WHERE e.customer_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
