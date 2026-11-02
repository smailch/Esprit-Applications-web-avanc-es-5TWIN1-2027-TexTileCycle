document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('style-modal');
  const formulaire = modal?.querySelector('[data-style-form]');
  if (!modal || !formulaire) return;

  const jeton = formulaire.querySelector('input[name="_token"]')?.value;
  const message = modal.querySelector('[data-style-message]');
  const resultat = modal.querySelector('[data-style-resultat]');
  const canvas = modal.querySelector('[data-style-canvas]');
  let scene = null;

  const ouvrir = () => {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  };

  const dire = (texte, erreur) => {
    if (!message) return;
    message.hidden = !texte;
    message.textContent = texte || '';
    message.classList.toggle('is-erreur', Boolean(erreur));
  };

  const afficher = (data) => {
    if (!data.est_vetement) {
      dire(data.message || 'Cette photo ne peut pas être restylée.', true);
      if (resultat) resultat.hidden = true;
      return;
    }

    dire(data.vue ? '' : 'Maquette 3D prête. Le rendu photo s’ajoutera quand le modèle d’image répondra.', false);
    if (resultat) resultat.hidden = false;
    const nom = modal.querySelector('[data-style-nom]');
    const titre = modal.querySelector('[data-style-titre]');
    const description = modal.querySelector('[data-style-description]');
    const gestes = modal.querySelector('[data-style-gestes]');
    const figure = modal.querySelector('[data-style-figure]');
    const vue = modal.querySelector('[data-style-vue]');
    if (nom) nom.textContent = data.style || 'Nouveau style';
    if (titre) titre.textContent = data.titre || 'Vêtement restylé';
    if (description) description.textContent = data.description || '';
    if (gestes) {
      gestes.replaceChildren();
      (data.gestes || []).forEach((geste) => {
        const item = document.createElement('li');
        item.textContent = geste;
        gestes.appendChild(item);
      });
    }
    if (figure && vue) {
      figure.hidden = !data.vue;
      vue.src = data.vue || '';
    }
    scene?.arreter();
    scene = monterMaquette(canvas, data.type, data.couleurs || []);
  };

  const envoyer = async (url, corps) => {
    ouvrir();
    dire('Lecture de la photo et construction du nouveau style…', false);
    if (resultat) resultat.hidden = true;
    const bouton = formulaire.querySelector('[data-style-envoyer]');
    if (bouton) bouton.disabled = true;
    try {
      const reponse = await fetch(url, {
        method: 'POST',
        body: corps,
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': jeton,
        },
      });
      const data = await reponse.json().catch(() => ({}));
      if (!reponse.ok && data.est_vetement !== false) {
        dire(data.message || 'Le nouveau style n’a pas pu être construit.', true);
        return;
      }
      afficher(data);
    } catch (error) {
      dire('Le styliste ne répond pas. Réessayez dans un instant.', true);
    } finally {
      if (bouton) bouton.disabled = false;
    }
  };

  formulaire.addEventListener('submit', (event) => {
    event.preventDefault();
    const photo = formulaire.querySelector('[data-style-photo]')?.files?.[0];
    if (!photo || !jeton) return;
    const corps = new FormData();
    corps.append('photo', photo);
    corps.append('_token', jeton);
    envoyer(formulaire.dataset.styleUrl, corps);
  });

  document.querySelectorAll('[data-style-piece]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
      if (!jeton) return;
      const corps = new FormData();
      corps.append('_token', jeton);
      envoyer(bouton.dataset.stylePiece, corps);
    });
  });
});

function monterMaquette(canvas, type, couleurs) {
  if (!canvas) return null;
  const contexte = canvas.getContext('2d');
  const palette = [
    couleurSure(couleurs[0], '#243E73'),
    couleurSure(couleurs[1], '#E8B931'),
    couleurSure(couleurs[2], '#F3EDE2'),
  ];
  const boites = habit(type, palette);
  let angle = 0.5;
  let actif = true;
  let glisse = false;
  let dernier = 0;

  const dessiner = () => {
    if (!actif) return;
    if (!glisse) angle += 0.008;
    tracer(contexte, canvas.width, canvas.height, boites, angle);
    requestAnimationFrame(dessiner);
  };

  const pointerDown = (event) => {
    glisse = true;
    dernier = event.clientX;
    canvas.setPointerCapture?.(event.pointerId);
  };
  const pointerMove = (event) => {
    if (!glisse) return;
    angle += (event.clientX - dernier) * 0.01;
    dernier = event.clientX;
  };
  const pointerUp = () => {
    glisse = false;
  };

  canvas.addEventListener('pointerdown', pointerDown);
  canvas.addEventListener('pointermove', pointerMove);
  canvas.addEventListener('pointerup', pointerUp);
  canvas.addEventListener('pointercancel', pointerUp);
  dessiner();

  return {
    arreter() {
      actif = false;
      canvas.removeEventListener('pointerdown', pointerDown);
      canvas.removeEventListener('pointermove', pointerMove);
      canvas.removeEventListener('pointerup', pointerUp);
      canvas.removeEventListener('pointercancel', pointerUp);
    },
  };
}

