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
            $table->boolean('doc_historical_delivered')->default(false)->after('use_custom_discount')->comment('Histórico escolar entregue');
            $table->boolean('doc_photo_3x4_delivered')->default(false)->after('doc_historical_delivered')->comment('Foto 3x4 entregue');
            $table->boolean('doc_declaration_delivered')->default(false)->after('doc_photo_3x4_delivered')->comment('Declaração entregue');
            $table->boolean('doc_residence_proof_delivered')->default(false)->after('doc_declaration_delivered')->comment('Comprovante de residência entregue');
            $table->boolean('doc_student_document_delivered')->default(false)->after('doc_residence_proof_delivered')->comment('Documento do aluno entregue');
            $table->boolean('doc_responsible_document_delivered')->default(false)->after('doc_student_document_delivered')->comment('Documento do responsável entregue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'doc_historical_delivered',
                'doc_photo_3x4_delivered',
                'doc_declaration_delivered',
                'doc_residence_proof_delivered',
                'doc_student_document_delivered',
                'doc_responsible_document_delivered',
            ]);
        });
    }
};
