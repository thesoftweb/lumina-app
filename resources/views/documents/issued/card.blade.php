{{--
    Carteirinha de autorização (85,6 × 54 mm), frente e verso lado a lado para dobrar na linha central.
    Usa o conteúdo congelado na emissão; a página que inclui carrega a fonte (documents.issued.fonts).
--}}
@php
    use App\Enums\IssuedDocumentStatus;

    $company = $document->data['company'] ?? [];
    $content = $document->data['content'] ?? [];
    $student = $content['student'] ?? [];
    $person = $content['person'] ?? [];
    $logo = $document->logoDataUri();
    $photo = $document->fileDataUri($student['photo'] ?? null);
    $initials = collect(preg_split('/\s+/', trim($student['name'] ?? '')))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $canceled = $document->status() === IssuedDocumentStatus::Canceled;
@endphp

@once
    <style>
        .card-pair {
            display: flex;
            width: 171.2mm;
            break-inside: avoid;
            outline: 0.2mm dashed #94a3b8;
            outline-offset: 1.5mm;
        }

        .pickup-card {
            position: relative;
            width: 85.6mm;
            height: 54mm;
            overflow: hidden;
            background: #fff;
            color: #0f172a;
            font-family: 'Inter Variable', Inter, system-ui, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .pickup-card + .pickup-card {
            border-left: 0.2mm dashed #94a3b8;
        }

        .pickup-card .band {
            display: flex;
            align-items: center;
            gap: 2mm;
            height: 10mm;
            padding: 0 3mm;
            background: #0369a1;
            color: #fff;
        }

        .pickup-card .band img {
            width: 7mm;
            height: 7mm;
            padding: 0.4mm;
            border-radius: 1mm;
            background: #fff;
            object-fit: contain;
        }

        .pickup-card .band .school {
            flex: 1;
            overflow: hidden;
            font-size: 6.5pt;
            font-weight: 700;
            line-height: 1.15;
            text-transform: uppercase;
        }

        .pickup-card .band .kind {
            font-size: 5pt;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-align: right;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .pickup-card .front-body {
            display: flex;
            gap: 3mm;
            padding: 2.5mm 3mm 0;
        }

        .pickup-card .photo {
            flex-shrink: 0;
            width: 19mm;
            height: 24mm;
            border-radius: 1.5mm;
            object-fit: cover;
            background: #e2e8f0;
        }

        .pickup-card .photo-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 14pt;
            font-weight: 700;
        }

        .pickup-card .label {
            display: block;
            color: #64748b;
            font-size: 4.8pt;
            font-weight: 650;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .pickup-card .student-name {
            display: -webkit-box;
            overflow: hidden;
            margin: 0.3mm 0 1mm;
            font-size: 8.5pt;
            font-weight: 700;
            line-height: 1.15;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .pickup-card .detail {
            font-size: 6.3pt;
            line-height: 1.35;
            color: #334155;
        }

        .pickup-card .person {
            position: absolute;
            right: 3mm;
            bottom: 2.5mm;
            left: 3mm;
            padding: 1.5mm 2mm;
            border-left: 0.8mm solid #0369a1;
            border-radius: 1mm;
            background: #f1f5f9;
        }

        .pickup-card .person strong {
            display: block;
            overflow: hidden;
            font-size: 8pt;
            line-height: 1.2;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .pickup-card .back-body {
            display: flex;
            gap: 3mm;
            padding: 3mm;
        }

        .pickup-card .qr {
            flex-shrink: 0;
            width: 25mm;
            height: 25mm;
        }

        .pickup-card .number {
            font-size: 8pt;
            font-weight: 700;
        }

        .pickup-card .code {
            font-size: 6.5pt;
            font-weight: 650;
            letter-spacing: 0.08em;
        }

        .pickup-card .rules {
            position: absolute;
            right: 3mm;
            bottom: 2.5mm;
            left: 3mm;
            font-size: 5.3pt;
            line-height: 1.35;
            color: #475569;
        }

        .pickup-card .canceled-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-18deg);
            padding: 1mm 3mm;
            border: 0.6mm solid #dc2626;
            border-radius: 1mm;
            color: #dc2626;
            font-size: 13pt;
            font-weight: 800;
            letter-spacing: 0.1em;
            opacity: 0.55;
        }
    </style>
@endonce

<div class="card-pair">
    <section class="pickup-card" aria-label="Frente da carteirinha">
        <div class="band">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
            <div class="school">{{ $company['name'] ?? 'Instituição de ensino' }}</div>
            <div class="kind">Autorização<br>de saída</div>
        </div>

        <div class="front-body">
            @if ($photo)
                <img class="photo" src="{{ $photo }}" alt="Foto de {{ $student['name'] ?? '' }}">
            @else
                <div class="photo photo-placeholder">{{ $initials }}</div>
            @endif
            <div>
                <span class="label">Aluno(a)</span>
                <div class="student-name">{{ $student['name'] ?? '' }}</div>
                <div class="detail">
                    @if (filled($student['grade'] ?? null))
                        {{ $student['grade'] }}<br>
                    @endif
                    Ano letivo {{ $student['academic_year'] ?? '' }}
                </div>
            </div>
        </div>

        <div class="person">
            <span class="label">Autorizado(a) a buscar</span>
            <strong>{{ $person['name'] ?? '' }}</strong>
            <div class="detail">{{ collect([$person['relationship'] ?? null, filled($person['document'] ?? null) ? 'Doc. '.$person['document'] : null])->filter()->implode(' · ') }}</div>
        </div>

        @if ($canceled)
            <div class="canceled-stamp">CANCELADA</div>
        @endif
    </section>

    <section class="pickup-card" aria-label="Verso da carteirinha">
        <div class="back-body">
            <img class="qr" src="{{ $document->qrCodeDataUri() }}" alt="QR code para conferir a carteirinha">
            <div>
                <span class="label">Carteirinha nº</span>
                <div class="number">{{ $document->number }}</div>
                <div class="detail">Válida até <strong>{{ $document->valid_until?->format('d/m/Y') }}</strong></div>
                <span class="label" style="margin-top: 1.5mm">Código</span>
                <div class="code">{{ $document->formattedCode() }}</div>
            </div>
        </div>

        <div class="rules">
            Apresente esta carteirinha e um documento com foto na saída. A escola confere a autorização pelo QR code
            e pode recusar a entrega em caso de dúvida. Perdeu? Avise a secretaria para cancelar.
            @if (filled($content['school_phone'] ?? null))
                <strong>{{ $content['school_phone'] }}</strong>
            @endif
        </div>

        @if ($canceled)
            <div class="canceled-stamp">CANCELADA</div>
        @endif
    </section>
</div>