function habit(type, couleurs) {
  const tissu = couleurs[0];
  const couture = couleurs[1];
  const piece = couleurs[2];
  if (type === 'Pantalon' || type === 'Jean' || type === 'Short') {
    const hauteur = type === 'Short' ? 0.7 : 1.15;
    return [
      boite(0, 0.85, 0, 1.05, 0.28, 0.42, tissu),
      boite(-0.28, 0.15, 0, 0.42, hauteur, 0.36, tissu),
      boite(0.28, 0.15, 0, 0.42, hauteur, 0.36, tissu),
      boite(-0.28, 0.35, 0.2, 0.28, 0.22, 0.06, piece),
      boite(0, 0.72, 0.22, 0.7, 0.04, 0.04, couture),
    ];
  }
  if (type === 'Jupe') {
    return [
      boite(0, 0.55, 0, 1.35, 1.15, 0.5, tissu),
      boite(0.28, 0.7, 0.28, 0.28, 0.28, 0.06, piece),
      boite(0, 1.05, 0.28, 0.9, 0.05, 0.04, couture),
    ];
  }
  if (type === 'Robe') {
    return [
      boite(0, 0.85, 0, 0.85, 0.7, 0.36, tissu),
      boite(0, 0.05, 0, 1.35, 1.05, 0.5, tissu),
      boite(-0.62, 0.85, 0, 0.28, 0.55, 0.28, tissu),
      boite(0.62, 0.85, 0, 0.28, 0.55, 0.28, tissu),
      boite(0.22, 0.9, 0.22, 0.22, 0.22, 0.05, piece),
      boite(0, 0.55, 0.28, 0.7, 0.04, 0.04, couture),
    ];
  }

  return [
    boite(0, 0.15, 0, 1.15, 1.45, 0.42, tissu),
    boite(-0.78, 0.25, 0, 0.38, 1.15, 0.34, tissu),
    boite(0.78, 0.25, 0, 0.38, 1.15, 0.34, tissu),
    boite(0, 0.82, 0, 0.55, 0.16, 0.46, couture),
    boite(0.28, 0.25, 0.24, 0.32, 0.32, 0.05, piece),
    boite(0, 0.05, 0.24, 0.9, 0.045, 0.04, couture),
  ];
}

function boite(x, y, z, w, h, d, couleur) {
  return { x, y, z, w, h, d, couleur };
}

function tracer(contexte, largeur, hauteur, boites, angle) {
  contexte.clearRect(0, 0, largeur, hauteur);
  contexte.fillStyle = '#f7faf8';
  contexte.fillRect(0, 0, largeur, hauteur);

  const faces = [];
  boites.forEach((forme) => {
    const hx = forme.w / 2;
    const hy = forme.h / 2;
    const hz = forme.d / 2;
    const coins = [
      [-hx, -hy, -hz], [hx, -hy, -hz], [hx, hy, -hz], [-hx, hy, -hz],
      [-hx, -hy, hz], [hx, -hy, hz], [hx, hy, hz], [-hx, hy, hz],
    ].map(([px, py, pz]) => tourner(forme.x + px, forme.y + py, forme.z + pz, angle));
    [[0, 1, 2, 3], [5, 4, 7, 6], [4, 0, 3, 7], [1, 5, 6, 2], [3, 2, 6, 7], [4, 5, 1, 0]]
      .forEach((indexes) => {
        const points = indexes.map((index) => coins[index]);
        const profondeur = points.reduce((somme, point) => somme + point[2], 0) / 4;
        faces.push({ points, profondeur, couleur: forme.couleur });
      });
  });

  faces.sort((a, b) => a.profondeur - b.profondeur);
  faces.forEach((face) => {
    contexte.beginPath();
    face.points.forEach((point, index) => {
      const projete = projeter(point, largeur, hauteur);
      if (index === 0) contexte.moveTo(projete[0], projete[1]);
      else contexte.lineTo(projete[0], projete[1]);
    });
    contexte.closePath();
    contexte.fillStyle = teinte(face.couleur, 0.72 + (face.profondeur + 1.5) * 0.12);
    contexte.fill();
  });
}

function tourner(x, y, z, angle) {
  const cosinus = Math.cos(angle);
  const sinus = Math.sin(angle);
  return [x * cosinus + z * sinus, y, -x * sinus + z * cosinus];
}

function projeter(point, largeur, hauteur) {
  const echelle = 3.4 / (3.4 + point[2] + 2.4);
  return [largeur / 2 + point[0] * echelle * 150, hauteur / 2 - point[1] * echelle * 150 + 10];
}

function teinte(hex, facteur) {
  const valeur = hex.replace('#', '');
  const canaux = [0, 2, 4].map((depart) => Math.max(0, Math.min(255, Math.round(parseInt(valeur.slice(depart, depart + 2), 16) * facteur))));
  return `rgb(${canaux[0]}, ${canaux[1]}, ${canaux[2]})`;
}

function couleurSure(valeur, defaut) {
  return /^#[0-9a-fA-F]{6}$/.test(valeur || '') ? valeur : defaut;
}
