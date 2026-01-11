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
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.8;
            color: #333;
        }

        .print-container {
            max-width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 30mm 25mm;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .print-actions {
            margin-bottom: 20px;
            text-align: center;
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

        .contract-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }

        .contract-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .contract-subtitle {
            font-size: 13px;
            font-style: italic;
            color: #666;
        }

        .contract-body {
            text-align: justify;
            font-size: 13px;
            line-height: 1.9;
        }

        .contract-body p {
            margin-bottom: 15px;
            text-indent: 2em;
        }

        .contract-body strong {
            font-weight: bold;
        }

        .parties-section {
            margin: 25px 0;
            font-size: 13px;
        }

        .party {
            margin-bottom: 15px;
        }

        .party-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .party-info {
            margin-left: 20px;
            font-size: 12px;
        }

        .signatures-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .signature-block {
            text-align: center;
            font-size: 12px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            padding-top: 40px;
            min-height: 60px;
        }

        .signature-name {
            font-weight: bold;
            margin-top: 10px;
        }

        .contract-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #999;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
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
                padding: 30mm 25mm;
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
            .print-container {
                padding: 20mm 15mm;
            }

            .signatures-section {
                grid-template-columns: 1fr;
            }

            .contract-body p {
                text-indent: 0;
            }
        }
    </style>
</head>

<body>
    <div class="print-actions">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir</button>
        <button class="btn-pdf" onclick="downloadPDF()">📄 Baixar PDF</button>
        <button class="btn-back" onclick="window.history.back()">← Voltar</button>
    </div>

    <div class="print-container">
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
                <strong>DO PLANO CONTRATADO:</strong><br>
                O <strong>RESPONSÁVEL</strong> contrata o plano denominado <strong>{{ $enrollment->plan->name ?? 'Não especificado' }}</strong>,
                constante na tabela de preços vigente, com vigência até {{ $enrollment->academicYear->end_date ? $enrollment->academicYear->end_date->format('d/m/Y') : 'fim do ano letivo' }}.
            </p>

            <p>
                <strong>DA MATRÍCULA:</strong><br>
                A matrícula foi formalizada em {{ $enrollment->enrollment_date ? $enrollment->enrollment_date->format('d/m/Y') : now()->format('d/m/Y') }}.
                O aluno(a) compromete-se a cumprir com as regras e normas disciplinares da instituição.
            </p>

            <p>
                <strong>DO CANCELAMENTO:</strong><br>
                O cancelamento da matrícula deve ser solicitado formalmente com antecedência mínima de 30 (trinta) dias.
                Em caso de inadimplência, a <strong>ESCOLA</strong> poderá suspender as atividades do aluno(a).
            </p>

            <p>
                Por estarem de acordo com os termos e condições deste contrato, as partes assinam o presente instrumento em duas vias de igual teor e forma.
            </p>

            <div class="signatures-section">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Responsável Legal</div>
                    <div style="font-size: 11px; margin-top: 10px;">CPF: ___________________</div>
                </div>

                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Representante da Escola</div>
                    <div style="font-size: 11px; margin-top: 10px;">Matrícula: ___________________</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px; font-size: 12px;">
                ___________________________________________________________________________<br>
                Local e Data
            </div>
        </div>

        <div class="contract-footer">
            <p>Contrato gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const enrollmentId = {
                {
                    $enrollment - > id
                }
            };
            window.location.href = `/enrollments/${enrollmentId}/contract/pdf`;
        }
    </script>
</body>

</html>