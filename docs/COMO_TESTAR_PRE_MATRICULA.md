# Como testar - Pré-matrícula, rematrícula e home

Guia de teste manual da branch `feat/pre-matricula`. As regras estão em `docs/PRE_MATRICULA.md`, e o que
já foi testado e corrigido está em `docs/RELATORIO_TESTES_PRE_MATRICULA.md`. Anote no relatório cada falha
encontrada.

## 1. Preparar o ambiente

```bash
git checkout feat/pre-matricula
composer install
php artisan migrate          # migrações 2026_10_27_000001 a 000005
php artisan optimize:clear
php artisan test             # esperado: todos passando (359 em 27/10/2026)
```

- **As migrações só acrescentam** colunas e tabelas, mas a `000004` muda dados: liga a 2026 as turmas sem
  ano letivo que só têm matrículas de 2026. Em produção, faça backup antes.
- **Cobrança no Asaas:** a ação "Gerar Fatura de Matrícula" cria a cobrança no Asaas de verdade. Use uma
  família de teste ou o ambiente sandbox.
- **Celular:** use o modo responsivo do navegador (F12 › ícone de celular, largura de 360px) ou abra o site
  num celular na mesma rede.

## 2. Conferir a base

| # | Onde | O que fazer | Resultado esperado |
| --- | --- | --- | --- |
| B1 | Anos letivos | Abrir 2026 e 2027 | Só 2026 está como "Ano letivo atual (padrão)". |
| B2 | Turmas | Filtrar pelo ano letivo | 2026: as turmas antigas. 2027: as turmas novas, sem BALLET. |
| B3 | Planos | Criar um plano com desconto "Nenhum" | Salva sem erro, e o valor final é igual ao valor base. |
| B4 | Planos | Criar um plano com 10% de desconto | Mostra "Valor com desconto (quando negociado)". 120% ou desconto maior que o valor base são recusados. |

## 3. Montar a pré-matrícula de um ano

| # | Onde | O que fazer | Resultado esperado |
| --- | --- | --- | --- |
| A1 | Anos letivos › 2027 › "Copiar turmas de outro ano" | Origem 2026, marcar as turmas | Cria as turmas em 2027 com as disciplinas e o plano de 2027 de mesmo nome. Rodar de novo não duplica. |
| A2 | Anos letivos › 2027 › editar | Ligar "Receber pré-matrículas" e informar o período e o texto da ficha | Aparece o "Link público da ficha" com botão de copiar. |
| A3 | Aba "Vagas e valores" › "Criar vagas a partir das turmas" | Confirmar | Uma vaga por turma, com plano e mensalidade, sem quantidade de vagas. Rodar de novo não duplica. |
| A4 | Vagas e valores › editar uma vaga | Informar "Quantidade de vagas" (ex.: 2) | A coluna "Fichas / vagas" mostra 0 / 2. |
| A5 | Vagas e valores › "Nova vaga" | Escolher uma turma | Nome, nível, série e plano são preenchidos sozinhos. Só aparecem turmas e planos de 2027. |

## 4. Ficha pública (novato)

| # | O que fazer | Resultado esperado |
| --- | --- | --- |
| P1 | Abrir `/pre-matricula` | Leva direto para `/pre-matricula/2027`. |
| P2 | Ver o bloco "Turmas e valores 2027" | Cartões por turma: Educação Infantil primeiro, mensalidade do plano, taxa (se houver). **Nenhuma menção a desconto.** |
| P3 | Turma com 2 vagas (A4) | Mostra "Últimas vagas". Esgotada, mostra "Lista de espera", mas continua dando para escolher. |
| P4 | Enviar sem escolher a turma, com CPF inválido ou sem aceitar o LGPD | Lista de erros no topo e mensagem em cada campo. As respostas continuam preenchidas. |
| P5 | Enviar uma ficha válida | Página de protocolo (ex.: `2027-K7QH3M`) com botão de copiar. O protocolo não aparece na URL. |
| P6 | Enviar de novo a mesma criança, com o mesmo CPF | Não duplica: a ficha aberta é atualizada. |
| P7 | Abrir `/pre-matricula/2026` (ano fechado) | Página de inscrições fechadas, sem o formulário. |
| P8 | Abrir o link com `?utm_source=instagram` e enviar | A origem aparece na ficha, no painel. |

## 5. Painel - Acadêmico › Pré-matrículas

| # | O que fazer | Resultado esperado |
| --- | --- | --- |
| L1 | Abrir a lista | Abre filtrada em 2027, sem fichas de 2026. O menu mostra quantas fichas novas há. |
| L2 | Abrir a ficha de P5 | Tipo "Novato", turma escolhida, idade no início do ano, origem e vínculos (cliente ou aluno, se já existirem). |
| L3 | "WhatsApp" | Abre o WhatsApp com mensagem pronta. A ficha passa para "Em contato" e o contato vai para o histórico. |
| L4 | "Registrar contato" e "Marcar desistência" | O histórico registra o contato. Desistência exige o motivo. |
| L5 | Registrar uma ficha pelo painel (alguém ligou) | O ano é obrigatório. Só aparecem vagas daquele ano. O tipo é calculado sozinho. |

