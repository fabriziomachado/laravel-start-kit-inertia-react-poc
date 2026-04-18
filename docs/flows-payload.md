# Payload inicial de execuções de fluxo

## Decisão (Fase 0)

- **Chave canónica:** `starter_user_id` (string) no primeiro elemento do array passado a `WorkflowService::run()`, ex.: `[['starter_user_id' => (string) $user->getKey()]]`.
- **Legado:** execuções antigas podem ter apenas `matricula_user_id`. A leitura do iniciador usa **primeiro** `starter_user_id`, depois `matricula_user_id` (ver `App\Flows\WorkflowStarterPayload`).

## Uso

- Novos fluxos e testes devem preferir `starter_user_id`.
- Integrações que ainda enviam `matricula_user_id` continuam listáveis até migrarem dados se necessário.
