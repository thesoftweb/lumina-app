# Proposta - Ano Letivo 2027, Histórico Escolar e Boletim no Portal

**Data:** 05/10/2026
**Escopo:** regras pedagógicas, virada de ano letivo sem afetar 2026, histórico escolar e boletim online (HTML e PDF) no portal do responsável.
**Status:** proposta para decisão. Nenhum código foi alterado.
**Relação com outros documentos:** complementa `SPRINT_PLAN_SEGURANCA_E_QUALIDADE.md`. As correções de segurança daquele plano (em especial o isolamento por tenant e a proteção de rotas públicas) devem entrar antes de o portal passar a expor notas.

---

## 1. Resumo

O sistema já tem as peças pedagógicas básicas (notas por bimestre, conceitos, frequência em lote, boletim em HTML/PDF para o painel). O que falta é tratar o **ano letivo como dimensão de todo o dado acadêmico**. Hoje só a matrícula conhece o ano. A turma, a grade de disciplinas, os professores atribuídos, os bimestres e as regras de avaliação não conhecem.

Consequência prática: ao abrir 2027 reaproveitando as turmas atuais, qualquer ajuste feito para 2027 (disciplina, professor, modo de avaliação, plano de pagamento) altera também o que o boletim de 2026 mostra, e alguns ajustes **apagam notas de 2026** (item 2.1). Há ainda telas do professor que permitem lançar nota de um bimestre de 2026 em uma matrícula de 2027.

A proposta tem cinco partes:

1. Blindar o que pode corromper 2026 (Fase 0, pequena e urgente).
2. Tornar turma, bimestre e regras escopados por ano letivo e criar o fluxo de abertura de ano e rematrícula (Fase 1).
3. Formalizar regras e fechamento de ano, com resultado final por aluno (Fase 2).
4. Boletim online no portal, com publicação controlada (Fase 3).
5. Histórico escolar com emissão numerada e validável (Fase 4).

---

## 2. Diagnóstico das regras e da estrutura atuais

Tudo abaixo foi conferido no código. Quando algo é inferência, está dito.

### 2.1 Riscos para os dados de 2026 ao abrir 2027

