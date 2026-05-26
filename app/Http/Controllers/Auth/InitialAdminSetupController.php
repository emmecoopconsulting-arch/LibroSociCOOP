<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InitialAdminSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InitialAdminSetupController extends Controller
{
    public function show(InitialAdminSetupService $setup): View|RedirectResponse
    {
        if (! $setup->isRequired()) {
            return redirect('/admin');
        }

        return view('auth.initial-admin-setup');
    }

    public function store(Request $request, InitialAdminSetupService $setup): RedirectResponse
    {
        $admin = $setup->defaultAdmin();

        if ((! $admin) || (! $setup->isRequired())) {
            return redirect('/admin');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'alpha_dash:ascii', 'max:255', 'unique:users,username,'.$admin->id],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $admin->forceFill([
            'name' => $data['username'],
            'username' => $data['username'],
            'password' => $data['password'],
        ])->save();

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
