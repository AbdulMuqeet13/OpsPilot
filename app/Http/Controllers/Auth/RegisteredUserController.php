<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterWorkspaceOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    public function store(RegisterRequest $request, RegisterWorkspaceOwner $registerWorkspaceOwner): RedirectResponse
    {
        $user = $registerWorkspaceOwner->handle($request->registrationDetails());

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
