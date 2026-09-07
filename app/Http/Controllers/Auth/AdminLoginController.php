<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.admin-login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if (! Auth::user()->isAdmin()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }
}
