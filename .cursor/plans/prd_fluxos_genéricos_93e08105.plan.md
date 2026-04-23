---
name: PRD Fluxos genéricos
overview: "Plano de desenvolvimento em etapas, com tarefas verificáveis, alinhado ao PRD acordado: catálogo genérico de processos, início de execução por workflow, UI e testes; nó `form_step` mantém-se na app; fases posteriores para apresentação reutilizável, auditoria e políticas."
todos:
  - id: phase-0
    content: "Fase 0: branch + decisão payload inicial (starter_user_id vs legado)"
    status: completed
  - id: phase-1-backend
    content: "Fase 1: FlowCatalogController, rotas flows/*, POST start run, GET run show"
    status: completed
  - id: phase-1-frontend
    content: "Fase 1: página Inertia flows + sidebar + redirect WorkflowFormController"
    status: completed
  - id: phase-1-migrate
    content: "Fase 1: remover/alias matricular, actualizar testes e seeder"
    status: completed
  - id: phase-2
    content: "Fase 2: FlowRunStarter partilhado, filtros listagem, checklist mobile"
    status: completed
  - id: phase-3-optional
    content: "Fase 3 (opcional): slug, auditoria BD, presentation_key"
    status: completed
  - id: phase-4-policies
    content: "Fase 4: policies por workflow"
    status: completed
isProject: false
---

# Plano de desenvolvimento — Plataforma de processos (PRD)

## Contexto de partida

- Rotas actuais: [`routes/web.php`](routes/web.php) — `/matricular` + `matricular.store` + `matricular.runs.show`; [`WorkflowFormController`](app/Http/Controllers/WorkflowFormController.php) fixa redirect final a `route('matricular')`.
- Binding fixo: [`MatriculaWorkflowBinding`](app/Http/Controllers/Wizards/MatriculaWorkflowBinding.php) com `DEFAULT_WORKFLOW_ID`.
- Nó de formulário: [`app/Workflow/Nodes/FormStepNode.php`](app/Workflow/Nodes/FormStepNode.php) permanece na app (fora do pacote).

```mermaid
flowchart LR
  subgraph today [Hoje]
    B[MatriculaWorkflowBinding]
    M[/matricular]
  end
  subgraph target [Alvo MVP]
    C[Catalogo workflows ativos]
    F[/flows]
  end
  today --> target
```

---

## Fase 0 — Preparação (branch e convenções)

| ID | Tarefa | Critério de conclusão |
|----|--------|------------------------|
| 0.1 | Trabalhar na branch `feature/generic-flows-process-catalog` (ou renomear se preferirem) | `git branch` mostra a branch correcta |
| 0.2 | Documentar no PR/issue interno o payload inicial padrão (ex. `starter_user_id` vs `matricula_user_id`) para não misturar semânticas | Decisão escrita numa linha no ticket |

---

## Fase 1 — MVP: catálogo genérico e início de execução

**Objectivo:** qualquer `Workflow` activo pode ser listado e iniciado; deixar de depender de um único ID em constante.

