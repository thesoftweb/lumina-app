# Relatório de testes - Pré-matrícula, rematrícula e home

**Branch:** `feat/pre-matricula`
**Ambiente:** local (Herd), MySQL `laravel` em 127.0.0.1:3306
**Início dos testes:** 27/10/2026
**Documento de referência:** `docs/PRE_MATRICULA.md`

Registro dos testes manuais feitos na web. Cada falha encontrada vira um item numerado, com o que foi
feito, o que aconteceu, a causa e a correção. Ao corrigir, atualize a situação e a data.

## 1. Testes automatizados (antes do teste manual)

| Data | Suíte | Resultado |
| --- | --- | --- |
| 27/10/2026 | `php artisan test` (SQLite em memória) | 343 testes passando, 2556 asserções |
| 27/10/2026 | Depois da correção da falha 1 | 350 testes passando, 2595 asserções |
| 27/10/2026 | Depois da falha 2 e do ajuste D1 | 352 testes passando |
| 27/10/2026 | Depois de M1, M2 e D3 | 359 testes passando, 2648 asserções |

> Os testes automatizados rodam em SQLite, que não tem o modo estrito do MySQL. Por isso eles não pegaram
> a falha 1 (coluna obrigatória sem valor padrão), que só aparece no MySQL.

## 2. Roteiro do teste manual

| # | Etapa | Situação |
| --- | --- | --- |
| R1 | Rodar `php artisan migrate` (migrações `2026_10_27_000001` e `000002`) | Feito |
| R2 | Anos letivos: criar 2027 com datas, sem marcar como padrão | Feito |
| R3 | Planos: criar o plano de 2027 com "Ano letivo" = 2027 (sem desconto e com desconto) | Falhou (falha 1), corrigido; testar de novo |
| R4 | Turmas: criar as turmas de 2027 e vincular o plano | Feito por "Copiar turmas de outro ano" (D3) |
| R5 | Ano 2027: ligar a pré-matrícula, cadastrar as vagas e copiar o link | Vagas criadas por turma (D3); conferir a ficha |
| R5b | Ficha pública: aparecem "Turmas e valores 2027" e a família escolhe a turma | Pendente |
| R6 | Home no celular: menu, barra fixa "Pré-matrícula" e acessibilidade | Pendente |
| R7 | Ficha pública `/pre-matricula`: enviar e conferir o lead "Novato" no painel | Pendente |
| R8 | Converter novato: sem seção de desconto; matrícula "Reservada" em 2027 | Pendente |
| R9 | Gerar rematrículas (2026 → 2027) e enviar ou copiar o link | Pendente |
| R10 | Link de rematrícula em aba anônima: confirmar a série | Pendente |
| R11 | Converter rematrícula: "Negociação" com "Sem desconto", "Desconto do plano" e "Desconto negociado" | Pendente |
| R12 | Ficha pública preenchida pela família de um aluno atual: sai como "Rematrícula" | Pendente |
| R13 | Taxa de matrícula de novato: vem da vaga e travada (cuidado: cria cobrança no Asaas) | Pendente |

## 3. Falhas encontradas

### Falha 1 - Erro ao salvar plano com "Tipo de Desconto" = Nenhum

- **Etapa:** R3 (Planos › Criar).
- **Situação:** corrigida em 27/10/2026. Aguarda novo teste manual.
- **Severidade:** alta. Bloqueia o cadastro de planos sem desconto. Pelo que a escola decidiu (mensalidade
  fixa, sem desconto no plano), esse é justamente o caso normal. Sem plano, não há como cadastrar turmas e
  vagas de 2027.
- **Dados usados:** nome "MENSALIDADE INFANTIL", ano letivo 2027 (id 2), valor base 235, tipo de desconto
  "Nenhum".
