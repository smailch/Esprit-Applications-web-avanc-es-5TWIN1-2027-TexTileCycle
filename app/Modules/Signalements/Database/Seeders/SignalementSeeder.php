<?php

namespace App\Modules\Signalements\Database\Seeders;

use App\Modules\Associations\Models\Association;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Auth\Models\User;
use App\Modules\Signalements\Models\Signalement;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Signalements de démonstration reliés aux vrais comptes et contenus de la plateforme
 * (relations auteur → User, traitePar → User admin, cible → Atelier / Vetement / Association / User).
 * Idempotent : ne touche qu'aux signalements `source = seed`.
 */
class SignalementSeeder extends Seeder
{
    public const EMAIL_DOMAIN = '@textilecycle.test';

    public function run(): void
    {
        Signalement::where('source', 'seed')->delete();

        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $citoyens = $this->citoyens();
        $cibles = collect()
            ->merge(Atelier::orderBy('created_at')->take(3)->get())
            ->merge(Vetement::orderBy('created_at')->take(2)->get())
            ->merge(Association::orderBy('created_at')->take(1)->get())
            ->merge($citoyens->slice(1, 1));

        if ($cibles->isEmpty()) {
            $this->command?->warn('Aucun atelier, vêtement ou association : lancez d\'abord les seeders des autres modules.');

            return;
        }

        $etats = ['enAttente', 'enAttente', 'traite', 'enAttente', 'rejete', 'enAttente', 'traite'];

        foreach ($cibles->values() as $i => $cible) {
            $etat = $etats[$i % count($etats)];
            $factory = Signalement::factory()
                ->for($citoyens[$i % $citoyens->count()], 'auteur')
                ->pour($cible);

            $factory = $etat === 'enAttente' ? $factory->enAttente() : $factory->{$etat}($admin);

            $factory->create([
                'source' => 'seed',
                'created_at' => now()->subDays(($cibles->count() - $i) * 3),
            ]);
        }

        $this->command?->info($cibles->count().' signalements de démonstration créés.');
    }

    /**
     * Citoyens existants, ou comptes de test créés via la factory si la base est vide.
     */
    private function citoyens()
    {
        $citoyens = User::where('role', User::ROLE_CITOYEN)->orderBy('created_at')->take(4)->get();

        if ($citoyens->count() >= 2) {
            return $citoyens;
        }

        return collect(range(1, 3))->map(fn (int $n) => User::where('email', 'citoyen'.$n.self::EMAIL_DOMAIN)->first()
            ?? User::factory()->create([
                'email' => 'citoyen'.$n.self::EMAIL_DOMAIN,
                'password' => Hash::make('Citoyen2026!'),
                'role' => User::ROLE_CITOYEN,
                'is_active' => true,
            ]));
    }
}
