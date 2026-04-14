<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserListController
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->is_admin) {
            abort(403);
        }

        return Inertia::render('users/index', [
            'users' => User::query()->orderBy('name')->paginate(20),
        ]);
    }
}
