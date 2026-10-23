# Plano de Sprint - Segurança e Qualidade

**Data do plano:** 05/10/2026
**Origem:** avaliação dos documentos em `docs/` cruzada com o código (rotas, controllers, seeders, testes)
**Status:** planejamento. Nenhum item foi implementado.

---

## 1. Contexto

A avaliação encontrou falhas de segurança graves em rotas públicas, um bug que quebra um endpoint da API e documentação desatualizada. Este plano organiza a correção em duas sprints. A Sprint 1 trata o que expõe dados ou permite execução remota. A Sprint 2 trata as melhorias estruturais.

**Premissas**

- Sprint de 2 semanas, sugerida de 05/10 a 16/10/2026 (Sprint 1) e de 19/10 a 30/10/2026 (Sprint 2).
- Estimativas em dias de uma pessoa, apenas como referência. Ajustar à capacidade real do time.
- Os itens marcados como "verificar" vieram de leitura de documentação. Não foram confirmados no código e podem mudar o escopo.

**Fora do escopo**

- Os 21 documentos pedagógicos do roadmap (`ROADMAP_DOCUMENTOS_PEDAGOGICOS.md`).
- Média final, aprovação automática, recuperação e histórico escolar (já declarados fora de escopo no painel do professor).
- Dashboard de pagamentos, split de pagamento e SMS/e-mail com link de pagamento (backlog do Asaas).

---

## 2. Objetivos da Sprint 1

1. Nenhuma rota pública executa comandos ou altera estado sem autenticação.
2. Nenhum dado pessoal ou financeiro é acessível por ID sequencial sem autorização.
3. Todo endpoint documentado da API responde conforme a documentação.
4. Cada correção tem teste automatizado que falha antes e passa depois.

---

## 3. Sprint 1 - Backlog

Ordem de execução = ordem da tabela. Itens S1-01 e S1-02 bloqueiam o restante da pauta de segurança e devem ir primeiro.

| ID | Item | Tipo | Estimativa | Prioridade |
| --- | --- | --- | --- | --- |
| S1-00 | Verificações iniciais (spike) | Spike | 1 d | Alta |
| S1-01 | Remover `POST /artisan` | Segurança | 0,5 d | Crítica |
| S1-02 | Autenticar o webhook do Asaas | Segurança | 2 d | Crítica |
| S1-03 | Proteger impressão de documentos e contratos | Segurança | 2 d | Crítica |
| S1-04 | Proteger o QR Code PIX do portal | Segurança | 1 d | Crítica |
| S1-05 | Endurecer o login do portal | Segurança | 1,5 d | Alta |
| S1-06 | Corrigir e isolar por tenant a API de alunos | Bug + Segurança | 2 d | Alta |
| S1-07 | Seeders e credenciais de teste | Segurança | 0,5 d | Alta |
| S1-08 | Atualizar documentação afetada | Docs | 1 d | Média |
| | **Total** | | **11,5 d** | |

### S1-00 - Verificações iniciais (spike)

Confirmar no código os pontos que a documentação apenas afirma. O resultado pode ajustar as estimativas dos demais itens.

- O model `Enrollment` (e demais models de dados de aluno) tem global scope por `company_id`? Define se S1-06 é pontual ou exige S2-01 antes.
- Policies: o registro em `AppServiceProvider::$policies` tem efeito no Laravel 12, ou a descoberta automática já cobre?
- Listagens do professor (`getEloquentQuery` dos resources de Attendance, ClassDiary e LessonPlan) filtram por `teacher_id`, ou só a policy `view` protege?
- O agendamento do Asaas existe de fato (`routes/console.php`), já que `app/Console/Kernel.php` não existe no Laravel 12?
- A migration de `agendas` tem um `classroom_id` único ou uma tabela pivô? A doc diz "multi seleção".
- Quem consome `/api/students*` e `/api/{tenant}/*` hoje (app, site, integração)? Sem essa resposta não se pode remover as rotas legadas.
- `ARTISAN_KEY` está definida em produção? O `/artisan` foi chamado? Conferir logs de acesso do servidor.

**Entregável:** nota curta anexada a este documento com as respostas.

### S1-01 - Remover `POST /artisan`

**Problema:** `ArtisanController` compara `$request->input('key') !== env('ARTISAN_KEY')`. Com a chave ausente ou com a config em cache, `null !== null` é falso e a requisição é autorizada. Permite rodar qualquer comando Artisan, inclusive `migrate:fresh --force`.

**Tarefas**

