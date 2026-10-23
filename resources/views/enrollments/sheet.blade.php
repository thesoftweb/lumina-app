@php
    use Illuminate\Support\Number;
    use Illuminate\Support\Str;

    $money = fn ($value) => $value === null ? null : Number::currency((float) $value, 'BRL', 'pt_BR');
    $yesNo = fn ($value) => $value ? 'Sim' : 'Não';
    $birth = $student?->date_of_birth;
    $companyContacts = collect([
        $company?->tax_id ? "CNPJ {$company->tax_id}" : null,
        $company?->inep_code ? "INEP {$company->inep_code}" : null,
        $company?->phone,
        $company?->email,
        $company?->website,
    ])->filter()->implode(' · ');
    $logo = $company?->logoDataUri();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de matrícula - {{ $student?->name }} - {{ $enrollment->academicYear?->description ?? $enrollment->number() }}</title>
    @include('documents.issued.fonts')
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: #cbd5e1;
            --band: #f1f5f9;
            --accent: #0369a1;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter Variable', Inter, "Segoe UI", Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: var(--ink);
            background: #e2e8f0;
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
            color: #f8fafc;
        }

        .toolbar strong {
            display: block;
            font-size: 14px;
        }

        .toolbar span {
            font-size: 12px;
            color: #cbd5e1;
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
        }

        .toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 9px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            background: #334155;
            color: #f8fafc;
        }

        .toolbar button.primary {
            background: #0ea5e9;
            color: #0f172a;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 24px auto;
            padding: 14mm;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
        }

        .doc-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 10px;
            margin-bottom: 14px;
            border-bottom: 2px solid var(--ink);
        }

        .school {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .school-logo {
            flex-shrink: 0;
            max-width: 64px;
            max-height: 64px;
            object-fit: contain;
        }

        .school-name {
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .school-info {
            margin-top: 2px;
            font-size: 9.5px;
            color: var(--muted);
        }

        .doc-title {
            flex-shrink: 0;
            text-align: right;
        }

        .doc-title h1 {
            font-size: 17px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .doc-title div {
            font-size: 10.5px;
        }

        section {
            margin-bottom: 12px;
            break-inside: avoid;
        }

        section h2 {
            padding: 4px 8px;
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: var(--band);
            border: 1px solid var(--line);
            border-bottom: 0;
        }

        .fields {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            border-top: 1px solid var(--line);
            border-left: 1px solid var(--line);
        }

        .field {
            min-height: 34px;
            padding: 3px 8px 5px;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .field small {
            display: block;
            font-size: 8px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .field b {
            display: block;
            font-size: 11px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .s1 { grid-column: span 1; }
        .s2 { grid-column: span 2; }
        .s3 { grid-column: span 3; }
        .s4 { grid-column: span 4; }
        .s6 { grid-column: span 6; }

        .checklist {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px 12px;
            padding: 8px;
            border: 1px solid var(--line);
        }

        .check {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .box {
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 12px;
            height: 12px;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            border: 1px solid var(--ink);
        }

        .declaration {
            margin: 16px 0 22px;
            font-size: 10.5px;
            text-align: justify;
        }

        .place-date {
            margin-bottom: 44px;
            font-size: 10.5px;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            break-inside: avoid;
        }

        .signature {
            padding-top: 4px;
            font-size: 10px;
            text-align: center;
            border-top: 1px solid var(--ink);
        }

        .signature strong {
            display: block;
            font-size: 10.5px;
        }

        .doc-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 24px;
            padding-top: 6px;
            font-size: 8.5px;
            color: var(--muted);
            border-top: 1px solid var(--line);
        }

        @page {
            size: A4;
            margin: 12mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            section h2 {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media screen and (max-width: 840px) {
            .sheet {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 16px;
                box-shadow: none;
            }

            .doc-header {
                flex-direction: column;
            }

            .doc-title {
                text-align: left;
            }

            .field {
                grid-column: 1 / -1;
            }

            .checklist,
            .signatures {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div>
            <strong>Ficha de matrícula</strong>
            <span>Para gerar o PDF, escolha "Salvar como PDF" no destino da impressão.</span>
        </div>
        <div class="toolbar-actions">
            <button type="button" onclick="window.close()">Fechar</button>
            <button type="button" class="primary" onclick="window.print()">Imprimir / Salvar em PDF</button>
        </div>
    </div>

    <main class="sheet">
        <header class="doc-header">
            <div class="school">
                @if ($logo)
                    <img class="school-logo" src="{{ $logo }}" alt="Logo {{ $company->name }}">
                @endif
                <div>
                    <div class="school-name">{{ $company?->name ?? 'Instituição de ensino' }}</div>
                    @if ($companyContacts)
                        <div class="school-info">{{ $companyContacts }}</div>
                    @endif
                    @if ($company?->full_address)
                        <div class="school-info">{{ $company->full_address }}</div>
                    @endif
                </div>
            </div>
            <div class="doc-title">
                <h1>Ficha de matrícula</h1>
                <div>Nº <strong>{{ $enrollment->number() }}</strong></div>
                <div>Ano letivo <strong>{{ $enrollment->academicYear?->description ?? '—' }}</strong></div>
            </div>
        </header>

        <section>
            <h2>1. Dados da matrícula</h2>
            <div class="fields">
                <div class="field s2"><small>Turma</small><b>{{ $enrollment->classroom?->name }}</b></div>
                <div class="field s2"><small>Nível de ensino</small><b>{{ $enrollment->classroom?->level?->name }}</b></div>
                <div class="field s1"><small>Data da matrícula</small><b>{{ $enrollment->enrollment_date?->format('d/m/Y') }}</b></div>
                <div class="field s1"><small>Situação</small><b>{{ $enrollment->status?->getLabel() }}</b></div>
                <div class="field s6"><small>Primeira matrícula na instituição</small><b>{{ $yesNo($enrollment->new_student) }}</b></div>
            </div>
        </section>

        <section>
            <h2>2. Dados do aluno</h2>
            <div class="fields">
                <div class="field s3"><small>Nome completo</small><b>{{ $student?->name }}</b></div>
                <div class="field s2"><small>Nascimento</small><b>{{ $birth?->format('d/m/Y') }}{{ $birth ? " ({$birth->age} anos)" : '' }}</b></div>
                <div class="field s1"><small>Gênero</small><b>{{ $student?->gender_label }}</b></div>
                <div class="field s2"><small>Naturalidade</small><b>{{ $student?->birthplace }}</b></div>
                <div class="field s2"><small>CPF</small><b>{{ $student?->doc_number }}</b></div>
                <div class="field s2"><small>RG</small><b>{{ $student?->reg_number }}</b></div>
                <div class="field s4"><small>Filiação 1</small><b>{{ $student?->affiliation_1 }}</b></div>
                <div class="field s2"><small>Telefone</small><b>{{ $student?->phone_primary }}</b></div>
                <div class="field s4"><small>Filiação 2</small><b>{{ $student?->affiliation_2 }}</b></div>
                <div class="field s2"><small>Telefone</small><b>{{ $student?->phone_secondary }}</b></div>
                @if (filled($student?->observations))
                    <div class="field s6"><small>Observações</small><b>{{ $student->observations }}</b></div>
                @endif
            </div>
        </section>

        <section>
            <h2>3. Responsável financeiro</h2>
            <div class="fields">
                <div class="field s4"><small>Nome completo</small><b>{{ $customer?->name }}</b></div>
                <div class="field s2"><small>Parentesco</small><b>{{ Str::ucfirst((string) $student?->degree_of_kinship) }}</b></div>
                <div class="field s2"><small>CPF</small><b>{{ $customer?->document }}</b></div>
                <div class="field s2"><small>Telefone</small><b>{{ $customer?->phone }}</b></div>
                <div class="field s2"><small>E-mail</small><b>{{ $customer?->email }}</b></div>
                <div class="field s6"><small>Endereço</small><b>{{ $customer?->full_address }}</b></div>
            </div>
        </section>

        <section>
            <h2>4. Plano e pagamento</h2>
            <div class="fields">
                <div class="field s2"><small>Plano</small><b>{{ $enrollment->plan?->name }}</b></div>
                <div class="field s1"><small>Valor base</small><b>{{ $money($tuition['base_amount'] ?? null) }}</b></div>
                <div class="field s1"><small>Desconto</small><b>{{ $enrollment->tuitionDiscountLabel() ?? ($tuition ? 'Sem desconto' : '') }}</b></div>
                <div class="field s1"><small>Mensalidade</small><b>{{ $money($tuition['final_amount'] ?? null) }}</b></div>
                <div class="field s1"><small>Vencimento</small><b>{{ $enrollment->day_of_payment ? "Todo dia {$enrollment->day_of_payment}" : '' }}</b></div>
                <div class="field s3"><small>Taxa de matrícula</small><b>{{ $enrollment->enrollment_tax_paid ? 'Paga' : 'Pendente' }}</b></div>
                <div class="field s3"><small>Mensalidades geradas</small><b>{{ $yesNo($enrollment->tuition_generated) }}</b></div>
                @if ($enrollment->use_custom_discount && filled($enrollment->discount_reason))
                    <div class="field s6"><small>Motivo do desconto</small><b>{{ $enrollment->discount_reason }}</b></div>
                @endif
            </div>
        </section>

        <section>
            <h2>5. Documentos entregues ({{ $enrollment->deliveredDocumentsCount() }} de {{ count(\App\Models\Enrollment::DOCUMENTS) }})</h2>
            <div class="checklist">
                @foreach (\App\Models\Enrollment::DOCUMENTS as $field => $label)
                    <div class="check"><span class="box">{{ $enrollment->{$field} ? 'X' : '' }}</span>{{ $label }}</div>
                @endforeach
            </div>
        </section>

        <p class="declaration">
            Declaro, para os devidos fins, que as informações prestadas nesta ficha são verdadeiras, que estou ciente
            das normas e do regimento da instituição de ensino e que me responsabilizo pelo pagamento das mensalidades
            nas datas de vencimento acordadas.
        </p>

        <p class="place-date">Local e data: {{ $company?->city_and_state ?? '____________________________________' }}, ______ / ______ / __________</p>

        <div class="signatures">
            <div class="signature">
                <strong>{{ $customer?->name ?? 'Responsável legal' }}</strong>
                Responsável legal{{ $customer?->document ? " · CPF {$customer->document}" : '' }}
            </div>
            <div class="signature">
                @if ($company?->document_signer_name)
                    <strong>{{ $company->document_signer_name }}</strong>
                    {{ $company->document_signer_role }} · {{ $company->name }}
                @else
                    <strong>{{ $company?->name ?? 'Instituição de ensino' }}</strong>
                    Secretaria escolar
                @endif
            </div>
        </div>

        <footer class="doc-footer">
            <span>Gerada em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }} por {{ $generatedBy }}</span>
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
