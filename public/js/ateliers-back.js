/**
 * TexTileCycle · Back office « Ateliers & services ».
 * - Carte de saisie de la position (Leaflet) synchronisée avec latitude / longitude.
 * - Éditeur d'horaires : plages ajoutables / supprimables, case « Fermé ».
 * - Confirmation de suppression via la modale.
 * Aucune donnée n'est injectée en HTML : uniquement textContent / setAttribute.
 */
(() => {
  'use strict';

  const refreshIcons = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  };

  const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
  ].join(', ');

  /** Échap ferme la modale ; Tab et Maj+Tab restent piégés à l'intérieur. */
  const bindModalKeys = (backdrop, close) => {
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

  /* ------------------------------------------------------------------ */
  /* Carte de saisie                                                     */
  /* ------------------------------------------------------------------ */

  const initLocationPicker = (picker) => {
    const form = picker.closest('form');
    const mapEl = picker.querySelector('[data-location-map]');
    const status = picker.querySelector('[data-location-status]');
    const latInput = form?.querySelector('[data-lat-input]');
    const lngInput = form?.querySelector('[data-lng-input]');
    const clearBtn = form?.querySelector('[data-clear-location]');

    if (!mapEl || !latInput || !lngInput) return;

    if (!window.L) {
      mapEl.replaceChildren();
      const p = document.createElement('p');
      p.className = 'ab-map__fallback';
      p.textContent = 'Carte indisponible : saisissez la latitude et la longitude à la main.';
      mapEl.appendChild(p);
      return;
    }

    const defaultCenter = [
      Number.parseFloat(picker.dataset.defaultLat) || 36.8065,
      Number.parseFloat(picker.dataset.defaultLng) || 10.1815,
    ];

    const round = (value) => Math.round(value * 1e6) / 1e6;

    const readInputs = () => {
      const lat = Number.parseFloat(latInput.value);
      const lng = Number.parseFloat(lngInput.value);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
      if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
      return [lat, lng];
    };

    const announce = (message) => {
      if (status) status.textContent = message;
    };

    mapEl.replaceChildren();
    const initial = readInputs();
    const map = window.L.map(mapEl, { scrollWheelZoom: false })
      .setView(initial ?? defaultCenter, initial ? 15 : 12);

    window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    let marker = null;

    const writeInputs = (latlng) => {
      latInput.value = String(round(latlng.lat));
      lngInput.value = String(round(latlng.lng));
      announce(`Position : ${latInput.value}, ${lngInput.value}`);
    };

    const placeMarker = (latlng) => {
      if (marker) {
        marker.setLatLng(latlng);
        return;
      }
      marker = window.L.marker(latlng, {
        draggable: true,
        keyboard: true,
        title: "Position de l'atelier",
        alt: "Position de l'atelier",
      }).addTo(map);
      marker.on('dragend', () => writeInputs(marker.getLatLng()));
    };

    if (initial) placeMarker(initial);

    map.on('click', (event) => {
      placeMarker(event.latlng);
      writeInputs(event.latlng);
    });

    const syncFromInputs = () => {
      const position = readInputs();
      if (!position) return;
      placeMarker(position);
      map.panTo(position);
    };

    latInput.addEventListener('change', syncFromInputs);
    lngInput.addEventListener('change', syncFromInputs);

    clearBtn?.addEventListener('click', () => {
      latInput.value = '';
      lngInput.value = '';
      if (marker) {
        marker.remove();
        marker = null;
      }
      map.setView(defaultCenter, 12);
      announce('Position effacée.');
    });

    // La carte peut être initialisée avant que la mise en page soit stable.
    window.setTimeout(() => map.invalidateSize(), 200);
  };

  /* ------------------------------------------------------------------ */
  /* Éditeur d'horaires                                                  */
  /* ------------------------------------------------------------------ */

  const initHoursEditor = (editor) => {
    const template = editor.parentElement?.querySelector('template[data-slot-template]');
    if (!template) return;

    const replaceTokens = (root, tokens) => {
      const swap = (text) => Object.entries(tokens).reduce((acc, [token, value]) => acc.split(token).join(value), text);

      root.querySelectorAll('*').forEach((el) => {
        Array.from(el.attributes).forEach((attr) => {
          if (attr.value.includes('__')) el.setAttribute(attr.name, swap(attr.value));
        });
      });

      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.nodeValue.includes('__')) node.nodeValue = swap(node.nodeValue);
      }
    };

    const renumber = (day) => {
      const jour = day.dataset.day;
      const slots = day.querySelectorAll('[data-slot]');

      slots.forEach((slot, position) => {
        const num = position + 1;
        const debut = slot.querySelector('[data-slot-label="debut"]');
        const fin = slot.querySelector('[data-slot-label="fin"]');
        if (debut) debut.textContent = `Début de la plage ${num} du ${jour}`;
        if (fin) fin.textContent = `Fin de la plage ${num} du ${jour}`;
        slot.querySelector('[data-remove-slot]')?.setAttribute('aria-label', `Supprimer la plage ${num} du ${jour}`);
      });

      const empty = day.querySelector('[data-day-empty]');
      if (empty) empty.hidden = slots.length > 0;
    };

    const addSlot = (day) => {
      const container = day.querySelector('[data-day-slots]');
      const empty = container?.querySelector('[data-day-empty]');
      if (!container) return;

      const index = Number.parseInt(day.dataset.nextIndex || '0', 10);
      day.dataset.nextIndex = String(index + 1);

      const fragment = template.content.cloneNode(true);
      replaceTokens(fragment, {
        __JOUR__: day.dataset.day,
        __INDEX__: String(index),
        __NUM__: String(container.querySelectorAll('[data-slot]').length + 1),
      });

      const slot = fragment.querySelector('[data-slot]');
      container.insertBefore(fragment, empty ?? null);
      renumber(day);
      refreshIcons();
      slot?.querySelector('input')?.focus();
    };

    const removeSlot = (slot) => {
      const day = slot.closest('[data-day]');
      const next = slot.nextElementSibling?.matches('[data-slot]') ? slot.nextElementSibling : slot.previousElementSibling;
      slot.remove();
      if (!day) return;
      renumber(day);
      (next?.querySelector('input') ?? day.querySelector('[data-add-slot]'))?.focus();
    };

    editor.addEventListener('click', (event) => {
      const addBtn = event.target.closest('[data-add-slot]');
      if (addBtn) {
        const day = addBtn.closest('[data-day]');
        if (day) addSlot(day);
        return;
      }

      const removeBtn = event.target.closest('[data-remove-slot]');
      if (removeBtn) {
        const slot = removeBtn.closest('[data-slot]');
        if (slot) removeSlot(slot);
      }
    });

    editor.addEventListener('change', (event) => {
      const toggle = event.target.closest('[data-day-closed]');
      if (!toggle) return;

      const day = toggle.closest('[data-day]');
      if (!day) return;

      day.classList.toggle('is-closed', toggle.checked);
      if (!toggle.checked && day.querySelectorAll('[data-slot]').length === 0) {
        addSlot(day);
      }
    });
  };

  /* ------------------------------------------------------------------ */
  /* Confirmation de suppression                                         */
  /* ------------------------------------------------------------------ */

  const initDeleteModal = (backdrop) => {
    const nameEl = backdrop.querySelector('[data-delete-name]');
    const servicesEl = backdrop.querySelector('[data-delete-services]');
    const confirmBtn = backdrop.querySelector('[data-delete-confirm]');
    const cancelBtn = backdrop.querySelector('[data-delete-cancel]');

    let pendingForm = null;
    let returnFocus = null;

    const close = () => {
      if (!backdrop.classList.contains('open') && !pendingForm) return;
      backdrop.classList.remove('open');
      backdrop.setAttribute('aria-hidden', 'true');
      pendingForm = null;
      returnFocus?.focus();
      returnFocus = null;
    };

    const open = (form, trigger) => {
      pendingForm = form;
      returnFocus = trigger ?? document.activeElement;

      const nbServices = Number.parseInt(form.dataset.services || '0', 10);
      if (nameEl) nameEl.textContent = form.dataset.nom || 'cet atelier';
      if (servicesEl) {
        servicesEl.textContent = nbServices === 0
          ? 'aucun service'
          : `ses ${nbServices} service${nbServices > 1 ? 's' : ''}`;
      }

      backdrop.classList.add('open');
      backdrop.setAttribute('aria-hidden', 'false');
      refreshIcons();
      (cancelBtn ?? confirmBtn)?.focus();
    };

    document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        open(form, event.submitter ?? form.querySelector('button[type="submit"]'));
      });
    });

    confirmBtn?.addEventListener('click', () => {
      const form = pendingForm;
      if (!form) return;
      confirmBtn.disabled = true;
      returnFocus = null;
      form.submit();
    });

    backdrop.querySelectorAll('[data-close-modal]').forEach((el) => el.addEventListener('click', close));
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop) close();
    });

    bindModalKeys(backdrop, close);
  };

  /* ------------------------------------------------------------------ */
  /* Modale de création / édition d'un service (espace atelier)          */
  /* ------------------------------------------------------------------ */

  const initServiceModal = (backdrop) => {
    const form = backdrop.querySelector('[data-service-form]');
    if (!form) return;

    const title = backdrop.querySelector('[data-service-title]');
    const submitLabel = backdrop.querySelector('[data-service-submit]');
    const methodInput = form.querySelector('[data-service-method]');
    const modeInput = form.querySelector('[data-service-mode]');
    const idInput = form.querySelector('[data-service-id]');
    const fields = {
      nom: form.elements.namedItem('nom'),
      description: form.elements.namedItem('description'),
      prix: form.elements.namedItem('prix_estime'),
      duree: form.elements.namedItem('duree_estimee'),
    };

    let returnFocus = null;

    const clearErrors = () => {
      form.querySelectorAll('.ab-error').forEach((el) => el.remove());
      form.querySelectorAll('.has-error').forEach((el) => el.classList.remove('has-error'));
      form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
      form.querySelectorAll('[aria-describedby]').forEach((el) => {
        const ids = el.getAttribute('aria-describedby').split(/\s+/).filter((id) => id && !id.endsWith('-error'));
        if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
        else el.removeAttribute('aria-describedby');
      });
    };

    const setValues = (values) => {
      Object.entries(fields).forEach(([key, input]) => {
        if (input) input.value = values[key] ?? '';
      });
    };

    const show = (trigger) => {
      returnFocus = trigger ?? document.activeElement;
      backdrop.classList.add('open');
      backdrop.setAttribute('aria-hidden', 'false');
      refreshIcons();
      fields.nom?.focus();
    };

    const close = () => {
      if (!backdrop.classList.contains('open') && !returnFocus) return;
      backdrop.classList.remove('open');
      backdrop.setAttribute('aria-hidden', 'true');
      returnFocus?.focus();
      returnFocus = null;
    };

    const openCreate = (trigger) => {
      clearErrors();
      setValues({});
      form.action = form.dataset.storeAction;
      if (methodInput) methodInput.disabled = true;
      if (modeInput) modeInput.value = 'create';
      if (idInput) idInput.value = '';
      if (title) title.textContent = 'Nouveau service';
      if (submitLabel) submitLabel.textContent = 'Ajouter le service';
      show(trigger);
    };

    const openEdit = (button) => {
      clearErrors();
      setValues({
        nom: button.dataset.nom,
        description: button.dataset.description,
        prix: button.dataset.prix,
        duree: button.dataset.duree,
      });
      form.action = button.dataset.action;
      if (methodInput) methodInput.disabled = false;
      if (modeInput) modeInput.value = 'edit';
      if (idInput) idInput.value = button.dataset.id || '';
      if (title) title.textContent = 'Modifier le service';
      if (submitLabel) submitLabel.textContent = 'Enregistrer les modifications';
      show(button);
    };

    document.querySelectorAll('[data-service-create]').forEach((button) => {
      button.addEventListener('click', () => openCreate(button));
    });

    document.querySelectorAll('[data-service-edit]').forEach((button) => {
      button.addEventListener('click', () => openEdit(button));
    });

    backdrop.querySelectorAll('[data-close-modal]').forEach((el) => el.addEventListener('click', close));
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop) close();
    });
    bindModalKeys(backdrop, close);

    // Rouverte par le serveur après une erreur de validation : focus sur le premier champ invalide.
    if (backdrop.hasAttribute('data-open-on-load')) {
      const serviceId = idInput?.value || '';
      returnFocus = Array.from(document.querySelectorAll('[data-service-edit]')).find((b) => b.dataset.id === serviceId)
        ?? document.querySelector('[data-service-create]');
      (form.querySelector('[aria-invalid="true"]') ?? fields.nom)?.focus();
    }
  };

  /* ------------------------------------------------------------------ */

  const init = () => {
    document.querySelectorAll('[data-location-picker]').forEach(initLocationPicker);
    document.querySelectorAll('[data-hours-editor]').forEach(initHoursEditor);
    document.querySelectorAll('[data-delete-modal]').forEach(initDeleteModal);
    document.querySelectorAll('[data-service-modal]').forEach(initServiceModal);
    refreshIcons();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
