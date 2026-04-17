# Editor visual: resposta HTTP e testes com Pest Browser

## Contexto

O editor em `/workflow-editor` é servido pelo Laravel a partir do `index.html` e dos assets em `workflow-editor/assets/*`, definidos em `routes/editor.php`. Os testes de browser do projeto usam o pacote **pest-plugin-browser**, que arranca um servidor HTTP interno e o Playwright para visitar a aplicação como um browser real.

## Problema encontrado

### Sintomas

- Testes Pest com `visit('/workflow-editor')` falhavam por timeout ou nunca encontravam texto como «Workflows» ou o título «Workflow Editor».
- O pedido HTTP parecia «completar», mas o conteúdo percebido pelo browser estava vazio ou incompleto.

### Causa técnica

O handler do editor usava `response()->file($caminho, …)` (resposta `BinaryFileResponse`).

O driver HTTP do `pest-plugin-browser` (`LaravelHttpServer`) obtém o corpo da resposta com `$response->getContent()`. Para `BinaryFileResponse`, o Symfony/Laravel devolve **string vazia** quando o conteúdo ainda não foi materializado em buffer (o fluxo normal é enviar o ficheiro no `sendContent()` da resposta HTTP real).

Como o condicional do driver só trata `getContent() === false` (e não string vazia), o servidor de teste enviava ao Playwright **corpo vazio** para o HTML e para os assets JS/CSS. Resultado: página sem markup útil, scripts não carregavam, a SPA não arrancava e as asserções falhavam ou esgotavam o tempo limite.

## O que foi alterado

### 1. `routes/editor.php` (pacote)

- **SPA (`index.html`)**: em vez de `response()->file()`, o conteúdo é lido com `file_get_contents()` e devolvido com `response($conteúdo, 200, ['Content-Type' => 'text/html'])`.
- **Assets** (`workflow-editor/assets/{file}`): mesma abordagem — `file_get_contents()` + `response(...)` com o `Content-Type` já calculado (JS, CSS, fontes, etc.).

Assim `getContent()` no ambiente de teste devolve o bytes do ficheiro e o servidor interno do Pest repassa um corpo HTTP correcto ao Playwright.

### 2. Testes na aplicação anfitriã

- **`tests/Browser/WorkflowEditorCreateWorkflowTest.php`**: fluxo E2E — lista de workflows, modal «New Workflow», preenchimento do nome (selector explícito `input[placeholder="My Workflow"]`), «Create», verificação de navegação para `/workflow-editor/{id}` e abertura do painel AI Builder (`button[title="AI Builder"]`). Usa `app()->detectEnvironment(fn () => 'local')` para alinhar com o middleware `Authorize` do editor em ambientes não locais simulados como `testing`, e `Playwright::setTimeout` adequado ao arranque da SPA.
- **`tests/Feature/WorkflowAutomationIntegrationTest.php`**: o `GET /workflow-editor` passa também a fazer `assertSee('Workflow Editor')`, garantindo que o corpo HTML devolvido contém o título esperado (regressão para clientes que materializam a resposta em string).

## Motivo

1. **Correctez com o servidor de testes do Pest Browser**: qualquer cliente que dependa de `getContent()` ou de um proxy que leia o corpo em memória deve receber o mesmo conteúdo que um browser real receberia na wire.
2. **Testabilidade E2E**: permitir validar o fluxo do editor e do AI Builder de forma automatizada, sem depender apenas de pedidos `GET` via `TestCase` que podem mascarar o problema do corpo vazio em `BinaryFileResponse`.
3. **Comportamento previsível**: a resposta continua a ser HTTP 200 com os mesmos headers de tipo; apenas deixa de usar streaming via `file()` nestes dois pontos.

## Notas e trade-offs

- **Memória**: assets grandes (por exemplo bundles JS muito pesados) passam a ser lidos inteiros para RAM antes de responder. Para o `index.html` e bundles típicos do editor isto é aceitável; se no futuro houver ficheiros muito grandes, pode avaliar-se `StreamedResponse` compatível com o materializar o corpo no teste, ou um ramo condicionado ao ambiente.
- **Produção**: o mesmo código corre em produção; o impacto é maior uso de RAM por pedido a estes endpoints estáticos em relação a `sendfile`/streaming nativo — avaliar carga se o tráfego for massivo nestes URLs.

## Referências úteis

- Rotas: `packages/laravel-workflow-automation/routes/editor.php`
- Middleware de acesso: `packages/laravel-workflow-automation/src/Http/Middleware/Authorize.php`
- Implementação do driver (referência externa): `vendor/pestphp/pest-plugin-browser/src/Drivers/LaravelHttpServer.php` (uso de `getContent()` na construção da resposta Amp)
