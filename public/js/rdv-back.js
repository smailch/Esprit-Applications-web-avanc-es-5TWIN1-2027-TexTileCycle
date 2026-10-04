/**
 * TexTileCycle · Back office « Rendez-vous ».
 * Les formulaires [data-motif-form] (refus, annulation) demandent un motif dans la modale
 * avant d'être envoyés. Aucune donnée n'est injectée en HTML : uniquement textContent / value.
 */
(() => {
  'use strict';

  const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
  ].join(', ');

  const refreshIcons = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  };

  const initMotifModal = (backdrop) => {
    const title = backdrop.querySelector('[data-motif-title]');
    const label = backdrop.querySelector('[data-motif-label]');
    const input = backdrop.querySelector('[data-motif-input]');
    const error = backdrop.querySelector('[data-motif-error]');
    const confirmBtn = backdrop.querySelector('[data-motif-confirm]');
    if (!input || !confirmBtn) return;

    let pendingForm = null;
    let returnFocus = null;

    const requis = () => pendingForm?.dataset.motifRequis === '1';

    const setError = (visible) => {
      if (!error) return;
      error.hidden = !visible;
      if (visible) {
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', 'rdv-motif-hint rdv-motif-error');
        error.id = 'rdv-motif-error';
      } else {
        input.removeAttribute('aria-invalid');
        input.setAttribute('aria-describedby', 'rdv-motif-hint');
      }
    };

    const close = () => {
      if (!backdrop.classList.contains('open')) return;
      backdrop.classList.remove('open');
      backdrop.setAttribute('aria-hidden', 'true');
      pendingForm = null;
      returnFocus?.focus();
      returnFocus = null;
    };

    const open = (form, trigger) => {
      pendingForm = form;
      returnFocus = trigger ?? document.activeElement;
      if (title) title.textContent = form.dataset.titre || 'Rendez-vous';
      if (label) label.textContent = requis() ? 'Motif du refus (obligatoire)' : "Motif de l'annulation (facultatif)";
      input.value = '';
      input.required = requis();
      setError(false);
      backdrop.classList.add('open');
      backdrop.setAttribute('aria-hidden', 'false');
      refreshIcons();
      input.focus();
    };

    document.querySelectorAll('form[data-motif-form]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (form.dataset.motifValide === '1') return;
        event.preventDefault();
        open(form, event.submitter ?? form.querySelector('button[type="submit"]'));
      });
    });

    confirmBtn.addEventListener('click', () => {
      const form = pendingForm;
      if (!form) return;

      const motif = input.value.trim();
      if (requis() && motif === '') {
        setError(true);
        input.focus();
        return;
      }

      const champ = form.querySelector('input[name="motif"]');
      if (champ) champ.value = motif;
      form.dataset.motifValide = '1';
      confirmBtn.disabled = true;
      returnFocus = null;
      form.submit();
    });

    input.addEventListener('input', () => {
      if (input.value.trim() !== '') setError(false);
    });

    backdrop.querySelectorAll('[data-close-modal]').forEach((el) => el.addEventListener('click', close));
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop) close();
    });

    backdrop.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
      }

      if (event.key !== 'Tab') return;

      const focusables = Array.from(backdrop.querySelectorAll(FOCUSABLE));
      if (focusables.length === 0) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  };

  const init = () => {
    document.querySelectorAll('[data-motif-modal]').forEach(initMotifModal);
    refreshIcons();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
