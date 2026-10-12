"""
Évaluation : compare les versions du moteur sur des photos annotées (photos-test/annotations.csv).

Usage :
    python evaluer.py                  (tableau comparatif + détail de la version utilisée par le microservice)
    python evaluer.py --dossier autre-dossier

Deux catalogues de services :
  - « Démo » : CATALOGUE_DEMO, dont les descriptions ont été écrites pendant le développement (mesure optimiste :
    elles reprennent les mots des phrases de réparation) ;
  - « Réel » : export en lecture seule de la base (php ia-diagnostic/exporter_catalogue.php -> photos-test/catalogue-reel.json),
    avec les vraies descriptions des ateliers ET les services de test sans signification (« smail », « sss »…).
    C'est la mesure qui compte.

Mesures : top-1 (première réponse juste), top-3 (bonne réponse parmi les trois premières) ;
défaut « famille » : trou et déchirure comptent comme une même réparation ;
service : au moins un des services jugés acceptables pour la photo ; « + texte » : avec la phrase du client.
"""

import argparse
import csv
import json
import sys
import time
from pathlib import Path

import torch
from PIL import Image

from diagnostic import (
    CATALOGUE_DEMO, DEFAUTS, ECHELLE_TEXTE, MoteurDiagnostic, Options, Service,
    melanger_lexique, mots_de, points_lexicaux,
)

BASE = dict(calibration_services=False, nlp=False, lexique=False)
VERSIONS = [
    ("V0 base (prototype)", Options(**{**BASE, "anglais": False, "ensemble": False, "calibration": False, "deux_temps": False, "zoom": False})),
    ("V1 + anglais", Options(**{**BASE, "ensemble": False, "calibration": False, "deux_temps": False, "zoom": False})),
    ("V2 + plusieurs phrases", Options(**{**BASE, "calibration": False, "deux_temps": False, "zoom": False})),
    ("V3 + calibration", Options(**{**BASE, "deux_temps": False, "zoom": False})),
    ("V4 + services en 2 temps", Options(**{**BASE, "zoom": False})),
    ("V5 + zoom", Options(**BASE)),
    ("V6 = V5 + modèle de phrases (NLP)", Options(**{**BASE, "nlp": True})),
    ("V7 = V5 + calibration services", Options(**{**BASE, "calibration_services": True})),
    ("V8 = V5 + vocabulaire métier", Options(**{**BASE, "lexique": True})),
]

FAMILLES = {"trou": "trou/déchirure", "déchirure": "trou/déchirure"}


def cle_nom(nom: str) -> str:
    """Même regroupement que Laravel : « recoudre une  Déchirure » = « Recoudre une déchirure »."""
    return " ".join(mots_de(nom))


def charger_catalogue_reel(dossier: Path) -> tuple[Service, ...] | None:
    fichier = dossier / "catalogue-reel.json"
    if not fichier.exists():
        return None
    services = {}
    for ligne in json.loads(fichier.read_text(encoding="utf-8")):
        cle = cle_nom(ligne["nom"])
        if cle and cle not in services:
            services[cle] = Service(cle, ligne["nom"].strip(), (ligne["description"] or "").strip())
    return tuple(services.values())


def ids_acceptables(noms: list[str], catalogue) -> set[str]:
    par_cle = {cle_nom(service.nom): service.id for service in catalogue}
    return {par_cle[cle_nom(nom)] for nom in noms if cle_nom(nom) in par_cle}


def lire_annotations(dossier: Path, reel) -> list[dict]:
    with open(dossier / "annotations.csv", encoding="utf-8") as fichier:
        lignes = [ligne for ligne in csv.DictReader(fichier) if not ligne["exclure"]]
    for ligne in lignes:
        noms = [nom for nom in ligne["services_acceptables"].split("|") if nom]
        ligne["services_demo"] = ids_acceptables(noms, CATALOGUE_DEMO)
        ligne["services_reel"] = ids_acceptables(noms, reel) if reel else set()
    return lignes


def dans_top(classement, attendus, n: int, famille=lambda x: x) -> bool:
    attendus = {famille(a) for a in attendus}
    return any(famille(cle) in attendus for cle, _ in classement[:n])


