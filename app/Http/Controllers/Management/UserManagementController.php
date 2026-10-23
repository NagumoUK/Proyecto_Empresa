<?php

namespace App\Http\Controllers\Management;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('management/users/index', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'email_verified_at', 'role', 'created_at'])
                ->latest()
                ->paginate(15),
            'roles' => UserRole::options(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);
        $newRole = UserRole::from($validated['role']);

        if ($request->user()->is($user) && $newRole !== UserRole::Admin) {
            return back()->withErrors([
                'role' => 'No puedes quitarte el rol de administrador a ti mismo.',
            ]);
        }

        if (
            $user->role === UserRole::Admin
            && $newRole !== UserRole::Admin
            && User::query()->where('role', UserRole::Admin)->count() <= 1
        ) {
            return back()->withErrors([
                'role' => 'No se puede quitar el último administrador del sistema.',
            ]);
        }

        $user->role = $newRole;
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Rol de {$user->name} actualizado.",
        ]);

        return back();
    }
}
