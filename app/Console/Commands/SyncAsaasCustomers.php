<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\AsaasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAsaasCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asaas:sync-customers
                            {--company-id= : Sincronizar clientes de uma empresa específica}
                            {--limit=100 : Quantidade máxima de clientes a sincronizar}
                            {--force : Resincronizar clientes já enviados ao Asaas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar clientes com o Asaas - criar ou atualizar dados no gateway';

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
        $this->info('🔄 Iniciando sincronização de clientes com Asaas...');

        $query = Customer::query();

        // Filtrar por empresa se especificado
        if ($this->option('company-id')) {
            $query->where('company_id', $this->option('company-id'));
            $this->info("📊 Filtrando clientes da empresa: {$this->option('company-id')}");
        }

        // Por padrão, sincronizar apenas clientes sem asaas_customer_id
        if (!$this->option('force')) {
            $query->whereNull('asaas_customer_id');
            $this->info('📌 Sincronizando apenas clientes sem ID Asaas');
        } else {
            $this->info('⚠️  Modo force: resincronizando todos os clientes');
        }

        $limit = (int) $this->option('limit');
        $customers = $query->limit($limit)->get();

        if ($customers->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente para sincronizar');
            return self::SUCCESS;
        }

        $this->info("📋 Total de clientes para sincronizar: {$customers->count()}");

        $bar = $this->output->createProgressBar($customers->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            try {
                $result = $this->asaasService->createOrUpdateCustomer($customer);

                if ($result && isset($result['id'])) {
                    $success++;
                } else {
                    $failed++;
                    $this->warn("  ❌ Falha ao sincronizar cliente {$customer->id}: {$customer->name}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ❌ Erro ao sincronizar cliente {$customer->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Sincronização concluída!");
        $this->line("   <fg=green>Sucesso:</> <fg=white>{$success}</>");
        $this->line("   <fg=red>Falhas:</> <fg=white>{$failed}</>");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

