/*
 * Avis de la fiche atelier : confort d'affichage uniquement.
 * - libellé de la note (« Très bien »…) au survol et au choix d'une étoile ;
 * - compteur de caractères du commentaire.
 * Aucune règle de validation ici : tout est contrôlé en PHP (AvisRequest).
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-avis-form]').forEach(function (form) {
        var libelle = form.querySelector('[data-avis-libelle]');
        var etoiles = form.querySelectorAll('input[name="note"]');

        if (libelle && etoiles.length) {
            var texteChoisi = function () {
                var coche = form.querySelector('input[name="note"]:checked');
                return coche ? coche.dataset.libelle : 'Choisissez une note';
            };

            etoiles.forEach(function (input) {
                var label = form.querySelector('label[for="' + input.id + '"]');

                input.addEventListener('change', function () {
                    libelle.textContent = texteChoisi();
                });

                if (label) {
                    label.addEventListener('mouseenter', function () {
                        libelle.textContent = input.dataset.libelle;
                    });
                    label.addEventListener('mouseleave', function () {
                        libelle.textContent = texteChoisi();
                    });
                }
            });
        }

        var zone = form.querySelector('[data-avis-commentaire]');
        var compteur = form.querySelector('[data-avis-compteur]');

        if (zone && compteur) {
            var max = parseInt(zone.dataset.max, 10) || 0;
            var majCompteur = function () {
                var longueur = Array.from(zone.value).length;
                compteur.textContent = longueur + ' / ' + max;
                compteur.classList.toggle('is-depasse', max > 0 && longueur > max);
            };

            zone.addEventListener('input', majCompteur);
            majCompteur();
        }
    });
})();
