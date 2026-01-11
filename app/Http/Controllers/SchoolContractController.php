<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use Spatie\LaravelPdf\Facades\Pdf;

class SchoolContractController extends Controller
{
    /**
     * Exibir página de impressão do contrato escolar
     */
    public function show(Enrollment $enrollment)
    {
        return view('contracts.school-contract-print', [
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Gerar PDF do contrato escolar
     */
    public function pdf(Enrollment $enrollment)
    {
        return Pdf::view('contracts.school-contract-pdf', [
            'enrollment' => $enrollment,
        ])
            ->format('a4')
            ->name($this->getFileName($enrollment));
    }

    /**
     * Gerar nome do arquivo PDF
     */
    private function getFileName(Enrollment $enrollment): string
    {
        $studentName = $enrollment->student->name ?? 'aluno';
        $date = $enrollment->created_at->format('Y-m-d');
        $id = str_pad($enrollment->id, 6, '0', STR_PAD_LEFT);

        return "contrato-escolar-{$studentName}-{$date}-{$id}.pdf";
    }
}
