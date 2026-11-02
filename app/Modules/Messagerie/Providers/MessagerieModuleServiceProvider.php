<?php

namespace App\Modules\Messagerie\Providers;

use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Policies\ConversationPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MessagerieModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/Messagerie/Database/Migrations'));

        Gate::policy(Conversation::class, ConversationPolicy::class);

        // Alias morph des contextes (contexte_type) : fusionnés avec la morph map existante.
        Relation::morphMap(Conversation::CONTEXTES);
    }
}
