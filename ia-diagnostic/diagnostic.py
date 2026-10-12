"""
Moteur de diagnostic textile avec CLIP, partagé par l'évaluation et le futur microservice.

Améliorations par rapport au prototype (chacune activable pour être mesurée dans evaluer.py) :
  - anglais     : les étiquettes internes passent par l'encodeur de texte ORIGINAL de CLIP, en anglais,
                  mieux aligné sur les images que l'encodeur multilingue (une copie apprise) ;
  - ensemble    : plusieurs phrases par étiquette, dont on fait la moyenne (« prompt ensembling ») ;
  - calibration : on retire à chaque étiquette son score moyen sur des images neutres (gris, noir,
                  bruit…), pour corriger les étiquettes « attirantes » (« Calibrate before use », 2021) ;
  - deux_temps  : photo -> défaut, puis défaut + vêtement -> service par le texte, au lieu de comparer
                  directement la photo aux services ;
  - zoom        : la photo entière plus des vues recadrées (centre, quarts) dont on fait la moyenne,
                  pour qu'un petit défaut ne disparaisse pas quand CLIP réduit l'image à 224 x 224 pixels ;
  - lexique     : recherche hybride. Un vocabulaire métier par défaut et par vêtement (ex. fermeture : fermeture, zip,
                  éclair) donne des points aux services qui contiennent ces mots (2 dans le nom, 1 dans la
                  description) ; la similarité vectorielle ne sert plus qu'à départager. Le texte du client passe
                  aussi par ce vocabulaire (« la fermeture est bloquée » -> défaut fermeture). Raison : sur le catalogue
                  réel, les similarités texte-texte de CLIP sont toutes entre 0,89 et 0,95, services de test compris ;
  - calibration_services : on retire à chaque service sa similarité moyenne avec l'ensemble des défauts.
                  Un service proche de TOUS les défauts (nom vide de sens, ex. « smail » ou « DSDSDS » saisis pour
                  tester) n'apporte aucune information et ne doit plus passer en tête ;
  - nlp         : les comparaisons TEXTE-TEXTE (défaut -> service, texte du client -> service) passent par un
                  modèle de similarité de phrases multilingue au lieu de l'encodeur multilingue de CLIP.
                  Désactivé par défaut : mesuré dans evaluer.py, il n'apporte pas de gain significatif
                  (texte -> service : 69 % contre 62 % en top-1 sur 16 phrases) et dégrade l'appariement
                  défaut -> service (45 % contre 73 %). Le modèle n'est chargé que si l'option est activée.
"""

from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass
from functools import lru_cache

import torch
from PIL import Image
from sentence_transformers import SentenceTransformer

MODELE_CLIP = "clip-ViT-B-32"
MODELE_TEXTE_MULTILINGUE = "sentence-transformers/clip-ViT-B-32-multilingual-v1"
MODELE_PHRASES = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"

# CLIP multiplie les similarités par 100 avant la softmax (facteur appris pendant son entraînement).
ECHELLE_CLIP = 100.0
# Entre deux textes, avec l'encodeur multilingue de CLIP (scores très resserrés autour de 0,9).
ECHELLE_TEXTE = 50.0
# Entre deux textes, avec le modèle de phrases (scores bien étalés, environ de 0,2 à 0,8).
ECHELLE_PHRASES = 20.0


@dataclass(frozen=True)
class Etiquette:
    cle: str                 # valeur renvoyée à Laravel
    fr: str                  # phrase française (version de base, sans amélioration)
    en: tuple[str, ...]      # descriptions anglaises (la première seule si « ensemble » est désactivé)
    reparation: str = ""     # phrase française de la réparation, pour l'appariement avec les services
    mots: tuple[str, ...] = ()   # vocabulaire métier (débuts de mots, sans accents) pour la recherche hybride


