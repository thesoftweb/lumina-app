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
  - `findPayment()` - Uma cobrança pelo id
  - `cancelCharge()` - Cancelar cobrança
  - `getPaymentLinks()` - Obter URLs de boleto/PIX
- **[app/Services/Asaas/PaymentSynchronizer.php](app/Services/Asaas/PaymentSynchronizer.php)** - Traduz o status de uma cobrança do Asaas para a fatura (ver "Conciliar com o Asaas")
- **[app/Services/Asaas/ChargeImporter.php](app/Services/Asaas/ChargeImporter.php)** - Importa como fatura "Outro" a cobrança criada no painel do Asaas para um responsável cadastrado
- **[app/Services/Asaas/WebhookProcessor.php](app/Services/Asaas/WebhookProcessor.php)** - Processa as notificações do webhook (ver "Webhook")
- **[app/Services/Asaas/ChargeOperations.php](app/Services/Asaas/ChargeOperations.php)** - Operações de fatura feitas no sistema e refletidas no Asaas (ver "Operações pelo sistema")
- **[app/Services/Asaas/AsaasApi.php](app/Services/Asaas/AsaasApi.php)** - Chamadas HTTP dessas operações; toda recusa vira `ChargeOperationException` com o `errors[].description` do Asaas

### Migrations
- **[database/migrations/2026_01_27_225134_add_asaas_fields_to_invoices.php](database/migrations/2026_01_27_225134_add_asaas_fields_to_invoices.php)** - Adiciona colunas:
  - `asaas_invoice_id` (string) - ID da cobrança no Asaas
  - `asaas_sync_status` (enum: pending, synced, canceled, failed) - Status de sincronização
  - `asaas_synced_at` (timestamp) - Último timestamp de sincronização

- **[database/migrations/2026_01_27_225311_add_asaas_fields_to_invoice_payments.php](database/migrations/2026_01_27_225311_add_asaas_fields_to_invoice_payments.php)** - Adiciona colunas:
  - `asaas_payment_id` (string) - ID do pagamento no Asaas
  - `asaas_sync_status` (enum: pending, synced, failed) - Status de sincronização
  - `asaas_synced_at` (timestamp) - Último timestamp de sincronização

- **[database/migrations/2026_10_23_100000_create_asaas_webhook_events_table.php](database/migrations/2026_10_23_100000_create_asaas_webhook_events_table.php)** - Tabela `asaas_webhook_events`: notificações do webhook (idempotência e auditoria)

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
| `deleted: true` | Cancela a fatura, se não tiver pagamento nem estiver paga/parcial (se a cobrança for restaurada, a fatura volta a ser cobrada) |
| `PENDING`/`OVERDUE` após `PAYMENT_RECEIVED_IN_CASH_UNDONE` (só pelo webhook) | Desfaz o pagamento que veio da cobrança (recibo cancelado) e reabre a fatura |

É idempotente: rodar de novo não cria outro pagamento nem outro recibo, e fatura já paga no sistema
nunca é alterada (se estiver paga aqui e em aberto no Asaas, sai um aviso para dar baixa lá).

**Relatório:** tabela das faturas que mudam (nº, cliente, status antes → depois, forma, data, valor),
totais por tipo de mudança, cobranças do Asaas **sem fatura no sistema** (com o motivo; só são
importadas com `--import`, ver abaixo) e avisos (estorno em andamento, chargeback, divergências).

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

#### 5. Importar cobranças criadas no painel do Asaas (`asaas:reconcile --import`)
```bash
php artisan asaas:reconcile --import [--dry-run] [--since=AAAA-MM-DD]
```

Cobrança do Asaas **sem fatura no sistema** é importada como fatura do tipo **Outro** quando:
- não tem `externalReference` (as cobranças geradas pelo sistema levam o id da fatura ou `EVENT-...`;
  se a fatura não existe mais, a cobrança não a ressuscita);
