<?php

namespace App\Modules\Associations\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class AssociationController extends Controller
{
    public function index()
    {
        return view('front.associations', ['donation' => false]);
    }
}
