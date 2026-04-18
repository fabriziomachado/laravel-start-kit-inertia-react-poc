# Catálogo de processos (aplicação)

Esta secção documenta as funcionalidades **da aplicação** por cima do pacote [Laravel Workflow Automation](https://laravel-workflow.pilyus.com/getting-started/installation.html): listagem de fluxos activos, início de execução por utilizador autenticado, ecrã de resumo de runs concluídas, e extensões opcionais (slug, auditoria, apresentações).

## Requisitos

- PHP 8.3+, Laravel compatível com o starter kit
- Pacote `aftandilmmd/laravel-workflow-automation` instalado e migrado (ver [Installation](https://laravel-workflow.pilyus.com/getting-started/installation.html))
- Frontend Inertia + React com [Wayfinder](https://github.com/laravel/wayfinder) para rotas tipadas
- Ambiente de desenvolvimento com **Laravel Sail** (recomendado para Artisan e testes)

Os exemplos de CLI usam Sail:

```bash
./vendor/bin/sail artisan <comando>
```

## O que foi adicionado à app


| Área              | Descrição                                                                                                                         |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Rotas web         | `GET /flows`, `POST /flows/{workflow}/runs`, `GET /flows/runs/{run}`; redirect de `/matricular` para `/flows`                     |
| Controlador       | `App\Http\Controllers\Flows\FlowCatalogController` — catálogo, início de run, resumo só de leitura quando concluído               |
| Domínio           | `App\Flows\WorkflowStarterPayload`, `App\Flows\FlowRunStarter` — payload inicial e redirecção para o primeiro passo do formulário |
| Autorização       | `WorkflowPolicy::start`, `WorkflowRunPolicy::view` (dono da execução ou administrador)                                            |
| UI                | Páginas Inertia `flows/Index`, `flows/Show`; item de menu **Processos**                                                           |
| Dados partilhados | Flash `flows_success` / `flows_error` (substituem mensagens específicas de matrícula)                                             |


## Migrações adicionais da aplicação

Para além das tabelas do pacote, a app pode incluir:


| Tabela / alteração           | Finalidade                                               |
| ---------------------------- | -------------------------------------------------------- |
| `workflows.slug`             | Identificador estável opcional (único quando preenchido) |
| `workflow_definition_audits` | Histórico de alterações à definição do workflow          |


Execute as migrações:

```bash
./vendor/bin/sail artisan migrate
```

> **Nota:** o backfill de `slug` na migração evita disparar observers antes da tabela de auditoria existir.

## Rotas nomeadas (verificação)

Confirme que as rotas estão registadas (utilizador autenticado e verificado):

```bash
./vendor/bin/sail artisan route:list --name=flows
```

Esperado (nomes típicos):


| Método | URI                     | Nome               |
| ------ | ----------------------- | ------------------ |
| GET    | `flows`                 | `flows.index`      |
| POST   | `flows/{workflow}/runs` | `flows.runs.store` |
| GET    | `flows/runs/{run}`      | `flows.runs.show`  |


Após alterar rotas PHP, regenere o Wayfinder no frontend:

```bash
./vendor/bin/sail artisan wayfinder:generate
```

## Payload inicial da execução

Quem inicia um fluxo pela UI envia, no primeiro passo do `WorkflowService::run`, o utilizador actual:

- **Chave canónica:** `starter_user_id` (string, id do utilizador autenticado)

Execuções antigas podem usar apenas `matricula_user_id`; a leitura do “iniciador” considera primeiro `starter_user_id`, depois o legado. Detalhes: [Payload inicial de execuções](../flows-payload.md).

## Comportamento na listagem

- Utilizadores **normais** vêem nas “Execuções recentes” apenas runs em que são o iniciador (segundo o payload acima).
- **Administradores** vêem execuções de todos os utilizadores (filtro no `FlowCatalogController`).

## Editor visual e passo de formulário

O editor do pacote continua disponível (por exemplo em `/workflow-editor` conforme a configuração). Os nós de formulário customizados da app (ex.: `form_step`) permanecem registados na aplicação, não no pacote — ver [Workflow UI Editor](https://laravel-workflow.pilyus.com/ui-editor.html) para o editor genérico.

## Registo de apresentações (`presentation_key`)

`App\Workflow\Presentations\RegisterWorkflowPresentations` é chamado no `AppServiceProvider` para associar chaves de apresentação a componentes React (extensível; ver implementação actual no repositório).

## Testes

```bash
./vendor/bin/sail test --filter=FlowCatalog
```

## Próximos passos

- [Payload inicial e legado `matricula_user_id](../flows-payload.md)`
- [Regras de `slug` em workflows](../flows-slug.md)
- [Checklist viewport estreita (flows + formulário)](../flows-mobile-checklist.md)
- Documentação do pacote: [Quick Start](https://laravel-workflow.pilyus.com/getting-started/quick-start.html)

