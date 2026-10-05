<?php

namespace App\Modules\Ateliers\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class AtelierController extends Controller
{
    public function index()
    {
        $workshops = [
            ['name' => 'Couture Plus', 'place' => 'La Marsa', 'rating' => '4.9', 'specialty' => 'Denim & retouches', 'distance' => '1,2 km'],
            ['name' => "L'Atelier Vert", 'place' => 'Centre-ville, Tunis', 'rating' => '4.8', 'specialty' => 'Upcycling créatif', 'distance' => '3,4 km'],
            ['name' => 'Fil & Aiguille', 'place' => 'El Menzah', 'rating' => '4.7', 'specialty' => 'Tricot & textile', 'distance' => '5,1 km'],
        ];

        return view('front.ateliers', compact('workshops'));
    }
}
