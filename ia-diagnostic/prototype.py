"""
Phase 1 : prototype pour comprendre CLIP, avant d'écrire le microservice.

Usage :
    python prototype.py photos-test/jean.jpg
    python prototype.py photos-test/jean.jpg --texte "trou près de la poche"
    python prototype.py photos-test/            (toutes les photos du dossier)

Pour chaque photo, le script affiche :
  1. le type de vêtement deviné (zero-shot) ;
  2. le défaut deviné (zero-shot) ;
  3. les services du catalogue qui correspondent le mieux (appariement sémantique).
"""

import argparse
import sys
import time
from pathlib import Path

import torch
from PIL import Image
from sentence_transformers import SentenceTransformer

# Deux modèles qui partagent le même espace de vecteurs (512 nombres) :
#   - l'encodeur d'image de CLIP ;
#   - un encodeur de texte multilingue aligné sur CLIP (il comprend le français).
MODELE_IMAGE = "clip-ViT-B-32"
MODELE_TEXTE = "sentence-transformers/clip-ViT-B-32-multilingual-v1"

# CLIP multiplie les similarités par 100 avant la softmax (facteur appris pendant son entraînement).
ECHELLE = 100.0

# Catégories zero-shot : on les écrit en phrases complètes, CLIP compare la photo à chacune.
VETEMENTS = {
    "jean": "une photo d'un jean",
    "pantalon": "une photo d'un pantalon",
    "chemise": "une photo d'une chemise",
    "t-shirt": "une photo d'un t-shirt",
    "robe": "une photo d'une robe",
    "jupe": "une photo d'une jupe",
    "veste": "une photo d'une veste",
    "manteau": "une photo d'un manteau",
    "pull": "une photo d'un pull en laine",
    "chaussette": "une photo d'une chaussette",
}

DEFAUTS = {
    "trou": "une photo d'un vêtement avec un trou",
    "déchirure": "une photo d'un vêtement déchiré",
    "fermeture cassée": "une photo d'une fermeture éclair cassée",
    "bouton manquant": "une photo d'un vêtement avec un bouton manquant",
    "ourlet décousu": "une photo d'un ourlet décousu",
    "couture défaite": "une photo d'une couture défaite",
    "maille filée": "une photo d'un tricot avec une maille filée",
    "tache": "une photo d'un vêtement taché",
    "trop grand": "une photo d'un vêtement trop grand à ajuster",
}

# Catalogue de test : les services de AtelierSeeder (le microservice recevra le vrai catalogue de Laravel).
CATALOGUE = {
    "Raccourcir un ourlet": "Reprendre la longueur d'un pantalon, d'un jean ou d'une jupe.",
    "Recoudre une déchirure": "Réparer un trou ou une déchirure dans le tissu.",
    "Changer une fermeture": "Remplacer une fermeture éclair cassée ou bloquée.",
    "Remplacement de boutons": "Recoudre ou remplacer des boutons manquants.",
    "Retouche denim": "Réparations et ajustements sur les jeans et le denim.",
    "Ajustement de robe": "Reprendre une robe trop grande à la taille ou aux épaules.",
    "Remplacement de doublure": "Changer la doublure usée d'une veste ou d'un manteau.",
    "Reprise de tricot": "Réparer une maille filée ou un trou dans un pull.",
    "Réparation textile délicat": "Soie, dentelle et tissus fragiles.",
    "Patchs et broderies": "Couvrir un trou ou une tache avec un patch ou une broderie.",
    "Upcycling créatif": "Transformer un vêtement abîmé en une nouvelle pièce.",
}

EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}


def softmax(similarites: torch.Tensor) -> torch.Tensor:
    """Étape 4 : similarités -> pourcentages qui totalisent 100 %."""
    return torch.softmax(similarites * ECHELLE, dim=-1)


def afficher(titre: str, noms: list[str], similarites: torch.Tensor, n: int = 3) -> None:
    pourcentages = softmax(similarites)
    print(f"  {titre}")
    for indice in torch.argsort(pourcentages, descending=True)[:n].tolist():
        print(f"    {noms[indice]:<28} cosinus {similarites[indice]:.3f}   {pourcentages[indice] * 100:5.1f} %")


def diagnostiquer(photo: Path, texte: str | None, image_modele, texte_modele, vecteurs) -> None:
    debut = time.perf_counter()

    # Étapes 1 et 2 : la photo devient un vecteur de 512 nombres, ramené à une longueur de 1.
    vecteur_photo = image_modele.encode(Image.open(photo).convert("RGB"), convert_to_tensor=True, normalize_embeddings=True)

    # Étape 3 : similarité cosinus = produit scalaire de vecteurs normalisés (opérateur @).
    sim_vetements = vecteurs["vetements"] @ vecteur_photo
    sim_defauts = vecteurs["defauts"] @ vecteur_photo
    sim_services = vecteurs["services"] @ vecteur_photo

    # Le texte du client (s'il y en a un) est comparé aux services, puis combiné avec la photo.
    if texte:
        vecteur_texte = texte_modele.encode(texte, convert_to_tensor=True, normalize_embeddings=True)
        sim_services = 0.6 * sim_services + 0.4 * (vecteurs["services"] @ vecteur_texte)

    print(f"\n=== {photo.name}" + (f"  (texte : « {texte} »)" if texte else ""))
    afficher("Vêtement :", list(VETEMENTS), sim_vetements)
    afficher("Défaut :", list(DEFAUTS), sim_defauts)
    afficher("Services du catalogue :", list(CATALOGUE), sim_services)
    print(f"  ({(time.perf_counter() - debut) * 1000:.0f} ms)")


def main() -> None:
    # La console Windows n'est pas en UTF-8 par défaut : sans ça, les accents s'affichent mal.
    sys.stdout.reconfigure(encoding="utf-8")

    parser = argparse.ArgumentParser(description="Prototype de diagnostic textile avec CLIP.")
    parser.add_argument("chemin", type=Path, help="Une photo ou un dossier de photos.")
    parser.add_argument("--texte", help="Précision du client, ex. « trou près de la poche ».")
    args = parser.parse_args()

    photos = sorted(p for p in args.chemin.iterdir() if p.suffix.lower() in EXTENSIONS) if args.chemin.is_dir() else [args.chemin]
    if not photos:
        raise SystemExit(f"Aucune photo trouvée dans {args.chemin} (formats : {', '.join(sorted(EXTENSIONS))}).")

    print("Chargement des modèles…")
    image_modele = SentenceTransformer(MODELE_IMAGE)
    texte_modele = SentenceTransformer(MODELE_TEXTE)

    # Les phrases ne changent pas : on les encode une seule fois pour toutes les photos.
    encoder = lambda phrases: texte_modele.encode(phrases, convert_to_tensor=True, normalize_embeddings=True)
    vecteurs = {
        "vetements": encoder(list(VETEMENTS.values())),
        "defauts": encoder(list(DEFAUTS.values())),
        "services": encoder([f"{nom}. {description}" for nom, description in CATALOGUE.items()]),
    }

    for photo in photos:
        diagnostiquer(photo, args.texte, image_modele, texte_modele, vecteurs)


if __name__ == "__main__":
    main()