| ID | Tarefa | Critério de conclusão |
|----|--------|------------------------|
| 1.1 | Criar namespace de controladores `App\Http\Controllers\Flows\` com `FlowCatalogController` (ou nome equivalente): **GET** lista `Workflow::where('is_active', true)` ordenado por nome, com id + nome + descrição | Resposta Inertia com dados; teste Feature cobre lista não vazia com seeder |
| 1.2 | **POST** `flows/{workflow}/runs` (route model binding por `id`) que chama `WorkflowService::run($workflow, [['starter_user_id' => auth id]])` (ou chave acordada em 0.2) e redirecciona para `workflow-forms.show` com o token (reutilizar lógica de [`MatricularWizardController::resumeTokenForWaitingRun`](app/Http/Controllers/Wizards/MatricularWizardController.php)) | Teste: post autenticado → redirect 302 para URL com token |
| 1.3 | **GET** `flows/runs/{run}` — detalhe de execução concluída (equivalente a `show` actual): autorização mínima igual ao actual (dono ou admin), reutilizar [`WorkflowFormProgress::completedRunReadOnlySections`](app/Support/WorkflowFormProgress.php) | Teste de 200 para dono; 403 para outro user |
| 1.4 | Nova página Inertia `flows/Index.tsx` (ou `flows/Catalog.tsx`): tabela/cards de processos + botão “Iniciar”; secção opcional de execuções recentes (reutilizar padrão de listagem de [`matricular/Index`](resources/js/pages/matricular/Index.tsx)) | Smoke manual ou teste browser opcional |
| 1.5 | Registar rotas em [`routes/web.php`](routes/web.php) com middleware `auth` + `verified` como o resto | `php artisan route:list` mostra `flows`, `flows.runs.store`, `flows.runs.show` |
| 1.6 | Ajustar [`WorkflowFormController`](app/Http/Controllers/WorkflowFormController.php): redirect pós-sucesso para **`route('flows.index')`** (ou nome escolhido) **com** flash de sucesso genérico (não “matrícula concluída”) | [`WorkflowFormControllerTest`](tests/Feature/WorkflowFormControllerTest.php) actualizado |
| 1.7 | **Deprecar ou remover** [`MatriculaWorkflowBinding`](app/Http/Controllers/Wizards/MatriculaWorkflowBinding.php): `MatricularWizardController` passa a usar `Workflow` vindo da rota ou deixa de existir se `/matricular` virar alias | Nenhum ficheiro com `DEFAULT_WORKFLOW_ID` obrigatório para o fluxo principal |
| 1.8 | Opção A: **redirect** `GET /matricular` → `GET /flows` para não partir bookmarks; Opção B: remover rota e actualizar sidebar | Sidebar aponta para `flows`; testes actualizados |
| 1.9 | Actualizar [`app-sidebar.tsx`](resources/js/components/app-sidebar.tsx) e gerar rotas Wayfinder se usarem | Item de menu “Processos” ou equivalente |
| 1.10 | Mover/renomear testes: [`MatricularWizardControllerTest`](tests/Feature/MatricularWizardControllerTest.php) → cenários cobertos por `FlowCatalogController` + redirects | Suite verde |

**Dependências:** 1.2 depende de 1.1 e 1.5; 1.6 depende de 1.4 e nome de rota final; 1.8 depende de 1.1–1.5.

---

## Fase 2 — Polimento MVP e contrato de payload

| ID | Tarefa | Critério de conclusão |
|----|--------|------------------------|
| 2.1 | Extrair método partilhado (trait ou classe `FlowRunStarter`) para “iniciar run + obter token” usado pelo POST, evitando duplicação com o antigo wizard | Uma única implementação chamada pelo controller |
| 2.2 | Garantir que execuções listadas no catálogo filtram por **starter** (ou política mínima documentada no PRD: “só as minhas” vs “todas para admin”) | Comportamento documentado + teste |
| 2.3 | Revisão mobile: [`flows` página](resources/js/pages/) e [`workflow-forms/Show`](resources/js/pages/workflow-forms/Show.tsx) em viewport estreita | Checklist manual ou screenshot policy da equipa |

---

## Fase 3 — PRD “Should” posteriores (opcional neste epic)

**3A — Identificador estável (slug)**

| ID | Tarefa | Critério |
|----|--------|----------|
| 3A.1 | Migration na app: `slug` nullable único em `workflows` + backfill a partir do nome | Route binding `flows/{workflow:slug}` opcional em paralelo ao id |
| 3A.2 | Documentar regra de unicidade e edição no editor | README interno |

**3B — Auditoria de definições (BD)**

| ID | Tarefa | Critério |
|----|--------|----------|
| 3B.1 | Tabela `workflow_definition_audits` (ou equivalente) + modelo + gravação em update de workflow | Registo criado ao editar grafo (hook ou observer) |

**3C — `presentation_key` e registo de componentes**

| ID | Tarefa | Critério |
|----|--------|----------|
| 3C.1 | Contrato + `App\Workflow\Presentations\RegisterWorkflowPresentations` no `AppServiceProvider` | Nós podem referenciar key; fallback UI genérica |

---

## Fase 4 — Políticas (fora do MVP do PRD)

| ID | Tarefa | Critério |
|----|--------|----------|
| 4.1 | Policies Laravel por `Workflow` (quem pode `start`, `viewRun`) | Testes de autorização por papel |

---

## Riscos e ordem sugerida

- **Regressão em testes:** actualizar redirects e seeds que referenciam `MatriculaWorkflowBinding` ([`WorkflowFormWizardExampleSeeder`](database/seeders/WorkflowFormWizardExampleSeeder.php)).
- **Wayfinder:** após mudar rotas, correr `php artisan wayfinder:generate` se o projecto usar.

Ordem recomendada: **Fase 0 → 1.1 → 1.5 → 1.2 → 1.4 → 1.6 → 1.3 → 1.7–1.10 → Fase 2 → Fases 3/4 conforme prioridade de negócio.**
