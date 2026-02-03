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
            $table->unsignedBigInteger('enrollment_id')->nullable()->after('customer_id');
            $table->foreign('enrollment_id')
                ->references('id')
                ->on('enrollments')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['enrollment_id']);
            $table->dropColumn('enrollment_id');
        });
    }
};
