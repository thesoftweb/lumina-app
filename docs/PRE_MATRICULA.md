# Pré-matrícula com link público (leads) e separação por ano letivo

**Data:** 27/10/2026
**Branch:** `feat/pre-matricula` (criada a partir de `feat/historico-boletim`)
**Relação com outros documentos:** antecipa parte da Fase 1 de `PROPOSTA_ANO_LETIVO_2027_HISTORICO_E_BOLETIM.md`.
Essa parte é a turma e o plano com ano letivo. A abertura de ano e a rematrícula em lote continuam pendentes.

---

## 1. Objetivo

- A escola divulga um **link público** de pré-matrícula por ano letivo (ex.: `/pre-matricula/2027`). O link
  mostra as séries ofertadas e os **valores** daquele ano.
- Cada ficha enviada vira um **lead**. A secretaria acompanha o lead no painel: contato por WhatsApp,
  histórico de contatos, situação no funil e desistência.
- Com um clique, a secretaria **converte a ficha em matrícula do ano da ficha**. O responsável e o aluno
  são reaproveitados quando já existem.
- **Nada se mistura entre anos.** Um lead de 2027 não aparece na lista de 2026. Um plano de 2026 não aparece
  na ficha de 2027. Uma matrícula nova de 2027 não cai em turma de 2026.

## 2. Regras de separação por ano

| Regra | Onde fica |
| --- | --- |
| A ficha pertence ao ano letivo do link e não muda de ano. | `pre_enrollments.academic_year_id` |
| O link só aceita inscrições com a chave ligada e dentro do período. | `AcademicYear::acceptsPreEnrollments()` |
| A vaga ofertada é do ano, e o plano da vaga precisa ser do mesmo ano. | `AdmissionOffer::saving` |
| A ficha reenviada para a mesma criança, o mesmo CPF e o mesmo ano atualiza o lead aberto. A mesma criança em outro ano gera outro lead. | `PreEnrollmentIntake`, `scopeSameApplicant` |
| A conversão grava a matrícula no ano da ficha, como **reservada**. | `PreEnrollmentConverter` |
| A conversão exige turma e plano do mesmo ano. Turma ou plano sem ano (legado) só vale se a ficha for do ano letivo padrão. | `PreEnrollmentConverter::belongsToYear` |
| Continua valendo uma matrícula não cancelada por aluno e ano. | `Enrollment::saving` (já existia) |
| Os formulários de matrícula filtram turma e plano pelo ano escolhido. | `Classroom::forAcademicYear`, `Plan::forAcademicYear` |
| A lista de pré-matrículas abre filtrada no ano com inscrições abertas. | `PreEnrollmentResource` |

### 2.1 Valores, desconto e novatos (decisão da escola, 27/10/2026)

- **A mensalidade é fixa:** é o "Valor Base" do plano do ano. A ficha pública mostra esse valor e a taxa de
  matrícula da vaga, sem nenhum desconto.
- **O plano pode ter um desconto** em valor fixo, em percentual ou nenhum. Ele **não vale sozinho**: é o
  desconto disponível para negociar.
- **O desconto só sai de uma negociação, ao efetivar a matrícula, e só na rematrícula.** A secretaria
  escolhe entre:
  - sem desconto;
  - o desconto do plano (`enrollments.apply_plan_discount`);
  - um desconto negociado em valor ou percentual, com motivo obrigatório.

  O desconto escolhido fica registrado na matrícula e é aplicado quando as mensalidades são geradas.
- **Aluno novato não tem desconto**, nem na taxa de matrícula nem nas mensalidades. A regra não aparece
  no site. Ela é garantida no sistema:
  - a conversão recusa o desconto;
  - a matrícula não grava desconto personalizado com "Primeira matrícula" marcada;
  - a taxa de matrícula do novato é a taxa configurada na vaga.
- **Novato ou veterano é decidido pelo histórico, não pelo que a família marcou.** Quem tem matrícula não
  cancelada em um ano anterior é veterano (`Student::previousEnrollmentBefore`). Matrículas antigas sem
  ano letivo também contam.

### 2.2 Rematrícula no mesmo fluxo

- **Fichas geradas em lote:** "Gerar rematrículas" cria uma ficha do tipo *Rematrícula* no ano novo para
  cada aluno matriculado no ano atual que ainda não tem matrícula nem ficha no ano novo. A ficha já vem
  com os dados do cadastro e aponta para a matrícula anterior. Rodar de novo só cria as fichas que faltam.
  Alunos sem data de nascimento ou sem o CPF do responsável aparecem numa lista, para a secretaria
  completar o cadastro.
- **Link individual:** cada ficha tem um link próprio (`/pre-matricula/{ano}/rematricula/{token}`), que a
  secretaria envia pelo WhatsApp. Nele a família confirma a série e atualiza contato e endereço. O link não
  mostra o CPF completo nem dados financeiros e deixa de funcionar depois da conversão ou da desistência.
