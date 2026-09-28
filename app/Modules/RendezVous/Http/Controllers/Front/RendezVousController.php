<?php

namespace App\Modules\RendezVous\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class RendezVousController extends Controller
{
    public function index()
    {
        return view('front.rdv');
    }
}
