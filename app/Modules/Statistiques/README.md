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

## CRUD Signalement (back office)

| Action | Route | Vue |
|---|---|---|
| Liste + filtres | `GET /admin/signalements` | `back/signalements/index` |
| Ajout | `GET /admin/signalements/creer`, `POST /admin/signalements` | `create` + partial `_form` |
| Détail | `GET /admin/signalements/{id}` | `show` |
| Modification (pré-remplie) | `GET /admin/signalements/{id}/modifier`, `PUT …` | `edit` + partial `_form` |
| Modération | `PATCH /admin/signalements/{id}/moderation` | `show` |
| Suppression (confirmation) | `DELETE /admin/signalements/{id}` | `index`, `show` |

- **Validation :** Form Requests `Back\StoreSignalementRequest` et `Back\UpdateSignalementRequest`, avec messages en français. Les erreurs s'affichent sous chaque champ avec `@error` et les saisies sont conservées avec `old()`.
- **Relations Eloquent :**
  - `Signalement::auteur()` et `traitePar()` → `User`
  - `Signalement::cible()` → `morphTo` vers Vetement, Atelier, Association, Don ou User
  - `Statistique::impact()` ↔ `ImpactEcologique::statistique()`, en 1:1 sur `periode`
- **Exploitation dans les vues :** les listes déroulantes « auteur » et « élément signalé » sont construites à partir des modèles des autres modules. La page détail affiche l'auteur, la cible, l'admin qui a traité le signalement et les autres signalements sur la même cible.

Côté citoyen, la page `GET /mes-signalements` permet de suivre ses propres signalements.

## Seeders et factories

- **Factories :**
  - `SignalementFactory` (états `enAttente()`, `traite($admin)`, `rejete($admin)` et `pour($cible)`)
  - `StatistiqueFactory`, qui crée automatiquement l'`ImpactEcologique` lié
  - `ImpactEcologiqueFactory`
- **Seeders**, appelés par `DatabaseSeeder` et idempotents (ils ne touchent qu'aux documents `source = seed`) :
  - `SignalementSeeder` : signalements reliés aux vrais citoyens, ateliers, vêtements et associations.
  - `HistoriqueStatistiquesSeeder` : 24 mois d'historique de démonstration *avant* la première activité réelle, avec croissance et saisonnalité. Ces mois portent le badge « démo » dans l'historique.

```bash
php artisan db:seed
```

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
