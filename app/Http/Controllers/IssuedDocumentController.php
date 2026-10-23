<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IssuedDocumentController extends Controller
{
    /** Documento emitido em A4 para imprimir ou salvar em PDF pelo navegador (reimpressão inclusa). */
    public function show(Request $request, IssuedDocument $issuedDocument)
    {
        Gate::authorize('view', $issuedDocument);

        return response()
            ->view('documents.issued.print', [
                'document' => $issuedDocument,
                'autoPrint' => $request->boolean('print'),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