| # | Achado | Onde | Efeito |
| --- | --- | --- | --- |
| 1 | `classrooms` não tem `academic_year_id`. A turma "5A" de 2027 seria a mesma linha da de 2026. | `2025_11_08_113311_create_classrooms_table.php`, `Classroom.php` | Matrículas de dois anos na mesma turma. Grade de disciplinas e professores compartilhados entre anos. |
| 2 | `classroom_subject` (a grade de disciplinas da turma) não tem ano. O boletim lê as disciplinas **atuais** da turma. | `ReportCardService.php:14,27` | Trocar a grade em 2027 muda o boletim de 2026. |
| 3 | `grades.classroom_subject_id` tem `ON DELETE CASCADE`. | `2025_11_28_195146_create_grades_table.php:17` | Remover uma disciplina da turma (ex.: para montar 2027) **apaga as notas de 2026** dessa disciplina. |
| 4 | `classroom_subject_teacher` e `classroom_teacher` não têm ano. | `2026_07_14_000001_...php:20`, `TeacherPanel.php` | Trocar o professor da disciplina em 2027 tira o acesso do professor de 2026 às notas que lançou, e concede ao novo professor as de 2026. |
| 5 | `Classroom::updating` bloqueia a troca de `assessment_mode` se a turma já tem nota, em qualquer ano. | `Classroom.php:27-32` | Uma turma com notas de 2026 não pode mudar de nota para conceito em 2027. |
| 6 | O lançamento de notas lista **todos os bimestres de todos os anos** e só valida que o bimestre existe. | `Gradebook.php:81-86`, `Gradebook.php:237` | Nota de bimestre de 2026 pode ser lançada em matrícula de 2027 (e o inverso). |
| 7 | Gradebook e chamada buscam `classroom_id = X AND status = 'active'` sem filtrar ano. | `Gradebook.php:112`, `TakeAttendance.php:74,106` | Se alunos de 2026 e 2027 estiverem ativos na mesma turma, o professor vê os dois grupos juntos. |
| 8 | Bimestres (`terms`) não têm FK para `academic_years`, nem `company_id`. O vínculo é o inteiro `year`. `grades.term_id` não tem FK. Só existe seeder de 2026. | `2025_11_28_195054_create_terms_table.php`, `Term2026Seeder.php`, `Term.php` | Sem bimestres de 2027 não há como lançar nota. Matrícula sem ano exibe os bimestres de todos os anos no boletim (`ReportCardService.php:16-19`). |
| 9 | Nada muda o status de uma matrícula para `completed` (o valor existe no enum). O boletim e o filtro de matrículas só enxergam `active`. | `EnrollmentStatus.php`, `ReportCards.php:221` | Sem rotina de encerramento, 2026 continua "ativa" em 2027 e o aluno aparece com duas matrículas ativas. Se alguém as encerrar, os boletins de 2026 somem da tela. |
| 10 | Não há unicidade de matrícula por aluno e ano. A lista de anos do formulário filtra por `is_default`, que tem default `true` e não é exclusivo. | `EnrollmentForm.php:26`, `2025_11_27_104957_create_academic_years_table.php:20` | Duas matrículas do mesmo aluno no mesmo ano. Vários anos "padrão" ao mesmo tempo. |
| 11 | `plans` e `classroom_plan` não têm ano. A mensalidade usa o plano vigente no momento da geração. | `2025_12_02_143942_create_plans_table.php`, `InvoiceService.php` | Reajustar o plano para 2027 pode alterar mensalidades ainda não geradas de 2026 (nov/dez). Faturas já criadas guardam o valor. |
| 12 | `minimum_passing_grade` fica em `companies` e é lido na hora (só para colorir a nota). | `Gradebook.php:272`, `2026_07_15_000001_add_assessment_strategy.php` | Mudar a média mínima altera retroativamente a cor/leitura de 2026. Nenhuma regra é gravada junto da nota. |
| 13 | Troca de turma no meio do ano (remanejamento): as notas apontam para `classroom_subject` da turma antiga, mas o boletim lê só a turma atual. | `ReportCardService.php:14,27` | Notas lançadas na turma anterior não aparecem no boletim. Inferência a partir do código; vale testar. |

### 2.2 Regras pedagógicas: o que existe e o que falta

| Tema | Hoje | Lacuna |
| --- | --- | --- |
| Média do bimestre | Média das duas maiores entre N1, N2 e recuperação (`GradeCalculationService`). A recuperação substitui a menor. | Regra fixa no código. Não é configurável por nível/ano nem registrada com a nota. |
| Média anual | Média simples dos bimestres, só quando os 4 existem (`ReportCardService.php:35-39`). | Sem recuperação final, sem peso, sem arredondamento definido, sem resultado. |
| Aprovação | Fora de escopo por decisão anterior (`TEACHER_PANEL_PENDING_IMPLEMENTATION.md`). | Histórico escolar e boletim final exigem resultado (aprovado, reprovado, etc.). |
| Frequência | Percentual = presenças / registros de chamada. | Não há mínimo (limite de faltas), nem cálculo sobre dias/horas letivos previstos, nem distinção por disciplina. |
| Modo de avaliação | Por turma: numérico ou conceito. | Trava depois da primeira nota. Vale por turma, não por ano (item 5 da tabela anterior). |
| Fechamento de bimestre | Não existe. | Qualquer professor altera nota de qualquer bimestre a qualquer momento. Sem trilha de alteração. |
| Educação Infantil | Escala de conceitos A-D. | Não há parecer descritivo (roadmap item 10). |
| Carga horária | `subjects` não tem carga horária. | O histórico escolar precisa dela por disciplina. |
| Sequência de séries | `levels` só tem nome e descrição. | Sem ordem nem "próxima série": não há como propor a rematrícula nem promover alunos. |
| Dados legais da escola | `companies` tem nome, CNPJ, endereço, logo. | Faltam código INEP, ato de autorização, mantenedora, diretor, secretário e assinaturas para o histórico. |

---

