<?php

namespace App\Modules\Messagerie\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Messagerie\Models\Conversation;

/**
 * - Participants (citoyen, partenaire contacté, équipe admin pour un échange support) : lecture et réponse.
 * - Administrateurs : lecture de tous les échanges (modération), modification et suppression.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->isAdmin() || $conversation->estParticipant($user);
    }

    public function repondre(User $user, Conversation $conversation): bool
    {
        return $conversation->estParticipant($user) && $conversation->estOuverte();
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $user->isAdmin();
    }
}
