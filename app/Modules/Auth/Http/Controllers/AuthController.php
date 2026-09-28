<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function login()
    {
        return view('front.auth.login');
    }

    public function register()
    {
        return view('front.auth.register');
    }
}