1. Remover a rota em `routes/web.php` e o `ArtisanController`.
2. Remover `ARTISAN_KEY` de `.env`, `.env.example` e do ambiente de produção.
3. Conferir logs de acesso por chamadas anteriores ao endpoint. Se houver, tratar como incidente (rotacionar `APP_KEY`, chaves do Asaas e senhas de banco).
4. Se o time usa essa rota para deploy (migrate, cache), substituir por pipeline de deploy.

**Critérios de aceite**

- `POST /artisan` retorna 404.
- Teste de feature confirma o 404.

### S1-02 - Autenticar o webhook do Asaas

**Problema:** `POST /webhooks/asaas` aceita qualquer payload. `PAYMENT_OVERDUE` e `PAYMENT_DELETED` alteram a invoice direto do payload, e o pagamento de evento (`EVENT-x-y`) marca participante como pago com dados do payload. O `externalReference` é o ID sequencial da invoice.

**Tarefas**

1. Configurar um token de webhook no painel do Asaas e em `config/asaas.php` (`webhook_token`, via `.env`).
2. Validar o cabeçalho `asaas-access-token` com `hash_equals`. Retornar 401 se ausente ou inválido. Se o token não estiver configurado, rejeitar tudo (falhar fechado).
3. Não confiar no payload para mudar estado. Em `OVERDUE`, `DELETED`, `RESTORED` e no pagamento de eventos, buscar o pagamento na API (`getPayment`) e aplicar o status retornado. `RECEIVED` e `CONFIRMED` já seguem esse caminho para invoices.
4. Idempotência: guardar eventos processados (tabela `asaas_webhook_events` com chave única por evento ou, se o payload não trouxer ID de evento, por `payment.id + event + status`). Evento repetido retorna 200 sem reprocessar. Verificar na documentação do Asaas qual campo identifica o evento.
5. Padronizar a resposta de erro: 500 quando o processamento falha (para o Asaas reenviar), 200 quando o evento é ignorado de propósito. Hoje invoices e eventos tratam isso de forma diferente.
6. Registrar tentativas rejeitadas em log, sem gravar o payload completo.

**Critérios de aceite**

- Requisição sem token ou com token errado retorna 401 e não altera nenhum dado.
- Evento `PAYMENT_DELETED` com token válido mas pagamento ainda ativo no Asaas não cancela a invoice.
- Mesmo evento enviado duas vezes processa uma vez.
- Testes cobrem token ausente, token inválido, token válido, evento duplicado e referência de evento.

**Dependência:** acesso ao painel do Asaas para cadastrar o token (sandbox e produção).

### S1-03 - Proteger impressão de documentos e contratos

**Problema:** `/documents/{id}/print`, `/documents/{id}/pdf`, `/enrollments/{id}/contract/print` e `/enrollments/{id}/contract/pdf` não têm middleware nem policy. IDs sequenciais expõem CPF, e-mail, telefone e dados do contrato.

**Tarefas**

1. Colocar as quatro rotas sob `auth` e verificar com `Gate::authorize('view', ...)`, como o `ReportCardController` já faz. Criar `DocumentPolicy` e a policy de contrato (ou usar `EnrollmentPolicy`, que a doc diz estar criada e não implementada).
2. A policy deve checar papel e `company_id` do usuário.
3. Garantir que as actions "Imprimir" e "Baixar PDF" do Filament continuem funcionando (a sessão do painel já autentica).
4. Decisão: o portal do responsável precisa baixar contrato? Se sim, usar URL assinada temporária (`URL::temporarySignedRoute`) gerada na sessão do portal, não rota aberta.

**Critérios de aceite**

- Visitante anônimo recebe redirecionamento ao login (ou 403).
- Usuário de outra empresa recebe 403.
- Usuário autorizado imprime e baixa o PDF normalmente.
- Testes cobrem anônimo, outra empresa e usuário autorizado, para as quatro rotas.

### S1-04 - Proteger o QR Code PIX do portal

**Problema:** `GET /portal/invoices/{invoice}/pix-qrcode` não exige sessão do portal nem verifica que a invoice é do cliente. Além de vazar valor e referência, cria cobrança no Asaas quando a invoice não foi sincronizada, em uma requisição GET pública.

**Tarefas**

1. Exigir `session('customer_document')` e verificar que `invoice.customer_id` pertence ao cliente da sessão. Caso contrário, 403 ou 404.
2. Extrair a verificação do portal para um middleware (`EnsurePortalCustomer`) que também sirva a `agendas.*` e `events.*`, hoje duplicada nos controllers.
3. Retirar a criação de cobrança do GET. Opções: gerar apenas pelo comando agendado e pela action do Filament, ou mover para um POST autenticado pelo portal. Recomendado: POST, com limite de taxa.
4. Aplicar a mesma verificação de posse a `events/{event}/pay` e `events/{event}/payment-link`.

