/**
 * TexTileCycle · Validation en temps réel des formulaires « Ateliers & services » (Laravel Precognition).
 * Aucune règle ici : à la sortie d'un champ modifié, le formulaire est envoyé à sa propre route avec
 * l'en-tête « Precognition ». Laravel exécute la FormRequest, s'arrête avant le contrôleur (aucune
 * écriture) et répond 204 (valide) ou 422 (messages en JSON). Ce script affiche ou efface les messages
 * avec le même HTML que les erreurs Blade. Sans JavaScript, l'envoi classique avec rechargement reste inchangé.
 * Aucune donnée n'est injectée en HTML : uniquement textContent / setAttribute.
 */
(() => {
  'use strict';

  const FORMULAIRES = 'form[data-atelier-form], form[data-service-form]';

  // Champs liés par une règle (required_with) : validés et affichés ensemble.
  const GROUPES = { latitude: ['latitude', 'longitude'], longitude: ['latitude', 'longitude'] };

  const refreshIcons = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  };

  /** « horaires[lundi][plages][0][debut] » ou « horaires.lundi » -> « horaires » : clé de la règle dans la FormRequest. */
  const racine = (nom) => nom.split(/[.[]/)[0];

  const valeurDe = (champ) => (champ.type === 'checkbox' ? String(champ.checked) : champ.value);

  const estChamp = (form, cible) => cible instanceof HTMLElement
    && cible.form === form
    && Boolean(cible.name)
    && !['hidden', 'submit', 'button'].includes(cible.type);

  const champVisible = (form, cle) => {
    const champ = form.elements.namedItem(cle);
    return champ instanceof HTMLElement && champ.type !== 'hidden' ? champ : null;
  };

  /* ------------------------------------------------------------------ */
  /* Requête de validation                                               */
  /* ------------------------------------------------------------------ */

  /**
   * cles = null : tout le formulaire. Résout avec l'objet « errors » de Laravel ({} si valide),
   * ou null pour toute autre réponse (session expirée, accès refusé, trop de requêtes…) :
   * dans ce cas c'est l'envoi classique qui décide.
   */
  const demanderValidation = (form, cles, signal) => {
    const entetes = { Precognition: 'true', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (cles) entetes['Precognition-Validate-Only'] = cles.join(',');

    // Le formulaire porte déjà _token (CSRF) et, en modification, _method=PUT.
    return fetch(form.action, {
      method: 'POST',
      headers: entetes,
      body: new FormData(form),
      credentials: 'same-origin',
      signal,
    }).then((reponse) => {
      if (reponse.status === 204) return {};
      if (reponse.status === 422) return reponse.json().then((donnees) => donnees.errors ?? {});
      return null;
    });
  };

  /* ------------------------------------------------------------------ */
  /* Affichage (même balisage que les erreurs Blade)                     */
  /* ------------------------------------------------------------------ */

  const icone = () => {
    const i = document.createElement('i');
    i.setAttribute('data-lucide', 'alert-circle');
    i.setAttribute('aria-hidden', 'true');
    return i;
  };

  const majDescription = (champ, idErreur, actif) => {
    const ids = (champ.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== idErreur);
    if (actif) ids.push(idErreur);
    if (ids.length) champ.setAttribute('aria-describedby', ids.join(' '));
    else champ.removeAttribute('aria-describedby');
  };

  /** message = null efface l'erreur, y compris celle rendue par Blade (même id). */
  const afficherErreurChamp = (champ, message) => {
    const idErreur = `${champ.id}-error`;
    document.getElementById(idErreur)?.remove();

    const conteneur = champ.closest('.ab-field');
    conteneur?.classList.toggle('has-error', Boolean(message));
    majDescription(champ, idErreur, Boolean(message));

    if (!message) {
      champ.removeAttribute('aria-invalid');
      return;
    }

    champ.setAttribute('aria-invalid', 'true');
    const p = document.createElement('p');
    p.id = idErreur;
    p.className = 'ab-error';
    p.append(icone(), document.createTextNode(` ${message}`));
    (conteneur ?? champ.parentElement).append(p);
  };

  /** Bloc d'erreurs des horaires, placé comme celui de horaires-editeur.blade.php. */
  const afficherErreursHoraires = (form, messages) => {
    form.querySelector('#horaires-error')?.remove();
    const editeur = form.querySelector('[data-hours-editor]');
    if (!editeur || messages.length === 0) return;

    const bloc = document.createElement('div');
    bloc.id = 'horaires-error';
    bloc.className = 'ab-error ab-error--block';
    bloc.setAttribute('role', 'alert');
    const liste = document.createElement('ul');
    messages.forEach((message) => {
      const li = document.createElement('li');
      li.textContent = message;
      liste.append(li);
    });
    bloc.append(icone(), liste);
    editeur.before(bloc);
  };

  /**
   * Met à jour l'affichage des clés validées. Renvoie false si une erreur ne correspond
   * à aucun champ visible (elle ne pourrait pas être montrée sans recharger la page).
   */
  const appliquer = (form, cles, erreurs) => {
    let toutAffiche = true;

    cles.forEach((cle) => {
      const messages = Object.entries(erreurs)
        .filter(([nom]) => racine(nom) === cle)
        .flatMap(([, liste]) => liste);

      if (cle === 'horaires') {
        afficherErreursHoraires(form, messages);
        return;
      }

      const champ = champVisible(form, cle);
      if (champ) afficherErreurChamp(champ, messages[0] ?? null);
      else if (messages.length) toutAffiche = false;
    });

    // Le résumé « Certains champs sont à corriger » affiché après un rechargement décrit une saisie précédente.
    document.querySelector('[data-validation-summary]')?.remove();
    refreshIcons();
    return toutAffiche;
  };

  /* ------------------------------------------------------------------ */
  /* Branchement d'un formulaire                                         */
  /* ------------------------------------------------------------------ */

  const brancher = (form) => {
    const valeursEntree = new WeakMap();
    const enCours = new Map();

    const valider = (cles) => {
      const id = cles.join(',');
      enCours.get(id)?.abort();
      const controleur = new AbortController();
      enCours.set(id, controleur);

      demanderValidation(form, cles, controleur.signal)
        .then((erreurs) => {
          if (erreurs) appliquer(form, cles, erreurs);
        })
        .catch(() => {
          // Réseau coupé ou requête remplacée par une plus récente : rien à afficher.
        })
        .finally(() => {
          if (enCours.get(id) === controleur) enCours.delete(id);
        });
    };

    const clesDe = (champ) => GROUPES[racine(champ.name)] ?? [racine(champ.name)];

    const validerSiModifie = (champ) => {
      const valeur = valeurDe(champ);
      if (valeursEntree.get(champ) === valeur) return;
      valeursEntree.set(champ, valeur);
      valider(clesDe(champ));
    };

    form.addEventListener('focusin', (event) => {
      if (estChamp(form, event.target)) valeursEntree.set(event.target, valeurDe(event.target));
    });

    // Champ texte : validé quand on le quitte, s'il a changé.
    form.addEventListener('focusout', (event) => {
      if (estChamp(form, event.target)) validerSiModifie(event.target);
    });

    // Liste déroulante ou case « Fermé » : validée dès qu'elle change.
    form.addEventListener('change', (event) => {
      const cible = event.target;
      if (estChamp(form, cible) && (cible.tagName === 'SELECT' || cible.type === 'checkbox')) validerSiModifie(cible);
    });

    // Plage horaire ajoutée ou supprimée : on revalide si des erreurs d'horaires sont affichées.
    form.addEventListener('click', (event) => {
      if (!event.target.closest?.('[data-add-slot], [data-remove-slot]')) return;
      if (!form.querySelector('#horaires-error')) return;
      window.setTimeout(() => valider(['horaires']), 0);
    });

    // Position placée ou effacée depuis la carte (ateliers-back.js) : on revalide si une erreur est affichée.
    form.addEventListener('ateliers:position', () => {
      if (form.querySelector('[name="latitude"][aria-invalid], [name="longitude"][aria-invalid]')) {
        valider(['latitude', 'longitude']);
      }
    });

    /* Envoi : validation complète d'abord. Erreurs -> affichées sans recharger ; valide -> envoi réel. */
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const bouton = form.querySelector('[type="submit"]');
      if (bouton) {
        bouton.disabled = true;
        bouton.setAttribute('aria-busy', 'true');
      }
      const liberer = () => {
        if (!bouton) return;
        bouton.disabled = false;
        bouton.removeAttribute('aria-busy');
      };

      demanderValidation(form, null)
        .then((erreurs) => {
          if (erreurs === null || Object.keys(erreurs).length === 0) {
            form.submit();
            return;
          }

          const cles = new Set(Object.keys(erreurs).map(racine));
          Array.from(form.elements).forEach((champ) => {
            if (champ.name) cles.add(racine(champ.name));
          });

          if (!appliquer(form, Array.from(cles), erreurs)) {
            form.submit();
            return;
          }

          liberer();
          const premiere = form.querySelector('[aria-invalid="true"], #horaires-error');
          premiere?.scrollIntoView({ block: 'center' });
          if (premiere?.matches('input, select, textarea')) premiere.focus({ preventScroll: true });
        })
        .catch(() => form.submit());
    });
  };

  const init = () => document.querySelectorAll(FORMULAIRES).forEach(brancher);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