- **Detecção pela ficha pública:** se a família de um aluno atual preencher a ficha pública, o sistema
  reconhece o aluno pelo CPF do responsável, pelo nome e pela data de nascimento, e marca a ficha como
  rematrícula.
- **Na conversão,** a matrícula nova guarda a matrícula do ano anterior (`previous_enrollment_id`).

Turmas e planos já cadastrados ficam **sem ano** (legado) e continuam funcionando como hoje para 2026.
Para 2027, a secretaria cadastra as turmas e os planos **com o ano 2027** e vincula os planos às turmas.

## 3. Modelo de dados (migração `2026_10_27_000001_create_pre_enrollment_tables.php`)

| Tabela | Mudança |
| --- | --- |
| `academic_years` | `admissions_open`, `admissions_starts_at`, `admissions_ends_at`, `admissions_notes` (texto da ficha) |
| `plans` | `academic_year_id` opcional (FK restrict) |
| `classrooms` | `academic_year_id` opcional (FK restrict) |
| `admission_offers` (nova) | Vaga do ano: série, turno, nível, plano, taxa de matrícula, número de vagas, descrição, ordem e se está ativa |
| `pre_enrollments` (nova) | A ficha (aluno, responsável, endereço, mensagem, origem e UTM, aceite LGPD, IP), dados do funil (situação, responsável interno, último contato, notas, motivo da desistência) e vínculos (cliente, aluno, matrícula criada, quem converteu e quando) |
| `pre_enrollment_contacts` (nova) | Histórico de contatos: canal, anotação, situação depois do contato e usuário |

O funil (`PreEnrollmentStatus`) tem estas situações: Nova › Em contato › Visita agendada › Aguardando
documentos › Matriculada, ou Desistiu.

## 4. Telas

**Público**

- `/pre-matricula`: redireciona para o ano aberto. Se houver mais de um ano aberto, lista os anos. Se não
  houver nenhum, mostra que as inscrições estão encerradas.
- `/pre-matricula/{ano}`: mostra os valores do ano em cartões (mensalidade, valor com desconto de
  pontualidade, taxa de matrícula e vagas restantes) e a ficha. A ficha valida o CPF, pede o aceite LGPD e
  tem proteção contra robôs (honeypot e limite de envios por IP).
- `/pre-matricula/{ano}/enviada`: mostra o protocolo. O código não aparece na URL.
- Na home, o botão "Matrícula" (antes ia para `matricula.html`, que não existe) passa a abrir a
  pré-matrícula.

**Painel (secretaria e administração)**

- **Ano letivo:** seção "Pré-matrícula" com a chave, o período, o texto e o link público para copiar, e a
  aba "Vagas e valores".
- **Acadêmico › Pré-matrículas:** abas por situação, filtro de ano e busca por aluno, responsável, CPF ou
  protocolo. Ações: WhatsApp, registrar contato, marcar desistência e **converter em matrícula**.
- **Planos e turmas:** campo "Ano letivo".

## 5. Como abrir a pré-matrícula de 2027 (passo a passo da secretaria)

1. Em **Anos letivos**, cadastrar 2027 com datas. Não marcar como padrão enquanto 2026 estiver em curso.
2. Em **Planos**, criar os planos de 2027 com o ano 2027. Não editar os planos de 2026.
3. Em **Turmas**, criar as turmas de 2027 com o ano 2027 e vincular os planos.
4. No ano 2027, cadastrar as **Vagas e valores** (uma por série ou turno) e ligar a **pré-matrícula**.
5. Copiar o link público e divulgar (site, Instagram, WhatsApp). Parâmetros `utm_*` no link registram a
   origem da campanha.
6. Atender os leads e converter. A matrícula nasce **reservada**. A taxa e as mensalidades são geradas
   pela própria matrícula, como hoje.

## 6. Fora do escopo (próximos passos)

- Assistente "Abrir ano letivo" para copiar turmas, grade e planos de 2026 para 2027 (Fase 1 da proposta
  de 2027).
- Sugerir a série do ano novo na rematrícula. Hoje não há uma sequência de séries cadastrada, então a
  família ou a secretaria escolhe a vaga.
- Ativar as matrículas reservadas quando o ano começa.
- Envio de documentos pela ficha (upload) e assinatura do contrato online.
- Aviso automático à secretaria (e-mail ou WhatsApp) quando chega uma ficha nova.
- Cobrança da taxa de matrícula direto na ficha, com PIX do Asaas.

## 7. Divisão do trabalho

| Parte | Responsável |
| --- | --- |
| Base: migração, enums, modelos, regra de CPF, serviços de entrada e conversão | Agente principal |
| Ficha pública: rotas, controller, validação, telas e testes | Agente "ficha pública" |
| Painel: ano letivo, vagas, pré-matrículas, conversão, filtros por ano e testes | Agente "painel" |
| Home mais acessível no celular | Agente "home" |
