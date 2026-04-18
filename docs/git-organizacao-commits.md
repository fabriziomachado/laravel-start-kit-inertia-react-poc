# Organização de commits (referência)

Este guia descreve a ordem sugerida para agrupar alterações por tema, com mensagens consistentes e histórico legível. Ajuste os caminhos se estiver noutra máquina ou branch.

## Pré-requisitos

1. Contentores Sail a correr, se for usar Artisan ou testes.
2. Revisar o estado: `git status` e `git diff`.
3. Não adicionar artefactos locais desnecessários: pasta `.cursor/plans/`, ficheiros de cache do editor, ou `package-lock.json` na raiz quando o lock oficial é `bun.lock`.

## Ordem recomendada (do mais isolado ao mais amplo)

### 1. Regra Cursor (Sail + Bun)

Ficheiros: `.cursor/rules/laravel-sail.mdc`

Documenta que Composer, Artisan, testes e **Bun** devem passar pelo Sail quando o ambiente é Docker.

```bash
git add .cursor/rules/laravel-sail.mdc
git commit -m "chore(cursor): documentar Bun via Sail na regra do ambiente"
```

### 2. Documentação da app (índice, catálogo, PRD)

Ficheiros:

- `docs/README.md`
- `docs/getting-started/process-catalog.md`
- `docs/flows-mvp-progress.md`

```bash
git add docs/README.md docs/getting-started/process-catalog.md docs/flows-mvp-progress.md
git commit -m "docs: índice em docs/, catálogo de processos e ligações no PRD"
```

### 3. Pacote workflow-automation — texto e rota do editor (Bun)

Ficheiros:

- `packages/laravel-workflow-automation/docs/ui-editor.md`
- `packages/laravel-workflow-automation/routes/editor.php`

```bash
git add packages/laravel-workflow-automation/docs/ui-editor.md packages/laravel-workflow-automation/routes/editor.php
git commit -m "docs(workflow-automation): build do editor UI com Bun e mensagem 404 alinhada"
```

### 4. Pacote workflow-automation — código React do editor

Ficheiro: `packages/laravel-workflow-automation/ui/src/components/config/fields/KeyValueField.tsx`

```bash
git add packages/laravel-workflow-automation/ui/src/components/config/fields/KeyValueField.tsx
git commit -m "refactor(workflow-automation-ui): simplificar spread em KeyValueField"
```

### 5. Pacote workflow-automation — assets gerados

Só quando o build do subprojeto `ui/` (ou docs) foi executado de propósito e o repositório versiona `dist/`.

Ficheiros típicos:

- `packages/laravel-workflow-automation/ui/dist/...`
- `packages/laravel-workflow-automation/ui/docs/.vitepress/dist/...` (se aplicável)

```bash
git add packages/laravel-workflow-automation/ui/dist packages/laravel-workflow-automation/ui/docs/.vitepress/dist
git commit -m "build(workflow-automation): regenerar assets do editor e docs locais"
```

### 6. Aplicação Inertia (formatação e UI)

Ficheiros em `resources/css/app.css`, `resources/js/...` (sidebar, aparência, páginas de fluxos, sessão, etc.).

```bash
git add resources/css/app.css resources/js
git commit -m "style(app): formatação Tailwind e ajustes nas páginas e componentes"
```

### 7. Política de lockfile (opcional, já aplicada no repositório)

Ficheiro: `.gitignore` (entrada `/package-lock.json` na raiz).

Evita commits acidentais de lock do npm quando o projeto usa **Bun**.

```bash
git add .gitignore
git commit -m "chore: ignorar package-lock.json na raiz quando o lock é bun.lock"
```

## Depois de commitar

- `git log --oneline -10` para confirmar a sequência.
- Opcional: `git push` na branch de trabalho.

## Notas

- Para **novas** alterações no futuro, prefira um commit por intenção (documentação, bugfix, refactor, build) em vez de um único commit grande.
- Na raiz do repositório, `package-lock.json` está listado em `.gitignore` porque o lock oficial é `bun.lock`. Apague o ficheiro local se tiver sido gerado por `npm install` por engano.
