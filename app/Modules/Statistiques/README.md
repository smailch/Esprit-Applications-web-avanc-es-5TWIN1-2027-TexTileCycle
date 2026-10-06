# Module 5 — Administration, Statistiques & Impact écologique

Responsable : Membre 5. Toutes les pages sont réservées au rôle `admin` (middleware `admin`).

## Périmètre

| Sous-module | Dossier | Routes | Collections MongoDB |
|---|---|---|---|
| Signalements (modération) | `app/Modules/Signalements` | `back.signalements.*`, `front.signalements.store` | `signalements` |
| Validation des partenaires | `app/Modules/Partenaires` | `back.partenaires.*` | `ateliers`, `associations` (champ `statut` uniquement) |
| Statistiques & impact + IA | `app/Modules/Statistiques` | `back.statistiques.*` | `statistiques`, `impact_ecologique` (lecture de toutes les autres) |

Les données des autres modules sont lues directement dans leurs collections via
`App\Modules\Core\Support\MongoCollections` : aucune dépendance aux modèles des autres membres.

## Pour les autres membres : bouton « Signaler »

```blade
<x-signaler cible-type="Atelier" :cible-id="$atelier->id" />
```

`cible-type` : `Vetement`, `Atelier`, `Association`, `Don` ou `User`. Le bouton n'apparaît que pour un utilisateur connecté.

## Indicateurs et impact

- **Vêtement sauvé** : statut `repare` / `donne` / `recycle` (module 1), RDV `termine` (module 3) ou don `accepte` (module 4). Chaque vêtement n'est compté qu'une seule fois.
- **Impact** (`ImpactCalculator`) : empreinte d'un vêtement neuf évité, selon le type (ordres de grandeur indicatifs). Réparation et don comptent à 100 %, recyclage à 30 %.

## IA : analyse prédictive (`AnalysePredictiveService`)

Le calcul se fait en PHP, sans API externe :

- **Prévision sur 3 mois** : régression linéaire par moindres carrés, corrigée d'un indice saisonnier quand l'historique couvre au moins 12 mois. La confiance est déduite du R².
- **Tendance** : 3 derniers mois complets comparés aux 3 précédents (seuil ±15 %).
- **Anomalie** : z-score du dernier mois complet (seuil |z| ≥ 2).
- **Saisonnalité** : écart moyen par saison par rapport à la moyenne annuelle.
- **Partenaires** : un atelier dont les RDV des 30 derniers jours tombent sous 50 % de sa moyenne mensuelle.

Il faut au moins 3 mois avec de l'activité. En dessous, l'IA affiche « Historique encore insuffisant ».

## Commandes

```bash
php artisan migrate                              # index signalements / statistiques
php artisan statistiques:consolider --mois=12    # (re)consolide les 12 derniers mois
```

La consolidation est planifiée tous les jours à 02:00 (`php artisan schedule:work` en local).
