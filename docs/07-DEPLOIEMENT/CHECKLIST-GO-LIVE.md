# Checklist go-live

## Avant le premier déploiement en production

- [ ] **Le mot de passe du compte `admin@life-mdg.com` (`admin` par défaut, seedé par `DatabaseSeeder`) a été changé.** Ce compte a délibérément tous les rôles pour faciliter le démarrage (Chantier 12) — des identifiants aussi prévisibles ne doivent jamais rester actifs sur une instance réellement exposée en production.
- [ ] Toutes les variables de `docs/07-DEPLOIEMENT/ENV-PRODUCTION.md` "à changer impérativement" sont configurées
- [ ] Les 6 secrets GitHub requis par `deploy.yml` sont configurés (`DEPLOY_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `SLACK_WEBHOOK_URL`, `SMOKE_TEST_TOKEN`)
- [x] `scripts/smoke-tests.js` est écrit (Chantier 11 — voir `docs/07-DEPLOIEMENT/README.md`)
- [ ] `APP_DOMAIN`/`DB_ROOT_PASSWORD` configurés dans `.env` sur le serveur cible, DNS de `APP_DOMAIN` vérifié propagé (`dig +short $APP_DOMAIN`) — voir `docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md`
- [ ] `scripts/deploy.sh` exécuté avec succès une première fois sur le serveur cible (initialise `DEPLOY_PATH` pour `deploy.yml`)
- [ ] `php artisan migrate:fresh --seed` s'exécute sans erreur sur une base de données de type production (MySQL, pas SQLite)
- [ ] `vendor/bin/pest` passe (les échecs pré-existants documentés dans `CLAUDE.md` sous "Known gaps" sont acceptés comme backlog, pas comme bloquants — mais aucun échec *nouveau* ne doit apparaître)
- [ ] `npm run build && npm run type-check` sans erreur
- [ ] Un parcours manuel du golden path a été testé : onboarding → création contact CRM → devis Sales → mouvement de stock Inventory → facture Accounting → pointage présence HR → cycle de paie Payroll → feuille de temps Timesheets sur un Project → création d'un ticket Helpdesk depuis une autre page → tableau de bord Strategy affichant des ratios cohérents
- [ ] Sauvegardes configurées et testées (`BACKUP_*` dans `.env`, cf. `spatie/laravel-backup`)
- [ ] Health check (`GET /api/health`) répond correctement depuis l'infrastructure de monitoring cible
- [ ] `ANTHROPIC_API_KEY` configurée si les guidances IA dynamiques sont souhaitées dès le lancement (sinon repli statique automatique, non bloquant)

## Après le premier déploiement

- [ ] Vérifier les logs applicatifs pendant les premières 24h (`storage/logs/laravel.log` ou Sentry si configuré)
- [ ] Vérifier que le job de rollback automatique de `deploy.yml` n'a pas été déclenché
- [ ] Confirmer que les workflows planifiés (`dependency-check.yml`, `security-audit-scheduled.yml`, `supply-chain.yml`) tournent bien selon leur cron une fois la branche mergée sur `main` (ces workflows ne se déclenchent pas automatiquement sur une branche `claude/**`, seul un déclenchement manuel via `workflow_dispatch` les exerce avant le merge)
