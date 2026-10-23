@php
    use App\Documents\PickupCard;
    use App\Enums\IssuedDocumentStatus;
    use App\Enums\IssuedDocumentType;
    use App\Models\IssuedDocument;
    use Illuminate\Support\Facades\Gate;

    $status = $document?->status();
    $intact = $document?->hasIntactContent();
    $isCard = $document?->type === IssuedDocumentType::PickupCard;
    // Carteirinha: a portaria vê o essencial; foto e nome completo da criança só para a equipe logada
    $fullCard = $isCard && auth()->check() && Gate::allows('view', $document);
    $cardContent = $isCard ? ($document->data['content'] ?? []) : [];
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

        .card-summary {
            border-color: #0369a1;
            background: #f0f9ff;
            color: #0c4a6e;
        }

        .card-summary dl {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 8px 16px;
            margin: 0;
        }

        .card-summary dt {
            padding-top: 3px;
            font-size: 12px;
            font-weight: 650;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #0369a1;
        }

        .card-summary dd {
            margin: 0;
        }

        .card-summary dd b {
            display: block;
            font-size: 18px;
            color: #0f172a;
        }

        .card-summary dd span {
            font-size: 14px;
        }

        .card-summary .hint {
            margin-top: 12px;
            font-size: 13px;
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
                <p>{{ $document->type->getLabel() }} nº {{ $document->number }}. Cancelamento em {{ $document->canceled_at->format('d/m/Y') }}. Motivo: {{ $document->cancel_reason }}</p>
            </div>
        @elseif ($status === IssuedDocumentStatus::Expired)
            <div class="result warning" role="status">
                <strong>Documento autêntico, mas vencido</strong>
                <p>{{ $document->type->getLabel() }} nº {{ $document->number }}. Emissão em {{ $document->issued_at->format('d/m/Y') }}, validade até {{ $document->valid_until->format('d/m/Y') }}.</p>
            </div>
        @elseif ($document)
            <div class="result valid" role="status">
                <strong>{{ $isCard ? 'Carteirinha válida' : 'Documento válido' }}</strong>
                <p>{{ $document->type->getLabel() }} nº {{ $document->number }}. Emissão em {{ $document->issued_at->format('d/m/Y') }} por {{ $document->data['company']['name'] ?? 'instituição de ensino' }}{{ $document->valid_until ? ', validade até '.$document->valid_until->format('d/m/Y') : '' }}.{{ $isCard ? '' : ' Compare com o documento abaixo, que é a versão original registrada.' }}</p>
            </div>
        @endif

        {{-- Só para carteirinha válida: cancelada ou vencida não autoriza ninguém --}}
        @if ($isCard && $intact && ! $fullCard && $status === IssuedDocumentStatus::Valid)
            <div class="result card-summary">
                <dl>
                    <dt>Autorizado(a)</dt>
                    <dd>
                        <b>{{ $cardContent['person']['name'] ?? '' }}</b>
                        <span>{{ collect([$cardContent['person']['relationship'] ?? null, filled($cardContent['person']['document'] ?? null) ? 'doc. '.IssuedDocument::maskDocument($cardContent['person']['document']) : null])->filter()->implode(' · ') }}</span>
                    </dd>
                    <dt>Pode buscar</dt>
                    <dd>
                        <b>{{ PickupCard::abbreviate($cardContent['student']['name'] ?? '') }}</b>
                        <span>{{ $cardContent['student']['grade'] ?? '' }}</span>
                    </dd>
                </dl>
                <p class="hint">Confira o documento com foto de quem veio buscar. A equipe da escola, com login, vê a carteirinha completa.</p>
            </div>
        @endif
    </section>

    @if ($document && $intact && (! $isCard || $fullCard))
        @if ($isCard)
            <div style="display: flex; justify-content: center; margin: 24px 0;">
                @include('documents.issued.card', ['document' => $document])
            </div>
        @else
            @include('documents.issued.document', ['document' => $document, 'public' => true])
        @endif
    @endif
</body>

</html>
