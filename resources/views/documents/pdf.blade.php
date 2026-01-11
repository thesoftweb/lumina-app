<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->template->name ?? 'Documento' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .document-container {
            width: 210mm;
            height: 297mm;
            padding: 20mm;
            margin: 0;
            background: white;
        }

        .document-header {
            border-bottom: 2px solid #ccc;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .document-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .document-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 11px;
            color: #666;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
        }

        .document-content {
            margin: 25px 0;
            min-height: 150px;
            font-size: 13px;
            line-height: 1.6;
        }

        .content-html img {
            max-width: 100%;
            height: auto;
        }

        .document-data {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
            font-size: 12px;
        }

        .data-section {
            padding: 10px;
            background: #f5f5f5;
            border-left: 3px solid #333;
            padding-left: 10px;
        }

        .data-section-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .data-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dotted #ddd;
        }

        .data-item strong {
            flex: 0 0 45%;
        }

        .document-footer {
            position: absolute;
            bottom: 20mm;
            left: 0;
            right: 0;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            font-size: 10px;
            color: #999;
            text-align: center;
            width: 170mm;
            margin: 0 20mm;
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .document-container {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 20mm;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <div class="document-container">
        <div class="document-header">
            <div class="document-title">{{ $document->template->name }}</div>
            <div class="document-meta">
                <div class="meta-item">
                    <strong>Data:</strong>
                    <span>{{ $document->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="meta-item">
                    <strong>Status:</strong>
                    <span>
                        @switch($document->status)
                        @case('draft')
                        Rascunho
                        @break
                        @case('generated')
                        Gerado
                        @break
                        @case('sent')
                        Enviado
                        @break
                        @default
                        {{ $document->status }}
                        @endswitch
                    </span>
                </div>
            </div>
        </div>

        <div class="document-content">
            <div class="content-html">
                {!! $document->content !!}
            </div>
        </div>

        @if ($documentData = json_decode($document->data, true))
        <div class="document-data">
            @if (!empty($documentData['customer_name']) || !empty($documentData['customer_email']) || !empty($documentData['phone']))
            <div class="data-section">
                <div class="data-section-title">Dados do Cliente</div>
                @if (!empty($documentData['customer_name']))
                <div class="data-item">
                    <strong>Nome:</strong>
                    <span>{{ $documentData['customer_name'] }}</span>
                </div>
                @endif
                @if (!empty($documentData['customer_email']))
                <div class="data-item">
                    <strong>Email:</strong>
                    <span>{{ $documentData['customer_email'] }}</span>
                </div>
                @endif
                @if (!empty($documentData['phone']))
                <div class="data-item">
                    <strong>Telefone:</strong>
                    <span>{{ $documentData['phone'] }}</span>
                </div>
                @endif
                @if (!empty($documentData['cpf']))
                <div class="data-item">
                    <strong>CPF:</strong>
                    <span>{{ $documentData['cpf'] }}</span>
                </div>
                @endif
                @if (!empty($documentData['cnpj']))
                <div class="data-item">
                    <strong>CNPJ:</strong>
                    <span>{{ $documentData['cnpj'] }}</span>
                </div>
                @endif
            </div>
            @endif

            @if (!empty($documentData['receipt_number']) || !empty($documentData['amount']) || !empty($documentData['date']))
            <div class="data-section">
                <div class="data-section-title">Dados da Transação</div>
                @if (!empty($documentData['receipt_number']))
                <div class="data-item">
                    <strong>Recibo:</strong>
                    <span>{{ $documentData['receipt_number'] }}</span>
                </div>
                @endif
                @if (!empty($documentData['amount']))
                <div class="data-item">
                    <strong>Valor:</strong>
                    <span>R$ {{ number_format($documentData['amount'], 2, ',', '.') }}</span>
                </div>
                @endif
                @if (!empty($documentData['date']))
                <div class="data-item">
                    <strong>Data:</strong>
                    <span>{{ $documentData['date'] }}</span>
                </div>
                @endif
                @if (!empty($documentData['document_number']))
                <div class="data-item">
                    <strong>Documento:</strong>
                    <span>{{ $documentData['document_number'] }}</span>
                </div>
                @endif
            </div>
            @endif

            @if (!empty($documentData['company_name']) || !empty($documentData['company_address']))
            <div class="data-section">
                <div class="data-section-title">Dados da Empresa</div>
                @if (!empty($documentData['company_name']))
                <div class="data-item">
                    <strong>Empresa:</strong>
                    <span>{{ $documentData['company_name'] }}</span>
                </div>
                @endif
                @if (!empty($documentData['company_address']))
                <div class="data-item">
                    <strong>Endereço:</strong>
                    <span>{{ $documentData['company_address'] }}</span>
                </div>
                @endif
            </div>
            @endif
        </div>
        @endif

        <div class="document-footer">
            <p>Documento gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</body>

</html>