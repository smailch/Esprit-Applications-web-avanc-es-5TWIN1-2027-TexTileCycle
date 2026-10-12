/**
 * TexTileCycle · Diagnostic par photo.
 * - Aperçu de la photo et glisser-déposer.
 * - Envoi sans rechargement (fetch) : la photo reste affichée et l'écran « résultat » remplace le formulaire.
 * - « Ce n'est pas ça ? » : relance l'analyse avec le défaut choisi par le citoyen.
 * Aucun contrôle de saisie ici : tout est validé en PHP (DiagnosticPhotoRequest), le script affiche les messages reçus.
 * Sans JavaScript (ou sans fetch), l'envoi classique avec rechargement fonctionne.
 * Les messages sont insérés avec textContent ; le résultat est du HTML rendu et échappé par Blade.
 */
(() => {
  'use strict';

  const form = document.querySelector('[data-diagnostic-form]');
  const page = form ? form.closest('.diag') : null;
  if (!form || !page) return;

  const ecranSaisie = page.querySelector('[data-ecran-saisie]');
  const zoneResultat = page.querySelector('[data-resultat-zone]');
  const annonce = page.querySelector('[data-annonce]');
  const alerteZone = form.querySelector('[data-alerte-zone]');
  const zone = form.querySelector('[data-depot]');
  const input = form.querySelector('[data-depot-input]');
  const apercu = form.querySelector('[data-depot-apercu]');
  const nom = form.querySelector('[data-depot-nom]');
  const texte = form.querySelector('#diagnostic-texte');
  const aidePhoto = form.querySelector('#diagnostic-photo-aide');
  const bouton = form.querySelector('[type="submit"]');
  const libelle = form.querySelector('[data-envoi-libelle]');
  const libelleInitial = libelle ? libelle.textContent : '';
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fetchDisponible = Boolean(window.fetch && window.FormData && window.DOMParser);

  if (!zone || !input || !apercu || !nom) return;

  const MESSAGES = {
    419: 'La page a expiré : rechargez-la, puis relancez le diagnostic.',
    429: "Trop d'analyses en peu de temps : réessayez dans une minute.",
    defaut: "Le diagnostic n'a pas pu aboutir. Réessayez dans un instant ou cherchez un atelier par service.",
  };

  let urlApercu = null;

  const refreshIcons = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
  };

  const icone = (nomIcone) => {
    const i = document.createElement('i');
    i.setAttribute('data-lucide', nomIcone);
    i.setAttribute('aria-hidden', 'true');
    return i;
  };

  /* ------------------------------------------------------------------ */
  /* Aperçu et glisser-déposer                                           */
  /* ------------------------------------------------------------------ */

  const afficher = (fichier) => {
    if (urlApercu) URL.revokeObjectURL(urlApercu);
    urlApercu = null;

    if (!fichier || !fichier.type.startsWith('image/')) {
      zone.classList.remove('a-une-photo');
      apercu.removeAttribute('src');
      nom.textContent = fichier ? fichier.name : '';
      return;
    }

    urlApercu = URL.createObjectURL(fichier);
    apercu.src = urlApercu;
    nom.textContent = fichier.name;
    zone.classList.add('a-une-photo');
  };

  input.addEventListener('change', () => afficher(input.files[0]));

  ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => {
    event.preventDefault();
    zone.classList.add('est-survole');
  }));

  ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (event) => {
    event.preventDefault();
    zone.classList.remove('est-survole');
  }));

  zone.addEventListener('drop', (event) => {
    const fichiers = event.dataTransfer ? event.dataTransfer.files : null;
    if (!fichiers || fichiers.length === 0) return;
    input.files = fichiers;
    afficher(fichiers[0]);
  });

  /* ------------------------------------------------------------------ */
  /* Position facultative : classer les ateliers par distance            */
  /* ------------------------------------------------------------------ */

  const blocPosition = form.querySelector('[data-position]');
  if (blocPosition && navigator.geolocation) {
    const boutonPosition = blocPosition.querySelector('[data-position-bouton]');
    const libellePosition = blocPosition.querySelector('[data-position-libelle]');
    const etatPosition = blocPosition.querySelector('[data-position-etat]');
    const latitude = blocPosition.querySelector('[data-position-lat]');
    const longitude = blocPosition.querySelector('[data-position-lng]');
    const apercuPosition = blocPosition.querySelector('[data-position-apercu]');
    const cartePosition = blocPosition.querySelector('[data-position-carte]');
    const coordonnees = blocPosition.querySelector('[data-position-coordonnees]');
    let carte = null;
    let point = null;
    let cercle = null;

    const nombre = (valeur, decimales) => valeur.toFixed(decimales).replace('.', ',');

    /** Petite carte de contrôle : le point de la position et un cercle de la précision annoncée par le navigateur. */
    const montrerPosition = (lat, lng, precision) => {
      if (!apercuPosition) return;
      apercuPosition.hidden = false;
      if (coordonnees) {
        coordonnees.textContent = `Latitude ${nombre(lat, 5)}, longitude ${nombre(lng, 5)}`
          + (precision ? `, précision d'environ ${Math.round(precision)} m` : '');
      }

      // Sans Leaflet (CDN bloqué), seules les coordonnées sont affichées.
      if (!window.L || !cartePosition) {
        if (cartePosition) cartePosition.hidden = true;
        return;
      }

      const centre = [lat, lng];

      if (!carte) {
        // La vue initiale est nécessaire avant d'ajouter le cercle : son calcul d'emprise dépend de la projection de la carte.
        carte = window.L.map(cartePosition, { scrollWheelZoom: false, zoomControl: true, attributionControl: true })
          .setView(centre, 15);
        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(carte);
      }
      if (cercle) cercle.remove();
      cercle = precision ? window.L.circle(centre, { radius: precision, color: '#2E7D32', weight: 1, fillOpacity: 0.08 }).addTo(carte) : null;
      if (point) point.setLatLng(centre);
      else {
        point = window.L.circleMarker(centre, { radius: 8, color: '#ffffff', weight: 3, fillColor: '#2E7D32', fillOpacity: 1 })
          .bindTooltip('Votre position')
          .addTo(carte);
      }

      // La carte vient d'apparaître : Leaflet doit recalculer sa taille avant de se centrer.
      window.setTimeout(() => {
        carte.invalidateSize();
        if (cercle) carte.fitBounds(cercle.getBounds(), { maxZoom: 16, padding: [16, 16] });
        else carte.setView(centre, 15);
      }, 0);
    };

    const cacherPosition = () => {
      if (apercuPosition) apercuPosition.hidden = true;
      if (coordonnees) coordonnees.textContent = '';
    };
    const libelleDepart = libellePosition ? libellePosition.textContent : '';
    const erreursPosition = {
      1: 'Position refusée : les ateliers seront classés sans tenir compte de la distance.',
      2: 'Position introuvable pour le moment. Vérifiez que la localisation est activée.',
      3: 'La localisation a pris trop de temps. Réessayez.',
    };

    blocPosition.hidden = false;

    boutonPosition?.addEventListener('click', () => {
      // Second clic : on retire la position.
      if (boutonPosition.getAttribute('aria-pressed') === 'true') {
        latitude.value = '';
        longitude.value = '';
        boutonPosition.setAttribute('aria-pressed', 'false');
        if (libellePosition) libellePosition.textContent = libelleDepart;
        if (etatPosition) etatPosition.textContent = 'Position retirée.';
        cacherPosition();
        return;
      }

      boutonPosition.disabled = true;
      if (etatPosition) etatPosition.textContent = 'Recherche de votre position…';

      navigator.geolocation.getCurrentPosition((resultat) => {
        latitude.value = resultat.coords.latitude.toFixed(5);
        longitude.value = resultat.coords.longitude.toFixed(5);
        boutonPosition.disabled = false;
        boutonPosition.setAttribute('aria-pressed', 'true');
        if (libellePosition) libellePosition.textContent = 'Position utilisée : ateliers classés par distance (cliquez pour la retirer)';
        if (etatPosition) etatPosition.textContent = '';
        montrerPosition(resultat.coords.latitude, resultat.coords.longitude, resultat.coords.accuracy);
      }, (erreur) => {
        boutonPosition.disabled = false;
        if (etatPosition) etatPosition.textContent = erreursPosition[erreur.code] ?? erreursPosition[2];
      }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
    });
  }

  /* ------------------------------------------------------------------ */
  /* Messages (erreurs de validation PHP, service indisponible…)         */
  /* ------------------------------------------------------------------ */

  const creerAlerte = (message) => {
    const alerte = document.createElement('div');
    alerte.className = 'diag-alerte';
    alerte.setAttribute('role', 'alert');
    const p = document.createElement('p');
    p.textContent = message;
    alerte.append(icone('alert-triangle'), p);
    return alerte;
  };

  const creerErreur = (id, message) => {
    const p = document.createElement('p');
    p.id = id;
    p.className = 'diag-erreur';
    p.append(icone('alert-circle'), document.createTextNode(` ${message}`));
    return p;
  };

  const effacerErreurs = () => {
    if (alerteZone) alerteZone.replaceChildren();
    page.querySelectorAll('.diag-alerte, #diagnostic-photo-erreur, #diagnostic-texte-erreur').forEach((el) => el.remove());
    zone.classList.remove('has-error');
    texte?.closest('.diag-champ')?.classList.remove('has-error');
    input.removeAttribute('aria-invalid');
    input.setAttribute('aria-describedby', 'diagnostic-photo-aide');
    texte?.removeAttribute('aria-invalid');
    texte?.removeAttribute('aria-describedby');
  };

  /** Erreurs 422 de la FormRequest : chaque message sous son champ, comme le rendu Blade. */
  const afficherErreurs = (erreurs) => {
    const premiere = (cle) => (Array.isArray(erreurs[cle]) ? erreurs[cle][0] : null);

    const photo = premiere('photo');
    if (photo) {
      zone.classList.add('has-error');
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', 'diagnostic-photo-aide diagnostic-photo-erreur');
      (aidePhoto ?? zone).after(creerErreur('diagnostic-photo-erreur', photo));
    }

    const precision = premiere('texte');
    if (precision && texte) {
      texte.closest('.diag-champ')?.classList.add('has-error');
      texte.setAttribute('aria-invalid', 'true');
      texte.setAttribute('aria-describedby', 'diagnostic-texte-erreur');
      texte.after(creerErreur('diagnostic-texte-erreur', precision));
    }

    const autre = Object.keys(erreurs).find((cle) => cle !== 'photo' && cle !== 'texte');
    if (autre && alerteZone) alerteZone.replaceChildren(creerAlerte(premiere(autre) ?? MESSAGES.defaut));

    refreshIcons();
    (photo ? input : texte)?.focus();
  };

  const messagePour = (statut, corps) => (statut === 503 && corps && typeof corps.message === 'string'
    ? corps.message
    : (MESSAGES[statut] ?? MESSAGES.defaut));

  /* ------------------------------------------------------------------ */
  /* Envoi et écran « résultat »                                         */
  /* ------------------------------------------------------------------ */

  const envoyer = async (donnees) => {
    const reponse = await fetch(form.action, {
      method: 'POST',
      body: donnees,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    let corps = null;
    try {
      corps = await reponse.json();
    } catch (e) {
      corps = null;
    }
    return { statut: reponse.status, corps };
  };

  const montrerResultat = (html) => {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    zoneResultat.replaceChildren(...Array.from(doc.body.childNodes, (noeud) => document.importNode(noeud, true)));

    // La photo n'a jamais quitté le navigateur : on la réaffiche dans l'écran résultat.
    const figure = zoneResultat.querySelector('[data-resultat-photo]');
    const image = figure ? figure.querySelector('[data-resultat-image]') : null;
    if (figure && image && urlApercu) {
      image.src = urlApercu;
      figure.hidden = false;
    }

    const affiner = zoneResultat.querySelector('[data-affiner]');
    if (affiner) affiner.hidden = false;
    if (ecranSaisie) ecranSaisie.hidden = true;

    refreshIcons();
    page.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
    zoneResultat.querySelector('#diag-resultat-titre')?.focus({ preventScroll: true });
    if (annonce) annonce.textContent = 'Résultat du diagnostic prêt.';
  };

  const occuperFormulaire = (actif) => {
    zone.classList.toggle('est-en-analyse', actif);
    if (!bouton) return;
    bouton.disabled = actif;
    if (actif) bouton.setAttribute('aria-busy', 'true');
    else bouton.removeAttribute('aria-busy');
    if (libelle) libelle.textContent = actif ? 'Analyse en cours…' : libelleInitial;
  };

  form.addEventListener('submit', async (event) => {
    if (!fetchDisponible) return; // envoi classique
    event.preventDefault();
    effacerErreurs();
    occuperFormulaire(true);
    if (annonce) annonce.textContent = 'Analyse en cours…';

    try {
      const { statut, corps } = await envoyer(new FormData(form));
      if (statut === 200 && corps && typeof corps.html === 'string') {
        montrerResultat(corps.html);
      } else if (statut === 422 && corps && corps.errors) {
        afficherErreurs(corps.errors);
      } else if (alerteZone) {
        alerteZone.replaceChildren(creerAlerte(messagePour(statut, corps)));
        refreshIcons();
      }
    } catch (e) {
      if (alerteZone) alerteZone.replaceChildren(creerAlerte(MESSAGES.defaut));
      refreshIcons();
    } finally {
      occuperFormulaire(false);
    }
  });

  /* « Ce n'est pas ça ? » : même photo, défaut imposé par le citoyen. */
  zoneResultat.addEventListener('click', async (event) => {
    const choix = event.target.closest('[data-defaut]');
    if (!choix || !fetchDisponible || !input.files.length) return;

    const resultat = zoneResultat.querySelector('[data-resultat]');
    const constat = zoneResultat.querySelector('.diag-resultat__constat');
    const figure = zoneResultat.querySelector('[data-resultat-photo]');
    const reparations = zoneResultat.querySelector('.diag-resultat__reparations');
    const boutons = zoneResultat.querySelectorAll('[data-defaut]');

    zoneResultat.querySelectorAll('.diag-alerte').forEach((el) => el.remove());
    boutons.forEach((b) => { b.disabled = true; });
    figure?.classList.add('est-en-analyse');
    reparations?.classList.add('est-en-analyse');
    resultat?.setAttribute('aria-busy', 'true');
    if (annonce) annonce.textContent = 'Nouvelle analyse en cours…';

    const donnees = new FormData(form);
    donnees.set('defaut', choix.dataset.defaut);

    try {
      const { statut, corps } = await envoyer(donnees);
      if (statut === 200 && corps && typeof corps.html === 'string') {
        montrerResultat(corps.html);
        return;
      }
      constat?.querySelector('.diag-affiner')?.before(creerAlerte(messagePour(statut, corps)));
    } catch (e) {
      constat?.querySelector('.diag-affiner')?.before(creerAlerte(MESSAGES.defaut));
    }

    // Échec : l'ancien résultat reste affiché et utilisable.
    refreshIcons();
    boutons.forEach((b) => { b.disabled = false; });
    figure?.classList.remove('est-en-analyse');
    reparations?.classList.remove('est-en-analyse');
    resultat?.removeAttribute('aria-busy');
  });

  /* « Essayer avec une autre photo » : retour au formulaire vide, sans recharger. */
  zoneResultat.addEventListener('click', (event) => {
    const lien = event.target.closest('[data-recommencer]');
    if (!lien || !ecranSaisie) return;
    event.preventDefault();

    zoneResultat.replaceChildren();
    form.reset();
    afficher(null);
    effacerErreurs();
    ecranSaisie.hidden = false;
    if (annonce) annonce.textContent = '';
    page.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
    input.focus({ preventScroll: true });
  });

  // Retour arrière du navigateur : la page restaurée ne doit pas garder le bouton bloqué.
  window.addEventListener('pageshow', () => occuperFormulaire(false));
})();
