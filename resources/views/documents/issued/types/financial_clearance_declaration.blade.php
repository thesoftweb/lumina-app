@php
    use App\Documents\DocumentText;
    use App\Models\IssuedDocument;

    $customer = $content['customer'] ?? [];
    $period = $content['period'] ?? [];
    $cpf = $public ? IssuedDocument::maskDocument($customer['cpf'] ?? null) : ($customer['cpf'] ?? null);
    $studentList = DocumentText::names($content['students'] ?? []);
@endphp

<p>
    Declaramos, para os devidos fins, que <strong>{{ $customer['name'] ?? '' }}</strong>{{ filled($cpf) ? ', CPF '.$cpf : '' }}@if ($studentList->toHtml() !== ''),
    responsável pelo pagamento das mensalidades de {{ $studentList }}@endif,
    @if (filled($period['year'] ?? null))
        quitou todos os débitos com esta instituição de ensino referentes ao ano de <strong>{{ $period['year'] }}</strong>.
    @else
        não possui débitos vencidos com esta instituição de ensino até a presente data, <strong>{{ $period['until'] ?? '' }}</strong>.
    @endif
</p>

@if (filled($period['year'] ?? null))
    <p>
        Esta declaração de quitação anual é emitida nos termos da Lei nº 12.007/2009 e substitui, para comprovar o pagamento,
        as quitações mensais das faturas do ano a que se refere.
    </p>
@else
    <p>
        Esta declaração não abrange mensalidades e faturas com vencimento posterior à data de sua emissão.
    </p>
@endif

@if ($document->purpose)
    <p>Esta declaração é emitida a pedido do interessado, para fins de {{ $document->purpose }}.</p>
@endif
