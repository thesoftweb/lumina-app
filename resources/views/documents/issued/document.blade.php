{{--
    Documento emitido em A4. Tudo vem do conteúdo congelado na emissão ($document->data).
    $public = true na página de verificação: oculta parte do CPF.
    A página que inclui este arquivo carrega a fonte Inter (documents.issued.fonts).
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
    ])->filter()->implode('  ·  ');
    $issuedOn = $document->issued_at->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
@endphp
<style>
    .issued-doc {
        --ink: #0f172a;
        --text: #1e293b;
        --muted: #64748b;
        --line: #e2e8f0;
        --accent: #0369a1;
        position: relative;
        width: 210mm;
        min-height: 297mm;
        margin: 24px auto;
        padding: 16mm 20mm 14mm;
        display: flex;
        flex-direction: column;
        background: #fff;
        color: var(--text);
        font-family: 'Inter Variable', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        font-size: 11pt;
        line-height: 1.75;
        -webkit-font-smoothing: antialiased;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    }

    .issued-doc * {
        box-sizing: border-box;
    }

    .issued-doc header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--line);
    }

    .issued-doc .logo {
        flex-shrink: 0;
        max-width: 64px;
        max-height: 64px;
        object-fit: contain;
    }

    .issued-doc .school-name {
        color: var(--ink);
        font-size: 12.5pt;
        font-weight: 650;
        line-height: 1.3;
        letter-spacing: -0.01em;
    }

    .issued-doc .school-info {
        margin-top: 2px;
        font-size: 8pt;
        line-height: 1.5;
        color: var(--muted);
        white-space: pre-wrap;
    }

    .issued-doc .title {
        margin: 22mm 0 14mm;
        text-align: center;
    }

    .issued-doc h1 {
        margin: 0;
        color: var(--ink);
        font-size: 15pt;
        font-weight: 700;
        line-height: 1.3;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .issued-doc .title::after {
        content: '';
        display: block;
        width: 36px;
        height: 2px;
        margin: 10px auto 8px;
        background: var(--accent);
    }

    .issued-doc .doc-number {
        font-size: 8.5pt;
        font-weight: 500;
        letter-spacing: 0.02em;
        color: var(--muted);
    }

    .issued-doc .body p {
        margin: 0 0 4.5mm;
        text-align: justify;
        hyphens: auto;
    }

    .issued-doc .body strong {
        color: var(--ink);
        font-weight: 600;
    }

    .issued-doc .place-date {
        margin: 12mm 0 0;
        text-align: right;
    }

    .issued-doc .signature {
        width: 80mm;
        margin: 26mm auto 0;
        padding-top: 8px;
        border-top: 1px solid var(--ink);
        text-align: center;
        line-height: 1.4;
    }

    .issued-doc .signature strong {
        display: block;
        color: var(--ink);
        font-weight: 600;
    }

    .issued-doc .signature small {
        display: block;
        margin-top: 2px;
        font-size: 9pt;
        color: var(--muted);
    }

    .issued-doc footer {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-top: auto;
        padding: 10px 12px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #f8fafc;
        font-size: 7.5pt;
        line-height: 1.6;
        color: var(--muted);
    }

    .issued-doc footer img {
        flex-shrink: 0;
        width: 22mm;
        height: 22mm;
        padding: 4px;
        border-radius: 4px;
        background: #fff;
    }

    .issued-doc footer .label {
        display: block;
        margin-bottom: 2px;
        color: var(--ink);
        font-size: 7pt;
        font-weight: 650;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .issued-doc footer strong {
        color: var(--text);
        font-weight: 600;
    }

    .issued-doc .code {
        color: var(--ink);
        font-size: 9.5pt;
        font-weight: 650;
        letter-spacing: 0.12em;
    }

    .issued-doc .watermark {
        position: absolute;
        top: 42%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-28deg);
        padding: 4mm 10mm;
        border: 4px solid #dc2626;
        border-radius: 6px;
        color: #dc2626;
        font-size: 38pt;
        font-weight: 750;
        letter-spacing: 0.14em;
        opacity: 0.3;
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

        .issued-doc .title {
            margin: 28px 0 20px;
        }

        .issued-doc .signature {
            margin-top: 48px;
        }

        .issued-doc footer {
            margin-top: 32px;
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
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
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

    <div class="title">
        <h1>{{ $document->type->getLabel() }}</h1>
        <div class="doc-number">Nº {{ $document->number }}</div>
    </div>

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
            <span class="label">Verificação de autenticidade</span>
            Código <strong class="code">{{ $document->formattedCode() }}</strong><br>
            Leia o QR code ou acesse <strong>{{ route('documents.verify.form') }}</strong> e informe o código.<br>
            Documento <strong>{{ $document->number }}</strong>, emitido em {{ $document->issued_at->format('d/m/Y') }} às {{ $document->issued_at->format('H:i') }}@if ($document->valid_until), válido até <strong>{{ $document->valid_until->format('d/m/Y') }}</strong>@endif.
        </div>
    </footer>
</article>
