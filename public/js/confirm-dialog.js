/**
 * Confirmations personnalisées TexTileCycle.
 * Intercepte la soumission des formulaires portant data-confirm et affiche
 * la boîte <dialog id="confirm-dialog"> au lieu du confirm() natif du navigateur.
 */
(() => {
    const dialog = document.getElementById('confirm-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return; // navigateur trop ancien : envoi direct

    const el = (selector) => dialog.querySelector(selector);
    const titre = el('[data-confirm-title]');
    const message = el('[data-confirm-message]');
    const ok = el('[data-confirm-ok]');
    const icone = el('[data-confirm-icon]');

    const TONS = {
        danger: { icon: 'trash-2', title: 'Confirmer la suppression', button: 'Supprimer' },
        warning: { icon: 'triangle-alert', title: 'Confirmer l\'action', button: 'Confirmer' },
        primary: { icon: 'circle-help', title: 'Confirmer l\'action', button: 'Confirmer' },
    };

    let formEnAttente = null;
    let boutonEnAttente = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return; // déjà confirmé : on laisse partir la requête
        }

        event.preventDefault();
        formEnAttente = form;
        boutonEnAttente = event.submitter || null;

        const ton = TONS[form.dataset.confirmTone] ? form.dataset.confirmTone : 'danger';
        dialog.dataset.tone = ton;
        titre.textContent = form.dataset.confirmTitle || TONS[ton].title;
        message.textContent = form.dataset.confirm;
        ok.textContent = form.dataset.confirmButton || TONS[ton].button;
        icone.innerHTML = `<i data-lucide="${TONS[ton].icon}"></i>`;
        if (window.lucide) window.lucide.createIcons({ nameAttr: 'data-lucide' });

        dialog.showModal();
        el('[data-confirm-cancel]').focus(); // action sûre par défaut
    }, true);

    dialog.addEventListener('close', () => {
        const form = formEnAttente;
        const bouton = boutonEnAttente;
        formEnAttente = boutonEnAttente = null;

        if (dialog.returnValue !== 'confirm' || !form) return;

        form.dataset.confirmed = '1';
        // requestSubmit conserve le bouton cliqué (ex. name="statut" value="rejete")
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(bouton && bouton.form === form ? bouton : undefined);
        } else {
            form.submit();
        }
    });

    // Clic sur le fond grisé = annuler
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close('cancel');
    });
})();