## 3. Princípios de projeto

1. **Aditivo, nunca destrutivo.** Toda mudança de esquema acrescenta colunas/tabelas e preenche 2026 a partir do que já existe. Nenhuma linha de 2026 é movida, apagada ou reescrita.
2. **O ano é dono do dado acadêmico.** Turma, bimestres, regras, grade e professores pertencem a um ano letivo.
3. **Fotografia no fechamento.** O que vai para boletim final e histórico é gravado como registro próprio (snapshot) quando o ano fecha. Mudanças posteriores de cadastro não alteram o documento emitido.
4. **Regra gravada com o resultado.** A média mínima, o mínimo de frequência e a fórmula valem pelo ano e ficam registradas.
5. **Reabertura controlada.** Corrigir uma nota fechada é possível, por papel autorizado, com motivo e registro de quem alterou, e gera nova versão do documento.

---

## 4. Modelo de dados proposto

### 4.1 Decisão central: turma por ano

**Recomendação:** adicionar `academic_year_id` à tabela `classrooms`. Cada ano tem suas próprias turmas ("5A 2026" e "5A 2027" são linhas diferentes).

Por que é a opção de menor risco:

- `classroom_subject`, `classroom_subject_teacher`, `classroom_teacher`, `classroom_plan`, `attendances`, `class_diaries` e `lesson_plans` já apontam para `classroom_id`. Todos passam a ser escopados por ano sem alteração de esquema.
- 2026 é preenchido com `academic_year_id = ano 2026` e continua exatamente como está.
- O fluxo "abrir 2027" vira uma cópia da estrutura de 2026 (item 5.2), sem tocar nas linhas de 2026.

Alternativa descartada: manter `classrooms` como cadastro permanente e criar uma tabela de "ofertas de turma por ano". Isola melhor, mas obriga a trocar `classroom_id` por `offering_id` em muitas tabelas e telas, com risco alto em dados vivos.

### 4.2 Mudanças em tabelas existentes

| Tabela | Mudança | Observação |
| --- | --- | --- |
| `academic_years` | `status` (planned, open, closing, closed), `minimum_passing_grade`, `minimum_attendance_percentage`, `planned_school_days`, `company_id`; `is_default` passa a exclusivo (um por empresa) ou é trocado por `is_current`; datas obrigatórias | Preencher 2026 com os valores atuais da empresa. |
| `classrooms` | `academic_year_id` (FK, restrict), nome único por ano, `previous_classroom_id` opcional | Backfill: ano da maioria das matrículas da turma. Revisar turmas sem matrícula à mão. |
| `terms` | `academic_year_id` (FK, restrict), `status`/`closed_at`, único `(academic_year_id, order)`; manter `year` por compatibilidade e depois remover | Backfill por `year`. |
| `grades` | FK `term_id` restrict; trocar `classroom_subject_id` para **restrict** em vez de cascade; `rules_snapshot` (JSON: média mínima e fórmula vigentes) | Trocar o cascade é a correção mais urgente (Fase 0). |
| `enrollments` | Único `(student_id, academic_year_id)` ignorando canceladas; `previous_enrollment_id`; `enrollment_type` (novo, rematrícula, transferência); `result` (ver 4.3); `result_decided_at`, `result_decided_by` | `student_id`, `classroom_id`, `academic_year_id` obrigatórios para novas matrículas. |
| `levels` | `sequence` (ordem), `next_level_id`, `segment` (infantil, fundamental I, fundamental II, médio) | Habilita rematrícula em lote e promoção. |
| `classroom_subject` | `workload_hours`, `sort_order` | Alimenta o histórico. Pode vir de uma matriz curricular por nível (Fase 5). |
| `plans` | `academic_year_id` | Ou criar planos novos para 2027 e desativar os de 2026, sem editar os existentes. |
| `companies` | Dados legais da escola (INEP, ato de autorização, mantenedora, cidade de emissão, nome e cargo de diretor e secretário, imagens de assinatura) | Pode ir para tabela `school_profiles`. |
| `students` | Campos exigidos pelo histórico (nacionalidade, certidão de nascimento, entre outros) | **Conferir a lista exata com a secretaria e a regra do órgão estadual/municipal da escola.** |

