<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\AsaasService;
use Illuminate\Console\Command;

class TestAsaasConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asaas:test-connection
                            {--customer-id= : ID do cliente para testar}
                            {--show-payload : Mostrar payload enviado}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testar conexão com Asaas e debug de erros';

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
        $this->info('🔧 Testando conexão com Asaas...');

        // Validar API key
        $apiKey = config('asaas.api_key');
        if (!$apiKey) {
            $this->error('❌ ASAAS_API_KEY não configurada no .env');
            return self::FAILURE;
        }

        $this->line("✅ API Key configurada: " . substr($apiKey, 0, 10) . '...');
        $this->line("✅ Ambiente: " . config('asaas.environment'));
        $this->line("✅ Base URL: " . config('asaas.endpoints.' . config('asaas.environment')));

        // Buscar cliente
        if ($this->option('customer-id')) {
            $customer = Customer::find($this->option('customer-id'));
            if (!$customer) {
                $this->error("❌ Cliente {$this->option('customer-id')} não encontrado");
                return self::FAILURE;
            }
        } else {
            $customer = Customer::whereNull('asaas_customer_id')->first();
            if (!$customer) {
                $this->error('❌ Nenhum cliente sem asaas_customer_id encontrado');
                $this->line('Dica: use --customer-id=ID para testar um cliente específico');
                return self::FAILURE;
            }
            $this->info("📋 Testando com cliente: {$customer->name} (ID: {$customer->id})");
        }

        // Validar dados do cliente
        $this->line("\n📊 Dados do Cliente:");
        $this->table(['Campo', 'Valor'], [
            ['Nome', $customer->name],
            ['Email', $customer->email],
            ['Telefone', $customer->phone ?? 'N/A'],
            ['Documento', $customer->document ?? 'N/A'],
            ['Asaas ID', $customer->asaas_customer_id ?? 'Não sincronizado'],
        ]);

        // Validar campos obrigatórios
        $this->line("\n✔️ Validação de Campos Obrigatórios:");
        $required = [
            'Nome' => $customer->name,
            'Email' => $customer->email,
            'Documento' => $customer->document,
        ];

        $allValid = true;
        foreach ($required as $field => $value) {
            if (!$value) {
                $this->error("  ❌ {$field} está vazio");
                $allValid = false;
            } else {
                $this->line("  ✅ {$field}: OK");
            }
        }

        if (!$allValid) {
            $this->error("\n❌ Preencha todos os campos obrigatórios antes de sincronizar");
            return self::FAILURE;
        }

        // Tentar sincronizar
        $this->line("\n🔄 Tentando sincronizar cliente com Asaas...");

        $result = $this->asaasService->createOrUpdateCustomer($customer);

        if ($result && isset($result['id'])) {
            $this->info("\n✅ Cliente sincronizado com sucesso!");
            $this->table(['Campo', 'Valor'], [
                ['Asaas ID', $result['id']],
                ['Nome', $result['name'] ?? 'N/A'],
                ['Email', $result['email'] ?? 'N/A'],
                ['CPF/CNPJ', $result['cpfCnpj'] ?? 'N/A'],
            ]);
            return self::SUCCESS;
        } else {
            $this->error("\n❌ Falha ao sincronizar cliente");
            $this->line("Verifique os logs em: storage/logs/laravel.log");
            $this->line("Dica: Execute 'php artisan pail' para ver logs em tempo real");
            return self::FAILURE;
        }
    }
}
