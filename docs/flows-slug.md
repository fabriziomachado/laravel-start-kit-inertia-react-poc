# `workflows.slug`

- Coluna **nullable**, **única** quando preenchida (ver migration `add_slug_to_workflows_table`).
- Backfill inicial derivado do **nome** do workflow (normalizado para URL).
- Ao editar o nome no editor de grafos, o slug pode ser recalculado ou mantido conforme regra de produto; evitar duplicados na mesma tabela.

Binding opcional: `flows/{workflow:slug}` em paralelo ao `id` quando o slug estiver definido.