- **O que aconteceu:**

  ```
  Illuminate\Database\QueryException
  SQLSTATE[HY000]: General error: 1364 Field 'discount_value' doesn't have a default value
  insert into `plans` (`name`, `academic_year_id`, `base_amount`, `discount_type`, `final_value`,
  `is_active`, `updated_at`, `created_at`) values (MENSALIDADE INFANTIL, 2, 235, none, 235, 1, ...)
  ```

- **Esperado:** o plano é salvo com valor final igual ao valor base (R$ 235,00).
- **Causa:**
  - A coluna `plans.discount_value` é obrigatória e não tem valor padrão (`2025_12_02_143942_create_plans_table.php`).
  - Quando o tipo de desconto é "Nenhum", o formulário (`PlanForm.php:64`) esconde o campo "Valor do
    Desconto". Campo escondido não é enviado, então o insert vai sem `discount_value`, e o MySQL em modo
    estrito recusa.
  - **A falha é anterior a esta branch.** O campo já era escondido antes; esta branch só acrescentou o
    "Ano letivo" ao formulário. Qualquer plano "sem desconto" falhava do mesmo jeito.
- **Decisão da escola (27/10/2026):** haverá planos com desconto (valor fixo, percentual ou sem desconto).
  O desconto do plano **não vale sozinho**: ele é negociado e aplicado ao efetivar a matrícula. Os campos de
  desconto continuam na tela de planos.
- **Correção:**
  1. **Plano:** o modelo `Plan` normaliza o desconto ao salvar, em qualquer tela.
     - "Nenhum": `discount_value = 0`, `has_discount = false` e valor final igual ao valor base.
     - Valor fixo ou percentual: `has_discount = true`, com o valor final recalculado.
     - O percentual não pode passar de 100%, e o desconto fixo não pode ser maior que o valor base.
  2. **Matrícula:** novo campo `apply_plan_discount` ("Aplicar o desconto do plano"). Ao efetivar a
     matrícula, a secretaria escolhe entre:
     - sem desconto;
     - o desconto do plano;
     - um desconto negociado, com valor ou percentual e motivo.

     O desconto do plano e o negociado não podem ser usados juntos.
  3. **Faturamento:** `InvoiceService` só aplica o desconto do plano quando a matrícula tem
     `apply_plan_discount`. As matrículas já existentes ficam sem a marca, então a cobrança delas não muda.
     Hoje nenhum plano tinha `has_discount` ligado, e por isso nenhuma fatura recebia o desconto do plano.
  4. **Novato:** continua sem desconto nenhum, nem o do plano nem o negociado.
  5. **Site:** continua mostrando só o valor base do plano.
- **Telas alteradas:**
  - **Planos:** o texto explica que o desconto não é automático. Com desconto, o campo "Valor Final" passa a
    se chamar "Valor com desconto (quando negociado)". O percentual não passa de 100%, e o desconto fixo
    não passa do valor base.
  - **Converter em matrícula (rematrícula):** a seção "Negociação" oferece "Sem desconto", "Desconto do
    plano (10%)" (desativado quando o plano não tem desconto) e "Desconto negociado", com prévia da
    mensalidade. O motivo é obrigatório no desconto negociado.
  - **Matrícula (formulário):** "Aplicar o desconto do plano" aparece só quando o plano tem desconto e o
    aluno não é novato. Marcar essa opção desmarca o desconto negociado, e vice-versa.
- **Migração:** `2026_10_27_000003_add_apply_plan_discount_to_enrollments.php`. Ela cria
  `enrollments.apply_plan_discount` e define o padrão 0 em `plans.discount_value`. Já foi aplicada no
  MySQL local.
- **Testes automatizados:** `tests/Feature/PlanDiscountNegotiationTest.php`, com 7 testes:
  - salvar pela tela de planos com "Nenhum" (o caso desta falha);
  - normalização do desconto do plano;
  - descontos inválidos;
  - desconto do plano só na matrícula que o aplicou;
  - bloqueio para novato e para os dois descontos juntos;
  - conversão pela tela com o desconto do plano;
  - recusa na conversão para novato e para plano sem desconto.

  Suíte completa: 350 testes passando.
