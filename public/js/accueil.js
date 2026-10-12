// Page d'accueil : vidéo du hero et carte des ateliers.
// La barre de navigation et les fils des sections sont gérés par trame.js (commun).
document.addEventListener('DOMContentLoaded', () => {
  const mouvementReduit = window.matchMedia('(prefers-reduced-motion: reduce)');

  // Vidéo : en pause sur le poster si l'utilisateur limite les animations.
  const video = document.querySelector('[data-hv-video]');
  const appliquerMouvement = () => {
    if (!video) return;
    if (mouvementReduit.matches) {
      video.removeAttribute('autoplay');
      video.pause();
      // load() remet la vidéo à zéro : le navigateur réaffiche le poster tant qu'elle ne joue pas.
      video.load();
    } else {
      video.play().catch(() => {});
    }
  };
  appliquerMouvement();
  mouvementReduit.addEventListener('change', appliquerMouvement);

  // Carte : même composant que la page Ateliers (créé par ateliers-map.js). Les ateliers arrivent de
  // carte.json quand la carte approche de l'écran ; en cas d'échec, elle reste centrée sur Tunis.
  const canvas = document.querySelector('.acc-carte [data-atelier-map]');
  const carte = canvas && canvas._atelierMap;
  if (!carte || !canvas.dataset.endpoint || !window.fetch) return;

  const statut = canvas.closest('.atelier-map')?.querySelector('[data-map-status]');
  const dire = (message) => { if (statut) statut.textContent = message; };

  const charger = () => {
    fetch(canvas.dataset.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((reponse) => {
        if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
        return reponse.json();
      })
      .then((json) => {
        const marqueurs = Array.isArray(json.data) ? json.data : [];
        carte.render(marqueurs);
        if (!marqueurs.length) dire('Aucun atelier géolocalisé pour le moment.');
      })
      .catch(() => dire('Les ateliers ne peuvent pas être affichés pour le moment. La page Ateliers reste disponible.'));
  };

  if (!('IntersectionObserver' in window)) {
    charger();
    return;
  }

  const guetteur = new IntersectionObserver((entrees) => {
    if (!entrees.some((e) => e.isIntersecting)) return;
    guetteur.disconnect();
    charger();
  }, { rootMargin: '300px 0px' });
  guetteur.observe(canvas);
});
