# 📋 Roadmap de Documentos Pedagógicos - Lumina App

**Status:** Em Desenvolvimento  
**Última Atualização:** 14 de Janeiro de 2026  
**Modelos Base Disponíveis:** Attendance, ClassDiary, LessonPlan, Grade, Enrollment, Classroom, Subject, Term

---

## 🎯 Documentos Prioritários (Implementar Primeiro)

### Tier 1 - Essenciais (Alto Impacto + Regulamentação)

-   [ ] **1. Plano de Aula Digital (Assinado)**

    -   Turma, disciplina, data, objetivos, conteúdo, metodologia
    -   Frequência de alunos naquela aula
    -   Assinatura digital + timestamp do professor
    -   Download em PDF com QR code de validação
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔴 CRÍTICA
    -   Modelos utilizados: `LessonPlan`, `ClassDiary`, `Attendance`, `Teacher`

-   [ ] **2. Carnê Escolar / Boletim Individual**

    -   Dados pessoais, matrícula, foto do aluno
    -   Notas por disciplina/termo com cores (aprovado/reprovado)
    -   Frequência (%) por período
    -   Comportamento e observações do professor
    -   Resultado final (aprovado/reprovado/recuperação)
    -   Download individual ou em lote (ZIP)
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔴 CRÍTICA
    -   Modelos utilizados: `Student`, `Grade`, `Enrollment`, `Attendance`, `ClassDiary`

-   [ ] **3. Ata de Frequência (Matriz de Presença)**
    -   Presença de cada aluno em cada aula
    -   Matriz: alunos (linhas) x datas (colunas)
    -   Totalizações por aluno
    -   Assinado pelo professor
    -   Filtrável por turma/período/disciplina
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔴 CRÍTICA
    -   Modelos utilizados: `Attendance`, `Enrollment`, `Classroom`, `Teacher`

---

### Tier 2 - Muito Importante (Gestão Pedagógica)

-   [ ] **4. Diário de Classe Consolidado**

    -   Relatório mensal/trimestral de todas as aulas
    -   Conteúdo ministrado por data (de ClassDiary)
    -   Atividades e tarefas propostas
    -   Observações sobre aprendizado da turma
    -   Assinado pelo professor com data
    -   Filtrável por turma/período
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟠 ALTA
    -   Modelos utilizados: `ClassDiary`, `Teacher`, `Classroom`, `Subject`

-   [ ] **5. Mapa de Notas (Boletim Consolidado da Turma)**

    -   Todas as notas de todos os alunos
    -   Por disciplina/termo
    -   Com aprovados/reprovados destacados (cores)
    -   Média geral por disciplina
    -   Totalizações e estatísticas
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟠 ALTA
    -   Modelos utilizados: `Grade`, `Student`, `Enrollment`, `Subject`, `Term`

-   [ ] **6. Ata de Resultado Final**

    -   Resultado aprovação/reprovado de cada aluno
    -   Notas finais por disciplina
    -   Assinado por professores, diretor, coordenador
    -   Documento oficial com número de série
    -   Período/ano letivo
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟠 ALTA
    -   Modelos utilizados: `Grade`, `Student`, `Enrollment`, `AcademicYear`

-   [ ] **7. Relatório de Desempenho da Turma**
    -   Média geral por disciplina
    -   Taxa de frequência da turma
    -   Alunos com baixo desempenho (< 6.0)
    -   Conteúdos que precisam reforço
    -   Sugestões pedagógicas por disciplina
    -   Gráficos comparativos
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟠 ALTA
    -   Modelos utilizados: `Grade`, `Attendance`, `ClassDiary`, `Classroom`

---

### Tier 3 - Importante (Intervenção Pedagógica)

-   [ ] **8. Plano de Recuperação**

    -   Alunos em risco (notas < 6.0)
    -   Conteúdos não dominados (de ClassDiary)
    -   Estratégias de recuperação
    -   Cronograma de aulas extras
    -   Responsável pela recuperação
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟡 MÉDIA
    -   Modelos utilizados: `Grade`, `Student`, `LessonPlan`, `ClassDiary`

