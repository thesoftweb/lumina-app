# Pendências

Questões em aberto: decisões da escola e débitos técnicos conhecidos. Ao resolver uma, registre a
decisão e a data e mova para "Resolvidas".

## Em aberto

### 1. Multa e juros por atraso acima do praticado

**Situação atual (23/10/2026):** o Asaas está configurado com **2% de multa** e **6% ao mês de juros**.
O Lumina usa os mesmos valores para estimar o valor atualizado na tela de Inadimplência e no
resumo da fatura (Empresa › Cobrança).

**Problema:** a multa de 2% está dentro do usual, mas 6% ao mês de juros é muito acima do que
instituições de ensino praticam. Também passa do limite que a lei admite para a cobrança de
mensalidades de pessoas físicas:

- **Juros:** a cobrança entre a escola e a família é uma relação de consumo entre partes que não
  são instituição financeira. Nela vale o limite da Lei de Usura (Decreto 22.626/1933), mantido pela
  Lei 14.905/2024 para esse tipo de obrigação: **12% ao ano, ou seja, 1% ao mês**. Juros acima disso
  podem ser declarados abusivos e reduzidos na Justiça ou no Procon. Isso pode levar à devolução do
  que foi cobrado a mais (em dobro, pelo art. 42 do CDC, se houver má-fé).
- **Multa:** o padrão é **2%**, por analogia ao art. 52, §1º, do CDC. Esse é o percentual aceito
  pelos tribunais para mensalidade escolar.
- **Contrato:** o contrato de matrícula atual não tem cláusula de multa e juros. Sem previsão em
  contrato, a cobrança de encargos fica mais frágil mesmo com percentuais baixos.

**Prática em instituições de ensino:**

| Encargo | Percentual usual | Como é cobrado |
| --- | --- | --- |
| Multa | 2% | Uma vez, sobre o valor da parcela |
| Juros de mora | 1% ao mês | Proporcional por dia de atraso (cerca de 0,033% ao dia) |
| Correção monetária (opcional) | IPCA ou IGP-M | Só em atrasos longos; o Asaas não aplica sozinho |
| Desconto de pontualidade | Perdido após o vencimento | Já é o comportamento atual |

**Sugestão:** adotar **2% de multa + 1% ao mês de juros** e incluir a cláusula no contrato de
matrícula.

**Passos para resolver:**

- [ ] Validar os percentuais com o jurídico ou o contador da escola.
- [ ] Alterar os juros no painel do Asaas (Minha conta › Configurações › Juros e multa).
- [ ] Confirmar no Asaas se cobranças já emitidas mantêm os 6%. Elas podem ter ficado com a regra da
      data em que foram criadas; se for o caso, decidir se essas cobranças serão ajustadas.
- [ ] Atualizar os mesmos valores no Lumina, em Empresa › Cobrança, para as estimativas baterem.
- [ ] Incluir no contrato de matrícula a cláusula de encargos por atraso: multa, juros e perda do
      desconto de pontualidade.
- [ ] Revisar, se houver, comunicados e mensagens que citem os percentuais antigos. Isso inclui o
      modelo da mensagem de cobrança, se tiver sido personalizado.

> Este item é um levantamento técnico e não substitui a orientação jurídica da escola.

### 2. Débito técnico: acesso ao portal do aluno só com o CPF

**Situação atual (23/10/2026):** o portal (`/portal/aluno`) entra apenas com o CPF do responsável,
sem senha, sem um segundo dado e sem limite de tentativas. Quem souber o CPF de um responsável vê os
dados da família: alunos, matrículas, faturas e PIX. Com boletim e histórico no portal, também passa
a ver notas e frequência de menores.

**Risco:** exposição de dados pessoais de crianças e adolescentes (LGPD, art. 14) e tentativa de
CPFs em sequência, já que não há limite de tentativas.

**Decisão (23/10/2026):** manter como está por enquanto e tratar depois.

**Opções quando for tratar:**

- CPF + data de nascimento de um dos alunos, com limite de tentativas. É a mais simples e não exige
  cadastro.
- Código de acesso enviado por WhatsApp ou e-mail. Depende de envio automático.
- Senha do responsável criada no primeiro acesso, com recuperação por e-mail.

O mínimo imediato, independente da opção, é limitar as tentativas de acesso (`throttle` na rota
`portal.access`).

## Resolvidas

_Nenhuma ainda._