VETEMENTS = (
    Etiquette("jean", "une photo d'un jean", ("a pair of jeans", "blue denim jeans"), mots=("jean", "denim")),
    Etiquette("pantalon", "une photo d'un pantalon", ("a pair of trousers", "fabric pants"), mots=("pantalon",)),
    Etiquette("chemise", "une photo d'une chemise", ("a shirt", "a button-up shirt"), mots=("chemise", "blouse")),
    Etiquette("t-shirt", "une photo d'un t-shirt", ("a t-shirt", "a cotton tee shirt")),
    Etiquette("robe", "une photo d'une robe", ("a dress", "a woman's dress"), mots=("robe",)),
    Etiquette("jupe", "une photo d'une jupe", ("a skirt",), mots=("jupe",)),
    Etiquette("veste", "une photo d'une veste", ("a jacket", "a blazer"), mots=("veste", "blouson")),
    Etiquette("manteau", "une photo d'un manteau", ("a coat", "a winter coat"), mots=("manteau",)),
    Etiquette("pull", "une photo d'un pull en laine", ("a knitted sweater", "a wool jumper"), mots=("pull", "tricot", "laine")),
    Etiquette("chaussette", "une photo d'une chaussette", ("a sock", "a pair of socks"), mots=("chaussette",)),
)

DEFAUTS = (
    Etiquette("trou", "une photo d'un vêtement avec un trou",
              ("a hole in the fabric", "a garment with a hole", "a small hole in clothing"),
              "réparer un trou dans un vêtement", ("trou", "accroc", "raccommod")),
    Etiquette("déchirure", "une photo d'un vêtement déchiré",
              ("a ripped garment", "torn fabric", "a tear in the clothing"),
              "recoudre une déchirure du tissu", ("dechir", "accroc", "recoud", "craque")),
    Etiquette("fermeture cassée", "une photo d'une fermeture éclair cassée",
              ("a broken zipper", "a damaged zipper on clothing"),
              "remplacer une fermeture éclair cassée", ("fermetur", "zip", "eclair", "glissiere")),
    Etiquette("bouton manquant", "une photo d'un vêtement avec un bouton manquant",
              ("a shirt with a missing button", "a loose button hanging by a thread"),
              "recoudre ou remplacer un bouton", ("bouton", "pression")),
    Etiquette("ourlet décousu", "une photo d'un ourlet décousu",
              ("an unstitched hem", "a hem coming undone"),
              "refaire un ourlet ou reprendre la longueur", ("ourlet", "longueur", "raccourc", "rallong")),
    Etiquette("couture défaite", "une photo d'une couture défaite",
              ("a split seam", "a seam coming apart"),
              "recoudre une couture défaite", ("couture", "recoud", "decous")),
    Etiquette("maille filée", "une photo d'un tricot avec une maille filée",
              ("a snag in a knitted sweater", "a pulled thread in knitwear"),
              "reprendre une maille de tricot", ("maille", "tricot")),
    Etiquette("tache", "une photo d'un vêtement taché",
              ("a stain on clothing", "a dirty spot on fabric"),
              "couvrir ou traiter une tache sur un vêtement", ("tache", "patch", "brod", "masqu", "usure")),
    Etiquette("trop grand", "une photo d'un vêtement trop grand à ajuster",
              ("an oversized garment that needs tailoring", "clothes that are too big"),
              "ajuster un vêtement trop grand", ("ajust", "taille", "retouch", "cintr", "resserr")),
)

CLES_DEFAUTS = [d.cle for d in DEFAUTS]

GABARITS_EN = ("a photo of {}.", "a close-up photo of {}.", "a cropped photo of {}.")


@dataclass(frozen=True)
class Options:
    anglais: bool = True
    ensemble: bool = True
    calibration: bool = True
    deux_temps: bool = True
    zoom: bool = True
    # Mesuré dans evaluer.py : fait chuter le service top-1 (18 % contre 73 %) ; désactivé.
    calibration_services: bool = False
    lexique: bool = True
    nlp: bool = False
    # Le diagnostic est jugé certain si le 1er défaut devance le 2e d'au moins cet écart de probabilité.
    ecart_certitude: float = 0.20


@dataclass(frozen=True)
class Service:
    id: str
    nom: str
    description: str = ""

    def texte(self) -> str:
        return f"{self.nom}. {self.description}".strip()


