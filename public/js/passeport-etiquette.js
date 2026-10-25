/* Étiquette du passeport : le bouton « Imprimer » ouvre la boîte d'impression du navigateur. */
(function () {
    'use strict';

    document.querySelectorAll('[data-imprimer]').forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            window.print();
        });
    });
})();
