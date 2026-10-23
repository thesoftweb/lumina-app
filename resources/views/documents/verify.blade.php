@php
    use App\Enums\IssuedDocumentStatus;

    $status = $document?->status();
    $intact = $document?->hasIntactContent();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificar documento</title>
    @include('documents.issued.fonts')
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e2e8f0;
            color: #0f172a;
            font-family: 'Inter Variable', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .panel {
            max-width: 210mm;
            margin: 24px auto 0;
            padding: 20px 24px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
        }

        h1 {
            margin: 0 0 4px;
            font-size: 20px;
        }

        p {
            margin: 0;
            color: #475569;
        }

        form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        input {
            flex: 1 1 260px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: 600 16px 'Inter Variable', Inter, system-ui, sans-serif;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        button {
            padding: 10px 18px;
            border: 0;
            border-radius: 8px;
            background: #0ea5e9;
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
        }

        .result {
            margin-top: 16px;
            padding: 14px 16px;
            border-left: 5px solid;
            border-radius: 8px;
        }

        .result strong {
            display: block;
            font-size: 17px;
        }

        .result p {
            margin-top: 2px;
            color: inherit;
        }

        .valid {
            border-color: #16a34a;
            background: #f0fdf4;
            color: #14532d;
        }

        .warning {
            border-color: #d97706;
            background: #fffbeb;
            color: #78350f;
        }

        .invalid {
            border-color: #dc2626;
            background: #fef2f2;
            color: #7f1d1d;
        }

        @media (max-width: 820px) {
            .panel {
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <section class="panel">
        <h1>Verificar autenticidade de documento</h1>
        <p>Digite o código de verificação impresso no rodapé do documento ou leia o QR code.</p>

        <form method="GET" action="{{ route('documents.verify.form') }}">
            <label for="codigo" style="position:absolute;left:-9999px">Código de verificação</label>
            <input id="codigo" name="codigo" value="{{ $code }}" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" required>
            <button type="submit">Verificar</button>
        </form>

        @if ($code !== null && ! $document)
            <div class="result invalid" role="status">
                <strong>Documento não encontrado</strong>
                <p>Nenhum documento foi emitido com este código. Confira se o código foi digitado corretamente.</p>
            </div>
        @elseif ($document && ! $intact)
            <div class="result invalid" role="status">
                <strong>Não foi possível confirmar este documento</strong>
                <p>O registro deste documento não confere com o original. Entre em contato com a escola.</p>
            </div>
        @elseif ($status === IssuedDocumentStatus::Canceled)
            <div class="result invalid" role="status">
                <strong>Documento cancelado</strong>
                <p>{{ $document->type->getLabel() }} nº {{ $document->number }}, cancelado em {{ $document->canceled_at->format('d/m/Y') }}. Motivo: {{ $document->cancel_reason }}</p>
            </div>
        @elseif ($status === IssuedDocumentStatus::Expired)
            <div class="result warning" role="status">
                <strong>Documento autêntico, mas vencido</strong>
                <p>{{ $document->type->getLabel() }} nº {{ $document->number }}, emitido em {{ $document->issued_at->format('d/m/Y') }} e válido até {{ $document->valid_until->format('d/m/Y') }}.</p>
            </div>
        @elseif ($document)
            <div class="result valid" role="status">
                <strong>Documento válido</strong>
                <p>{{ $document->type->getLabel() }} nº {{ $document->number }}, emitido em {{ $document->issued_at->format('d/m/Y') }} por {{ $document->data['company']['name'] ?? 'instituição de ensino' }}{{ $document->valid_until ? ', válido até '.$document->valid_until->format('d/m/Y') : '' }}. Compare com o documento abaixo, que é a versão original registrada.</p>
            </div>
        @endif
    </section>

    @if ($document && $intact)
        @include('documents.issued.document', ['document' => $document, 'public' => true])
    @endif
</body>

</html>
