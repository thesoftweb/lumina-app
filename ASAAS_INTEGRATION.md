# Integração Asaas - Documentação

## Visão Geral

Este documento descreve a implementação completa da integração com o gateway de pagamento **Asaas** para automação de cobranças mensais de mensalidades e matrículas.

## Arquivos Implementados

### Configuração
- **[config/asaas.php](config/asaas.php)** - Configuração centralizada do Asaas com endpoints, timeouts e settings

### Serviços
- **[app/Services/AsaasService.php](app/Services/AsaasService.php)** - Serviço principal com métodos:
  - `createOrUpdateCustomer()` - Criar/sincronizar cliente no Asaas
  - `createCharge()` - Gerar cobrança no Asaas
  - `getPayment()` - Obter detalhes do pagamento
  - `syncPaymentStatus()` - Sincronizar status de pagamento
  - `listPayments()` - Listar cobranças
  - `cancelCharge()` - Cancelar cobrança
  - `getPaymentLinks()` - Obter URLs de boleto/PIX

### Migrations
- **[database/migrations/2026_01_27_225134_add_asaas_fields_to_invoices.php](database/migrations/2026_01_27_225134_add_asaas_fields_to_invoices.php)** - Adiciona colunas:
  - `asaas_invoice_id` (string) - ID da cobrança no Asaas
  - `asaas_sync_status` (enum: pending, synced, canceled, failed) - Status de sincronização
  - `asaas_synced_at` (timestamp) - Último timestamp de sincronização

- **[database/migrations/2026_01_27_225311_add_asaas_fields_to_invoice_payments.php](database/migrations/2026_01_27_225311_add_asaas_fields_to_invoice_payments.php)** - Adiciona colunas:
  - `asaas_payment_id` (string) - ID do pagamento no Asaas
  - `asaas_sync_status` (enum: pending, synced, failed) - Status de sincronização
  - `asaas_synced_at` (timestamp) - Último timestamp de sincronização

### Comandos Artisan

#### 1. Sincronizar Clientes
```bash
php artisan asaas:sync-customers [options]
```

**Opções:**
- `--company-id=ID` - Sincronizar apenas clientes de uma empresa
- `--limit=100` - Número máximo de clientes (padrão: 100)
- `--force` - Resincronizar clientes já enviados

**Exemplo:**
```bash
php artisan asaas:sync-customers --company-id=1 --limit=500
```

#### 2. Gerar Cobranças
```bash
php artisan asaas:generate-charges [options]
```

**Opções:**
- `--company-id=ID` - Gerar apenas para uma empresa
- `--customer-id=ID` - Gerar apenas para um cliente
- `--limit=100` - Número máximo de invoices (padrão: 100)
- `--force` - Regenerar cobranças já criadas

**Exemplo:**
```bash
php artisan asaas:generate-charges --company-id=1 --limit=1000
```

#### 3. Sincronizar Pagamentos
```bash
php artisan asaas:sync-payments [options]
```

**Opções:**
- `--company-id=ID` - Sincronizar apenas uma empresa
- `--limit=100` - Número máximo de invoices (padrão: 100)

**Exemplo:**
```bash
php artisan asaas:sync-payments --company-id=1 --limit=500
```

### Agendamento Automático

Configurado em [app/Console/Kernel.php](app/Console/Kernel.php):

| Comando | Frequência | Hora | Descrição |
|---------|-----------|------|-----------|
| `asaas:sync-customers` | Diário | 00:30 | Sincroniza clientes com Asaas |
| `asaas:generate-charges` | Mensal | 08:00 do 1º | Gera cobranças do mês |
| `asaas:sync-payments` | 4 em 4 horas | - | Sincroniza status de pagamentos |

### Webhook

**Rota:** `POST /webhooks/asaas`

Controlador: [app/Http/Controllers/AsaasWebhookController.php](app/Http/Controllers/AsaasWebhookController.php)

**Eventos suportados:**
- `PAYMENT_RECEIVED` - Pagamento recebido
- `PAYMENT_CONFIRMED` - Pagamento confirmado
- `PAYMENT_OVERDUE` - Pagamento vencido
- `PAYMENT_DELETED` - Pagamento deletado
- `PAYMENT_RESTORED` - Pagamento restaurado

**Configuração no Asaas Dashboard:**
1. Ir para Configurações → Webhooks
2. Adicionar URL: `https://seu-dominio.com/webhooks/asaas`
3. Selecionar eventos a monitorar

### Interface Filament

**Arquivo:** [app/Filament/Resources/Invoices/Pages/ViewInvoice.php](app/Filament/Resources/Invoices/Pages/ViewInvoice.php)

**Ações disponíveis na tela de detalhes da invoice:**

1. **Gerar Cobrança Asaas** 🔼
   - Envia a invoice para Asaas
   - Cria automaticamente boleto e PIX dinâmico
   - Visível apenas se invoice não tem `asaas_invoice_id`
   - Requer confirmação

2. **Sincronizar Pagamento** 🔄
   - Busca status atual no Asaas
   - Atualiza status local (open, partial, paid, overdue)
   - Registra pagamento se recebido
   - Visível apenas se invoice tem `asaas_invoice_id`