### 4.3 Tabelas novas

| Tabela | Finalidade |
| --- | --- |
| `assessment_rules` | Regras por ano e, opcionalmente, por nível: modo, média mínima, mínimo de frequência, política de recuperação, arredondamento. Substitui a leitura direta de `companies.minimum_passing_grade`. |
| `enrollment_subject_results` | Resultado final por matrícula e disciplina, gravado no fechamento: média anual, recuperação final, faltas, carga horária, resultado. |
| `enrollment_results` (ou campos em `enrollments`) | Resultado final do aluno no ano: aprovado, reprovado por nota, reprovado por falta, progressão parcial, transferido, evadido, cursando. |
| `academic_records` e `academic_record_subjects` | Histórico escolar: um registro por aluno e ano com cabeçalho (escola, série, resultado, frequência, dias letivos) e uma linha por disciplina. Gerado do fechamento, com `revision`. |
| `external_academic_records` | Histórico de anos cursados em outra escola (lançado manualmente ou anexado), para compor o histórico completo. |
| `issued_documents` | Cada documento emitido: tipo, número de série, código de validação, hash, quem emitiu, quando, caminho do PDF, versão. Base para validação pública por QR code. |
| `report_card_publications` | Controle de publicação do boletim: matrícula, bimestre (ou final), versão, publicado por/em, ciência do responsável (quando e por quem). |
| `grade_audits` | Trilha de alterações de nota e frequência depois do fechamento: valor anterior, novo, motivo, usuário. |

---

## 5. Features propostas

### 5.1 Fase 0 - Blindagem (antes de existir qualquer matrícula de 2027)

Objetivo: impedir que o trabalho de 2027 corrompa 2026. Pequena, de alto retorno.

- Trocar `ON DELETE CASCADE` de `grades.classroom_subject_id` e `grades.enrollment_id` por `restrict`. Impedir remover disciplina/matrícula com nota.
- Gradebook: listar só os bimestres do ano da turma e rejeitar no servidor nota cujo bimestre não pertença ao ano da matrícula (`Gradebook.php:81-86,237`).
- Gradebook e chamada: filtrar matrículas também por `academic_year_id` da turma/ano corrente enquanto a Fase 1 não chega.
- Impedir duas matrículas ativas do mesmo aluno no mesmo ano (validação e índice único).
- Tornar `is_default` exclusivo.
- Bloquear exclusão de disciplina, professor ou turma que tenha notas, frequência ou diário.
- Cadastrar os 4 bimestres de 2027 (seeder equivalente ao de 2026) com `year = 2027`, ainda sem lançamento.
- Teste de regressão: "boletim de 2026 não muda depois de alterar a grade da turma".

**Status (06/10/2026):** implementada na branch `feat/ano-letivo-2027-fase-0`, ainda sem commit e sem migração aplicada em MySQL.

- Migração `2026_10_06_000001_protect_academic_history_from_cascade.php`: `grades` e `attendances` passam a `RESTRICT`; FK de `grades.term_id` só é criada se não houver notas órfãs.
- Models: bloqueio de exclusão de turma, disciplina da turma, disciplina, professor, matrícula e ano letivo com histórico; matrícula única por aluno e ano (canceladas não contam); `is_default` exclusivo.
- Gradebook: lista só bimestres dos anos dos alunos da turma, só mostra matrículas do ano do bimestre e rejeita no servidor bimestre de outro ano.
- Chamada: só lista matrículas cujo ano letivo cobre a data.
- `Term2027Seeder` com datas **provisórias** (ajustar ao calendário real). Não é chamado automaticamente.
- Testes em `tests/Feature/AcademicYearProtectionTest.php` (suíte completa: 42 passando).
- Antes de aplicar em produção: rodar a consulta de notas com `term_id` órfão e checar matrículas duplicadas já existentes (a regra só vale para novas gravações).

### 5.2 Fase 1 - Estrutura multi-ano e rematrícula