- o `customer` dela é um responsável cadastrado (Customer com aquele `asaas_customer_id`);
- não está excluída nem estornada, e tem valor e vencimento.

A fatura criada recebe o valor, o vencimento, a data de criação como emissão, a descrição da cobrança
na referência e nas observações (com o id da cobrança), o `asaas_invoice_id`, o link de pagamento e a
escola das faturas anteriores do responsável (ou a única escola cadastrada). Em seguida a situação da
cobrança é aplicada pelo sincronizador (paga, vencida...). Com `--dry-run` só lista o que seria
importado. As cobranças que não puderam ser importadas aparecem no relatório com o motivo.
Sem `--import`, o relatório lista as cobranças sem fatura e indica quais seriam importáveis.

O webhook faz a mesma importação automaticamente (ver abaixo); o `--import` serve para as cobranças
anteriores à configuração do webhook ou que ele não trouxe.

### Webhook

**Rota:** `POST /webhooks/asaas` (sem CSRF), protegida pelo middleware
[app/Http/Middleware/VerifyAsaasWebhookToken.php](app/Http/Middleware/VerifyAsaasWebhookToken.php).

Controlador: [app/Http/Controllers/AsaasWebhookController.php](app/Http/Controllers/AsaasWebhookController.php);
processamento: [app/Services/Asaas/WebhookProcessor.php](app/Services/Asaas/WebhookProcessor.php);
importação: [app/Services/Asaas/ChargeImporter.php](app/Services/Asaas/ChargeImporter.php).

#### Token de autenticação (obrigatório)

O Asaas manda o token cadastrado na configuração do webhook no cabeçalho `asaas-access-token`. O
sistema compara (com `hash_equals`) com `ASAAS_WEBHOOK_SECRET` e responde **401** se faltar ou for
diferente. **Se `ASAAS_WEBHOOK_SECRET` não estiver configurado, todas as notificações são rejeitadas**
(401) e o log registra o erro "ASAAS_WEBHOOK_SECRET não está configurado" — o webhook nunca fica aberto.

Como configurar (sandbox e produção, cada um com seu token):
1. Gerar um token forte, ex.: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
2. No `.env` do servidor: `ASAAS_WEBHOOK_SECRET=<token>` e `php artisan config:clear` (ou
   `config:cache`, se o deploy usa cache de configuração).
3. No painel do Asaas: **Integrações → Webhooks** (no menu do usuário), editar/criar o webhook de
   cobranças:
   - URL: `https://seu-dominio.com/webhooks/asaas`
   - **Token de autenticação**: o mesmo `<token>`
   - Versão da API: v3; tipo de envio: sequencial
   - Eventos: marque os de **cobrança** (os listados abaixo; os não tratados são só registrados)
   - Fila de sincronização ativada
4. Se a fila tiver sido **pausada** (o Asaas pausa depois de 15 falhas seguidas, por exemplo enquanto
   o token não estava configurado), reative-a no mesmo painel; as notificações retidas são reenviadas.

Faça a troca nesta ordem (token no `.env` e no painel do Asaas juntos) para não acumular 401.

#### Recebimento, idempotência e auditoria

Toda notificação autenticada é gravada em `asaas_webhook_events` (`event_id` único = `id` da
notificação no Asaas, `event`, `payment_id`, `external_reference`, `payload` completo, `status`,
`result`, `error`, `attempts`, `processed_at`) e o Asaas recebe **200 na hora**. O processamento
roda logo em seguida, no mesmo processo, depois de a resposta ser enviada (job
[ProcessAsaasWebhookEvent](app/Jobs/ProcessAsaasWebhookEvent.php) com `afterResponse()`; não depende
de worker de fila).

- `status`: `received` (gravada, aguardando), `processed` (aplicada; `result` diz o que mudou e os
  avisos), `ignored` (só informativa ou sem nada a fazer; `result` diz o motivo), `failed` (erro em
  `error`).
