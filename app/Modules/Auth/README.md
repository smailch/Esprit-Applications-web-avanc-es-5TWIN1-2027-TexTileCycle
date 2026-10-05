# Module Auth — Utilisateurs (MongoDB)

## Périmètre

- Inscription / connexion / déconnexion (Front Office)
- Gestion CRUD utilisateurs (Back Office, admin uniquement)
- Modèle `User` stocké dans MongoDB — collection `users`

## Structure

```
app/Modules/Auth/
├── Models/User.php
├── Services/UserService.php
├── Http/Controllers/AuthController.php
├── Http/Controllers/Back/UserController.php
├── Http/Requests/
├── Http/Middleware/EnsureUserIsAdmin.php
├── Database/Migrations/
├── Database/Seeders/AdminUserSeeder.php
├── Routes/front.php
├── Routes/back.php
└── Providers/AuthModuleServiceProvider.php
```

## Commandes

```bash
php artisan migrate
php artisan db:seed
```

Compte admin par défaut (via `.env`) :

- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`

## Rôles

| Rôle | Front office | Back office | Menu back |
|------|--------------|-------------|-----------|
| `citoyen` | Oui (connecté pour Mes vêtements, RDV, Dons) | **Non** | — |
| `atelier` | Oui | Oui | Dashboard, Vêtements, Ateliers, RDV, Paramètres |
| `association` | Oui | Oui | Dashboard, Dons, Associations, Paramètres |
| `admin` | Oui | Oui (complet) | Toutes les sections + Utilisateurs |

Middleware : `backoffice`, `backoffice.route`, `admin`, `citizen`.
