<?php

namespace App\Modules\Associations\Http\Controllers\Front;

use App\Modules\Associations\Models\Association;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AssociationController extends Controller
{
    /** Liste publique des associations actives. */
    public function index(Request $request)
    {
        $associations = Association::where('statut', Association::STATUT_ACTIF)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('front.associations', [
            'associations' => $associations,
            'donation'     => false,
        ]);
    }
}
