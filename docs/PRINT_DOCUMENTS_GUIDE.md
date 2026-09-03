# 📄 Funcionalidade de Impressão de Documentos

Implementação completa de impressão e geração de PDF para documentos usando **spatie/laravel-pdf**.

## ✨ Funcionalidades

### 1. **Página de Impressão HTML (A4)**

-   Layout responsivo otimizado para A4
-   Botões de ação: Imprimir, Baixar PDF, Voltar
-   Estilos específicos para impressão
-   Exibição de todos os dados do documento e cliente

### 2. **Geração de PDF**

-   Formato A4 automático
-   Nomes descritivos para arquivos PDF
-   Sem dependências adicionais (usa spatie/laravel-pdf)

### 3. **Integração com Filament**

-   Action "Imprimir" na tabela de documentos
-   Actions "Imprimir" e "Baixar PDF" na página de visualização
-   Abre em nova aba (print) ou faz download direto (PDF)

## 📁 Arquivos Criados

```
app/
├── Http/
│   └── Controllers/
│       └── DocumentPrintController.php    # Controller para imprimir e gerar PDF
│
resources/
└── views/
    └── documents/
        ├── print.blade.php               # View para página de impressão
        └── pdf.blade.php                 # View para geração de PDF

routes/
└── web.php                               # Rotas de impressão adicionadas
```

## 🔧 Arquivo Modificado

-   `app/Filament/Resources/Documents/Tables/DocumentsTable.php` - Action "Imprimir" adicionada
-   `app/Filament/Resources/Documents/Pages/ViewDocument.php` - Actions de impressão adicionadas

## 🚀 Como Usar

### 1. **Acessar a Página de Impressão**

#### Via Filament

-   Clique no botão **"Imprimir"** na tabela de documentos
-   Ou clique em **"Imprimir"** na página de visualização do documento

#### Via URL Direta

```
/documents/{id}/print
```

### 2. **Gerar PDF**

#### Via Filament

-   Clique em **"Baixar PDF"** na página de visualização do documento

#### Via URL Direta

```
/documents/{id}/pdf
```

### 3. **Imprimir (Browser)**

Na página de impressão, clique em **"🖨️ Imprimir"** ou use `Ctrl+P`

## 📐 Especificações de Impressão

-   **Tamanho:** A4 (210mm x 297mm)
-   **Margem:** 20mm em todos os lados
-   **Fonte:** Arial, Helvetica, sans-serif
-   **Orientação:** Retrato (portrait)

## 🎨 Layout da Página de Impressão

```
┌─────────────────────────────────┐
│      Botões de Ação (topo)      │  ← Não imprime
├─────────────────────────────────┤
│    CABEÇALHO DO DOCUMENTO       │
│    Data | Status                │
├─────────────────────────────────┤
│                                 │
│  CONTEÚDO DO DOCUMENTO          │
│  (HTML renderizado com          │
│   merge tags substituídos)      │
│                                 │
├─────────────────────────────────┤
│                                 │
│  DADOS DO CLIENTE               │
│  DADOS DA TRANSAÇÃO             │
│  DADOS DA EMPRESA               │
│                                 │
├─────────────────────────────────┤
│    Rodapé com data/hora         │
└─────────────────────────────────┘
```

## 📊 Dados Exibidos

### Cabeçalho

-   Nome do modelo
-   Data de criação
-   Status (Rascunho, Gerado, Enviado)

### Conteúdo

-   Conteúdo HTML do documento com merge tags substituídos

### Dados do Cliente (se disponível)

-   Nome
-   Email
-   Telefone
-   CPF
-   CNPJ

### Dados da Transação (se disponível)

-   Número do Recibo
-   Valor (formatado como R$)
-   Data
-   Número do Documento

### Dados da Empresa (se disponível)

-   Nome da Empresa
-   Endereço

## 🖨️ Dicas de Impressão

### Para Melhor Resultado em PDF

1. Use **Chrome** ou **Edge** para melhor compatibilidade
2. Na página de impressão, clique em **"Baixar PDF"**
3. Ou use: `Ctrl+P` → Salvar como PDF

### Configurações Recomendadas

-   **Papel:** A4
-   **Orientação:** Retrato
-   **Margens:** Normal ou Mínima
-   **Headers/Footers:** Desabilitar (opcional)

## 🔗 Rotas Disponíveis

```php
GET  /documents/{id}/print   → DocumentPrintController@show    (documents.print)
GET  /documents/{id}/pdf     → DocumentPrintController@pdf     (documents.pdf)
```

## 🎯 Fluxo de Uso

```
Filament Lista de Documentos
    ↓
[Clique em "Imprimir"]
    ↓
Abre: /documents/{id}/print (Nova Aba)
    ↓
Página de Impressão A4
    ├→ [Imprimir] (Ctrl+P)
    ├→ [Baixar PDF] (Gera arquivo)
    └→ [Voltar] (Retorna ao Filament)
```

## 💾 Nome do Arquivo PDF

Formato: `{nome-modelo}-{data}-{id-documento}.pdf`

Exemplo: `Recibo-2026-01-11-000001.pdf`

## ⚙️ Requisitos

-   Laravel 12+
-   Filament 4.x
-   spatie/laravel-pdf: 1.\*
-   PHP 8.1+

## 🐛 Troubleshooting

### PDF não está sendo gerado

-   Verifique se `spatie/laravel-pdf` está instalado
-   Certifique-se de que a view `documents.pdf` existe

### Layout quebrado na impressão

-   Teste em diferentes navegadores
-   Verifique as margens do documento no CSS
-   Ajuste a altura/largura da página no CSS se necessário

### Caracteres especiais não aparecem

-   Use UTF-8 encoding (já configurado)
-   Se necessário, ajuste as fontes no CSS

---

**Status:** ✅ Pronto para produção
**Última atualização:** 11 de janeiro de 2026
