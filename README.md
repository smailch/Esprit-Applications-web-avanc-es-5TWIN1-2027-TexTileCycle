# TexTileCycle-Laravel

Plateforme web **TexTileCycle** — économie circulaire textile en Tunisie. Deux interfaces dans le même design system : **Front Office** (citoyens) et **Back Office** (ateliers, associations, administrateurs).

**Dépôt GitHub :** [mouradmissa/TexTileCycle-Laravel](https://github.com/mouradmissa/TexTileCycle-Laravel)

---

## Choix du template de départ

Ce projet ne part pas d’une page blanche : il s’appuie sur un socle Laravel standard, enrichi pour l’équipe.

| Élément | Choix retenu | Rôle |
|--------|--------------|------|
| Framework | **Laravel 9** | Routing, vues, auth, BDD |
| Vues | **Blade** | Front + back office, composants réutilisables |
| Structure | **Modules métier** (`app/Modules/`) | Travail collaboratif par domaine |
| API (prévu) | **Laravel Sanctum** | Authentification API |
| Styles | **`public/css/textilecycle.css`** | Design system vert `#2E7D32` |
| Langue | **Français** | Interface et contenus |

Architecture détaillée (diagrammes, modules, conventions équipe) : **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

---

## Prérequis

- **PHP** ≥ 8.0 (extensions : `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- **Composer** 2.x
- **MySQL** (ou MariaDB) — optionnel pour la phase actuelle (pages démo)
- **Git**

---

## Étapes de lancement du projet (local)

### 1. Cloner le dépôt

```bash
git clone https://github.com/mouradmissa/TexTileCycle-Laravel.git
cd TexTileCycle-Laravel
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer l’environnement

```bash
copy .env.example .env
```

Sous Linux / macOS :

```bash
cp .env.example .env
```

Ajuster au minimum dans `.env` :

```env
APP_NAME=TexTileCycle
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=textilecycle
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Clé d’application

```bash
php artisan key:generate
```

### 5. Base de données (quand les migrations métier seront prêtes)

```bash
php artisan migrate
```

### 6. Lancer le serveur de développement

```bash
php artisan serve
```

Ouvrir dans le navigateur :

- **Front Office :** [http://127.0.0.1:8000](http://127.0.0.1:8000)
- **Back Office :** [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)

### 7. Vérifier les routes

```bash
php artisan route:list
```

---

## Publier le projet sur GitHub (premier push)

À exécuter **à la racine du projet** si le dépôt local n’est pas encore initialisé, ou pour repartir proprement après avoir préparé le code.

> Ne jamais committer le fichier `.env` (secrets). Il est ignoré via `.gitignore`.

```bash
git init
git add .
git commit -m "first commit"
git branch -M main
git remote add origin https://github.com/mouradmissa/TexTileCycle-Laravel.git
git push -u origin main
```

Si le remote `origin` existe déjà :

```bash
git remote set-url origin https://github.com/mouradmissa/TexTileCycle-Laravel.git
git push -u origin main
```

---

## Structure rapide

```
app/Modules/          # Code métier par module (Vetements, Ateliers, Dons, …)
resources/views/      # Blade front / back + composants
routes/web.php        # Charge automatiquement les routes de chaque module
public/css/           # Feuille de style TexTileCycle
docs/ARCHITECTURE.md  # Maille architecturale et guide équipe
```

---

## Travail en équipe

- Un **module** = un périmètre (contrôleurs, routes, futurs modèles).
- Branches suggérées : `feature/module-vetements`, `feature/module-ateliers-rdv`, etc.
- Modifications du **Core** ou des layouts partagés : petite PR / revue croisée.

---

## Licence

Projet académique / équipe — voir le dépôt pour les conditions d’utilisation. Le framework Laravel est sous [licence MIT](https://opensource.org/licenses/MIT).
