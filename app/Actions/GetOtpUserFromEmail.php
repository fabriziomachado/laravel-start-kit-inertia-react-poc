<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use BenBjurstrom\Otpz\Models\Concerns\Otpable;
use Illuminate\Validation\ValidationException;

final class GetOtpUserFromEmail
{
    public function handle(string $email): Otpable
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'Nenhum usuário encontrado com este email.',
            ]);
        }

        return $user;
    }
}
