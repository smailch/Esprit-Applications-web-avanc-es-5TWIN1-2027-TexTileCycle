<?php

namespace App\Modules\Messagerie\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Messagerie\Models\Conversation;

/**
 * Seuls l'équipe d'administration et l'interlocuteur de la conversation y ont accès.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->isAdmin() || (string) $conversation->user_id === (string) $user->getKey();
    }

    public function repondre(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $conversation->estOuverte();
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
