<?php

declare(strict_types=1);

return [

    'session_key' => 'impersonated_by',

    'session_guard' => 'impersonator_guard',

    'session_guard_using' => 'impersonator_guard_using',

    'default_impersonator_guard' => 'web',

    /**
     * Após iniciar impersonation, voltar à página anterior (mesma rota).
     */
    'take_redirect_to' => 'back',

    /**
     * Após sair da impersonation, voltar à página anterior (mesma rota).
     */
    'leave_redirect_to' => 'back',

];