- O Asaas entrega "pelo menos uma vez": a mesma notificação repetida (mesmo `id`) responde 200
  (`{"status":"duplicate"}`) **sem processar de novo**. Exceção: se a anterior ficou `failed`, a
  reentrega é processada de novo (`{"status":"retrying"}`).
- Falha no processamento **não** vira erro HTTP (o Asaas pausaria a fila depois de 15 falhas): fica
  `failed` com o erro e no log; a conciliação diária (`asaas:reconcile`) corrige a fatura.
- Notificação sem o campo `event` responde 400 (não é do Asaas). Sem `id` (formato antigo), a chave
  de idempotência é o hash do corpo.

Para conferir: `select event, status, result, error from asaas_webhook_events order by id desc;`

#### Eventos tratados

Para eventos de cobrança de faturas, a fatura é achada pelo `asaas_invoice_id` ou pelo
`externalReference` (= id da fatura, desde que ela não esteja ligada a outra cobrança nem seja de outro
cliente do Asaas). A cobrança é **relida na API** (`GET /payments/{id}`), porque as notificações podem
chegar fora de ordem; se a consulta falhar, vale o objeto `payment` da notificação. A situação é
aplicada pelo `PaymentSynchronizer` (mesma tabela de "Conciliar com o Asaas").

| Evento | Efeito |
|--------|--------|
| `PAYMENT_CREATED` | Fatura existente: liga à cobrança e traz valor/vencimento. Sem fatura: **importa** (ver `--import`) |
| `PAYMENT_UPDATED` | Valor e vencimento alterados no Asaas vão para a fatura em aberto sem pagamentos |
| `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED` | Paga, com forma/data/valor reais (inclusive baixa em dinheiro); CONFIRMED + RECEIVED não duplicam o pagamento |
| `PAYMENT_OVERDUE` | Vencida |
| `PAYMENT_DELETED` | Cancela a fatura **só se não tiver pagamento** (fatura paga/parcial nunca é cancelada; fica o aviso) |
| `PAYMENT_RESTORED` | Fatura cancelada pela exclusão volta a ser cobrada |
| `PAYMENT_REFUNDED` | Desfaz o pagamento (recibo cancelado) e cancela a fatura |
| `PAYMENT_PARTIALLY_REFUNDED` | Não altera; aviso no `result` para ajuste manual |
| `PAYMENT_REFUND_IN_PROGRESS`, `PAYMENT_REFUND_DENIED`, `PAYMENT_CHARGEBACK_REQUESTED`, `PAYMENT_CHARGEBACK_DISPUTE`, `PAYMENT_AWAITING_CHARGEBACK_REVERSAL` | Não altera; aviso no `result` |
| `PAYMENT_RECEIVED_IN_CASH_UNDONE` | Desfaz o pagamento local que veio da cobrança (recibo cancelado) e reabre a fatura (vencida se o vencimento passou; parcial se houver outros pagamentos) |
| `PAYMENT_DUNNING_REQUESTED` / `PAYMENT_DUNNING_RECEIVED` | Vencida (negativação) / paga |
| `PAYMENT_AWAITING_RISK_ANALYSIS`, `PAYMENT_APPROVED_BY_RISK_ANALYSIS`, `PAYMENT_REPROVED_BY_RISK_ANALYSIS`, `PAYMENT_AUTHORIZED`, `PAYMENT_CREDIT_CARD_CAPTURE_REFUSED`, `PAYMENT_ANTICIPATED`, `PAYMENT_BANK_SLIP_VIEWED`, `PAYMENT_CHECKOUT_VIEWED`, `PAYMENT_BANK_SLIP_CANCELLED`, `PAYMENT_SPLIT_*` e eventos que não são de cobrança | `ignored` (só registrados) |

