{{--
    Boîte de confirmation personnalisée (remplace window.confirm).
    Incluse une seule fois dans layouts/app. Usage sur n'importe quel formulaire :

    <form method="post" action="..."
          data-confirm="Ce signalement sera définitivement supprimé."
          data-confirm-title="Supprimer le signalement ?"     (optionnel)
          data-confirm-button="Supprimer"                     (optionnel)
          data-confirm-tone="danger|warning|primary">         (optionnel, danger par défaut)
--}}
<dialog id="confirm-dialog" class="confirm-dialog" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
    <form method="dialog" class="confirm-dialog__box">
        <div class="confirm-dialog__icon" data-confirm-icon>
            <i data-lucide="triangle-alert"></i>
        </div>
        <div class="confirm-dialog__body">
            <h2 id="confirm-dialog-title" data-confirm-title>Confirmer l'action</h2>
            <p id="confirm-dialog-message" data-confirm-message></p>
        </div>
        <div class="confirm-dialog__actions">
            <button type="submit" value="cancel" class="btn btn-secondary" data-confirm-cancel>Annuler</button>
            <button type="submit" value="confirm" class="btn confirm-dialog__ok" data-confirm-ok>Confirmer</button>
        </div>
    </form>
</dialog>
