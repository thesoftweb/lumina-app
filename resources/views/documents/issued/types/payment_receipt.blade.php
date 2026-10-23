@php
    use App\Documents\DocumentText;
    use App\Models\IssuedDocument;

    $payer = $content['payer'] ?? [];
    $student = $content['student'] ?? null;
    $invoice = $content['invoice'] ?? [];
    $payment = $content['payment'] ?? [];
    $cpf = $public ? IssuedDocument::maskDocument($payer['cpf'] ?? null) : ($payer['cpf'] ?? null);
    $ofStudent = $student
        ? ' '.DocumentText::byGender($student['gender'] ?? null, 'do aluno', 'da aluna', 'do(a) aluno(a)').' <strong>'.e($student['name']).'</strong>'
        : '';
@endphp

<div class="receipt-amount">
    <span>Valor recebido</span>
    <strong>{{ $payment['amount'] ?? '' }}</strong>
</div>

<p>
    Recebemos de <strong>{{ $payer['name'] ?? '' }}</strong>{{ filled($cpf) ? ', CPF '.$cpf : '' }}, a importância de <strong>{{ $payment['amount'] ?? '' }}</strong> ({{ $payment['amount_in_words'] ?? '' }}), referente {{ $invoice['article'] ?? 'à' }} <strong>{{ $invoice['description'] ?? '' }}</strong>{!! $ofStudent !!}, fatura nº {{ $invoice['number'] ?? '' }}, paga em {{ $payment['date'] ?? '' }}{{ filled($payment['method'] ?? null) ? ', forma de pagamento: '.$payment['method'] : '' }}.
</p>

@if ($payment['partial'] ?? false)
    <p>Pagamento parcial da fatura, cujo valor total é de {{ $invoice['amount'] ?? '' }}.</p>
@endif

<p>Para maior clareza, firmamos o presente recibo, dando quitação do valor recebido.</p>

<style>
    .issued-doc .receipt-amount {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin: -4mm 0 8mm;
        padding: 10px 14px;
        border: 1px solid var(--line);
        border-left: 3px solid var(--accent);
        border-radius: 6px;
        background: #f8fafc;
    }

    .issued-doc .receipt-amount span {
        font-size: 8pt;
        font-weight: 650;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .issued-doc .receipt-amount strong {
        font-size: 16pt;
        font-weight: 700;
        color: var(--ink);
    }
</style>
