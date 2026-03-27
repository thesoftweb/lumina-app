# Feature: Agenda do Aluno

## Descrição
Permitir que o administrador cadastre eventos/agendas e que eles apareçam no portal do aluno.

## Regras

- Agenda pode ser:
  - Para todas as turmas
  - Para turmas específicas

- Campos:
  - title
  - description
  - date
  - classroom_id (nullable)
  - global (boolean)

## Comportamento

- Se global = true → aparece para todos alunos
- Se turma_id definido → aparece apenas para alunos da turma

## Origem dos dados

- Cadastro via painel admin (Filament)
- Consumo via portal do aluno

## Requisitos

- CRUD completo no admin
- Filtro por turma automaticamente
