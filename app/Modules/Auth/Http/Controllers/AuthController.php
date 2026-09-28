<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Services\RoleNavigationService;
use App\Modules\Auth\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private UserService $users
    ) {
    }

    public function login()
    {
        return view('front.auth.login');
    }

    public function loginStore(LoginRequest $request)
    {
        $user = $this->users->verifyCredentials(
            $request->input('email'),
            $request->input('password')
        );

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Identifiants incorrects ou compte inactif.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(RoleNavigationService::redirectAfterLogin($user));
    }

    public function register()
    {
        return view('front.auth.register');
    }

    public function registerStore(RegisterRequest $request)
    {
        $user = $this->users->create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(RoleNavigationService::redirectAfterLogin($user))
            ->with('success', 'Bienvenue sur TexTileCycle, '.$user->name.' !');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('front.home');
    }
}
