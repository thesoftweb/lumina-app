<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\AsaasService;
use Illuminate\Console\Command;

class SyncAsaasPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asaas:sync-payments
                            {--company-id= : Sincronizar pagamentos de uma empresa específica}
                            {--limit=100 : Limite de invoices a sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar status de pagamentos do Asaas com invoices locais';

    protected AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        parent::__construct();
        $this->asaasService = $asaasService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Iniciando sincronização de pagamentos do Asaas...');

        // Validar API key
        if (!config('asaas.api_key')) {
            $this->error('❌ ASAAS_API_KEY não configurada no .env');
            return self::FAILURE;
        }

        $query = Invoice::query()
            ->whereNotNull('asaas_invoice_id')
            ->whereIn('status', ['open', 'partial', 'overdue']);

        if ($this->option('company-id')) {
            $query->where('company_id', $this->option('company-id'));
            $this->info("📊 Sincronizando invoices da empresa: {$this->option('company-id')}");
        }

        $limit = (int) $this->option('limit');
        $invoices = $query->limit($limit)->orderBy('due_date', 'desc')->get();

        if ($invoices->isEmpty()) {
            $this->warn('⚠️  Nenhuma invoice com cobrança Asaas para sincronizar');
            return self::SUCCESS;
        }

        $this->info("📋 Total de invoices para sincronizar: {$invoices->count()}");

        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        $synced = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            try {
                $result = $this->asaasService->syncPaymentStatus($invoice);

                if ($result) {
                    $synced++;
                    $this->line("  ✅ Sincronizado: {$invoice->reference} → {$invoice->status}");
                } else {
                    $failed++;
                    $this->warn("  ⚠️  Falha ao sincronizar: {$invoice->reference}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ❌ Erro ao sincronizar invoice {$invoice->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Sincronização concluída!");
        $this->line("   <fg=green>Sincronizadas:</> <fg=white>{$synced}</>");
        $this->line("   <fg=red>Falhas:</> <fg=white>{$failed}</>");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