**Critérios de aceite**

- Sem sessão do portal: 401 ou redirecionamento ao login.
- Cliente A não acessa invoice do cliente B.
- Nenhum GET cria cobrança no Asaas.
- Testes para sessão ausente, invoice de terceiro e invoice própria.

### S1-05 - Endurecer o login do portal

**Problema:** o acesso usa só o CPF. Não há limite de tentativas nem `session()->regenerate()`. As mensagens de erro diferenciam "cadastro não encontrado" de "sem matrícula". A sanitização do documento está comentada, então CPF digitado com máscara depende do formato armazenado.

**Tarefas (nesta sprint)**

1. `throttle` na rota `portal.access` (por IP e por documento).
2. `session()->regenerate()` após o login.
3. Mensagem de erro única para qualquer falha de identificação.
4. Normalizar o documento (somente dígitos) na entrada e na comparação. Exige conferir como `customers.document` está armazenado hoje e, se necessário, migração de dados.
5. Validar o documento como CPF ou CNPJ (dígitos verificadores), não apenas `min:11`.
6. Corrigir a formatação de exibição para não depender de o documento ser só dígitos.

**Fica para a Sprint 2:** segundo fator (S2-04), pois exige decisão de produto.

**Critérios de aceite**

- A sexta tentativa em um minuto recebe 429.
- Documentos inexistentes e sem matrícula geram a mesma mensagem.
- CPF com e sem máscara acessam o mesmo cadastro.
- Testes para throttle, mensagem genérica e normalização.

### S1-06 - Corrigir e isolar por tenant a API de alunos

**Problema 1 (bug):** `StudentController::show()` usa `$student` antes de defini-lo e mistura `||` com a query. O endpoint `GET /students/{id}` sempre cai no `catch` e retorna 500.

**Problema 2 (segurança):** `Enrollment::with(...)` não filtra por empresa. As rotas legadas listam alunos de todas as escolas, sem autenticação. As rotas com `{tenant}` resolvem o tenant mas os controllers não o usam. Respostas de erro devolvem `$e->getMessage()`.

**Tarefas**

1. Reescrever `show()`: buscar a matrícula do aluno com `whereHas`, depois `first()`, depois 404 se não existir.
2. Filtrar `index`, `show` e `byClassroom` por `company_id` do tenant resolvido (usar `app('tenant_id')` ou escopo do S2-01 quando existir).
3. Padronizar o campo da turma. Hoje `index` e `byClassroom` devolvem `turma`, `show` devolve `serie`, a doc diz `serie`. Escolher um nome, preferencialmente o que o consumidor atual usa (resposta do S1-00), e atualizar a doc.
4. Não devolver `$e->getMessage()`. Registrar no log e responder com mensagem genérica.
5. Rotas legadas sem tenant: se o S1-00 mostrar que há consumidores, marcá-las como obsoletas com cabeçalho `Deprecation`, limitar a uma empresa por configuração e definir data de remoção. Se não houver consumidores, remover.
6. Corrigir o teste existente (`TenantApiTest`) para cobrir o isolamento.

**Critérios de aceite**

- `GET /api/{tenant}/students/{id}` retorna 200 com o aluno do tenant, 404 para aluno de outro tenant.
- Listagem de um tenant nunca contém aluno de outro.
- Erro interno não vaza mensagem de exceção.
- Testes para os três endpoints, com dois tenants.

**Fica para a Sprint 2:** autenticação com Sanctum, resources, paginação (S2-03).

### S1-07 - Seeders e credenciais de teste

**Problema:** `TeacherUserSeeder` cria `admin@admin.com` e `professor@example.com` com a senha `password123`. A doc cita `admin@example.com`, que o seeder não cria.

**Tarefas**

1. Bloquear a criação desses usuários quando `app()->environment('production')`.
2. Ler a senha de variável de ambiente ou gerar aleatória e exibir no console.
3. Confirmar se o `DatabaseSeeder` chama esse seeder e se já houve seed em produção. Se sim, trocar as senhas e remover os usuários de teste.
4. Remover as credenciais dos documentos.

**Critérios de aceite**

- `db:seed` em ambiente `production` não cria usuários de teste.
- Nenhum documento do repositório contém senha em texto claro.

### S1-08 - Atualizar documentação afetada

