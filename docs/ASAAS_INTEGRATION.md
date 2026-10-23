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
  - `syncPaymentStatus()` / `syncPayment()` - Sincronizar a fatura com a cobrança (via `PaymentSynchronizer`)
  - `listPayments()` - Listar cobranças (uma página)
  - `paginatePayments()` - Todas as cobranças, página a página, com filtros da API
- **[app/Services/Asaas/PaymentSynchronizer.php](app/Services/Asaas/PaymentSynchronizer.php)** - Traduz o status de uma cobrança do Asaas para a fatura (ver "Conciliar com o Asaas")
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

#### 4. Conciliar com o Asaas (`asaas:reconcile`)
```bash
php artisan asaas:reconcile [--dry-run] [--since=AAAA-MM-DD] [--status=STATUS]
```

Percorre **todas** as cobranças do Asaas (paginação de 100 em 100 até `hasMore=false`), acha a fatura
de cada uma (pelo `asaas_invoice_id` ou, se não houver, pelo `externalReference` = id da fatura) e
aplica a situação pelo `App\Services\Asaas\PaymentSynchronizer` — o mesmo usado pelo webhook, pelo
botão "Sincronizar com Asaas" e por `asaas:sync-payments`. Cobranças de eventos (`EVENT-...`) são ignoradas.

**Opções:**
- `--dry-run` - Só imprime o relatório do que mudaria; não grava nada
- `--since=AAAA-MM-DD` - Só cobranças com **vencimento** a partir da data (`dueDate[ge]`). Vencimento, e não
  criação, para pegar cobranças antigas pagas ou baixadas depois
- `--status=RECEIVED_IN_CASH` - Só cobranças num status do Asaas

**Como o status do Asaas vira status da fatura:**

| Asaas | Fatura |
|-------|--------|
| `RECEIVED`, `CONFIRMED`, `RECEIVED_IN_CASH`, `DUNNING_RECEIVED` | Paga; registra o pagamento com a forma real (`RECEIVED_IN_CASH` = dinheiro; senão pelo `billingType`: PIX, boleto, cartão, transferência), a data real (`paymentDate` → `clientPaymentDate` → `confirmedDate`), o valor recebido e a referência = id da cobrança |
| `PENDING`, `AWAITING_RISK_ANALYSIS` | Aberta (vencida se o vencimento já passou); valor e vencimento são trazidos do Asaas se a fatura está em aberto e sem pagamentos |
| `OVERDUE`, `DUNNING_REQUESTED` | Vencida (idem valor/vencimento) |
| `REFUNDED` | Desfaz o pagamento (o recibo é cancelado) e cancela a fatura com a nota "Cobrança estornada no Asaas em dd/mm/aaaa" |
| `REFUND_REQUESTED`, `REFUND_IN_PROGRESS`, `CHARGEBACK_*`, `AWAITING_CHARGEBACK_REVERSAL` | Não muda; aparece nos avisos |
| `deleted: true` | Cancela a fatura, se não tiver pagamento (se a cobrança for restaurada, a fatura volta a ser cobrada) |

É idempotente: rodar de novo não cria outro pagamento nem outro recibo, e fatura já paga no sistema
nunca é alterada (se estiver paga aqui e em aberto no Asaas, sai um aviso para dar baixa lá).

**Relatório:** tabela das faturas que mudam (nº, cliente, status antes → depois, forma, data, valor),
totais por tipo de mudança, cobranças do Asaas **sem fatura no sistema** (só relatório, nada é importado)
e avisos (estorno em andamento, chargeback, divergências).

**Em produção (primeira vez):**
1. `php artisan asaas:reconcile --dry-run` (ou com `--since=` para começar por um período)
2. Conferir o relatório: faturas que serão baixadas, formas e datas, avisos e cobranças sem fatura
3. `php artisan asaas:reconcile` com as mesmas opções para aplicar
4. Tratar à mão os avisos (estornos/chargebacks em andamento, faturas pagas aqui e abertas no Asaas)

Depois disso o agendamento diário mantém tudo em dia.

### Agendamento Automático

`asaas:reconcile --since=<hoje - 1 ano>` roda todo dia às 03:00, sem sobreposição
(`withoutOverlapping()`), configurado em [routes/console.php](routes/console.php).

Os agendamentos abaixo estão em [app/Console/Kernel.php](app/Console/Kernel.php), que **não é carregado**
pelo Laravel 12 (o app usa `bootstrap/app.php`); para ativá-los, mova-os para `routes/console.php`:

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
Webhook Asaas → AsaasWebhookController → AsaasService::syncPaymentStatus() → PaymentSynchronizer → Atualiza status invoice
Agendamento diário → asaas:reconcile → PaymentSynchronizer (todas as cobranças) → Relatório
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
