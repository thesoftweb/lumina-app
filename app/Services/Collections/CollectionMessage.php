<?php

namespace App\Services\Collections;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use App\Services\AsaasService;
use Illuminate\Support\Str;

/**
 * Mensagem de cobrança para o responsável (WhatsApp ou outro canal) e os telefones para enviá-la.
 *
 * O texto vem do modelo das configurações da empresa ou, sem ele, do padrão. Cada fatura vai com o
 * link de pagamento do Asaas (que mostra o valor exato atualizado) e o PIX copia e cola.
 */
class CollectionMessage
{
    public const DEFAULT_TEMPLATE = <<<'TEXT'
        Olá, {responsavel}! Tudo bem?

        Aqui é da secretaria da {escola}. Identificamos em aberto {quantidade}, no valor atualizado de {total} até hoje:

        {faturas}

        Se o pagamento já foi feito, por favor desconsidere esta mensagem e nos envie o comprovante. Ficamos à disposição para conversar.
        TEXT;

    /** Variáveis do modelo, com a explicação mostrada nas configurações. */
    public const VARIABLES = [
        '{responsavel}' => 'primeiro nome do responsável',
        '{responsavel_completo}' => 'nome completo do responsável',
        '{escola}' => 'nome da escola',
        '{telefone_escola}' => 'telefone da escola',
        '{quantidade}' => '"1 fatura vencida", "3 faturas vencidas"',
        '{valor_original}' => 'soma das faturas vencidas, sem multa e juros',
        '{total}' => 'soma com multa e juros até hoje',
        '{faturas}' => 'cada fatura com aluno, vencimento, valor atualizado, link de pagamento e PIX copia e cola',
    ];

    public function __construct(private AsaasService $asaas) {}

    public function build(OverdueDebt $debt, ?Company $company): string
    {
        $template = trim((string) $company?->collection_message) ?: self::DEFAULT_TEMPLATE;
        $count = $debt->count();

        $text = strtr($template, [
            '{responsavel}' => Str::of($debt->customer->name)->trim()->before(' ')->toString(),
            '{responsavel_completo}' => trim($debt->customer->name),
            '{escola}' => (string) $company?->name,
            '{telefone_escola}' => (string) $company?->phone,
            '{quantidade}' => $count === 1 ? '1 fatura vencida' : "{$count} faturas vencidas",
            '{valor_original}' => OverdueDebt::money($debt->balance()),
            '{total}' => OverdueDebt::money($debt->updatedTotal()),
            '{faturas}' => $debt->items->map(fn (array $item): string => $this->invoiceBlock($item['invoice'], $item['amounts']))->implode("\n\n"),
        ]);

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $text)));
    }

    private function invoiceBlock(Invoice $invoice, LateAmounts $amounts): string
    {
        $student = $invoice->enrollment?->student?->name;

        $lines = [
            '*'.OverdueDebt::invoiceLabel($invoice).'*'.($student ? " ({$student})" : ''),
            'Vencimento: '.$invoice->due_date?->format('d/m/Y').' · Valor atualizado: '.OverdueDebt::money($amounts->total),
        ];

        if (filled($invoice->invoice_link)) {
            $lines[] = 'Pagar pelo link: '.$invoice->invoice_link;
        }

        if ($pix = $this->pixCode($invoice)) {
            $lines[] = 'PIX copia e cola:';
            $lines[] = $pix;
        }

        return implode("\n", $lines);
    }

    /** PIX consultado na hora (o Asaas atualiza o código da cobrança vencida); sem resposta, o guardado. */
    private function pixCode(Invoice $invoice): ?string
    {
        if (ChargeOperations::hasActiveCharge($invoice)) {
            $payload = $this->asaas->getPixQrCode($invoice)['payload'] ?? null;

            if (filled($payload)) {
                return $payload;
            }
        }

        return filled($invoice->invoice_qrcode) ? $invoice->invoice_qrcode : null;
    }

    /**
     * Telefones conhecidos do responsável e dos alunos dele, no formato do WhatsApp (55 + DDD + número).
     *
     * @return array<string, string> número => rótulo
     */
    public static function phones(Customer $customer): array
    {
        $candidates = [[$customer->phone, 'Responsável']];

        foreach ($customer->students->sortBy('name') as $student) {
            $name = Str::of($student->name)->trim()->before(' ')->toString();
            $candidates[] = [$student->phone_primary, "Cadastro de {$name}"];
            $candidates[] = [$student->phone_secondary, "Cadastro de {$name} (2º)"];
        }

        $phones = [];

        foreach ($candidates as [$raw, $label]) {
            $number = self::normalizePhone($raw);

            if ($number !== null && ! isset($phones[$number])) {
                $phones[$number] = "{$label} · ".trim((string) $raw);
            }
        }

        return $phones;
    }

    /** "(83) 98888-7777" → "5583988887777"; null quando não parece um telefone brasileiro. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = ltrim(preg_replace('/\D/', '', (string) $phone), '0');

        if (in_array(strlen($digits), [10, 11], true)) {
            return '55'.$digits;
        }

        return in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55') ? $digits : null;
    }

    public static function whatsappUrl(string $phone, string $message): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
