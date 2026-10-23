<?php

namespace App\Services\Asaas;

/** O que a sincronização de uma cobrança do Asaas fez (ou faria, na simulação) com a fatura local. */
enum SyncChange: string
{
    case Paid = 'paid';
    case Opened = 'opened';
    case Overdue = 'overdue';
    case Refunded = 'refunded';
    case ReceiptUndone = 'receipt_undone';
    case Canceled = 'canceled';
    case Updated = 'updated';
    case Attention = 'attention';
    case Unchanged = 'unchanged';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Baixadas como pagas',
            self::Opened => 'Reabertas / em aberto',
            self::Overdue => 'Marcadas como vencidas',
            self::Refunded => 'Estornadas (pagamento desfeito e fatura cancelada)',
            self::ReceiptUndone => 'Recebimento desfeito no Asaas (pagamento desfeito, fatura reaberta)',
            self::Canceled => 'Canceladas (cobrança excluída no Asaas)',
            self::Updated => 'Valor ou vencimento atualizados',
            self::Attention => 'Exigem atenção (sem alteração)',
            self::Unchanged => 'Já estavam em dia',
            self::Unknown => 'Status desconhecido (sem alteração)',
        };
    }
}