1. **Migrações aditivas** do item 4.2 com backfill de 2026 e comando de verificação (contagem de notas, frequências e matrículas antes e depois; qualquer diferença aborta).
2. **Assistente "Abrir ano letivo 2027"** (Filament, para coordenação/admin):
   - Cria o `academic_year` 2027 em status `planned`, com datas e bimestres.
   - Copia turmas, grade de disciplinas, atribuição de professores, planos e regras de 2026 para 2027, com opção de ajustar antes de confirmar.
   - Modo *dry-run* que lista o que seria criado, sem gravar.
   - Idempotente: rodar duas vezes não duplica.
3. **Rematrícula em lote:**
   - Lista os alunos de 2026 com resultado definido ou a definir, propõe a turma de 2027 pela sequência de séries (`next_level_id`) e permite ajustar aluno a aluno (reprovado repete a série, transferido sai).
   - Cria matrículas de 2027 com status `reserved` (já existe no enum). Elas viram `active` quando o ano abre.
   - Mantém `previous_enrollment_id`, herda responsável, plano e dia de vencimento como sugestão editável.
   - Faturas: gera taxa de matrícula e mensalidades de 2027 pelo plano de 2027. Nunca regera nem altera faturas de 2026.
4. **Troca de ano corrente:** ao mudar o ano ativo, as telas do professor, do portal e os widgets passam a filtrar por ano. Um seletor de ano visível no painel, com "ano corrente" como padrão e acesso somente leitura aos anos fechados.
5. **Remanejamento de turma no ano:** o boletim passa a considerar as disciplinas de todas as turmas pelas quais a matrícula passou, ou usa o snapshot do fechamento (Fase 2).

### 5.3 Fase 2 - Regras e fechamento do ano

1. **Regras configuráveis** (`assessment_rules`): média mínima, frequência mínima, modo, fórmula do bimestre e da média anual, política de recuperação, arredondamento. Gravar `rules_snapshot` em cada nota.
2. **Fechamento de bimestre:** coordenação fecha o bimestre; depois disso o professor não edita. Reabertura exige papel de coordenação, motivo e fica em `grade_audits`.
3. **Pendências antes de fechar:** painel que lista notas faltantes por turma/disciplina/professor e alunos sem frequência registrada no período, para a coordenação cobrar.
4. **Cálculo de resultado:** serviço único que, ao fechar o ano, calcula média anual, aplica recuperação final (se a escola adotar), calcula frequência sobre os dias letivos previstos e **propõe** o resultado de cada aluno.
5. **Decisão humana:** a coordenação confirma ou altera o resultado (conselho de classe), com justificativa. O sistema não aprova nem reprova sozinho sem confirmação. Isso respeita a decisão anterior de não fazer aprovação automática e atende o fluxo real das escolas.
6. **Fechamento do ano:** grava `enrollment_subject_results` e `enrollment_results`, muda matrículas para `completed` e o ano para `closed`. Ano fechado fica somente leitura.
7. **Frequência:** relatório de alunos próximos do limite de faltas durante o ano e bloqueio de lançamento de frequência em data futura ou fora do período do ano.

### 5.4 Fase 3 - Boletim online no portal

Escopo: o responsável vê o boletim no navegador e baixa o PDF, por filho e por ano.

**Experiência**

- No portal, na página do aluno/matrículas, lista por filho e por ano com status do boletim (publicado, parcial, não publicado).
- Visualização em HTML responsiva (já existe a base em `report-cards/partials/card.blade.php`) e botão "Baixar PDF".
- Boletim parcial por bimestre, mais o boletim final com resultado depois do fechamento.
- Indicação de data de publicação, versão e, se houve correção, "versão atualizada em".
- Opcional: botão "Li e estou ciente" gravando data e responsável.

**Regras**