def evaluer(moteur: MoteurDiagnostic, photos: list[dict], dossier: Path, reel) -> tuple[dict, list]:
    compteurs = dict.fromkeys(["vet1", "fam1", "demo1", "demo3", "demotxt1", "reel1", "reel3", "reeltxt1", "reeltxt3",
                               "certains", "certains_justes"], 0)
    details, duree = [], 0.0
    famille = lambda cle: FAMILLES.get(cle, cle)

    for photo in photos:
        image = Image.open(dossier / photo["fichier"])
        debut = time.perf_counter()
        demo = moteur.diagnostiquer(image)
        duree += time.perf_counter() - debut

        juste = dans_top(demo.defauts, [photo["defaut"]], 1, famille)
        compteurs["vet1"] += dans_top(demo.vetements, [photo["vetement"]], 1)
        compteurs["fam1"] += juste
        compteurs["demo1"] += dans_top(demo.services, photo["services_demo"], 1)
        compteurs["demo3"] += dans_top(demo.services, photo["services_demo"], 3)
        compteurs["demotxt1"] += dans_top(moteur.diagnostiquer(image, texte=photo["texte"]).services, photo["services_demo"], 1)
        compteurs["certains"] += demo.certain
        compteurs["certains_justes"] += demo.certain and juste

        reel_photo = reel_texte = None
        if reel:
            reel_photo = moteur.diagnostiquer(image, services=reel)
            reel_texte = moteur.diagnostiquer(image, services=reel, texte=photo["texte"])
            compteurs["reel1"] += dans_top(reel_photo.services, photo["services_reel"], 1)
            compteurs["reel3"] += dans_top(reel_photo.services, photo["services_reel"], 3)
            compteurs["reeltxt1"] += dans_top(reel_texte.services, photo["services_reel"], 1)
            compteurs["reeltxt3"] += dans_top(reel_texte.services, photo["services_reel"], 3)
        details.append((photo, demo, reel_photo, reel_texte))

    compteurs["ms"] = duree / len(photos) * 1000
    return compteurs, details


# Banc de test du texte seul : phrases qu'un client écrirait, et noms des services acceptables.
TEXTES = [
    ("la fermeture éclair de ma veste est cassée", ["Changer une fermeture"]), ("le zip est bloqué", ["Changer une fermeture"]),
    ("il manque deux boutons à ma chemise", ["Remplacement de boutons"]), ("un bouton s'est décousu", ["Remplacement de boutons"]),
    ("mon pantalon est trop long", ["Raccourcir un ourlet"]), ("il faut raccourcir le bas de la jupe", ["Raccourcir un ourlet"]),
    ("trou dans mon pull en laine", ["Reprise de tricot", "Recoudre une déchirure"]),
    ("une maille a filé sur mon gilet", ["Reprise de tricot"]),
    ("mon jean est déchiré au genou", ["Recoudre une déchirure", "Retouche denim", "Patchs et broderies"]),
    ("petits trous sur le devant du t-shirt", ["Recoudre une déchirure", "Patchs et broderies"]),
    ("la robe est trop large à la taille", ["Ajustement de robe"]), ("la doublure de mon manteau est usée", ["Remplacement de doublure"]),
    ("réparer une blouse en soie", ["Réparation textile délicat"]), ("cacher une tache avec une broderie", ["Patchs et broderies"]),
    ("transformer un vieux jean en sac", ["Upcycling créatif"]), ("accroc sur ma chemise", ["Recoudre une déchirure", "Patchs et broderies"]),
]


def classement_vocabulaire(moteur: MoteurDiagnostic, phrase: str, catalogue) -> list[str]:
    """Texte -> défauts (vocabulaire métier) -> services (recherche hybride). Sans mot reconnu : similarité vectorielle."""
    vecteurs = moteur._vecteurs_services(catalogue, moteur._texte_fr)
    points = torch.tensor([float(sum(any(m.startswith(r) for m in mots_de(phrase)) for r in d.mots)) for d in DEFAUTS])
    if points.sum() > 0:
        par_defaut = melanger_lexique(torch.softmax(moteur.vec_reparations @ vecteurs.T * ECHELLE_TEXTE, dim=1),
                                      points_lexicaux(DEFAUTS, catalogue))
        scores = (points / points.sum()) @ par_defaut
    else:
        scores = vecteurs @ moteur._texte_fr([phrase])[0]
    return [catalogue[i].id for i in torch.argsort(scores, descending=True).tolist()]


