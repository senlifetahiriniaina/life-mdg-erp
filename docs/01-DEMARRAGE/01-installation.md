# Installation

## Prérequis

- PHP 8.2+ (le projet cible 8.3/8.4 en CI et en production, cf. `Dockerfile`)
- Composer 2.x
- Node.js 22+ et npm
- Une base de données (MySQL 8.4+ recommandé en production ; SQLite en mémoire suffit pour les tests)
- Redis (cache/session/queue en production — optionnel en développement local, cf. `.env.example`)

## Installation backend

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Le `.env.example` contient déjà `ENABLED_MODULES` avec la liste des 27 modules du périmètre life-mdg-erp, une base par défaut nommée `life_mdg_erp`, et des valeurs de démonstration pour Reverb/Meilisearch/S3 — à adapter selon votre environnement.

`php artisan migrate --seed` exécute `database/seeders/RolesAndPermissionsSeeder.php`, qui crée les 22 rôles RBAC (voir `docs/09-RBAC-SECURITE/MATRICE-RBAC.md`).

## Installation frontend

```bash
npm install
npm run dev
```

Le frontend est un module Vue 3 + Inertia.js classique : pas de dossier `webapp/` séparé, le point d'entrée est `resources/js/app.js`, servi par le serveur Laravel lui-même (pas de serveur frontend distinct en production — `npm run build` génère les assets dans `public/build/`).

## Démarrer le serveur de développement

```bash
php artisan serve
```

Avec `npm run dev` lancé en parallèle pour le hot-reload Vite.

## Vérifier l'installation

```bash
vendor/bin/pest                          # tests backend (Unit + Feature + Integration)
npm run build && npm run type-check      # build + vérification TypeScript frontend
php -l $(find app Modules -name "*.php") # sweep de syntaxe PHP — utile après tout ajout de module
```

## Dépannage courant

- **`nunomaduro/larastan` échoue à l'installation** : ce package (analyse statique, dev uniquement) peut nécessiter un accès réseau à l'API GitHub selon votre environnement. Il n'est pas requis pour lancer l'application ou la suite de tests — vous pouvez le retirer temporairement de `composer.json` si votre réseau le bloque.
- **`composer.lock` / `package-lock.json` absents du dépôt** : ils sont volontairement dans `.gitignore` (convention héritée de Widehalo-ERP) — relancez `composer install`/`npm install` (pas `npm ci`, qui exige un lockfile déjà existant plutôt que d'en générer un — cette confusion a fait échouer les workflows CI avant correction, voir l'historique de commits de `.github/workflows/`) pour les régénérer localement.
- **Erreurs de namespace PSR-4 après ajout d'un fichier dans `Modules/`** : ce dépôt a un historique de mismatches namespace/nom-de-fichier qui font disparaître silencieusement des classes de l'autoload (voir l'historique git de l'extraction). Un `php -l` large sur les fichiers modifiés est la meilleure protection.