**Cobrança sem fatura no sistema:** nos eventos CREATED, UPDATED, CONFIRMED, RECEIVED, OVERDUE,
RESTORED e DUNNING_* é importada como fatura "Outro" se for de um responsável cadastrado (regras em
"Importar cobranças criadas no painel do Asaas"); senão fica `ignored` com o motivo e um aviso no log.

**Faturas de eventos:** cobranças com `externalReference` `EVENT-{event_id}-{customer_id}` marcam o
participante como pago (RECEIVED/CONFIRMED, uma vez só) ou cancelado (DELETED, se ainda não pagou). As
faturas de evento cobradas pelo id da fatura passam pelo sincronizador e o participante acompanha a
fatura (paga → pago, cancelada → cancelado).

### Operações pelo sistema

A operação das faturas é feita **no sistema** e refletida no Asaas por
[`ChargeOperations`](app/Services/Asaas/ChargeOperations.php). Regra: **Asaas primeiro**. Cada
operação trava a fatura (`lockForUpdate`), confere as regras, chama o Asaas, valida a resposta e só
então grava no sistema, na mesma transação. Se o Asaas recusar ou não responder, **nada muda** e o
usuário vê o motivo (o `errors[].description` do Asaas) numa notificação.

A trava fica de pé durante a chamada ao Asaas de propósito: o webhook que chega logo em seguida passa
pelo `PaymentSynchronizer`, que trava a mesma fatura, espera a operação terminar e encontra a fatura já
atualizada.

| Operação | No Asaas | No sistema |
|----------|----------|------------|
| Criar fatura (página "Nova fatura", aba Faturas do responsável, "Gerar Fatura de Matrícula", "Gerar Mensalidades") | Cadastra o responsável, se preciso, e `POST /payments` | A fatura só é gravada se a cobrança for criada. Se a gravação falhar depois, a cobrança criada é excluída. Nas mensalidades em lote, cada mês é criado com a cobrança ou não é criado, e a notificação lista os meses recusados e o motivo |
| Gerar cobrança (fatura antiga sem cobrança) | `POST /payments` (só do saldo, se já houver pagamento) | Vincula `asaas_invoice_id`, link e QR Code |
| Editar valor, vencimento ou observações | `GET` (para manter o `billingType`) + `PUT /payments/{id}` com `billingType`, `value`, `dueDate` e `description` (as observações, até 500 caracteres) | Só em fatura **aberta/vencida sem pagamentos**. Em fatura paga, parcial ou cancelada, valor e vencimento ficam só leitura e as observações mudam só no sistema. Classificação financeira, referência e tipo nunca chamam o Asaas. O responsável de fatura com cobrança não pode ser trocado |
| Receber **total** | `POST /payments/{id}/receiveInCash` com `paymentDate`, `value` e `notifyCustomer` | Quita com a forma escolhida (dinheiro por padrão; PIX, transferência ou cartão) e **referência = id da cobrança**: o `PAYMENT_RECEIVED` que vem depois não registra outro pagamento |
| Receber **parcial** | `PUT /payments/{id}` com `value` = saldo restante (e desconto zerado) | Registra o pagamento parcial **sem** a referência da cobrança; quando o responsável pagar o restante por PIX/boleto, a sincronização registra esse restante como outro pagamento e quita a fatura. Cada pagamento tem o seu recibo |
| Desfazer recebimento (baixa da cobrança) | `POST /payments/{id}/undoReceivedInCash` | Aplica a cobrança devolvida pelo mesmo caminho do webhook `PAYMENT_RECEIVED_IN_CASH_UNDONE` (`PaymentSynchronizer` com `undoReceipt`): o pagamento é desfeito, o recibo cancelado e a fatura volta a aberta, vencida ou parcial. O webhook que chega depois não acha mais pagamento ligado e não desfaz nada |
| Desfazer pagamento parcial | `PUT /payments/{id}` com o saldo devolvido | Apaga o pagamento (recibo cancelado). Enquanto a cobrança estiver baixada, só a baixa pode ser desfeita |
| Cancelar | `DELETE /payments/{id}` | Só sem pagamentos (com pagamento, desfaça o recebimento antes). A fatura é cancelada com o motivo nas observações |
| Restaurar | `POST /payments/{id}/restore` | A fatura cancelada volta a ser cobrada (aberta ou vencida). Não vale para fatura de matrícula cancelada |
| Excluir (só admin) | Nenhuma chamada | Bloqueado para fatura com cobrança ativa (use Cancelar). Sem cobrança, ou com a cobrança já excluída, funciona como antes |