- **Publicação controlada:** o responsável só vê o que a escola publicou (`report_card_publications`). Nota em lançamento não aparece.
- **Posse:** o portal só serve boletim de matrícula cujo aluno pertence ao cliente da sessão. A rota atual `report-cards.*` exige usuário do painel e `Gate` sobre `Student`, então é preciso rota própria do portal, como `portal/matriculas/{enrollment}/boletim` e `.../pdf`, protegida pelo middleware de sessão do portal descrito em S1-04 do plano de segurança.
- **Inadimplência:** recomendação de **não** condicionar o acesso ao boletim à situação financeira. A Lei 9.870/1999 (art. 6º) veda a retenção de documentos escolares por inadimplência. Confirmar o entendimento com o jurídico da escola antes de decidir.
- **Boletim final = snapshot:** depois do fechamento, o PDF é gerado a partir de `enrollment_subject_results`, não de recálculo. Reemissão após correção gera nova versão e mantém a anterior.
- **Auditoria:** registrar visualizações e downloads do responsável.

**Pontos técnicos**

- Geração de PDF hoje usa `spatie/laravel-pdf` 1.x, que depende de um navegador headless (Chrome/Node) no servidor. Confirmar que o ambiente de produção tem isso. A existência da rota `/artisan` sugere um ambiente sem acesso SSH, onde isso pode não estar instalado. Se não houver, avaliar outro motor de PDF antes de prometer PDF no portal.
- Gerar o PDF final uma vez, guardar em disco privado (`issued_documents`) e servir por rota autenticada, em vez de gerar a cada acesso.
- Cache de curta duração para o HTML do boletim parcial.

### 5.5 Fase 4 - Histórico escolar

1. **Histórico por aluno**, gerado dos fechamentos (`academic_records`), com todos os anos cursados na escola, mais anos de outras escolas (`external_academic_records`).
2. **Conteúdo mínimo sugerido:** identificação do aluno e filiação, naturalidade, documento, escola e dados legais, para cada ano: série, resultado, carga horária e notas por disciplina, dias letivos, frequência, observações. **Os campos obrigatórios variam por estado e município; validar o modelo com a secretaria da escola e com o órgão fiscalizador.**
3. **Emissão numerada:** número de série, data, assinatura (imagens do diretor e do secretário em `school_profiles`), QR code e página pública de validação (`/validar/{codigo}`) que confirma autenticidade sem expor dados sensíveis.
4. **Entrada no painel:** ação "Emitir histórico" na ficha do aluno, para coordenação e secretaria. No portal, solicitação opcional com aprovação da secretaria (não emissão automática).
5. **Importação inicial:** como o sistema só tem 2026 estruturado, anos anteriores entram como lançamento manual ou anexo digitalizado em `external_academic_records`, e o histórico indica a origem.
6. **Correção:** alteração gera nova revisão do registro, mantém a anterior e invalida a validação pública da antiga com aviso.

### 5.6 Fase 5 - Complementos (depois)

- Parecer descritivo para Educação Infantil.
- Matriz curricular por nível e ano, alimentando a grade ao abrir o ano.
- Ata de resultado final, mapa de notas, relatórios de frequência e de desempenho (itens do `ROADMAP_DOCUMENTOS_PEDAGOGICOS.md`).
- Notificação ao responsável quando o boletim é publicado.

---

## 6. Como proteger 2026 durante a virada

1. **Backup e ensaio:** restaurar uma cópia do banco de produção em ambiente de teste e rodar nele migrações, backfill e o assistente de abertura de ano. Só repetir em produção depois de conferir.
2. **Migrações aditivas e reversíveis:** só colunas e tabelas novas; backfill em comando próprio, idempotente, com *dry-run*.
3. **Verificação automática:** comando que compara, antes e depois, contagem e soma de verificação de `grades`, `attendances` e `enrollments` de 2026. Falhou, aborta e restaura.
4. **Chave de funcionalidade (feature flag):** ligar o filtro por ano nas telas do professor e do portal só depois de o backfill ser validado.
5. **Janela de mudança:** fazer a virada fora do horário de lançamento de notas e chamada.
6. **Teste de regressão permanente:** boletim de 2026 de um aluno de amostra precisa ser igual antes e depois de cada etapa.
7. **O que não fazer:** editar turma/grade/plano de 2026 para "virar" 2027; remover disciplina que tenha nota; reutilizar bimestres de 2026; mudar a média mínima da empresa com 2026 aberto sem a Fase 2.

