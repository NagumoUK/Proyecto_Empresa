<?php

namespace App\Http\Controllers;

use App\Events\ActivityCreated;
use App\Models\CompanyActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyActivityController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:280'],
        ]);

        $activity = CompanyActivity::query()->create([
            'user_id' => $request->user()->id,
            'description' => $validated['description'],
        ]);

        ActivityCreated::dispatch($activity);

        return to_route('dashboard')->with('success', 'Actividad registrada.');
    }
}
