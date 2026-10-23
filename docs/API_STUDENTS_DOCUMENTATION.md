# API de Alunos - Documentação

## 📋 Estrutura

```
app/Http/Controllers/Api/
└── StudentController.php    # Controller com os endpoints

routes/
└── api.php                  # Definição das rotas da API
```

## 🚀 Endpoints

### 1. Listar Todos os Alunos
```http
GET /api/students
```

**Resposta (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "1",
      "nome": "Pedro Gael",
      "serie": "5A"
    },
    {
      "id": "2",
      "nome": "Maria Santos",
      "serie": "5A"
    },
    {
      "id": "3",
      "nome": "Pedro Oliveira",
      "serie": "5A"
    }
  ]
}
```

### 2. Buscar Aluno por ID
```http
GET /api/students/{studentId}
```

**Exemplo:**
```http
GET /api/students/1
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "id": "1",
    "nome": "Pedro Gael",
    "serie": "5A"
  }
}
```

**Resposta (404):**
```json
{
  "success": false,
  "message": "Aluno não encontrado"
}
```

### 3. Listar Alunos por Turma
```http
GET /api/students/classroom/{classroomId}
```

**Exemplo:**
```http
GET /api/students/classroom/1
```

**Resposta (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "1",
      "nome": "Pedro Gael",
      "serie": "5A"
    },
    {
      "id": "2",
      "nome": "Maria Santos",
      "serie": "5A"
    }
  ]
}
```

## ✅ Características

- ✔️ **Sem Autenticação** - Endpoints públicos acessíveis para qualquer pessoa
- ✔️ **Bem Organizado** - Estrutura em pastas separadas por funcionalidade
- ✔️ **Tratamento de Erros** - Respostas padronizadas com códigos HTTP apropriados
- ✔️ **Formato Consistente** - Todos os endpoints retornam `{ success, data }`

## 🔧 Como Usar

### cURL
```bash
# Listar todos
curl http://seu-dominio/api/students

# Buscar um aluno
curl http://seu-dominio/api/students/1

# Alunos por turma
curl http://seu-dominio/api/students/classroom/1
```

### JavaScript/Fetch
```javascript
// Listar todos os alunos
fetch('/api/students')
  .then(res => res.json())
  .then(data => console.log(data));

// Buscar aluno específico
fetch('/api/students/1')
  .then(res => res.json())
  .then(data => console.log(data));
```

### PHP
```php
// Usando Laravel HTTP Client
$response = Http::get('/api/students');
$students = $response->json();
```

## 📦 Próximas Melhorias

Se necessário, você pode facilmente expandir a API adicionando:
- Filtros (por série, por nome, etc)
- Paginação
- Ordenação
- Campos específicos
- Rate limiting
- Cache

Basta adicionar novos métodos no `StudentController` e rotas no `api.php`.
