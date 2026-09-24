document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const menuBtn = document.querySelector('[data-mobile-menu]');
  const nav = document.querySelector('[data-front-nav]');
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', () => nav.classList.toggle('open'));
  }

  const closeModal = (backdrop) => {
    backdrop?.classList.remove('open');
    backdrop?.setAttribute('aria-hidden', 'true');
  };

  const openModal = (backdrop) => {
    backdrop?.classList.add('open');
    backdrop?.setAttribute('aria-hidden', 'false');
  };

  document.querySelectorAll('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-open-modal');
      openModal(document.getElementById(id));
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((el) => {
    el.addEventListener('click', () => {
      closeModal(el.closest('.modal-backdrop'));
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) closeModal(backdrop);
    });
  });
});
