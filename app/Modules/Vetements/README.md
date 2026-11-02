# Module Vetements

## Entités MongoDB

| Modèle | Collection | Description |
|--------|------------|-------------|
| `Vetement` | `vetements` | Pièce textile déclarée par un citoyen |
| `CycleVieEvent` | `cycle_vie_events` | Événement du parcours (timeline) |

## Relation

```
User 1 ──< Vetement 1 ──< CycleVieEvent N
```

- `Vetement.user_id` → propriétaire (`User._id`)
- `CycleVieEvent.vetement_id` → `Vetement._id`

## Statuts vêtement

`en_attente` | `en_reparation` | `repare` | `donne` | `recycle`

## Parcours citoyen (choix à la déclaration)

`intended_action` : **`reparation`** | **`don`**

- Met à jour la timeline (`CycleVieEvent` étape 2)
- Boutons sur chaque carte pour changer le parcours
- Lien CTA : ateliers (réparation) ou dons (don)

## Analyse photo

À la déclaration, une photo peut être lue par un modèle de vision (Gemini par défaut, OpenAI en secours). Le résultat est enregistré dans `Vetement.analyse_ia` et une étape `analyse_ia` est ajoutée au cycle de vie.

Verdicts :

| Verdict | Deuxième chance | Recommandation |
|---------|-----------------|----------------|
| `reparable` | oui | réparation en atelier |
| `inutile` | non, la pièce est encore portable | don |
| `irreparable` | non | recyclage |
| `hors_sujet` | indéterminé | la photo ne montre pas un vêtement |

La gravité `severe` (trou large, moisissure, pièce inutilisable) impose le verdict `irreparable`, même si le modèle se dit optimiste.

Configuration (`.env`) : `GEMINI_API_KEY` (modèle par défaut `gemini-3.5-flash-lite`) ou `OPENAI_API_KEY`. Sans clé, la déclaration reste possible et l'analyse est simplement omise. Si le modèle configuré ne répond pas, l'appel bascule vers un modèle flash lite.

## Nouveau style 3D

Depuis Mes vêtements, une photo réelle d'une pièce à réparer produit une proposition de style (titre, couleurs, gestes d'atelier) et une maquette 3D que l'on tourne à la souris. Le vêtement garde son type. Le style vient de la réparation : couture contrastée, pièce rapportée, teinture. Un rendu photo du vêtement restylé s'ajoute quand le modèle d'image Gemini répond.

## Conseiller textile

Sur Mes vêtements et l'historique, un chatbot répond uniquement aux questions de réparation, de don, de recyclage, d'entretien et de déclaration. Il connaît les pièces du citoyen connecté (type, état, parcours, verdict), sans photo. Une question hors de ce cadre reçoit un refus fixe, avant même l'appel au modèle si aucun mot textile n'est présent.

## Commandes

```bash
php artisan migrate
```

Les vêtements sont créés uniquement via le formulaire **Déclarer un vêtement** (données dynamiques MongoDB).
