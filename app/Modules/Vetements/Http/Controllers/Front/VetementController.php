<?php

namespace App\Modules\Vetements\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class VetementController extends Controller
{
    public function index()
    {
        $clothes = [
            ['name' => 'Veste en jean', 'type' => 'Veste', 'size' => 'M', 'condition' => 'Bon état', 'status' => 'En attente', 'tone' => 'orange', 'image' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&q=80', 'done' => 2],
            ['name' => 'Pull en laine', 'type' => 'Pull', 'size' => 'L', 'condition' => 'À réparer', 'status' => 'En réparation', 'tone' => 'blue', 'image' => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=500&q=80', 'done' => 2],
            ['name' => 'Pantalon chino', 'type' => 'Pantalon', 'size' => '42', 'condition' => 'Très bon état', 'status' => 'Donné', 'tone' => 'purple', 'image' => 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=500&q=80', 'done' => 3],
        ];

        return view('front.vetements', compact('clothes'));
    }
}
