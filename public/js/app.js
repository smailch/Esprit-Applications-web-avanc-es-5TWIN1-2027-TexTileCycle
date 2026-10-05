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

  document.querySelectorAll('[data-condition-radios], [data-action-radios]').forEach((group) => {
    group.querySelectorAll('input[type="radio"]').forEach((input) => {
      input.addEventListener('change', () => {
        group.querySelectorAll('.radio, .action-choice').forEach((label) => label.classList.remove('active'));
        input.closest('.radio, .action-choice')?.classList.add('active');
      });
    });
  });

  document.querySelectorAll('[data-upload-zone]').forEach((zone) => {
    const input = zone.querySelector('[data-upload-input]');
    const label = zone.querySelector('[data-upload-label]');
    if (!input) return;

    zone.addEventListener('click', (e) => {
      if (e.target === input) return;
      input.click();
    });

    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (file && label) {
        label.textContent = file.name;
      }
    });

    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));

    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('dragover');
      const file = e.dataTransfer?.files?.[0];
      if (file && file.type.startsWith('image/')) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    });
  });
});
