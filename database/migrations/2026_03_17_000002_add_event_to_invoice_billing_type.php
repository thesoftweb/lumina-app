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
        // Modify the billing_type enum to include 'event'
        Schema::table('invoices', function (Blueprint $table) {
            // For MySQL, we need to modify the column
            DB::statement("ALTER TABLE invoices MODIFY billing_type ENUM('tuition', 'service', 'material', 'other', 'enrollment', 'event') DEFAULT 'tuition'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            DB::statement("ALTER TABLE invoices MODIFY billing_type ENUM('tuition', 'service', 'material', 'other', 'enrollment') DEFAULT 'tuition'");
        });
    }
};
