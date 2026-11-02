<?php

namespace App\Modules\Messagerie\Database\Seeders;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Auth\Models\User;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Models\Message;
use App\Modules\Signalements\Models\Signalement;
use Illuminate\Database\Seeder;

/**
 * Conversations de démonstration entre l'administration et de vrais comptes
 * (auteur d'un signalement, responsable d'un atelier en attente, citoyen).
 * Relations : interlocuteur / admin → User, messages (hasMany), contexte (morphTo).
 * Idempotent : ne touche qu'aux documents `source = seed`.
 */
class MessagerieSeeder extends Seeder
{
    public function run(): void
    {
        $anciennes = Conversation::where('source', 'seed')->get();
        Message::whereIn('conversation_id', $anciennes->map(fn ($c) => (string) $c->getKey())->all())->delete();
        Conversation::where('source', 'seed')->delete();

        $admin = User::where('role', User::ROLE_ADMIN)->orderBy('created_at')->first();

        if (! $admin) {
            $this->command?->warn('Aucun administrateur : lancez d\'abord AdminUserSeeder.');

            return;
        }

        $nb = 0;

        // 1. Admin → auteur d'un signalement (contexte : Signalement)
        $signalement = Signalement::whereNotNull('user_id')->orderBy('created_at', 'desc')->get()
            ->first(fn ($s) => $s->auteur && ! $s->auteur->isAdmin());
        if ($signalement) {
            Conversation::factory()
                ->for($signalement->auteur, 'interlocuteur')
                ->for($admin, 'admin')
                ->surContexte($signalement)
                ->avecEchange([
                    'Bonjour, merci pour votre signalement concernant « '.$signalement->cibleLabel().' ». Pouvez-vous préciser la date de votre visite ?',
                    'Bonjour, c\'était samedi dernier vers 10h, l\'atelier était fermé alors que les horaires indiquaient une ouverture.',
                    'Merci pour ces précisions. Nous avons contacté le partenaire pour qu\'il corrige ses horaires.',
                ], 1)
                ->create(['sujet' => 'Votre signalement : '.$signalement->cibleLabel(), 'source' => 'seed']);
            $nb++;
        }

        // 2. Admin ↔ responsable d'un atelier (contexte : Atelier)
        $atelier = Atelier::orderBy('created_at')->get()->first(fn ($a) => $a->user);
        if ($atelier) {
            Conversation::factory()
                ->for($atelier->user, 'interlocuteur')
                ->for($admin, 'admin')
                ->surContexte($atelier)
                ->avecEchange([
                    'Bonjour, votre fiche « '.$atelier->nom.' » a été vérifiée. Pourriez-vous compléter vos horaires du samedi ?',
                    'Bonjour, c\'est fait : nous sommes ouverts le samedi de 9h à 13h.',
                ], 0)
                ->create(['sujet' => 'Votre atelier « '.$atelier->nom.' »', 'source' => 'seed']);
            $nb++;
        }

        // 3. Citoyen → équipe (conversation initiée par l'utilisateur, non lue côté admin)
        $citoyen = User::where('role', User::ROLE_CITOYEN)->where('is_active', '!=', false)->orderBy('created_at')->first();
        if ($citoyen) {
            Conversation::factory()
                ->for($citoyen, 'interlocuteur')
                ->for($admin, 'admin')
                ->avecEchange([
                    'Bonjour, comment puis-je savoir si mon vêtement a bien été recyclé ? Merci !',
                ], 1)
                ->create(['sujet' => 'Question sur le recyclage', 'initiee_par' => Conversation::COTE_UTILISATEUR, 'source' => 'seed']);
            $nb++;

            // 4. Conversation fermée (historique)
            Conversation::factory()
                ->for($citoyen, 'interlocuteur')
                ->for($admin, 'admin')
                ->fermee()
                ->avecEchange([
                    'Bonjour, votre compte a bien été vérifié. Bonne utilisation de TexTileCycle !',
                    'Merci beaucoup !',
                ], 0)
                ->create(['sujet' => 'Bienvenue sur TexTileCycle', 'source' => 'seed']);
            $nb++;
        }

        $this->command?->info("{$nb} conversations de démonstration créées.");
    }
}