- `ASAAS_INTEGRATION.md`: token do webhook, idempotência, localização real do agendamento, itens pendentes atualizados.
- `API_STUDENTS_DOCUMENTATION.md`: rotas com `{tenant}`, `/login`, `/settings`, `/classes`, campo da turma, remoção do texto "sem autenticação" como característica, aviso de depreciação das rotas legadas.
- `PRINT_DOCUMENTS_GUIDE.md`: remover "pronto para produção" sem ressalva, documentar a proteção por login e policy.
- `TEACHER_PANEL_IMPLEMENTATION.md` e `PEDAGOGICAL_MODELS_IMPLEMENTATION.md`: remover credenciais, corrigir `admin@example.com` e o trecho "quando o Resource for criado".
- `AGENDA_IMPLEMENTATION.md`: remover "próximas etapas" já feitas e alinhar com a decisão sobre turma única ou múltipla.
- `ROADMAP_DOCUMENTOS_PEDAGOGICOS.md`: marcar o que o painel do professor já entrega (boletim, PDF) e corrigir a tabela de status.

---

## 4. Sprint 2 - Backlog previsto

Detalhar e reestimar ao final da Sprint 1, depois de ler o resultado do S1-00.

| ID | Item | Tipo | Estimativa inicial |
| --- | --- | --- | --- |
| S2-01 | Global scope por `company_id` nos models de dados escolares | Segurança | 3 d |
| S2-02 | Asaas: filas, retry com backoff, geração de cobranças em lote | Melhoria | 4 d |
| S2-03 | API: Sanctum, API Resources, paginação, remoção das rotas legadas | Melhoria | 3 d |
| S2-04 | Portal: segundo fator de acesso (decisão de produto) | Segurança | 2 d |
| S2-05 | Regras pedagógicas pendentes: sem frequência em data futura, bloqueio de edição de diário antigo | Melhoria | 2 d |
| S2-06 | Testes: policies dos resources pedagógicos, Asaas (serviço e comandos), portal | Qualidade | 3 d |
| S2-07 | URLs de documentos com UUID ou assinadas, em vez de ID sequencial | Segurança | 1,5 d |
| S2-08 | Consolidar documentação em um README de arquitetura | Docs | 1 d |

**Decisões de produto necessárias antes da Sprint 2**

- Qual segundo fator o responsável usa no portal (data de nascimento do aluno, código por e-mail, link mágico)?
- A agenda de uma turma pode ter várias turmas por registro, ou uma só?
- Quem são os consumidores da API e como eles vão se autenticar?

---

## 5. Riscos

| Risco | Impacto | Mitigação |
| --- | --- | --- |
| `/artisan` já foi explorado em produção | Banco ou segredos comprometidos | Conferir logs no S1-01; rotacionar segredos se houver acesso suspeito |
| Consumidor desconhecido da API legada quebra ao proteger ou remover rotas | Indisponibilidade de integração | S1-00 identifica consumidores; depreciar antes de remover |
| Token do webhook mal configurado no Asaas | Pagamentos confirmados não refletem no sistema | Testar primeiro no sandbox; manter o comando `asaas:sync-payments` a cada 4 horas como rede de segurança |
| Normalização de CPF exige migração de dados | Clientes antigos deixam de entrar no portal | Conferir formato armazenado no S1-00; comparar com versões normalizada e original durante a transição |
| Global scope (S2-01) altera consultas existentes do Filament e dos comandos | Dados "somem" em relatórios ou jobs sem usuário logado | Cobrir com testes antes e usar `withoutGlobalScopes()` de forma explícita nos comandos |
| Itens "verificar" mudam o escopo | Estouro da sprint | S1-00 vai primeiro, e o plano é reestimado no segundo dia |

---

## 6. Definição de pronto

- Código revisado por outra pessoa.
- Teste automatizado para o caso de falha original e para o caso permitido.
- `php artisan test` e `npm run build` passando.
- Documentação do item atualizada (S1-08).
- Variáveis de ambiente novas registradas em `.env.example`.
- Deploy em homologação validado manualmente antes de produção.
- Para itens de segurança: verificação manual do ataque original (por exemplo, `curl` sem credenciais) com resultado registrado na descrição da tarefa.

---

## 7. Ordem de deploy recomendada

1. S1-01 sozinho, o mais cedo possível, em release dedicada.
2. S1-02, junto com a configuração do token no Asaas, na mesma janela.
3. S1-03, S1-04 e S1-05 em uma release.
4. S1-06 e S1-07 em outra, avisando antes os consumidores da API.

---

## 8. Acompanhamento

| ID | Responsável | Status | Observações |
| --- | --- | --- | --- |
| S1-00 | | A fazer | |
| S1-01 | | A fazer | |
| S1-02 | | A fazer | |
| S1-03 | | A fazer | |
| S1-04 | | A fazer | |
| S1-05 | | A fazer | |
| S1-06 | | A fazer | |
| S1-07 | | A fazer | |
| S1-08 | | A fazer | |
