<?php

namespace App\Modules\Dons\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class DonController extends Controller
{
    public function index()
    {
        return view('front.associations', ['donation' => true]);
    }
}