@dataclass
class Resultat:
    vetements: list[tuple[str, float]]
    defauts: list[tuple[str, float]]
    services: list[tuple[str, float]]   # (id du service, score entre 0 et 1)
    certain: bool


# Catalogue de démonstration : les services de AtelierSeeder (le microservice recevra le vrai catalogue).
CATALOGUE_DEMO = (
    Service("ourlet", "Raccourcir un ourlet", "Reprendre la longueur d'un pantalon, d'un jean ou d'une jupe."),
    Service("dechirure", "Recoudre une déchirure", "Réparer un trou ou une déchirure dans le tissu."),
    Service("fermeture", "Changer une fermeture", "Remplacer une fermeture éclair cassée ou bloquée."),
    Service("boutons", "Remplacement de boutons", "Recoudre ou remplacer des boutons manquants."),
    Service("denim", "Retouche denim", "Réparations et ajustements sur les jeans et le denim."),
    Service("robe", "Ajustement de robe", "Reprendre une robe trop grande à la taille ou aux épaules."),
    Service("doublure", "Remplacement de doublure", "Changer la doublure usée d'une veste ou d'un manteau."),
    Service("tricot", "Reprise de tricot", "Réparer une maille filée ou un trou dans un pull."),
    Service("delicat", "Réparation textile délicat", "Soie, dentelle et tissus fragiles."),
    Service("patchs", "Patchs et broderies", "Couvrir un trou ou une tache avec un patch ou une broderie."),
    Service("upcycling", "Upcycling créatif", "Transformer un vêtement abîmé en une nouvelle pièce."),
)


@lru_cache(maxsize=1)
def charger_modeles() -> tuple[SentenceTransformer, SentenceTransformer]:
    """Chargés une seule fois par processus (environ 1 Go en mémoire)."""
    return SentenceTransformer(MODELE_CLIP), SentenceTransformer(MODELE_TEXTE_MULTILINGUE)


@lru_cache(maxsize=1)
def charger_modele_phrases() -> SentenceTransformer:
    """Modèle de phrases, chargé seulement si l'option « nlp » est activée (environ 470 Mo)."""
    return SentenceTransformer(MODELE_PHRASES)


def images_neutres() -> list[Image.Image]:
    """Images « sans contenu » pour mesurer le biais de chaque étiquette (calibration)."""
    generateur = torch.Generator().manual_seed(0)
    bruit = lambda: Image.fromarray((torch.rand(224, 224, 3, generator=generateur) * 255).to(torch.uint8).numpy())
    unies = [Image.new("RGB", (224, 224), couleur) for couleur in ("gray", "white", "black", "navy", "beige")]
    return unies + [bruit(), bruit()]


def mots_de(texte: str) -> list[str]:
    """« Fermetures éclair » -> ["fermetures", "eclair"] : minuscules, sans accents."""
    sans_accents = unicodedata.normalize("NFKD", texte.lower()).encode("ascii", "ignore").decode()
    return re.findall(r"[a-z]+", sans_accents)


def points_lexicaux(etiquettes, services) -> torch.Tensor:
    """Étiquettes x services : 2 points par mot du vocabulaire trouvé dans le nom, 1 dans la description."""
    scores = torch.zeros(len(etiquettes), len(services))
    for j, service in enumerate(services):
        nom, description = mots_de(service.nom), mots_de(service.description)
        for i, etiquette in enumerate(etiquettes):
            for racine in etiquette.mots:
                if any(mot.startswith(racine) for mot in nom):
                    scores[i, j] += 2
                elif any(mot.startswith(racine) for mot in description):
                    scores[i, j] += 1
    return scores


def melanger_lexique(semantique: torch.Tensor, points: torch.Tensor, poids: float = 0.75) -> torch.Tensor:
    """Ligne par ligne : si un service contient le vocabulaire, les points décident (pondérés), sinon la sémantique seule."""
    resultat = semantique.clone()
    for i in range(points.shape[0]):
        total = points[i].sum()
        if total > 0:
            resultat[i] = poids * (points[i] / total) + (1 - poids) * semantique[i]
    return resultat


