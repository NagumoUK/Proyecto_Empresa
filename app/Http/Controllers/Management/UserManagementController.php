<?php

namespace App\Http\Controllers\Management;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);
        $validated['email'] = Str::lower($validated['email']);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::random(64),
        ]);
        $user->role = UserRole::from($validated['role']);
        $user->save();

        return $this->sendInvitation($user);
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return to_route('management.usuarios.index')->withErrors([
                'invitation' => 'Esta cuenta ya verificó su correo.',
            ]);
        }

        return $this->sendInvitation($user);
    }

    private function sendInvitation(User $user): RedirectResponse
    {
        $token = Password::broker()->createToken($user);
        $setupUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        try {
            Mail::to($user->email)->send(new UserInvitationMail(
                name: $user->name,
                role: $user->role->label(),
                setupUrl: $setupUrl,
            ));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'La cuenta existe, pero no se pudo enviar el enlace para establecer la contraseña. Inténtalo de nuevo desde la lista de usuarios.',
            ]);

            return to_route('management.usuarios.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Se envió al correo de {$user->name} un enlace para establecer su contraseña.",
        ]);

        return to_route('management.usuarios.index');
    }
}
