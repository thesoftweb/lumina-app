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
        // Use Eloquent to be database agnostic (SQLite, MySQL, etc)
        \App\Models\Enrollment::where('customer_id', null)
            ->with('student')
            ->chunk(100, function ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    if ($enrollment->student && $enrollment->student->customer_id) {
                        $enrollment->update(['customer_id' => $enrollment->student->customer_id]);
                    }
                }
            });
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
