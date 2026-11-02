// Style « trame » (accueil et pages en layouts.trame) : barre de navigation et fils multicolonnes.
document.addEventListener('DOMContentLoaded', () => {
  // Barre de navigation : sa hauteur réserve la place en haut de la page (--tr-nav-h) ;
  // fond sombre dès que la page a défilé.
  const nav = document.querySelector('[data-tr-nav]');
  if (nav) {
    const mesurer = () => document.documentElement.style.setProperty('--tr-nav-h', `${nav.offsetHeight}px`);
    mesurer();
    if ('ResizeObserver' in window) new ResizeObserver(mesurer).observe(nav);

    const majFond = () => nav.classList.toggle('est-defile', window.scrollY > 8);
    majFond();
    window.addEventListener('scroll', majFond, { passive: true });
  }

  // Fils multicolonnes (.tr-rang) : chacun se dessine une fois quand il arrive à l'écran.
  // Sans IntersectionObserver ou avec le mouvement réduit, les fils restent simplement affichés.
  const fils = document.querySelectorAll('[data-tr-fil]');
  const mouvementReduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!fils.length || mouvementReduit || !('IntersectionObserver' in window)) return;

  document.documentElement.classList.add('tr-anime');
  const observateur = new IntersectionObserver((entrees) => {
    entrees.forEach((entree) => {
      if (!entree.isIntersecting) return;
      entree.target.classList.add('est-visible');
      observateur.unobserve(entree.target);
    });
  }, { rootMargin: '0px 0px -15% 0px' });

  fils.forEach((fil) => observateur.observe(fil));
});