---

## 7. Plano de entrega (estimativa para uma pessoa)

| Fase | Entrega | Estimativa | Pré-requisito |
| --- | --- | --- | --- |
| 0 | Blindagem | 4 d | Nenhum. **Obrigatória antes de qualquer matrícula 2027.** |
| 1 | Estrutura multi-ano, abertura de ano, rematrícula | 9 d | Fase 0 |
| 2 | Regras, fechamento de bimestre e de ano, resultado | 9 d | Fase 1 e decisões da seção 8 |
| 3 | Boletim no portal (HTML + PDF, publicação) | 7 d | Fase 2 para o boletim final; o parcial por bimestre pode sair antes. Segurança do portal (S1-04/S1-05). |
| 4 | Histórico escolar com emissão e validação | 9 d | Fase 2 e dados legais da escola |
| 5 | Complementos | a definir | Demanda |

Total das Fases 0 a 4: cerca de 38 dias de trabalho. As Fases 0 e 1 são o mínimo para receber matrículas de 2027 com segurança. A Fase 2 deve estar pronta antes de fechar 2026 (dezembro). As Fases 3 e 4 podem seguir em janeiro/fevereiro de 2027, com o boletim parcial do 1º bimestre como primeira entrega ao portal.

Sugestão de ordem com o plano de segurança: Sprint 1 (segurança), depois Fase 0 e Fase 1, depois Fase 2, depois Fase 3 e 4. A Fase 0 é pequena e pode entrar ainda na Sprint 2.

---

## 8. Decisões necessárias (coordenação pedagógica e direção)

1. **Datas:** quando começam as matrículas/reservas de 2027? Isso define o prazo da Fase 0 e da Fase 1.
2. **Média mínima e frequência mínima:** 6,0 e 75% valem para todos os níveis? A escola usa recuperação final? Como arredondar?
3. **Níveis de ensino atendidos:** Educação Infantil (parecer descritivo, sem retenção), Fundamental I e II, Médio? Cada segmento muda a regra de resultado.
4. **Resultado:** a coordenação confirma o resultado de cada aluno (recomendado) ou o sistema aplica a regra direto?
5. **Publicação do boletim:** quem publica, por bimestre ou só o final, e o responsável precisa registrar ciência?
6. **Inadimplência e boletim:** confirmar com o jurídico a posição de não bloquear (item 5.4).
7. **Dados legais e modelo do histórico:** quais campos o órgão do estado/município exige, e quem assina?
8. **Histórico anterior:** os anos anteriores a 2026 existem em papel/planilha? Será feita importação ou anexo?
9. **Planos 2027:** haverá reajuste? Prefere planos novos por ano ou `academic_year_id` nos planos atuais?
10. **Ambiente de produção:** existe Chrome/Node no servidor para PDF, ou será necessário trocar o motor de PDF?

---

## 9. Testes a escrever

- Boletim de 2026 inalterado depois de: alterar grade, trocar professor, criar turma de 2027, mudar média mínima.
- Impossível excluir disciplina, matrícula, turma ou professor com notas ou frequência.
- Nota de bimestre de um ano rejeitada em matrícula de outro ano.
- Professor vê apenas alunos e disciplinas do ano corrente; vê os de 2026 só em modo leitura.
- Abertura de ano idempotente (rodar duas vezes não duplica) e com *dry-run* sem gravação.
- Rematrícula: aluno reprovado repete a série; transferido não gera matrícula; matrícula de 2027 nasce `reserved`.
- Fechamento: resultado calculado conforme a regra do ano; ano fechado recusa alteração; reabertura exige papel e motivo e gera auditoria.
- Portal: responsável acessa só o boletim publicado dos próprios filhos; outra família recebe 403/404; boletim não publicado não aparece; PDF e HTML consistentes.
- Histórico: número de série único, validação pública correta, revisão invalida a anterior.

---

## 10. Itens fora desta proposta

- Notas fiscais e contabilidade de mensalidades.
- Integração com sistemas oficiais de censo escolar.
- Aplicativo móvel.
