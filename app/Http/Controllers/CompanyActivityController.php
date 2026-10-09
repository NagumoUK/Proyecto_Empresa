<?php

namespace App\Http\Controllers;

use App\Contracts\CompanyActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyActivityController extends Controller
{
    public function __construct(private CompanyActivityRecorder $activities) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:280'],
        ]);

        $this->activities->record($request->user(), $validated['description']);

        return to_route('dashboard')->with('success', 'Actividad registrada.');
    }
}
