<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $document->number }} · {{ $document->type->getLabel() }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e2e8f0;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            background: #0f172a;
            color: #fff;
        }

        .toolbar small {
            display: block;
            color: #cbd5e1;
        }

        .toolbar .actions {
            display: flex;
            gap: 8px;
        }

        .toolbar button {
            padding: 8px 16px;
            border: 0;
            border-radius: 8px;
            background: #334155;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .toolbar button.primary {
            background: #0ea5e9;
        }

        .toolbar .canceled {
            color: #fca5a5;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div>
            <strong>{{ $document->type->getLabel() }} · {{ $document->number }}</strong>
            @if ($document->canceled_at)
                <small class="canceled">Cancelado em {{ $document->canceled_at->format('d/m/Y') }}: {{ $document->cancel_reason }}</small>
            @else
                <small>Para gerar o PDF, escolha "Salvar como PDF" no destino da impressão.</small>
            @endif
        </div>
        <div class="actions">
            <button type="button" onclick="window.close()">Fechar</button>
            <button type="button" class="primary" onclick="window.print()">Imprimir / Salvar em PDF</button>
        </div>
    </div>

    @include('documents.issued.document', ['document' => $document, 'public' => false])

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>

</html>
