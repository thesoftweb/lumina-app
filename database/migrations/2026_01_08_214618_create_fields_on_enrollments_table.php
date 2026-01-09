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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('enrollment_tax_paid')->default(false)->after('status');
            $table->boolean('tuition_generated')->default(false)->after('enrollment_tax_paid');
            $table->boolean('new_student')->default(false)->after('enrollment_tax_paid');
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->after('plan_id');
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            $table->text('discount_reason')->nullable()->after('discount_value');
            $table->boolean('use_custom_discount')->default(false)->after('discount_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn([
                // 'enrollment_tax_paid',
                // 'tuition_generated',
                // 'new_student',
                // 'discount_type',
                // 'discount_value',
                // 'discount_reason',
                // 'use_custom_discount'
            ]);
        });
    }
};
