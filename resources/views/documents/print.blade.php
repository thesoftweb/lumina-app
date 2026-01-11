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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
                'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue',
                sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }

        .print-container {
            max-width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 20mm;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            line-height: 1.5;
        }

        .print-actions {
            margin-bottom: 20px;
            text-align: center;
            display: no-print;
        }

        .print-actions button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 0 5px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .print-actions button:hover {
            background: #1d4ed8;
        }

        .print-actions .btn-back {
            background: #6b7280;
        }

        .print-actions .btn-back:hover {
            background: #4b5563;
        }

        .document-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .document-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 12px;
            color: #666;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
        }

        .document-content {
            margin: 30px 0;
            min-height: 150px;
        }

        .content-html {
            font-size: 14px;
            line-height: 1.6;
        }

        .content-html img {
            max-width: 100%;
            height: auto;
        }

        .document-footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            margin-top: 30px;
            font-size: 11px;
            color: #999;
            text-align: center;
        }

        .document-data {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
            font-size: 13px;
        }

        .data-section {
            padding: 10px;
            background: #f9fafb;
            border-left: 3px solid #2563eb;
            padding-left: 12px;
        }

        .data-section-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .data-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .data-item strong {
            flex: 0 0 40%;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .print-container {
                max-width: 100%;
                height: 100%;
                margin: 0;
                padding: 20mm;
                box-shadow: none;
                page-break-after: always;
            }

            .print-actions {
                display: none;
            }

            @page {
                size: A4;
                margin: 0;
            }
        }

        @media (max-width: 768px) {

            .document-meta,
            .document-data {
                grid-template-columns: 1fr;
            }

            .print-container {
                padding: 15mm;
            }
        }
    </style>
</head>

<body>
    <div class="print-actions" style="no-print: true">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir</button>
        <button class="btn-pdf" onclick="downloadPDF()">📄 Baixar PDF</button>
        <button class="btn-back" onclick="window.history.back()">← Voltar</button>
    </div>

    <div class="print-container">
        <div class="document-header">
            <div class="document-title">{{ $document->template->name }}</div>
            <div class="document-meta">
                <div class="meta-item">
                    <strong>Criado em:</strong>
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

    <script>
        function downloadPDF() {
            const documentId = {
                {
                    $document - > id
                }
            };
            window.location.href = `/documents/${documentId}/pdf`;
        }
    </script>
</body>

</html>