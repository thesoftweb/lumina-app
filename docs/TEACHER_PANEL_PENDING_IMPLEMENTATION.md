# Painel do Professor - Etapas de Implementação

## Objetivo

Entregar ao professor uma área isolada para consultar suas turmas e alunos, acompanhar aniversariantes, registrar diário e frequência, lançar notas por bimestre e gerar boletins.

## Status geral

| Etapa | Entrega | Status |
| --- | --- | --- |
| 0 | Branch e baseline | Concluída |
| 1 | Segurança e autorização | Concluída |
| 2 | Ano letivo e relações acadêmicas | Concluída |
| 3 | Professor x turma x disciplina | Concluída |
| 4 | Minhas turmas e meus alunos | Concluída |
| 5 | Aniversariantes | Concluída |
| 6 | Diário de classe | Concluída |
| 7 | Frequência em lote | Concluída |
| 8 | Notas por bimestre | Concluída |
| 9 | Visualização e PDF do boletim | Concluída |
| 10 | Dashboard e perfil | Concluída |
| 11 | Seeders e testes | Concluída |

## Entregas e critérios de aceite

### Segurança

- O painel exige role autorizada e vínculo User -> Teacher para professores.
- O Filament usa autorização estrita e policies para recursos administrativos.
- Listagens pedagógicas são filtradas pelo professor.
- O professor não altera dados cadastrais dos alunos.

### Estrutura acadêmica

- Matrículas usam academic_year_id e relação belongsTo.
- classroom_subject_teacher registra as disciplinas de cada professor por turma.
- Notas são únicas por matrícula, disciplina e bimestre.
- Frequências são únicas por matrícula, turma e data.

### Operação docente

- **Minhas turmas:** somente turmas atribuídas.
- **Alunos:** nome, nascimento, Filiação 1/2, turma e observações em modo leitura.
- **Aniversariantes:** widget e consulta por mês.
- **Diário:** turma e disciplina validadas contra as atribuições.
- **Frequência:** chamada em lote com justificativa e correção.
- **Notas:** lançamento em lote por disciplina e bimestre, de 0 a 10.
- **Boletim:** notas, faltas e percentual de frequência, sem aprovação automática.
- **Perfil:** edição somente dos dados pessoais do professor.

### Validação

- Executar php artisan migrate.
- Executar php artisan db:seed --class=RolePermissionSeeder.
- Atribuir turma e disciplinas ao professor antes do primeiro acesso.
- Executar php artisan test e npm run build antes do merge.

## Fora do escopo

- Média final, aprovação ou reprovação automática.
- Recuperação, conselho de classe e histórico escolar.
- Professor editar cadastro ou dados financeiros dos alunos.
- Inferir mãe ou pai nos campos genéricos de filiação.