class MoteurDiagnostic:
    def __init__(self, options: Options = Options()):
        self.options = options
        self.clip, self.multilingue = charger_modeles()
        self.phrases = charger_modele_phrases() if options.nlp else None
        # Comparaisons texte-texte : modèle de phrases (nlp) ou, pour mesurer l'écart, l'encodeur multilingue de CLIP.
        self._texte_comparable = self._texte_phrases if options.nlp else self._texte_fr
        self.echelle_texte = ECHELLE_PHRASES if options.nlp else ECHELLE_TEXTE

        self.vec_vetements = self._vecteurs_etiquettes(VETEMENTS)
        self.vec_defauts = self._vecteurs_etiquettes(DEFAUTS)

        if options.calibration:
            vec_neutres = self._images(images_neutres())
            self.biais_vetements = (vec_neutres @ self.vec_vetements.T).mean(dim=0)
            self.biais_defauts = (vec_neutres @ self.vec_defauts.T).mean(dim=0)
        else:
            self.biais_vetements = torch.zeros(len(VETEMENTS))
            self.biais_defauts = torch.zeros(len(DEFAUTS))

        # Phrases françaises qui relient un défaut ou un vêtement aux services (appariement en deux temps).
        self.vec_reparations = self._texte_comparable([d.reparation for d in DEFAUTS])
        self.vec_vetements_fr = self._texte_comparable([v.cle for v in VETEMENTS])
        self._cache_services: dict[tuple, torch.Tensor] = {}

    # ------------------------------------------------------------------ encodage

    def _images(self, images) -> torch.Tensor:
        return self.clip.encode(images, convert_to_tensor=True, normalize_embeddings=True)

    @staticmethod
    def _vues(image: Image.Image) -> list[Image.Image]:
        """Photo entière + centre zoomé + quatre quarts qui se chevauchent."""
        largeur, hauteur = image.size
        l, h = int(largeur * 0.6), int(hauteur * 0.6)
        coins = [(0, 0), (largeur - l, 0), (0, hauteur - h), (largeur - l, hauteur - h)]
        centre = ((largeur - l) // 2, (hauteur - h) // 2)
        return [image] + [image.crop((x, y, x + l, y + h)) for x, y in [centre, *coins]]

    def _vecteur_photo(self, image: Image.Image) -> torch.Tensor:
        if not self.options.zoom:
            return self._images(image)
        moyenne = self._images(self._vues(image)).mean(dim=0)
        return moyenne / moyenne.norm()

    def _texte_fr(self, phrases: list[str]) -> torch.Tensor:
        return self.multilingue.encode(phrases, convert_to_tensor=True, normalize_embeddings=True)

    def _texte_phrases(self, phrases: list[str]) -> torch.Tensor:
        return self.phrases.encode(phrases, convert_to_tensor=True, normalize_embeddings=True)

    def _texte_en(self, phrases: list[str]) -> torch.Tensor:
        return self.clip.encode(phrases, convert_to_tensor=True, normalize_embeddings=True)

    def _vecteurs_etiquettes(self, etiquettes) -> torch.Tensor:
        if not self.options.anglais:
            return self._texte_fr([e.fr for e in etiquettes])

        vecteurs = []
        for etiquette in etiquettes:
            if self.options.ensemble:
                phrases = [gabarit.format(description) for description in etiquette.en for gabarit in GABARITS_EN]
            else:
                phrases = [GABARITS_EN[0].format(etiquette.en[0])]
            moyenne = self._texte_en(phrases).mean(dim=0)
            vecteurs.append(moyenne / moyenne.norm())
        return torch.stack(vecteurs)

    def _vecteurs_services(self, services, encodeur) -> torch.Tensor:
        """Vecteurs des services, gardés en cache par texte (le catalogue change rarement)."""
        cles = [(encodeur.__name__, service.texte()) for service in services]
        nouvelles = [cle for cle in dict.fromkeys(cles) if cle not in self._cache_services]
        if nouvelles:
            self._cache_services.update(zip(nouvelles, encodeur([texte for _, texte in nouvelles])))
        return torch.stack([self._cache_services[cle] for cle in cles])

    # ------------------------------------------------------------------ diagnostic

    def diagnostiquer(self, image: Image.Image, services=CATALOGUE_DEMO, texte: str | None = None,
                      defaut: str | None = None) -> Resultat:
        """defaut : correction de l'utilisateur (« Ce n'est pas ça ? »), qui remplace l'avis de la photo sur le défaut."""
        vec_image = self._vecteur_photo(image.convert("RGB"))

        p_vetements = torch.softmax((self.vec_vetements @ vec_image - self.biais_vetements) * ECHELLE_CLIP, dim=0)
        p_defauts = torch.softmax((self.vec_defauts @ vec_image - self.biais_defauts) * ECHELLE_CLIP, dim=0)
        if defaut is not None:
            p_defauts = torch.zeros(len(DEFAUTS))
            p_defauts[CLES_DEFAUTS.index(defaut)] = 1.0

        # Le vocabulaire métier repère le défaut décrit par le client (« la fermeture est bloquée » -> fermeture cassée).
        texte_reconnu = False
        if self.options.lexique and texte and defaut is None:
            mots_texte = mots_de(texte)
            points = torch.tensor([float(sum(any(m.startswith(r) for m in mots_texte) for r in d.mots)) for d in DEFAUTS])
            if points.sum() > 0:
                p_defauts = 0.5 * p_defauts + 0.5 * points / points.sum()
                texte_reconnu = True

        # Services dans l'espace des comparaisons texte-texte (appariement en deux temps et texte du client).
        vec_services_texte = self._vecteurs_services(services, self._texte_comparable)
        sim_defauts = self.vec_reparations @ vec_services_texte.T          # défauts x services
        sim_vetements = self.vec_vetements_fr @ vec_services_texte.T       # vêtements x services
        # Biais de chaque service : sa similarité moyenne avec tous les défauts (élevée pour un nom vide de sens).
        biais_services = sim_defauts.mean(dim=0) if self.options.calibration_services else torch.zeros(len(services))
        if self.options.calibration_services:
            sim_defauts = sim_defauts - biais_services
            sim_vetements = sim_vetements - sim_vetements.mean(dim=0)

        if self.options.deux_temps:
            # Pour chaque défaut (et chaque vêtement), quels services lui correspondent le mieux par le texte ?
            par_defaut = torch.softmax(sim_defauts * self.echelle_texte, dim=1)
            par_vetement = torch.softmax(sim_vetements * self.echelle_texte, dim=1)
            if self.options.lexique:
                # Recherche hybride : le vocabulaire métier décide, la similarité vectorielle départage.
                par_defaut = melanger_lexique(par_defaut, points_lexicaux(DEFAUTS, services))
                par_vetement = melanger_lexique(par_vetement, points_lexicaux(VETEMENTS, services))
            # Pondéré par la probabilité de chaque défaut et de chaque vêtement vus sur la photo.
            p_services = 0.8 * (p_defauts @ par_defaut) + 0.2 * (p_vetements @ par_vetement)
        else:
            # Version de base : la photo comparée directement aux services, dans l'espace de CLIP.
            vec_services_clip = self._vecteurs_services(services, self._texte_fr)
            p_services = torch.softmax(vec_services_clip @ vec_image * ECHELLE_CLIP, dim=0)

        if texte and not texte_reconnu:
            # Texte sans mot du vocabulaire : comparaison vectorielle avec les services.
            # On combine des pourcentages (et non des similarités brutes, qui ne sont pas à la même échelle).
            vec_texte = self._texte_comparable([texte])[0]
            p_texte = torch.softmax((vec_services_texte @ vec_texte - biais_services) * self.echelle_texte, dim=0)
            p_services = 0.6 * p_services + 0.4 * p_texte

        classer = lambda cles, probabilites: sorted(zip(cles, probabilites.tolist()), key=lambda paire: -paire[1])
        defauts = classer([d.cle for d in DEFAUTS], p_defauts)
        return Resultat(
            vetements=classer([v.cle for v in VETEMENTS], p_vetements),
            defauts=defauts,
            services=classer([s.id for s in services], p_services),
            certain=defaut is not None or defauts[0][1] - defauts[1][1] >= self.options.ecart_certitude,
        )