- **Para testar de novo (R3):** criar "MENSALIDADE INFANTIL", ano 2027, valor base 235, desconto "Nenhum".
  Depois criar outro plano com desconto (percentual ou fixo) e conferir o "Valor com desconto".

### Falha 2 - Todo ano letivo novo nascia como "ano padrão"

- **Etapa:** encontrada ao preparar R4 (conferência das turmas e dos anos no banco local).
- **Situação:** corrigida em 27/10/2026.
- **Severidade:** alta. Ela quebra a separação por ano.
- **O que aconteceu:** 2026 e 2027 estavam marcados como padrão ao mesmo tempo. A conversão só aceita
  turma ou plano sem ano (legado) quando a ficha é do ano padrão. Com 2027 marcado como padrão, uma ficha de
  2027 podia ser matriculada numa turma de 2026, justamente o que a regra deveria impedir.
- **Causa:**
  - O formulário de ano letivo não tinha o campo "padrão".
  - No banco, a coluna `academic_years.is_default` tem padrão `true`
    (`2025_11_27_104957_create_academic_years_table.php`).
  - O modelo só desmarca os outros anos quando `is_default` é informado ao salvar. Por isso, todo ano criado
    pela tela virava padrão sem desmarcar o anterior.
- **Correção:**
  - O modelo `AcademicYear` passa a criar o ano com `is_default = false`.
  - O formulário ganhou a chave "Ano letivo atual (padrão)". Marcar um ano desmarca os outros.
  - No banco local, 2027 foi desmarcado como padrão. 2026 continua sendo o padrão.
- **Teste automatizado:** `ClassroomYearBackfillTest::test_a_new_academic_year_is_not_default_unless_marked`.
- **Atenção na produção:** antes de usar a pré-matrícula, conferir se só 2026 está como padrão.

## 4. Ajustes de dados

### D1 - Turmas existentes vinculadas a 2026 (pedido em 27/10/2026)

- **Pedido:** as turmas existentes sem ano letivo passam a ser de 2026.
- **Antes (banco local):** 10 turmas sem ano.
  - Todas as matrículas eram de 2026, menos uma sem ano (legado) em INFANTIL 5.
  - A turma "Bellet" não tinha matrícula.
- **Como foi feito:** migração `2026_10_27_000004_assign_2026_to_classrooms_without_year.php`, para que a
  produção receba o mesmo ajuste ao rodar `php artisan migrate`.
  - Por segurança, só recebe 2026 a turma sem ano que não tem matrícula de outro ano. Uma turma nessa
    situação continua sem ano, para revisão manual.
  - A migração não tem volta automática.
- **Depois (banco local):** as 10 turmas são de 2026:
  - 1 ANO FUNDAMENTAL, 2 ANO FUNDAMENTAL, 3 ANO FUNDAMENTAL, 4 ANO FUNDAMENTAL e 5 ANO FUNDAMENTAL;
  - INFANTIL 3, INFANTIL 4 e INFANTIL 5;
  - BALLET e Bellet.
- **Efeito:**
  - As turmas de 2026 não aparecem mais para matrículas de 2027.
  - Na conversão de fichas de 2027, só aparecem as turmas criadas com o ano 2027.
- **Teste automatizado:** `ClassroomYearBackfillTest::test_classrooms_without_year_are_assigned_to_2026_unless_they_have_another_year`.
- **Observação:** "BALLET" e "Bellet" pareciam a mesma turma cadastrada duas vezes. Ver D2.

### D2 - Exclusão da turma duplicada "Bellet" (pedido em 27/10/2026)

