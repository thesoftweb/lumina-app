<?php

use App\Models\Customer;
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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Customer::class)->constrained()->onDelete('cascade');
            $table->string('name');
            $table->date('date_of_birth');
            $table->char('gender', 1)->default('M');
            $table->string('city_of_birth')->nullable();
            $table->string('state_of_birth')->nullable();
            $table->string('reg_number')->nullable();
            $table->string('doc_number')->nullable();
            $table->string('affiliation_1')->nullable();
            $table->string('affiliation_2')->nullable();
            $table->string('phone_primary')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
