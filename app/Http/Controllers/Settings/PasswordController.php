<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password updated.']);

        return back();
    }
}