Faturas antigas **sem cobrança** no Asaas são operadas só no sistema. "Gerar Cobrança Asaas" vincula
uma cobrança a elas.

**Onde ficam as ações** (uma fonte só, em `app/Filament/Resources/Invoices/Actions`):
- Edição da fatura: "Receber pagamento" no cabeçalho. "Desfazer recebimento", "Cancelar fatura",
  "Restaurar fatura" e "Excluir fatura" ficam em "Mais ações". Salvar o formulário passa pela operação
  de editar.
- Listagem: grupo "Operações" em cada linha. A exclusão em lote recusa faturas com cobrança.
- Aba Faturas do responsável: "Receber pagamento", "Editar" (valor, vencimento e observações) e, em
  "Mais ações", desfazer, cancelar, restaurar e excluir. "Alterar dia de vencimento" em lote passa por
  cada fatura pela operação de editar e lista as recusadas. Saíram as ações que mudavam só o banco
  (pagamento/cancelamento manual, status em lote, desvincular). "Vincular fatura" só oferece faturas
  sem cobrança.

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
ASAAS_WEBHOOK_SECRET=token_do_webhook   # o mesmo cadastrado no painel do Asaas; sem ele o webhook rejeita tudo
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
Fatura criada no sistema → ChargeOperations::createInvoice() → POST /payments → grava a fatura com asaas_invoice_id + link + QR Code (recusa = fatura não criada)
Comando asaas:generate-charges → AsaasService::createCharge() (faturas antigas sem cobrança)
```

### 3. Sincronização de Pagamento
```
Webhook Asaas → token (VerifyAsaasWebhookToken) → AsaasWebhookController grava em asaas_webhook_events e responde 200
             → WebhookProcessor (após a resposta) → relê a cobrança → PaymentSynchronizer / ChargeImporter
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
- 401 no painel do Asaas: o token do webhook no painel difere de `ASAAS_WEBHOOK_SECRET`, ou ele não está
  no `.env` (veja o log: "ASAAS_WEBHOOK_SECRET não está configurado" / "token ausente ou inválido").
  Depois de corrigir, reative a fila se o Asaas a pausou
- Notificações chegando mas fatura sem mudar: veja `status`, `result` e `error` em `asaas_webhook_events`
- Teste com `curl` (o token é obrigatório):
```bash
curl -X POST http://localhost:8000/webhooks/asaas \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: $ASAAS_WEBHOOK_SECRET" \
  -d '{"id":"evt_teste_1","event":"PAYMENT_RECEIVED","payment":{"id":"pay_123","status":"RECEIVED","value":100,"paymentDate":"2026-01-27","externalReference":"1"}}'
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
- [x] Autenticar o webhook (token `asaas-access-token`), idempotência e auditoria em `asaas_webhook_events`
- [ ] Tela no painel para consultar/reprocessar notificações `failed` de `asaas_webhook_events`
- [ ] Limpeza periódica de `asaas_webhook_events` antigos
- [x] Testes de integração (`tests/Feature/AsaasWebhookTest.php`, `tests/Feature/AsaasReconciliationTest.php`)

## Suporte

Para dúvidas sobre a API Asaas, consulte:
- [Documentação Asaas](https://docs.asaas.com)
- [Dashboard Asaas](https://app.asaas.com)
