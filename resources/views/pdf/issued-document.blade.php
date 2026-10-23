{{--
    Documento emitido em PDF (dompdf): mesmo conteúdo congelado da página do documento, com layout de
    tabelas. O corpo vem da view do tipo (documents.issued.types.*).
--}}
@php
    use App\Enums\IssuedDocumentStatus;

    $company = $document->data['company'] ?? [];
    $signer = $document->data['signer'] ?? [];
    $content = $document->data['content'] ?? [];
    $signers = array_values(array_filter([$signer, $content['second_signer'] ?? null]));
    $issuedOn = $document->issued_at->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $document->type->getLabel() }} {{ $document->number }}</title>
    @include('pdf.styles')
    <style>
        .doc-body p {
            margin: 0 0 3mm;
            text-align: justify;
            font-size: 10pt;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    @if ($document->status() === IssuedDocumentStatus::Canceled)
        <div class="pdf-watermark">CANCELADO</div>
    @endif

    @include('pdf.header', ['school' => $company, 'logo' => $document->logoDataUri()])

    <div class="pdf-title">
        {{ $document->type->getLabel() }}
        <div class="pdf-subtitle">Nº {{ $document->number }}</div>
    </div>

    <div class="doc-body">
        @include($document->type->view(), ['content' => $content, 'public' => false, 'document' => $document])
    </div>

    <p style="margin-top: 6mm; text-align: right">{{ filled($company['city'] ?? null) ? $company['city'].', ' : '' }}{{ $issuedOn }}.</p>

    <table class="pdf-signatures">
        <tr>
            @foreach ($signers as $person)
                <td>
                    <div class="line">
                        <strong>{{ $person['name'] ?? '' }}</strong><br>
                        <span class="pdf-muted">{{ $person['role'] ?? '' }}</span>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="pdf-verification">
        <tr>
            <td style="width: 26mm"><img src="{{ $document->qrCodePngDataUri() }}" alt=""></td>
            <td>
                <strong style="color: #0f172a">VERIFICAÇÃO DE AUTENTICIDADE</strong><br>
                Código <strong style="color: #0f172a; letter-spacing: 1px">{{ $document->formattedCode() }}</strong><br>
                Leia o QR code ou acesse {{ route('documents.verify.form') }} e informe o código.<br>
                Documento {{ $document->number }}, emitido em {{ $document->issued_at->format('d/m/Y') }} às {{ $document->issued_at->format('H:i') }}@if ($document->valid_until), válido até {{ $document->valid_until->format('d/m/Y') }}@endif.
            </td>
        </tr>
    </table>
</body>
</html>