- **Conferência antes de excluir (turma #9):** nenhuma matrícula, frequência, diário, plano de aula, evento
  ou agenda.
- **Vínculos de configuração removidos junto:**
  - plano "BOLSA FUNDAMENTAL I 30%" (os planos continuam cadastrados);
  - professora "Juliana dos Santos" (a professora continua cadastrada).
- **Feito:** só no banco local, direto no banco, sem migração. Se a turma também existir em produção,
  excluir pela tela de Turmas.
- **Depois:** 9 turmas, todas de 2026 (1 a 5 ANO FUNDAMENTAL, BALLET, INFANTIL 3, 4 e 5).

### D3 - Turmas e vagas de 2027 (pedido em 27/10/2026)

- **Pedido:** criar em 2027 as mesmas turmas de 2026, menos BALLET, com os planos de 2027.1. A família
  precisa poder escolher a turma na ficha, e a quantidade de vagas é opcional.
- **Feito no banco local:**
  - **8 turmas em 2027:** 1 a 5 ANO FUNDAMENTAL com o plano MENSALIDADE FUNDAMENTAL I (R$ 245,00), e
    INFANTIL 3, 4 e 5 com o plano MENSALIDADE INFANTIL (R$ 235,00).
  - **O que foi copiado de 2026:** nome, nível, série, dias letivos, modo de avaliação e disciplinas, com a
    carga horária.
  - **Professores:** não foram copiados, porque a atribuição muda de um ano para o outro.
  - **Bolsas:** "BOLSA FUNDAMENTAL I 30%" e "BOLSA INFANTIL 30%" não existem em 2027 e ficaram sem vínculo.
    Se houver bolsa em 2027, crie o plano e vincule às turmas.
  - **8 vagas na pré-matrícula de 2027,** uma por turma, com o plano da turma e sem limite de vagas. Na
    ficha, a Educação Infantil aparece primeiro.
- **Como foi feito:** pelos mesmos serviços das telas novas, sem migração. Em produção, use as telas
  abaixo.

## 5. Mudanças feitas durante os testes

### M1 - A família escolhe a turma na ficha

- **Motivo:** no teste, a ficha não mostrava turmas nem valores. A pré-matrícula estava aberta, mas o ano
  não tinha vagas cadastradas, e a ficha só monta esse bloco a partir das vagas. A ficha enviada nesse
  teste ficou sem turma escolhida.
- **O que mudou:**
  - **Vaga ligada a uma turma** (`admission_offers.classroom_id`, migração `2026_10_27_000005`). A vaga só
    aceita turma do mesmo ano.
  - **Ficha pública:** quando as vagas são turmas, o bloco se chama "Turmas e valores 2027" e pede "Escolha
    a turma pretendida". Sem quantidade de vagas, a turma aparece sem limite e sem número.
  - **Conversão em matrícula:** a turma escolhida pela família e o plano dela já vêm marcados.
  - **Ano letivo › Vagas e valores:**
    - Campo "Turma", que preenche nome, nível, série e plano.
    - Ação "Criar vagas a partir das turmas", que cria uma vaga por turma e pode ser rodada de novo sem
      duplicar.
    - "Quantidade de vagas (opcional)".
  - **Ano letivo, página do ano:** ação "Copiar turmas de outro ano".
    - Escolhe o ano de origem e as turmas.
    - Copia a estrutura e liga os planos do ano novo que tenham o mesmo nome.
    - Não altera as turmas de origem e não duplica.
- **Correção durante o ajuste:** as vagas criadas em lote saíram todas com ordem 1. Corrigido, e as vagas
  locais foram reordenadas.
- **Testes automatizados:** `tests/Feature/ClassroomOffersForPreEnrollmentTest.php`, com 7 testes.

### M2 - Plano com desconto "Fixo" e valor 0

- O plano BALLET de 2026 está gravado como desconto "Fixo" com valor 0. Com a regra da falha 1, editar esse
  plano dava erro. Agora, desconto com valor 0 é tratado como "Nenhum".

## 6. Observações

- **O1:** a ficha enviada antes das vagas existirem continua sem turma. Ajuste a turma pela edição da ficha
  no painel, ou na conversão.
- **O2:** os nomes das turmas de 2026 e 2027 são iguais. Nas telas com filtro de turma (como Matrículas),
  use também o filtro de ano letivo.
