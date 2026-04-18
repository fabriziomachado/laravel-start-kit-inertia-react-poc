# Acompanhamento — Fluxos genéricos (PRD)

Plano de referência: `.cursor/plans/prd_fluxos_genéricos_93e08105.plan.md` (ou cópia local em Cursor).

**Guia de uso (novidades implementadas):** [Catálogo de processos](./getting-started/process-catalog.md) · [Índice `docs/`](./README.md).

**Comandos no ambiente de desenvolvimento:** usar **`./vendor/bin/sail`** para Artisan, testes e Composer (ver `.cursor/rules/laravel-sail.mdc`), com os contentores Docker a correr.

**Última actualização:** 2026-04-18

---

## Resumo por fase

| Fase | Estado | Notas |
|------|--------|-------|
| Fase 0 | Concluída | `docs/flows-payload.md` |
| Fase 1 | Concluída | `FlowCatalogController`, `flows/*`, testes `FlowCatalogControllerTest` |
| Fase 2 | Concluída | `FlowRunStarter`, filtro starter/admin, `docs/flows-mobile-checklist.md` |
| Fase 3 | Concluída (opcional) | slug, auditoria, `RegisterWorkflowPresentations` |
| Fase 4 | Concluída | `WorkflowPolicy`, `WorkflowRunPolicy` |

---

## Fase 0 — Preparação

- [x] **0.1** Branch de trabalho (`feature/generic-flows-process-catalog` ou equivalente)
- [x] **0.2** Decisão documentada (payload inicial: `starter_user_id` vs `matricula_user_id` / legado)

---

## Fase 1 — MVP (catálogo + início + UI + migração)

- [x] **1.1** `FlowCatalogController` — GET lista workflows activos + Inertia + teste
- [x] **1.5** Rotas `flows/*` em `routes/web.php` (`auth` + `verified`)
- [x] **1.2** POST `flows/{workflow}/runs` — start + redirect para token
- [x] **1.4** Página `flows/Index.tsx` (catálogo + iniciar + listagem recente opcional)
- [x] **1.6** `WorkflowFormController` redirect + flash genérico + testes
- [x] **1.3** GET `flows/runs/{run}` — detalhe concluído + testes 200/403
- [x] **1.7** Remover/deprecar `MatriculaWorkflowBinding` + ajustar controllers
- [x] **1.8** `/matricular` → redirect ou remoção + testes
- [x] **1.9** Sidebar + Wayfinder (`routes`)
- [x] **1.10** Testes renomeados / `MatricularWizardControllerTest` substituído — suite verde

---

## Fase 2 — Polimento MVP

- [x] **2.1** `FlowRunStarter` (ou equivalente) — lógica única iniciar + token
- [x] **2.2** Filtro listagem execuções (starter vs admin) + teste + doc
- [x] **2.3** Checklist mobile (flows + workflow-forms)

---

## Fase 3 — Opcional (PRD)

### 3A — Slug

- [x] **3A.1** Migration `slug` em `workflows` + binding opcional
- [x] **3A.2** Documentação unicidade / editor (`docs/flows-slug.md`)

### 3B — Auditoria

- [x] **3B.1** Tabela + modelo + gravação em alterações ao grafo

### 3C — Presentation

- [x] **3C.1** Registo `presentation_key` + `AppServiceProvider`

---

## Fase 4 — Políticas

- [x] **4.1** Policies por workflow + testes por papel

---

## Dependências (ordem sugerida)

1. 0.2 → 1.1 → 1.5 → 1.2 → 1.4 → 1.6 → 1.3 → 1.7–1.10 → Fase 2 → Fases 3/4

---

## Riscos a verificar ao fechar Fase 1

- [x] `WorkflowFormWizardExampleSeeder` e referências a `MatriculaWorkflowBinding`
- [x] `./vendor/bin/sail artisan wayfinder:generate` após novas rotas
