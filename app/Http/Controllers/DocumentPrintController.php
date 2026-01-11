<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Spatie\LaravelPdf\Facades\Pdf;

class DocumentPrintController extends Controller
{
    /**
     * Exibir página de impressão do documento
     */
    public function show(Document $document)
    {
        return view('documents.print', [
            'document' => $document,
        ]);
    }

    /**
     * Gerar PDF do documento
     */
    public function pdf(Document $document)
    {
        return Pdf::view('documents.pdf', [
            'document' => $document,
        ])
            ->format('a4')
            ->name($this->getFileName($document));
    }

    /**
     * Gerar nome do arquivo PDF
     */
    private function getFileName(Document $document): string
    {
        $templateName = $document->template->name ?? 'documento';
        $date = $document->created_at->format('Y-m-d');
        $id = str_pad($document->id, 6, '0', STR_PAD_LEFT);

        return "{$templateName}-{$date}-{$id}.pdf";
    }
}