-   [ ] **9. Relatório de Desenvolvimento por Aluno**

    -   Progresso em cada disciplina (comparativo termo a termo)
    -   Comportamento evolutivo
    -   Pontos fortes e áreas de melhoria
    -   Recomendações para próximo período
    -   Assinado pelo professor
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟡 MÉDIA
    -   Modelos utilizados: `Grade`, `ClassDiary`, `Attendance`, `Student`

-   [ ] **10. Parecer Descritivo**

    -   Análise qualitativa do aluno
    -   Comportamento, socialização, aprendizado
    -   Comentários de cada professor
    -   Recomendações para família
    -   Observações especiais
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟡 MÉDIA
    -   Modelos utilizados: `Student`, `ClassDiary`, `Enrollment`

-   [ ] **11. Lista de Chamada (Gerada Dinamicamente)**
    -   Turma, data, hora, disciplina, professor
    -   Lista com espaço para marcação
    -   Código QR para validação
    -   Opção de marcar presença digitalmente
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🟡 MÉDIA
    -   Modelos utilizados: `Classroom`, `Attendance`, `Teacher`, `Subject`

---

### Tier 4 - Complementar (Documentação Legal)

-   [ ] **12. Histórico Escolar**

    -   Todas as disciplinas cursadas (multi-ano)
    -   Notas obtidas por ano/termo
    -   Faltas totais por período
    -   Observações pedagógicas acumuladas
    -   Certificado de conclusão de série
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Student`, `Grade`, `Attendance`, `Enrollment`, `AcademicYear`

-   [ ] **13. Certidão de Matrícula**

    -   Comprovante legal de matrícula
    -   Série, turma, ano letivo
    -   Data de emissão, data de nascimento
    -   Assinado por diretor/secretário
    -   Número de série do documento
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Enrollment`, `Student`, `Classroom`, `AcademicYear`

-   [ ] **14. Comprovante de Frequência**

    -   Percentual de presença
    -   Datas de faltas justificadas
    -   Total de aulas ministradas vs. comparecidas
    -   Período específico
    -   Assinado pelo professor
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Attendance`, `Classroom`, `Student`

-   [ ] **15. Relatório de Frequência Geral (Gestão)**

    -   Taxa de presença por turma
    -   Alunos com muitas faltas (risco de abandono)
    -   Comparativo com períodos anteriores
    -   Tendências (aumentando/diminuindo)
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Attendance`, `Classroom`, `Student`

-   [ ] **16. Ficha de Acompanhamento Pedagógico**

    -   Histórico de intervenções pedagógicas
    -   Reuniões com responsáveis (data, pauta, resultado)
    -   Evolução após intervenções
    -   Próximas ações recomendadas
    -   Assinado por professor/coordenador
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Student`, `ClassDiary`, `Grade`

-   [ ] **17. Certificado de Participação**

    -   Em projetos ou disciplinas extras
    -   Carga horária
    -   Data de conclusão
    -   Assinado pelo professor/diretor
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Student`, `Teacher`

-   [ ] **18. Diploma/Certificado de Conclusão**

    -   Final de série/ano letivo
    -   Dados do aluno, série cursada
    -   Média final
    -   Assinado por diretor e secretário
    -   Número de série com data
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Student`, `Enrollment`, `AcademicYear`

-   [ ] **19. Relatório de Conteúdo Ministrado**

    -   Cronograma de conteúdos planejados (de LessonPlan)
    -   O que foi ensinado x o que estava planejado
    -   Justificativas de atrasos/adiantamentos
    -   Próximos conteúdos a ministrar
    -   Filtrável por turma/disciplina/período
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `LessonPlan`, `ClassDiary`, `Classroom`, `Subject`

-   [ ] **20. Atestado de Frequência (Fins Legais)**

    -   Comprovante que aluno estava presente
    -   Em período específico
    -   Para justificar ausências em outros contextos
    -   Assinado pela secretaria
    -   Número de série
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Attendance`, `Student`, `Enrollment`

