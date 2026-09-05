<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $credentials = [...$request->safe()->only(['username', 'password']), 'active' => true];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'Username atau password tidak sesuai.'])->onlyInput('username');
        }

        $request->session()->regenerate();
        $logger->log($request->user(), 'login', 'Pengguna masuk ke sistem.');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $logger->log($request->user(), 'logout', 'Pengguna keluar dari sistem.');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