## 6. Conversão em matrícula

| # | O que fazer | Resultado esperado |
| --- | --- | --- |
| C1 | "Converter em matrícula" numa ficha de **novato** | Turma e plano já vêm marcados. Mostra a mensalidade. **Não aparece a seção "Negociação".** |
| C2 | Confirmar | Abre a matrícula: ano 2027, "Reservada", "Primeira matrícula" marcada. Responsável e aluno são criados, ou reaproveitados pelo CPF. |
| C3 | Converter uma ficha de 2027 escolhendo turma de 2026 | Impossível: só aparecem turmas de 2027. |
| C4 | Converter ficha de aluno já matriculado em 2027 | Erro "Este aluno já possui uma matrícula não cancelada neste ano letivo", e a ficha continua aberta. |
| C5 | Matrícula do novato › "Gerar Fatura de Matrícula" (cuidado: Asaas) | Valor da taxa da vaga, travado. |

## 7. Rematrícula

| # | O que fazer | Resultado esperado |
| --- | --- | --- |
| R1 | Pré-matrículas › "Gerar rematrículas" (2026 → 2027) | Mostra quantos alunos recebem ficha e lista os que estão sem CPF do responsável. Cria fichas "Rematrícula". Rodar de novo não duplica. |
| R2 | Numa ficha de rematrícula › "Enviar link de rematrícula" ou "Copiar link" | WhatsApp com o link individual. O contato fica registrado. |
| R3 | Abrir o link numa aba anônima | Mostra o nome da criança e a turma atual. O CPF aparece mascarado (`***.234.214-**`). Não mostra dados financeiros. |
| R4 | Confirmar a turma e atualizar o telefone | Página de confirmação. No painel, a ficha mostra "Confirmou pelo link em …". |
| R5 | Converter a rematrícula | A seção "Negociação" aparece com "Sem desconto", "Desconto do plano (x%)" (só se o plano tiver desconto) e "Desconto negociado" (valor ou %, motivo obrigatório), com prévia da mensalidade. |
| R6 | Confirmar com "Desconto do plano" | Matrícula com "Aplicar o desconto do plano" marcado. A mensalidade mostra o desconto "(do plano)". |
| R7 | Abrir o link de R3 depois da conversão | "O link não está mais ativo", sem dados. |
| R8 | Ficha pública preenchida com o CPF do responsável, o nome e a data de nascimento de um aluno atual | A ficha entra como "Rematrícula", ligada à matrícula de 2026. |

## 8. Regras de desconto na matrícula comum

| # | O que fazer | Resultado esperado |
| --- | --- | --- |
| D1 | Matrículas › Nova: escolher um aluno sem matrícula anterior e o ano 2027 | "Primeira matrícula" é marcada sozinha. As opções de desconto somem. |
| D2 | Escolher um aluno com matrícula em 2026 | Aparecem "Usar desconto negociado" e, se o plano tiver desconto, "Aplicar o desconto do plano". Marcar uma opção desmarca a outra. |
| D3 | Gerar mensalidades de uma matrícula sem desconto escolhido, num plano com desconto | Cobra o valor base. O desconto do plano não vale sozinho. |

## 9. Home no celular (`/`, largura de 360px)

| # | O que fazer | Resultado esperado |
| --- | --- | --- |
| H1 | Rolar a página | Sem rolagem horizontal. Barra fixa embaixo com "Contato" e "Pré-matrícula". |
| H2 | Menu (ícone ☰) | Abre e fecha. Fecha com Esc, ao tocar num link e ao tocar fora. |
| H3 | "Pré-matrícula" (menu, barra e botões) | Abre `/pre-matricula`. |
| H4 | Teclado: Tab desde o início | Primeiro aparece "Pular para o conteúdo". O foco fica visível em todos os links. |
| H5 | Formulário de contato | Tocar num campo não dá zoom no iPhone. Teclado numérico no telefone. |

## 10. Antes de levar para produção

- [ ] Backup do banco e `php artisan migrate`.
- [ ] Conferir se só o ano letivo atual está como padrão (falha 2 do relatório).
- [ ] Conferir se alguma turma ficou sem ano depois da migração `000004` (turmas com matrícula de outro ano
      ficam para revisão).
- [ ] Criar os planos de 2027. Copiar as turmas e criar as vagas pelas telas (seção 3).
- [ ] Revisar o texto da ficha e o período da pré-matrícula. Só então divulgar o link.
