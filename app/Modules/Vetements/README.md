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

## Commandes

```bash
php artisan migrate
```

Les vêtements sont créés uniquement via le formulaire **Déclarer un vêtement** (données dynamiques MongoDB).
