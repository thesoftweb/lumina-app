<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\AsaasService;
use Illuminate\Console\Command;

class GenerateAsaasCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asaas:generate-charges
                            {--company-id= : Gerar cobranças apenas de uma empresa}
                            {--customer-id= : Gerar cobrança para um cliente específico}
                            {--limit=100 : Limite de cobranças a gerar}
                            {--force : Regenerar cobranças já enviadas ao Asaas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gerar cobranças no Asaas para invoices abertas/em aberto';

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
        $this->info('🔄 Iniciando geração de cobranças no Asaas...');

        // Validar API key
        if (!config('asaas.api_key')) {
            $this->error('❌ ASAAS_API_KEY não configurada no .env');
            return self::FAILURE;
        }

        $query = Invoice::query();

        // Filtrar apenas invoices do mês corrente (vencimento)
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $query->whereMonth('due_date', $currentMonth)
              ->whereYear('due_date', $currentYear);

        $this->info("📅 Filtrando apenas invoices do mês corrente: " . now()->format('m/Y'));

        // Filtrar por status (aberto ou parcial)
        $query->whereIn('status', [
            InvoiceStatus::Open->value,
            InvoiceStatus::Partial->value,
        ]);

        // Faturas avulsas continuam elegíveis; faturas de matrículas canceladas não.
        $query->where(function ($query) {
            $query->whereNull('enrollment_id')
                ->orWhereHas('enrollment', function ($query) {
                    $query->where('status', '!=', EnrollmentStatus::Canceled->value);
                });
        });

        // Por padrão, apenas invoices sem cobrança no Asaas
        if (!$this->option('force')) {
            $query->whereNull('asaas_invoice_id');
            $this->info('📌 Gerando apenas invoices sem cobrança no Asaas');
        } else {
            $this->info('⚠️  Modo force: regenerando cobranças existentes');
        }

        // Filtrar por empresa se especificado
        if ($this->option('company-id')) {
            $query->where('company_id', $this->option('company-id'));
            $this->info("📊 Filtrando invoices da empresa: {$this->option('company-id')}");
        }

        // Filtrar por cliente se especificado
        if ($this->option('customer-id')) {
            $query->where('customer_id', $this->option('customer-id'));
            $this->info("👤 Filtrando invoices do cliente: {$this->option('customer-id')}");
        }

        $limit = (int) $this->option('limit');
        $invoices = $query->limit($limit)->orderBy('due_date', 'asc')->get();

        if ($invoices->isEmpty()) {
            $this->warn('⚠️  Nenhuma invoice para gerar cobrança');
            return self::SUCCESS;
        }

        $this->info("📋 Total de invoices para processar: {$invoices->count()}");

        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            try {
                // Sincronizar cliente se necessário
                if (!$invoice->customer->asaas_customer_id) {
                    $customerResult = $this->asaasService->createOrUpdateCustomer($invoice->customer);
                    if (!$customerResult) {
                        $failed++;
                        $bar->advance();
                        continue;
                    }
                }

                // Criar cobrança
                $result = $this->asaasService->createCharge($invoice);

                if ($result && isset($result['id'])) {
                    $success++;
                    $this->line("  ✅ Cobrança gerada: {$invoice->reference} → {$result['id']}");
                } else {
                    $failed++;
                    $this->warn("  ❌ Falha ao gerar cobrança: {$invoice->reference}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ❌ Erro ao processar invoice {$invoice->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Processamento concluído!");
        $this->line("   <fg=green>Sucesso:</> <fg=white>{$success}</>");
        $this->line("   <fg=red>Falhas:</> <fg=white>{$failed}</>");
        $this->line("   <fg=yellow>Puladas:</> <fg=white>{$skipped}</>");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
