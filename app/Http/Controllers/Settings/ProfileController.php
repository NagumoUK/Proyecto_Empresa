<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Settings\StoreProfileAvatarRequest;
use App\Services\ImageStorage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function updateAvatar(
        StoreProfileAvatarRequest $request,
        ImageStorage $imageStorage,
    ): RedirectResponse {
        $user = $request->user();
        $avatar = $request->file('avatar');

        if (! $avatar instanceof UploadedFile) {
            throw new RuntimeException('The validated profile image is missing.');
        }

        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = $imageStorage->store(
            $avatar,
            "users/{$user->id}/avatars",
            256,
            256,
            cover: true,
        );
        $user->avatar_path = $newAvatarPath;

        try {
            $user->save();
        } catch (Throwable $exception) {
            try {
                $imageStorage->delete($newAvatarPath);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }

        $imageStorage->delete($oldAvatarPath);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Foto de perfil actualizada correctamente.',
        ]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, ImageStorage $imageStorage): RedirectResponse
    {
        $user = $request->user();
        $avatarPath = $user->avatar_path;

        Auth::logout();

        $user->delete();
        $imageStorage->delete($avatarPath);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
