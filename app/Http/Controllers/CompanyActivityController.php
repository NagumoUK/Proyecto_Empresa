<?php

namespace App\Http\Controllers;

use App\Contracts\CompanyActivityRecorder;
use App\Http\Requests\StoreCompanyActivityRequest;
use Illuminate\Http\RedirectResponse;

class CompanyActivityController extends Controller
{
    public function __construct(private CompanyActivityRecorder $activities) {}

    public function store(StoreCompanyActivityRequest $request): RedirectResponse
    {
        $this->activities->record($request->user(), $request->validated('description'));

        return to_route('dashboard')->with('success', 'Actividad registrada.');
    }
}
