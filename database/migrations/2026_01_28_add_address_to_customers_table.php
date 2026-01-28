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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('phone');
            $table->string('address_number')->nullable()->after('address');
            $table->string('address_complement')->nullable()->after('address_number');
            $table->unsignedBigInteger('city_id')->nullable()->after('address_complement');
            $table->string('postal_code')->nullable()->after('city_id');

            $table->foreign('city_id')
                ->references('id')
                ->on('cities')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['city_id']);
            $table->dropColumn([
                'address',
                'address_number',
                'address_complement',
                'city_id',
                'postal_code',
            ]);
        });
    }
};