-   [ ] **21. Relatório de Adaptação (Alunos Novos)**
    -   Primeiras semanas de observação
    -   Comportamento e aceitação na turma
    -   Desempenho inicial
    -   Necessidades de apoio
    -   Recomendações
    -   Status: ⏳ Não iniciado
    -   Prioridade: 🔵 BAIXA
    -   Modelos utilizados: `Student`, `Enrollment`, `ClassDiary`

---

## 📊 Resumo de Status

| Tier                 | Documentos | Completos | Em Andamento | Não Iniciados |
| -------------------- | ---------- | --------- | ------------ | ------------- |
| 1 - Essenciais       | 3          | 0         | 0            | 3             |
| 2 - Muito Importante | 3          | 0         | 0            | 3             |
| 3 - Importante       | 3          | 0         | 0            | 3             |
| 4 - Complementar     | 12         | 0         | 0            | 12            |
| **TOTAL**            | **21**     | **0**     | **0**        | **21**        |

---

## 🛠️ Estrutura Técnica Necessária

### Services a Criar

-   `PdfGenerationService` - Base para geração de PDFs
-   `DocumentSignatureService` - Assinaturas digitais e QR codes
-   `ReportService` - Consolidação de dados para relatórios

### Controllers/Actions Filament

-   `DocumentGenerationAction` - Ação genérica para gerar docs
-   `BulkDocumentDownload` - Baixar múltiplos docs em ZIP

### Modelos Base (Já Existem)

-   ✅ `Attendance` - Frequência
-   ✅ `ClassDiary` - Diário de Classe
-   ✅ `LessonPlan` - Plano de Aula
-   ✅ `Grade` - Notas
-   ✅ `Student` - Alunos
-   ✅ `Teacher` - Professores
-   ✅ `Classroom` - Turmas
-   ✅ `Subject` - Disciplinas
-   ✅ `Term` - Trimestres/Bimestres
-   ✅ `AcademicYear` - Ano Letivo
-   ✅ `Enrollment` - Matrículas

### Templates Necessários

```
resources/views/documents/
├── pdf/
│   ├── lesson-plan.blade.php
│   ├── school-report.blade.php
│   ├── attendance-sheet.blade.php
│   ├── class-diary.blade.php
│   ├── grades-map.blade.php
│   └── ... (mais templates)
└── email/
    └── document-generated.blade.php
```

---

## 📝 Instruções de Uso

### Para Implementar um Documento:

1. **Marcar como "Em Andamento"**

    ```
    - [ ] **N. Nome do Documento** ➜ - [ ] **N. Nome do Documento** (Em Andamento ⚙️)
    ```

2. **Criar arquivo de implementação**

    ```
    - Service: `app/Services/Document{Name}Service.php`
    - Template: `resources/views/documents/pdf/{name}.blade.php`
    - Action: `app/Filament/Actions/Generate{Name}Action.php`
    ```

3. **Após conclusão, marcar como feito**

    ```
    - [ ] **N. Nome do Documento** ➜ - [x] **N. Nome do Documento** ✅
    ```

4. **Atualizar tabela de status** - incrementar coluna "Completos"

---

## 🚀 Como Começar?

**Recomendação:** Comece pelo **Tier 1** (Essenciais)

1. **Plano de Aula Digital** - Mais simples, menos dados relacionados
2. **Carnê Escolar** - Usa dados já consolidados
3. **Ata de Frequência** - Formato tabular, mais direto

Depois avance para **Tier 2** conforme necessidade.

---

**Última Atualização:** 14/01/2026  
**Mantido por:** Equipe Lumina App Development
