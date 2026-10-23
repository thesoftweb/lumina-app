{{--
    Documento emitido em A4. Tudo vem do conteúdo congelado na emissão ($document->data).
    $public = true na página de verificação: oculta parte do CPF.
--}}
@php
    use App\Enums\IssuedDocumentStatus;

    $company = $document->data['company'] ?? [];
    $signer = $document->data['signer'] ?? [];
    $content = $document->data['content'] ?? [];
    $public = $public ?? false;
    $logo = $document->logoDataUri();
    $status = $document->status();
    $contacts = collect([
        filled($company['tax_id'] ?? null) ? "CNPJ {$company['tax_id']}" : null,
        filled($company['inep_code'] ?? null) ? "INEP {$company['inep_code']}" : null,
        $company['phone'] ?? null,
        $company['email'] ?? null,
    ])->filter()->implode(' · ');
    $issuedOn = $document->issued_at->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
@endphp
<style>
    .issued-doc {
        --ink: #0f172a;
        --muted: #475569;
        --line: #cbd5e1;
        position: relative;
        width: 210mm;
        min-height: 297mm;
        margin: 24px auto;
        padding: 18mm 20mm 14mm;
        display: flex;
        flex-direction: column;
        background: #fff;
        color: var(--ink);
        font: 12pt/1.6 Georgia, 'Times New Roman', serif;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    }

    .issued-doc * {
        box-sizing: border-box;
    }

    .issued-doc header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--ink);
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    }

    .issued-doc .logo {
        flex-shrink: 0;
        max-width: 72px;
        max-height: 72px;
        object-fit: contain;
    }

    .issued-doc .school-name {
        font-size: 15px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .issued-doc .school-info {
        font-size: 10px;
        line-height: 1.5;
        color: var(--muted);
    }

    .issued-doc h1 {
        margin: 18mm 0 4mm;
        text-align: center;
        font-size: 17pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .issued-doc .doc-number {
        margin-bottom: 12mm;
        text-align: center;
        font: 11px system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        color: var(--muted);
    }

    .issued-doc .body p {
        margin: 0 0 5mm;
        text-align: justify;
        text-indent: 12mm;
    }

    .issued-doc .place-date {
        margin: 10mm 0 0;
        text-align: right;
    }

    .issued-doc .signature {
        width: 90mm;
        margin: 24mm auto 0;
        padding-top: 6px;
        border-top: 1px solid var(--ink);
        text-align: center;
        line-height: 1.4;
    }

    .issued-doc .signature small {
        display: block;
        font-size: 10pt;
        color: var(--muted);
    }

    .issued-doc footer {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: auto;
        padding-top: 10px;
        border-top: 1px solid var(--line);
        font: 9.5px/1.5 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        color: var(--muted);
    }

    .issued-doc footer img {
        flex-shrink: 0;
        width: 26mm;
        height: 26mm;
    }

    .issued-doc footer strong {
        color: var(--ink);
    }

    .issued-doc .code {
        font-family: ui-monospace, Consolas, monospace;
        font-size: 11px;
        letter-spacing: 0.05em;
    }

    .issued-doc .watermark {
        position: absolute;
        top: 42%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-28deg);
        padding: 4mm 10mm;
        border: 4px solid #dc2626;
        color: #dc2626;
        font: 700 40pt system-ui, sans-serif;
        letter-spacing: 0.1em;
        opacity: 0.35;
        pointer-events: none;
    }

    @media screen and (max-width: 820px) {
        .issued-doc {
            width: auto;
            min-height: 0;
            margin: 0;
            padding: 16px;
            box-shadow: none;
        }

        .issued-doc h1 {
            margin-top: 24px;
        }
    }

    @media print {
        @page {
            size: A4;
            margin: 0;
        }

        .issued-doc {
            margin: 0;
            box-shadow: none;
        }
    }
</style>

<article class="issued-doc">
    @if ($status === IssuedDocumentStatus::Canceled)
        <div class="watermark">CANCELADO</div>
    @endif

    <header>
        @if ($logo)
            <img class="logo" src="{{ $logo }}" alt="Logo {{ $company['name'] ?? '' }}">
        @endif
        <div>
            <div class="school-name">{{ $company['name'] ?? 'Instituição de ensino' }}</div>
            @if ($contacts)
                <div class="school-info">{{ $contacts }}</div>
            @endif
            @if (filled($company['address'] ?? null))
                <div class="school-info">{{ $company['address'] }}</div>
            @endif
        </div>
    </header>

    <h1>{{ $document->type->getLabel() }}</h1>
    <div class="doc-number">Nº {{ $document->number }}</div>

    <div class="body">
        @include($document->type->view(), ['content' => $content, 'public' => $public, 'document' => $document])
    </div>

    <p class="place-date">{{ filled($company['city'] ?? null) ? $company['city'].', ' : '' }}{{ $issuedOn }}.</p>

    <div class="signature">
        <strong>{{ $signer['name'] ?? '' }}</strong>
        <small>{{ $signer['role'] ?? '' }}</small>
    </div>

    <footer>
        <img src="{{ $document->qrCodeDataUri() }}" alt="QR code para verificar este documento">
        <div>
            Documento <strong>{{ $document->number }}</strong>, emitido em {{ $document->issued_at->format('d/m/Y') }} às {{ $document->issued_at->format('H:i') }}.
            @if ($document->valid_until)
                Válido até <strong>{{ $document->valid_until->format('d/m/Y') }}</strong>.
            @endif
            <br>
            Código de verificação: <strong class="code">{{ $document->formattedCode() }}</strong><br>
            Confira a autenticidade lendo o QR code ou em <strong>{{ route('documents.verify.form') }}</strong>
        </div>
    </footer>
</article>