3. **Ver Links de Pagamento** 🔗
   - Exibe URLs dos boletos e PIX
   - Cópia para compartilhar com cliente
   - Visível apenas se invoice tem `asaas_invoice_id`

### Portal do Cliente

**Arquivo:** [resources/views/portal/student.blade.php](resources/views/portal/student.blade.php)

**Recursos:**
- Nova coluna "Pagamento" na tabela de faturas
- Botão "Pagar" que abre modal com opções:
  - Link para Boleto
  - Link para PIX
- Modal responsivo com design Tailwind
- Visível apenas para invoices com `asaas_invoice_id`

## Configuração de Ambiente

Adicionar ao `.env`:

```env
# Asaas Gateway
ASAAS_API_KEY=sua_chave_api_aqui
ASAAS_ENVIRONMENT=sandbox   # sandbox ou production
ASAAS_TIMEOUT=30
ASAAS_CONNECT_TIMEOUT=10
ASAAS_GENERATE_PIX=true
ASAAS_NOTIFY_CUSTOMER=true
ASAAS_AUTO_REISSUE_OVERDUE=false
ASAAS_RETRY_ENABLED=true
ASAAS_RETRY_MAX_ATTEMPTS=3
ASAAS_RETRY_DELAY=60
ASAAS_SYNC_PAYMENTS_ENABLED=true
ASAAS_SYNC_FREQUENCY=360
```

## Fluxo de Funcionamento

### 1. Sincronização Inicial
```
Customer cadastrado → Artisan Command → AsaasService::createOrUpdateCustomer() → Armazena asaas_customer_id
```

### 2. Geração de Cobrança
```
Invoice criada → Artisan Command / Action Filament → AsaasService::createCharge() → Armazena asaas_invoice_id + PIX + Boleto
```

### 3. Sincronização de Pagamento
```
Webhook Asaas → AsaasWebhookController → AsaasService::syncPaymentStatus() → Atualiza status invoice
```

### 4. Portal do Cliente
```
Cliente acessa portal → Vê invoices com status → Clica em "Pagar" → Abre links Boleto/PIX → Realiza pagamento
```

## Testando Localmente

### 1. Executar Migrações
```bash
php artisan migrate
```

### 2. Sincronizar Clientes (opcional, para teste)
```bash
php artisan asaas:sync-customers --force
```

### 3. Gerar Cobranças de Teste
```bash
php artisan asaas:generate-charges --limit=10
```

### 4. Acessar Admin Filament
1. Ir para Invoice
2. Clicar em uma invoice
3. Usar ações "Gerar Cobrança Asaas" ou "Sincronizar Pagamento"

### 5. Testar Webhook Localmente

Usar **ngrok** ou similar para expor localhost:
```bash
ngrok http 8000
```

Copiar URL e configurar em Asaas Dashboard (substitua `seu-dominio.com`).

## Logs e Debug

Todos os eventos são registrados em `storage/logs/laravel.log`:

```php
Log::info("Asaas charge created for invoice {$invoice->id}: {$data['id']}");
Log::error("Asaas error creating charge for invoice {$invoice->id}: " . $e->getMessage());
Log::info("Asaas payment synced for invoice {$invoice->id}: {$status}");
```

Monitorar logs em tempo real:
```bash
php artisan pail
```

## Troubleshooting

### Erro: "ASAAS_API_KEY não configurada"
- Verifique `.env` e adicione `ASAAS_API_KEY=sua_chave`
- Certifique-se que o arquivo `.env` foi recarregado

### Webhook não recebe notificações
- Confirme URL pública em `{seu-dominio}/webhooks/asaas`
- Teste com `curl`:
```bash
curl -X POST http://localhost:8000/webhooks/asaas \
  -H "Content-Type: application/json" \
  -d '{"event":"PAYMENT_RECEIVED","payment":{"id":"test123","value":100,"confirmedDate":"2026-01-27","externalReference":"1"}}'
```

### Clientes não sincronizam
- Verifique se `Customer::find(id)->email` está preenchido
- Verifique logs: `grep "Asaas customer" storage/logs/laravel.log`

### Cobranças não geram
- Confirme que customer tem `asaas_customer_id`
- Verifique status de invoice (deve ser 'open' ou 'partial')
- Cheque logs para mensagens de erro específicas

## Próximas Melhorias

- [ ] Integrar fila (Queue) para processamento assíncrono de cobranças
- [ ] Adicionar retry automático com backoff exponencial
- [ ] Implementar sincronização em lote com batching
- [ ] Criar dashboard com relatórios de pagamentos
- [ ] Adicionar SMS/Email com link de pagamento automático
- [ ] Implementar split de pagamento entre múltiplas contas
- [ ] Adicionar validação de webhook signature
- [ ] Criar testes unitários e de integração

## Suporte

Para dúvidas sobre a API Asaas, consulte:
- [Documentação Asaas](https://docs.asaas.com)
- [Dashboard Asaas](https://app.asaas.com)
