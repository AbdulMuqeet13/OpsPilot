<?php

namespace App\Http\Controllers;

use App\Actions\Demo\CreateDemoSandbox;
use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DemoSandboxController extends Controller
{
    public function store(Request $request, UserRole $role, CreateDemoSandbox $createDemoSandbox): RedirectResponse
    {
        $user = $createDemoSandbox->handle($role);

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "You're signed in as {$user->name} ({$role->label()}). This sandbox is yours for the next ".config('demo.sandbox.lifetime_hours').' hours.',
        ]);

        return redirect()->route('dashboard');
    }
}
