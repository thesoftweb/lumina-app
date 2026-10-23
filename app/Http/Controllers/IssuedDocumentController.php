<?php

namespace App\Http\Controllers;

use App\Enums\IssuedDocumentType;
use App\Models\IssuedDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class IssuedDocumentController extends Controller
{
    /** Documento emitido para imprimir ou salvar em PDF pelo navegador (reimpressão inclusa). */
    public function show(Request $request, IssuedDocument $issuedDocument)
    {
        Gate::authorize('view', $issuedDocument);

        if ($issuedDocument->type === IssuedDocumentType::PickupCard) {
            return $this->cardsPage(collect([$issuedDocument]), $request->boolean('print'));
        }

        return response()
            ->view('documents.issued.print', [
                'document' => $issuedDocument,
                'autoPrint' => $request->boolean('print'),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Documento emitido em PDF (dompdf): abre no navegador ou, com ?download=1, baixa o arquivo. */
    public function pdf(Request $request, IssuedDocument $issuedDocument)
    {
        Gate::authorize('view', $issuedDocument);
        abort_if($issuedDocument->type === IssuedDocumentType::PickupCard, 404);

        $pdf = Pdf::loadView('pdf.issued-document', ['document' => $issuedDocument])->setPaper('a4');
        $filename = str($issuedDocument->type->getLabel().' '.$issuedDocument->subject.' '.$issuedDocument->number)->slug().'.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /** Várias carteirinhas numa folha A4 (até 4 por folha): ?ids[]=1&ids[]=2 */
    public function cards(Request $request)
    {
        $ids = collect($request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer']])['ids']);

        $documents = IssuedDocument::query()
            ->whereKey($ids)
            ->where('type', IssuedDocumentType::PickupCard)
            ->get()
            ->sortBy(fn (IssuedDocument $document): int => $ids->search($document->getKey()))
            ->values();

        abort_if($documents->isEmpty(), 404);
        $documents->each(fn (IssuedDocument $document) => Gate::authorize('view', $document));

        return $this->cardsPage($documents, $request->boolean('print'));
    }

    private function cardsPage(Collection $documents, bool $autoPrint)
    {
        return response()
            ->view('documents.issued.cards', ['documents' => $documents, 'autoPrint' => $autoPrint])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
