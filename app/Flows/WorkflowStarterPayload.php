<?php

declare(strict_types=1);

namespace App\Flows;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class WorkflowStarterPayload
{
    public const string STARTER_USER_ID_KEY = 'starter_user_id';

    public const string LEGACY_MATRICULA_USER_ID_KEY = 'matricula_user_id';

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(Authenticatable $user): array
    {
        return [[self::STARTER_USER_ID_KEY => (string) $user->getKey()]];
    }

    public static function starterUserId(WorkflowRun $run): ?string
    {
        $row = $run->initial_payload[0] ?? null;
        if (! is_array($row)) {
            return null;
        }

        $canonical = $row[self::STARTER_USER_ID_KEY] ?? null;
        if ($canonical !== null && $canonical !== '') {
            return (string) $canonical;
        }

        $legacy = $row[self::LEGACY_MATRICULA_USER_ID_KEY] ?? null;
        if ($legacy !== null && $legacy !== '') {
            return (string) $legacy;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $initialPayloadRow
     */
    public static function starterUserIdFromInitialPayloadRow(array $initialPayloadRow): ?string
    {
        $canonical = $initialPayloadRow[self::STARTER_USER_ID_KEY] ?? null;
        if ($canonical !== null && $canonical !== '') {
            return (string) $canonical;
        }

        $legacy = $initialPayloadRow[self::LEGACY_MATRICULA_USER_ID_KEY] ?? null;
        if ($legacy !== null && $legacy !== '') {
            return (string) $legacy;
        }

        return null;
    }
}
