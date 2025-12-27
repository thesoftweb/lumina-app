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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance')->nullable();
            $table->enum('status', ['open', 'partial', 'paid', 'canceled', 'overdue'])->default('open');
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->enum('billing_type', ['tuition', 'service', 'material', 'other', 'enrollment'])->default('tuition');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
