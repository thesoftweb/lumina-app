@php
    use App\Documents\DocumentText;
    use Illuminate\Support\Number;

    $year = $enrollment->academicYear;
    $yearLabel = $year?->description ?? (string) ($year?->year ?? now()->year);
    $gender = $student?->gender;
    $word = fn (string $male, string $female, string $neutral): string => DocumentText::byGender($gender, $male, $female, $neutral);
    $grade = collect([$enrollment->classroom?->level?->name, $enrollment->classroom?->name])->filter()->implode(' – ');
    $monthly = $tuition['final_amount'] ?? null;
    $logo = $company?->logoDataUri();
    $schoolContacts = collect([
        $company?->tax_id ? "CNPJ {$company->tax_id}" : null,
        $company?->inep_code ? "INEP {$company->inep_code}" : null,
        $company?->phone,
        $company?->email,
    ])->filter()->implode('  ·  ');
    $schoolQualification = collect([
        $company?->tax_id ? "inscrita no CNPJ sob o nº {$company->tax_id}" : null,
        $company?->inep_code ? "código INEP {$company->inep_code}" : null,
        $company?->full_address ? "com sede em {$company->full_address}" : null,
    ])->filter()->implode(', ');
    $guardianQualification = collect([
        $customer?->document ? "CPF {$customer->document}" : null,
        $customer?->full_address ? "residente em {$customer->full_address}" : null,
        $customer?->phone ? "telefone {$customer->phone}" : null,
        $customer?->email ? "e-mail {$customer->email}" : null,
    ])->filter()->implode(', ');
    $planTerms = collect([
        $monthly !== null
            ? 'com mensalidade de <strong>'.e(Number::currency((float) $monthly, 'BRL', 'pt_BR')).'</strong> ('.e(DocumentText::moneyInWords($monthly)).')'
                .($enrollment->day_of_payment ? ' e vencimento todo dia <strong>'.e($enrollment->day_of_payment).'</strong>' : '')
            : null,
        'com vigência até '.e($year?->end_at ? $year->end_at->format('d/m/Y') : 'o fim do ano letivo'),
    ])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Contrato de matrícula - {{ $student?->name }} - {{ $yearLabel }}</title>
    @include('documents.issued.fonts')
    <style>
        :root {
            --ink: #0f172a;
            --text: #1e293b;
            --muted: #64748b;
            --line: #e2e8f0;
            --accent: #0369a1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e2e8f0;
            color: var(--text);
            font-family: 'Inter Variable', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
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

        .contract {
            width: 210mm;
            margin: 24px auto;
            padding: 16mm 20mm;
            background: #fff;
            font-size: 10.5pt;
            line-height: 1.7;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
        }

        header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
        }

        .logo {
            flex-shrink: 0;
            max-width: 64px;
            max-height: 64px;
            object-fit: contain;
        }

        .school-name {
            color: var(--ink);
            font-size: 12.5pt;
            font-weight: 650;
            line-height: 1.3;
        }

        .school-info {
            margin-top: 2px;
            font-size: 8pt;
            line-height: 1.5;
            color: var(--muted);
            white-space: pre-wrap;
        }

        .title {
            margin: 12mm 0 9mm;
            text-align: center;
        }

        h1 {
            margin: 0;
            color: var(--ink);
            font-size: 15pt;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .title::after {
            content: '';
            display: block;
            width: 36px;
            height: 2px;
            margin: 10px auto 0;
            background: var(--accent);
        }

        .subtitle {
            margin-top: 4px;
            font-size: 8.5pt;
            font-weight: 500;
            color: var(--muted);
        }

        h2 {
            margin: 7mm 0 2mm;
            color: var(--ink);
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            break-after: avoid;
        }

        p {
            margin: 0 0 3mm;
            text-align: justify;
            hyphens: auto;
        }

        strong {
            color: var(--ink);
            font-weight: 600;
        }

        .party {
            margin-bottom: 3mm;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-left: 3px solid var(--accent);
            border-radius: 6px;
            background: #f8fafc;
            break-inside: avoid;
        }

        .party p {
            margin: 0;
        }

        .party .label {
            display: block;
            margin-bottom: 2px;
            font-size: 7.5pt;
            font-weight: 650;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .closing {
            break-inside: avoid;
        }

        .place-date {
            margin: 8mm 0 0;
            text-align: right;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14mm;
            margin-top: 20mm;
        }

        .signature {
            padding-top: 8px;
            border-top: 1px solid var(--ink);
            text-align: center;
            line-height: 1.4;
        }

        .signature strong {
            display: block;
        }

        .signature small {
            display: block;
            margin-top: 2px;
            font-size: 8.5pt;
            color: var(--muted);
        }

        footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 10mm;
            padding-top: 8px;
            border-top: 1px solid var(--line);
            font-size: 7.5pt;
            color: var(--muted);
        }

        @media screen and (max-width: 820px) {
            .contract {
                width: auto;
                margin: 0;
                padding: 16px;
                box-shadow: none;
            }

            .signatures {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }

        @media print {
            /* Contrato com mais de uma página: numeração e espaço para rubrica no rodapé de cada folha */
            @page {
                size: A4;
                margin: 14mm 0 16mm;

                @bottom-left {
                    content: "Matrícula nº {{ $enrollment->number() }}  ·  Rubricas: ________  ________";
                    padding-left: 20mm;
                    font: 7.5pt 'Inter Variable', Inter, sans-serif;
                    color: #64748b;
                }

                @bottom-right {
                    content: "Página " counter(page) " de " counter(pages);
                    padding-right: 20mm;
                    font: 7.5pt 'Inter Variable', Inter, sans-serif;
                    color: #64748b;
                }
            }

            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .contract {
                margin: 0;
                padding: 0 20mm;
                box-shadow: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div>
            <strong>Contrato de matrícula · {{ $student?->name }}</strong>
            <small>Para gerar o PDF, escolha "Salvar como PDF" no destino da impressão.</small>
        </div>
        <div class="actions">
            <button type="button" onclick="window.close()">Fechar</button>
            <button type="button" class="primary" onclick="window.print()">Imprimir / Salvar em PDF</button>
        </div>
    </div>

    <main class="contract">
        <header>
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="Logo {{ $company->name }}">
            @endif
            <div>
                <div class="school-name">{{ $company?->name ?? 'Instituição de ensino' }}</div>
                @if ($schoolContacts)
                    <div class="school-info">{{ $schoolContacts }}</div>
                @endif
                @if ($company?->full_address)
                    <div class="school-info">{{ $company->full_address }}</div>
                @endif
            </div>
        </header>

        <div class="title">
            <h1>Contrato de Matrícula Escolar</h1>
            <div class="subtitle">Ano letivo {{ $yearLabel }} · Matrícula nº {{ $enrollment->number() }}</div>
        </div>

        <h2>Contratantes</h2>

        <div class="party">
            <span class="label">Contratada (instituição escolar)</span>
            <p><strong>{{ $company?->name ?? 'Instituição de ensino' }}</strong>{{ $schoolQualification ? ', '.$schoolQualification : '' }}, doravante denominada simplesmente <strong>"ESCOLA"</strong>.</p>
        </div>

        <div class="party">
            <span class="label">Contratante (responsável legal)</span>
            <p><strong>{{ $customer?->name ?? '________________________________' }}</strong>{{ $guardianQualification ? ', '.$guardianQualification : '' }}, responsável legal {{ $word('do aluno indicado', 'da aluna indicada', 'do(a) aluno(a) indicado(a)') }} abaixo, doravante denominado simplesmente <strong>"RESPONSÁVEL"</strong>.</p>
        </div>

        <div class="party">
            <span class="label">{{ $word('Aluno', 'Aluna', 'Aluno(a)') }}</span>
            <p>{{ DocumentText::studentIntro([
                'name' => $student?->name,
                'gender' => $gender,
                'birth_date' => $student?->date_of_birth?->format('d/m/Y'),
                'cpf' => $student?->doc_number,
                'parents' => array_values(array_filter([$student?->affiliation_1, $student?->affiliation_2], 'filled')),
            ], false) }}@if ($grade), {{ $word('matriculado', 'matriculada', 'matriculado(a)') }} em <strong>{{ $grade }}</strong>@endif.</p>
        </div>

        <h2>Das obrigações</h2>
        <p>Pelo presente instrumento, o <strong>RESPONSÁVEL</strong> se obriga a efetuar o pagamento de todas as mensalidades escolares referentes ao ano letivo de <strong>{{ $yearLabel }}</strong>, conforme tabela de preços fornecida pela <strong>ESCOLA</strong>.</p>
        <p>A <strong>ESCOLA</strong>, por sua vez, se obriga a ministrar aulas e atividades educacionais conforme seu projeto pedagógico, cumprindo com as obrigações curriculares exigidas pela legislação educacional brasileira.</p>

        <h2>Do plano contratado</h2>
        <p>O <strong>RESPONSÁVEL</strong> contrata o plano denominado <strong>{{ $enrollment->plan?->name ?? 'não especificado' }}</strong>, constante na tabela de preços vigente, {!! $planTerms !!}.</p>

        <h2>Da matrícula</h2>
        <p>A matrícula foi formalizada em <strong>{{ ($enrollment->enrollment_date ?? now())->format('d/m/Y') }}</strong>. {{ $word('O aluno', 'A aluna', 'O(a) aluno(a)') }} compromete-se a cumprir as regras e normas disciplinares da instituição.</p>

        <h2>Do cancelamento</h2>
        <p>O cancelamento da matrícula deve ser solicitado formalmente com antecedência mínima de 30 (trinta) dias. Em caso de inadimplência, a <strong>ESCOLA</strong> poderá suspender as atividades {{ $word('do aluno', 'da aluna', 'do(a) aluno(a)') }}.</p>

        <div class="closing">
            <p>Por estarem de acordo com os termos e condições deste contrato, as partes assinam o presente instrumento em duas vias de igual teor e forma.</p>

            <p class="place-date">{{ $company?->city_and_state ?? '______________________' }}, ______ de ________________________ de __________.</p>

            <div class="signatures">
                <div class="signature">
                    <strong>{{ $customer?->name ?? 'Responsável legal' }}</strong>
                    <small>Responsável legal{{ $customer?->document ? ' · CPF '.$customer->document : '' }}</small>
                </div>
                <div class="signature">
                    @if ($company?->document_signer_name)
                        <strong>{{ $company->document_signer_name }}</strong>
                        <small>{{ $company->document_signer_role }} · {{ $company->name }}</small>
                    @else
                        <strong>{{ $company?->name ?? 'Instituição de ensino' }}</strong>
                        <small>Representante da escola</small>
                    @endif
                </div>
            </div>
        </div>

        <footer>
            <span>Gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }} por {{ $generatedBy }}</span>
            <span>Matrícula nº {{ $enrollment->number() }}</span>
        </footer>
    </main>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => document.fonts.ready.then(() => window.print()));
        </script>
    @endif
</body>

</html>
