<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Escolar</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.8;
            color: #333;
        }

        .document-container {
            width: 210mm;
            height: 297mm;
            padding: 30mm 25mm;
            margin: 0;
            background: white;
        }

        .contract-header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .contract-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .contract-subtitle {
            font-size: 12px;
            font-style: italic;
            color: #666;
        }

        .contract-body {
            text-align: justify;
            font-size: 12px;
            line-height: 1.8;
        }

        .contract-body p {
            margin-bottom: 12px;
            text-indent: 1.5em;
        }

        .contract-body strong {
            font-weight: bold;
        }

        .parties-section {
            margin: 20px 0;
            font-size: 12px;
        }

        .party {
            margin-bottom: 10px;
        }

        .party-label {
            font-weight: bold;
            margin-bottom: 3px;
        }

        .party-info {
            margin-left: 15px;
            font-size: 11px;
            line-height: 1.6;
        }

        .signatures-section {
            margin-top: 35px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .signature-block {
            text-align: center;
            font-size: 11px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 3px;
            padding-top: 30px;
            min-height: 50px;
        }

        .signature-name {
            font-weight: bold;
            margin-top: 5px;
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
            }
        }
    </style>
</head>

<body>
    <div class="document-container">
        <div class="contract-header">
            <div class="contract-title">Contrato de Matrícula Escolar</div>
            <div class="contract-subtitle">Ano Letivo {{ $enrollment->academicYear->year ?? now()->year }}</div>
        </div>

        <div class="contract-body">
            <p>
                <strong>CONTRATANTES:</strong>
            </p>

            <div class="parties-section">
                <div class="party">
                    <div class="party-label">CONTRATADA (INSTITUIÇÃO ESCOLAR):</div>
                    <div class="party-info">
                        A Instituição Escolar, doravante denominada simplesmente <strong>"ESCOLA"</strong>, estabelecida e funcionando na jurisdição competente.
                    </div>
                </div>

                <div class="party">
                    <div class="party-label">CONTRATANTE (RESPONSÁVEL LEGAL):</div>
                    <div class="party-info">
                        O responsável legal do aluno(a) <strong>{{ $enrollment->student->name }}</strong>,
                        portador(a) do RG <strong>{{ $enrollment->student->cpf ?? 'N/A' }}</strong>,
                        residente em endereço fornecido ao ato da inscrição, doravante denominado simplesmente <strong>"RESPONSÁVEL"</strong>.
                    </div>
                </div>

                <div class="party">
                    <div class="party-label">ALUNO(A):</div>
                    <div class="party-info">
                        <strong>{{ $enrollment->student->name }}</strong><br>
                        Data de Nascimento: <strong>{{ $enrollment->student->birth_date ? $enrollment->student->birth_date->format('d/m/Y') : 'N/A' }}</strong><br>
                        Série/Turma: <strong>{{ $enrollment->classroom->name ?? 'N/A' }}</strong>
                    </div>
                </div>
            </div>

            <p>
                Pelo presente instrumento, o <strong>RESPONSÁVEL</strong> se obriga a efetuar o pagamento de todas as
                mensalidades escolares referentes ao ano letivo de {{ $enrollment->academicYear->year ?? now()->year }}, conforme tabela de preços fornecida pela <strong>ESCOLA</strong>.
            </p>

            <p>
                A <strong>ESCOLA</strong>, por sua vez, se obriga a ministrar aulas e atividades educacionais conforme seu projeto pedagógico,
                cumprindo com as obrigações curriculares exigidas pela legislação educacional brasileira.
            </p>

            <p>
                <strong>DO PLANO CONTRATADO:</strong> O <strong>RESPONSÁVEL</strong> contrata o plano denominado
                <strong>{{ $enrollment->plan->name ?? 'Não especificado' }}</strong>, constante na tabela de preços vigente.
            </p>

            <p>
                <strong>DA MATRÍCULA:</strong> A matrícula foi formalizada em
                {{ $enrollment->enrollment_date ? $enrollment->enrollment_date->format('d/m/Y') : now()->format('d/m/Y') }}.
            </p>

            <p>
                <strong>DO CANCELAMENTO:</strong> O cancelamento da matrícula deve ser solicitado formalmente com antecedência mínima de 30 (trinta) dias.
            </p>

            <div class="signatures-section">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Responsável Legal</div>
                </div>

                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Representante da Escola</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px; font-size: 10px;">
                Contrato gerado em {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>
    </div>
</body>

</html>