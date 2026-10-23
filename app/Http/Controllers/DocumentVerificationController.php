<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use Illuminate\Http\Request;

/** Página pública para conferir a autenticidade de um documento pelo código de verificação. */
class DocumentVerificationController extends Controller
{
    public function form(Request $request)
    {
        if ($request->filled('codigo')) {
            return redirect()->route('documents.verify', ['code' => trim((string) $request->query('codigo'))]);
        }

        return $this->page(null, null);
    }

    public function show(string $code)
    {
        $normalized = IssuedDocument::normalizeCode($code);
        $document = strlen($normalized) === IssuedDocument::CODE_LENGTH
            ? IssuedDocument::where('verification_code', $normalized)->first()
            : null;

        return $this->page($code, $document);
    }

    private function page(?string $code, ?IssuedDocument $document)
    {
        return response()
            ->view('documents.verify', ['code' => $code, 'document' => $document])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