def evaluer_textes(reel) -> None:
    """Texte du client -> service, sans photo : CLIP multilingue, modèle de phrases, vocabulaire métier."""
    moteur = MoteurDiagnostic(Options(nlp=True))
    # Le vocabulaire métier s'appuie sur les phrases de réparation encodées par CLIP (moteur par défaut, sans NLP).
    moteur_v8 = MoteurDiagnostic(Options())
    catalogues = [("démo", CATALOGUE_DEMO)] + ([("réel", reel)] if reel else [])
    print(f"\nTexte seul -> service ({len(TEXTES)} phrases), top-1 / top-3 :")
    for nom_catalogue, catalogue in catalogues:
        methodes = {
            "CLIP multilingue": lambda p: [catalogue[i].id for i in torch.argsort(
                moteur._vecteurs_services(catalogue, moteur._texte_fr) @ moteur._texte_fr([p])[0], descending=True).tolist()],
            "Modèle de phrases (NLP)": lambda p: [catalogue[i].id for i in torch.argsort(
                moteur._vecteurs_services(catalogue, moteur._texte_phrases) @ moteur._texte_phrases([p])[0], descending=True).tolist()],
            "Vocabulaire métier (V8)": lambda p: classement_vocabulaire(moteur_v8, p, catalogue),
        }
        for nom_methode, classer in methodes.items():
            top1 = top3 = 0
            for phrase, noms in TEXTES:
                acceptables = ids_acceptables(noms, catalogue)
                ordre = classer(phrase)
                top1 += ordre[0] in acceptables
                top3 += bool(set(ordre[:3]) & acceptables)
            print(f"  catalogue {nom_catalogue:<5} {nom_methode:<26} {top1:>2}/{len(TEXTES)} {top1 / len(TEXTES):4.0%}   {top3:>2}/{len(TEXTES)} {top3 / len(TEXTES):4.0%}")


def main() -> None:
    sys.stdout.reconfigure(encoding="utf-8")
    parser = argparse.ArgumentParser(description="Évaluation du diagnostic textile.")
    parser.add_argument("--dossier", type=Path, default=Path("photos-test"))
    args = parser.parse_args()

    reel = charger_catalogue_reel(args.dossier)
    photos = lire_annotations(args.dossier, reel)
    n = len(photos)
    pct = lambda valeur: f"{valeur:>2}/{n} {valeur / n * 100:3.0f} %"
    print(f"{n} photos annotées ; catalogue réel : "
          + (f"{len(reel)} services distincts" if reel else "absent (php ia-diagnostic/exporter_catalogue.php)") + "\n")
    entete = ["Vêtement", "Défaut (fam.)", "Démo top-1", "Démo top-3", "Démo+txt top-1", "Réel top-1", "Réel top-3",
              "Réel+txt top-1", "Réel+txt top-3"]
    print(f"{'Version':<34}" + "".join(f"{titre:>15}" for titre in entete) + f"{'ms/photo':>10}")

    # Le détail porte sur la version utilisée par le microservice (les options par défaut).
    reference = None
    for nom, options in VERSIONS:
        compteurs, details = evaluer(MoteurDiagnostic(options), photos, args.dossier, reel)
        colonnes = ["vet1", "fam1", "demo1", "demo3", "demotxt1", "reel1", "reel3", "reeltxt1", "reeltxt3"]
        print(f"{nom:<34}" + "".join(f"{pct(compteurs[c]):>15}" for c in colonnes) + f"{compteurs['ms']:>9.0f}")
        if options == Options():
            reference = (nom, compteurs, details)

    if reference is None:
        print("\n(Les options par défaut ne correspondent à aucune version du tableau.)")
        return

    nom, compteurs, details = reference
    print(f"\nVersion du microservice : « {nom} »")
    print(f"Certitude (écart ≥ {Options().ecart_certitude:.0%} entre les 2 premiers défauts) : "
          f"{compteurs['certains']}/{n} diagnostics jugés certains, dont {compteurs['certains_justes']} justes (famille).")

    if reel:
        noms_reel = {service.id: service.nom for service in reel}
        print("\nDétail par photo, catalogue réel (1er service, photo seule puis + texte) :")
        for photo, demo, reel_photo, reel_texte in details:
            ok = lambda r: "✓" if r.services[0][0] in photo["services_reel"] else "✗"
            print(f"  {photo['fichier']:<24} défaut {demo.defauts[0][0]:<16} "
                  f"{ok(reel_photo)} {noms_reel[reel_photo.services[0][0]]:<27} {ok(reel_texte)} {noms_reel[reel_texte.services[0][0]]}")

    evaluer_textes(reel)


if __name__ == "__main__":
    main()
