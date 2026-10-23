<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificações recebidas do webhook do Asaas: auditoria e idempotência (o Asaas entrega "pelo menos
 * uma vez", então a mesma notificação pode chegar repetida; o id do evento é único).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asaas_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique()->comment('id da notificação no Asaas (evt_...)');
            $table->string('event', 100)->index()->comment('ex.: PAYMENT_RECEIVED');
            $table->string('payment_id')->nullable()->index()->comment('id da cobrança no Asaas (pay_...)');
            $table->string('external_reference')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('received')->index()->comment('received, processed, ignored, failed');
            $table->text('result')->nullable()->comment('o que o processamento fez, avisos');
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_webhook_events');
    }
};
