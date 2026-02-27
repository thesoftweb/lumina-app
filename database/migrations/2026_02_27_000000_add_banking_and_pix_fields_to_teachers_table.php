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
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('account_type')->nullable()->after('document_number');
            $table->string('bank_name')->nullable()->after('account_type');
            $table->string('bank_code', 3)->nullable()->after('bank_name');
            $table->string('agency_number')->nullable()->after('bank_code');
            $table->string('account_number')->nullable()->after('agency_number');
            $table->string('account_holder_name')->nullable()->after('account_number');
            $table->string('pix_key_type')->nullable()->after('account_holder_name');
            $table->string('pix_key')->nullable()->after('pix_key_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn([
                'account_type',
                'bank_name',
                'bank_code',
                'agency_number',
                'account_number',
                'account_holder_name',
                'pix_key_type',
                'pix_key',
            ]);
        });
    }
};
