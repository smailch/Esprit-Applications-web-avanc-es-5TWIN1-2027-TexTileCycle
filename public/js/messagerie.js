/**
 * Messagerie TexTileCycle — polling AJAX (sans WebSocket ni service externe).
 *  - fil ouvert : nouveaux messages toutes les 4 s, envoi sans rechargement, accusé « Lu » ;
 *  - badges [data-messagerie-badge] : nombre de conversations non lues toutes les 30 s.
 * Le contenu des messages est toujours inséré avec textContent (aucun HTML interprété).
 */
(() => {
    const INTERVALLE_FIL = 4000;
    const INTERVALLE_BADGE = 30000;
    const json = { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };

    /* ------------------------------------------------------------ badges */
    const badges = document.querySelectorAll('[data-messagerie-badge]');

    const majBadges = () => {
        const urls = new Set([...badges].map((b) => b.dataset.messagerieBadge));
        urls.forEach((url) => {
            fetch(url, json)
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    if (!data) return;
                    document.querySelectorAll(`[data-messagerie-badge="${url}"]`).forEach((b) => {
                        b.textContent = data.non_lues > 99 ? '99+' : String(data.non_lues);
                        b.hidden = data.non_lues === 0;
                        b.setAttribute('aria-label', `${data.non_lues} conversation(s) non lue(s)`);
                    });
                })
                .catch(() => {});
        });
    };

    if (badges.length) {
        majBadges();
        setInterval(() => { if (!document.hidden) majBadges(); }, INTERVALLE_BADGE);
    }

    /* ------------------------------------------------------------ recherche de destinataire */
    const dest = document.querySelector('[data-messagerie-destinataire]');
    if (dest) {
        const MIN = 3;
        const champ = dest.querySelector('[data-dest-recherche]');
        const resultats = dest.querySelector('[data-dest-resultats]');
        const aide = dest.querySelector('[data-dest-aide]');
        const valeur = dest.querySelector('[data-dest-valeur]');
        const choisi = dest.querySelector('[data-dest-choisi]');
        const zone = dest.querySelector('[data-dest-zone]');
        let minuterie = null;
        let actif = -1;
        let requete = 0;

        const fermer = () => {
            resultats.hidden = true;
            resultats.innerHTML = '';
            champ.setAttribute('aria-expanded', 'false');
            actif = -1;
        };

        const choisir = (item) => {
            valeur.value = item.valeur;
            dest.querySelector('[data-dest-nom]').textContent = item.nom;
            choisi.hidden = false;
            zone.hidden = true;
            fermer();
        };

        const surligner = (index) => {
            const options = resultats.querySelectorAll('[role="option"]');
            options.forEach((o, i) => o.setAttribute('aria-selected', String(i === index)));
            actif = index;
            champ.setAttribute('aria-activedescendant', options[index] ? options[index].id : '');
            if (options[index]) options[index].scrollIntoView({ block: 'nearest' });
        };

        const afficher = (items) => {
            resultats.innerHTML = '';
            if (!items.length) {
                aide.textContent = 'Aucun résultat. Essayez un autre nom ou « équipe ».';
                fermer();
                return;
            }
            items.forEach((item, i) => {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.id = `msg-dest-option-${i}`;
                li.className = 'msg-dest__option';
                const icone = document.createElement('i');
                icone.dataset.lucide = item.icone;
                const texte = document.createElement('span');
                const nom = document.createElement('b');
                nom.textContent = item.nom;
                const detail = document.createElement('small');
                detail.textContent = item.detail;
                texte.append(nom, detail);
                li.append(icone, texte);
                li.addEventListener('mousedown', (e) => { e.preventDefault(); choisir(item); });
                li._item = item;
                resultats.appendChild(li);
            });
            if (window.lucide) window.lucide.createIcons();
            resultats.hidden = false;
            champ.setAttribute('aria-expanded', 'true');
            aide.textContent = `${items.length} résultat(s) — flèches pour naviguer, Entrée pour choisir.`;
            surligner(0);
        };

        champ.addEventListener('input', () => {
            valeur.value = '';
            clearTimeout(minuterie);
            const q = champ.value.trim();
            if (q.length < MIN) {
                fermer();
                aide.textContent = q.length ? `Encore ${MIN - q.length} caractère(s)…` : 'Exemples : « Couture », « Marsa », « équipe ».';
                return;
            }
            aide.textContent = 'Recherche…';
            minuterie = setTimeout(() => {
                const numero = ++requete;
                const url = new URL(dest.dataset.url, location.origin);
                url.searchParams.set('q', q);
                fetch(url, json)
                    .then((r) => (r.ok ? r.json() : { resultats: [] }))
                    .then((data) => { if (numero === requete) afficher(data.resultats || []); })
                    .catch(() => { aide.textContent = 'La recherche est indisponible pour le moment.'; });
            }, 250);
        });

        champ.addEventListener('keydown', (e) => {
            const options = resultats.querySelectorAll('[role="option"]');
            if (e.key === 'ArrowDown' && options.length) { e.preventDefault(); surligner((actif + 1) % options.length); }
            if (e.key === 'ArrowUp' && options.length) { e.preventDefault(); surligner((actif - 1 + options.length) % options.length); }
            if (e.key === 'Enter') {
                e.preventDefault(); // ne pas envoyer le formulaire sans destinataire choisi
                if (options[actif]) choisir(options[actif]._item);
            }
            if (e.key === 'Escape') fermer();
        });
        champ.addEventListener('blur', () => setTimeout(fermer, 150));

        dest.querySelector('[data-dest-effacer]').addEventListener('click', () => {
            valeur.value = '';
            choisi.hidden = true;
            zone.hidden = false;
            champ.value = '';
            champ.focus();
        });
    }

    /* ------------------------------------------------------------ fil de discussion */
    const fil = document.querySelector('[data-messagerie-fil]');
    if (!fil) return;

    const liste = fil.querySelector('[data-messagerie-messages]');
    const fin = liste.querySelector('#fin');
    const form = fil.querySelector('[data-messagerie-form]');
    const zone = form ? form.querySelector('textarea') : null;
    const erreur = form ? form.querySelector('[data-messagerie-erreur]') : null;
    const ids = new Set([...liste.querySelectorAll('.msg[data-id]')].map((li) => li.dataset.id));
    let dernier = fil.dataset.last || '';
    let luAutre = fil.dataset.luAutre || '';

    const enBas = () => liste.scrollHeight - liste.scrollTop - liste.clientHeight < 80;
    const descendre = () => { liste.scrollTop = liste.scrollHeight; };
    descendre();

    const dernierJour = () => {
        const jours = liste.querySelectorAll('.msg-jour');
        return jours.length ? jours[jours.length - 1].dataset.jour : null;
    };

    const ajouter = (m) => {
        if (ids.has(m.id)) return;
        ids.add(m.id);

        if (m.date !== dernierJour()) {
            const jour = document.createElement('li');
            jour.className = 'msg-jour';
            jour.dataset.jour = m.date;
            const span = document.createElement('span');
            span.textContent = m.date;
            jour.appendChild(span);
            liste.insertBefore(jour, fin);
        }

        const li = document.createElement('li');
        li.className = `msg ${m.moi ? 'msg--moi' : 'msg--autre'} est-nouveau`;
        li.dataset.id = m.id;
        li.dataset.created = m.created_at;

        const bulle = document.createElement('div');
        bulle.className = 'msg__bulle';
        bulle.textContent = m.contenu;

        const meta = document.createElement('div');
        meta.className = 'msg__meta';
        if (!m.moi) {
            const auteur = document.createElement('span');
            auteur.textContent = `${m.auteur} ·`;
            meta.appendChild(auteur);
        }
        const heure = document.createElement('time');
        heure.dateTime = m.created_at;
        heure.textContent = m.heure;
        meta.appendChild(heure);

        li.append(bulle, meta);
        liste.insertBefore(li, fin);

        if (m.created_at > dernier) dernier = m.created_at;
    };

    /** Accusé de lecture : « Lu » / « Envoyé » sous mon dernier message. */
    const majAccuse = () => {
        fil.querySelectorAll('[data-messagerie-lu]').forEach((el) => el.remove());
        const miens = liste.querySelectorAll('.msg--moi');
        const moi = miens[miens.length - 1];
        if (!moi) return;
        const lu = luAutre && Date.parse(luAutre) >= Date.parse(moi.dataset.created);
        const span = document.createElement('span');
        span.className = 'msg__lu';
        span.dataset.messagerieLu = '';
        span.textContent = `· ${lu ? 'Lu' : 'Envoyé'}`;
        moi.querySelector('.msg__meta').appendChild(span);
    };

    let enCours = false;
    const interroger = () => {
        if (enCours || document.hidden) return;
        enCours = true;
        const url = new URL(fil.dataset.pollUrl, location.origin);
        if (dernier) url.searchParams.set('apres', dernier);

        fetch(url, json)
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (!data) return;
                const suivre = enBas();
                data.messages.forEach(ajouter);
                luAutre = data.lu_par_autre_at || luAutre;
                majAccuse();
                if (data.messages.length && suivre) descendre();

                if (!data.ouverte && form) {
                    // Conversation fermée par l'administration pendant la lecture.
                    const ferme = document.createElement('p');
                    ferme.className = 'msg-ferme';
                    ferme.textContent = 'Cette conversation vient d\'être fermée.';
                    form.replaceWith(ferme);
                }
            })
            .catch(() => {})
            .finally(() => { enCours = false; });
    };

    setInterval(interroger, INTERVALLE_FIL);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) interroger(); });

    if (!form) return;

    /* ------------------------------------------------------------ envoi */
    const ajusterHauteur = () => {
        zone.style.height = 'auto';
        zone.style.height = `${Math.min(zone.scrollHeight, 160)}px`;
    };
    zone.addEventListener('input', ajusterHauteur);

    // Entrée = envoyer, Maj+Entrée = nouvelle ligne.
    zone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const contenu = zone.value.trim();
        if (!contenu) { zone.focus(); return; }

        const bouton = form.querySelector('button[type="submit"]');
        bouton.disabled = true;
        erreur.hidden = true;

        fetch(form.action, {
            method: 'POST',
            headers: { ...json.headers, 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
            body: new FormData(form),
        })
            .then(async (r) => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    const texte = (data.errors && data.errors.contenu && data.errors.contenu[0])
                        || (r.status === 429 ? 'Trop de messages envoyés, patientez une minute.' : 'Le message n\'a pas pu être envoyé.');
                    throw new Error(texte);
                }
                ajouter(data.message);
                majAccuse();
                zone.value = '';
                ajusterHauteur();
                descendre();
            })
            .catch((err) => {
                erreur.textContent = err.message;
                erreur.hidden = false;
            })
            .finally(() => {
                bouton.disabled = false;
                zone.focus();
            });
    });
})();
