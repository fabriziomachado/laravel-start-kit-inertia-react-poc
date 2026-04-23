<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clientes de e-mail / cópia de texto plano podem expor URLs com "&amp;" literal
 * em vez de "&". O Laravel assina com "&" real; {@see \Illuminate\Routing\UrlGenerator::hasCorrectSignature}
 * lê QUERY_STRING bruto — sem esta correcção, a validação falha com 403.
 */
final class RepairHtmlEntityAmpersandsInQueryString
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('workflow-approvals/*')) {
            return $next($request);
        }

        $raw = (string) $request->server->get('QUERY_STRING', '');

        if ($raw === '' || ! str_contains($raw, '&amp;')) {
            return $next($request);
        }

        $fixed = str_replace('&amp;', '&', $raw);
        $request->server->set('QUERY_STRING', $fixed);

        parse_str($fixed, $parsed);
        if (is_array($parsed)) {
            $request->query->replace($parsed);
        }

        return $next($request);
    }
}
