{{-- Confirmation des formulaires [data-confirm-delete] (data-nom, data-services). $cible : 'atelier' ou 'service'. --}}
@php $estService = ($cible ?? 'atelier') === 'service'; @endphp

<div class="modal-backdrop" id="ab-delete-modal" aria-hidden="true" data-delete-modal>
    <div class="modal ab-modal" role="dialog" aria-modal="true" aria-labelledby="ab-delete-title" aria-describedby="ab-delete-text">
        <div class="modal-head">
            <div>
                <p class="kicker ab-kicker-danger">Suppression définitive</p>
                <h2 id="ab-delete-title">Supprimer <span data-delete-name>{{ $estService ? 'ce service' : 'cet atelier' }}</span> ?</h2>
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Fermer la fenêtre"><i data-lucide="x" aria-hidden="true"></i></button>
        </div>
        <div class="ab-modal__body">
            <div class="ab-modal__icon" aria-hidden="true"><i data-lucide="trash-2"></i></div>
            <p id="ab-delete-text">
                @if ($estService)
                    Ce service sera retiré de votre catalogue et ne pourra plus être réservé. Cette action est irréversible.
                @else
                    L'atelier et <strong data-delete-services>ses services</strong> seront supprimés. Il disparaîtra du site et cette action est irréversible.
                @endif
            </p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-close-modal data-delete-cancel>Annuler</button>
            <button type="button" class="btn ab-btn-danger" data-delete-confirm><i data-lucide="trash-2" aria-hidden="true"></i> Supprimer définitivement</button>
        </div>
    </div>
</div>
