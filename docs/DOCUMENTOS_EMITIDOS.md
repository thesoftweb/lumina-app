# Documentos emitidos

Declarações e recibos emitidos pela escola, numerados e com verificação pública de autenticidade.

## Onde emitir

| Documento | Onde | Regra |
| --- | --- | --- |
| Declaração de matrícula | Matrícula › Documentos e cobranças | Matrícula ativa |
| Declaração de frequência | Matrícula › Documentos e cobranças | Matrícula ativa e chamadas lançadas no período (do início do ano letivo, ou da matrícula, até hoje) |
| Declaração de quitação | Clientes › abrir o responsável | "Até hoje": sem faturas vencidas com saldo. "Quitação anual" (Lei nº 12.007/2009): todas as faturas de um ano encerrado pagas |
| Recibo de pagamento | Fatura (topo da tela) ou Clientes › Faturas › Mais ações | Um recibo por pagamento; pedir de novo reabre o mesmo (2ª via) |

Para consultar, reimprimir e cancelar: **Documentos › Documentos emitidos**. Secretaria e admin emitem e reimprimem; só o admin cancela, com motivo.

Antes da primeira emissão, preencha em **Configurações › Empresa › Documentos** quem assina e o cargo (obrigatório), a logo, e na aba Instituição o CNPJ, o INEP e o endereço.

## Identificação e validade

- **Número:** série e ano, sem repetir nem pular: `DEC-2026-000001` (declarações) e `REC-2026-000001` (recibos).
- **Código de verificação:** 16 caracteres sem letras ambíguas, impresso no rodapé com o QR code.
- **Conteúdo congelado:** na emissão são gravados os dados da escola, quem assina, uma cópia da logo e os dados do documento. Mudanças posteriores nos cadastros não alteram o que foi emitido. Um hash do conteúdo detecta alteração direta no banco.
- **Correção:** documento emitido não se edita nem se exclui. O admin cancela e um novo é emitido.
- **Cancelamento automático:** estornar um pagamento ou excluir a fatura cancela o recibo correspondente.

## Verificação pública

`/verificar` (ou o QR code) mostra o documento como foi emitido, com o CPF parcialmente oculto, e a situação: válido, vencido, cancelado (com o motivo) ou não encontrado. A página aceita o código em minúsculas e sem hífens, limita a 30 consultas por minuto e não é indexada por buscadores.

## Implantação

- `APP_URL` precisa ser o endereço público: o QR code e o endereço impresso no rodapé usam essa variável.
- `php artisan migrate` e `php artisan storage:link` (logo da empresa).
- A impressão é feita pelo navegador ("Imprimir / Salvar em PDF"), sem depender do Puppeteer no servidor.

## Código

- Emissão, numeração e cancelamento: `App\Services\DocumentIssuer`.
- Cada tipo: `App\Documents\*` (dados gravados) e `resources/views/documents/issued/types/*` (texto). Novo tipo: um case em `App\Enums\IssuedDocumentType`, uma classe em `App\Documents` e uma view.
- Layout A4 comum: `resources/views/documents/issued/document.blade.php`.
- Testes: `IssuedDocumentsTest`, `AttendanceAndClearanceDeclarationsTest`, `PaymentReceiptsTest`.
