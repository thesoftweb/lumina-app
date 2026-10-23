<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $documents->count() === 1 ? 'Carteirinha '.$documents->first()->number : $documents->count().' carteirinhas' }}</title>
    @include('documents.issued.fonts')
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e2e8f0;
            font-family: 'Inter Variable', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
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
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .toolbar button.primary {
            background: #0ea5e9;
        }

        /* Folha A4: até 4 carteirinhas, cada uma com frente e verso lado a lado */
        .sheet {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12mm;
            width: 210mm;
            min-height: 297mm;
            margin: 24px auto;
            padding: 14mm 0;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
        }

        .hint {
            width: 171.2mm;
            margin: -6mm 0 -4mm;
            font-size: 7.5pt;
            color: #64748b;
        }

        @media screen and (max-width: 820px) {
            .sheet {
                width: auto;
                margin: 0;
                padding: 16px;
                overflow-x: auto;
                align-items: flex-start;
                box-shadow: none;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                box-shadow: none;
                break-after: page;
            }

            .sheet:last-child {
                break-after: auto;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div>
            <strong>{{ $documents->count() === 1 ? 'Carteirinha '.$documents->first()->number : $documents->count().' carteirinhas' }}</strong>
            <small>Imprima em tamanho real (100%), recorte na linha pontilhada, dobre na linha central e plastifique.</small>
        </div>
        <div class="actions">
            <button type="button" onclick="window.close()">Fechar</button>
            <button type="button" class="primary" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    @foreach ($documents->chunk(4) as $page)
        <main class="sheet">
            <div class="hint">Recorte na linha pontilhada · dobre na linha central (frente e verso) · plastifique</div>
            @foreach ($page as $document)
                @include('documents.issued.card', ['document' => $document])
            @endforeach
        </main>
    @endforeach

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => document.fonts.ready.then(() => window.print()));
        </script>
    @endif
</body>

</html>
