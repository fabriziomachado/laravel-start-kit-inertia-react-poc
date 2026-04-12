<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

/**
 * Implementado por controllers Inertia que querem permanecer na mesma rota
 * quando um GET falha com uma excepção tratada globalmente (e.g. ProcedureExecutionException).
 * O handler renderiza o componente devolvido por este método com os props de estado vazio,
 * em vez de redirecionar para outra página.
 */
interface HasInertiaFallback
{
    /**
     * Componente Inertia e props a renderizar quando o pedido falha.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function inertiaFallback(): array;
}
