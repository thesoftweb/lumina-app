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
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('discount_source', ['plan', 'enrollment_custom', 'manual'])->default('plan')->after('amount');
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->after('discount_source');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            $table->decimal('original_amount', 10, 2)->nullable()->after('discount_value');
            $table->decimal('final_amount', 10, 2)->nullable()->after('original_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_source', 'discount_type', 'discount_value', 'original_amount', 'final_amount']);
        });
    }
};
