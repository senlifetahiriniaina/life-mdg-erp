# Documentation life-mdg-erp

Documentation technique de life-mdg-erp, adaptée depuis celle de Widehalo-ERP et widehalo-erp-core, réduite au périmètre des 27 modules retenus pour le lancement de Life MDG. Voir aussi `CLAUDE.md` (périmètre exact, décisions de trim, gaps connus) et `README.md` (démarrage rapide) à la racine.

- [**Catalogue des fonctionnalités**](CATALOGUE-FONCTIONNALITES.md) — vue commerciale/produit de ce que l'application permet de faire, module par module, avec une roadmap des extensions futures (base de contenu pour le site web commercial)

## 01 — Démarrage

- [Installation](01-DEMARRAGE/01-installation.md)

## 02 — Architecture

- [Vue d'ensemble](02-ARCHITECTURE/01-vue-ensemble.md)
- [Structure du code](02-ARCHITECTURE/02-structure-code.md)
- [Les 7 piliers fondateurs](02-ARCHITECTURE/PRINCIPES.md)
- [Carte des 27 modules et de leurs dépendances](02-ARCHITECTURE/CARTE-MODULES.md)

## 03 — Modules

Un fichier deep-dive par module gardé (27), groupés comme dans `CLAUDE.md` :

**Socle CORE / système (12)**
[Core](03-MODULES/Core.md) · [AI](03-MODULES/AI.md) · [Security](03-MODULES/Security.md) · [AuditLog](03-MODULES/AuditLog.md) · [API](03-MODULES/API.md) · [Integration](03-MODULES/Integration.md) · [Validation](03-MODULES/Validation.md) · [Shared](03-MODULES/Shared.md) · [Settings](03-MODULES/Settings.md) · [Setup](03-MODULES/Setup.md) · [Workflow](03-MODULES/Workflow.md) · [Calendar](03-MODULES/Calendar.md)

**Compta et Finance**
[Accounting](03-MODULES/Accounting.md)

**Commercial et CRM**
[CRM](03-MODULES/CRM.md) · [Sales](03-MODULES/Sales.md)

**Stock et Logistique**
[Inventory](03-MODULES/Inventory.md) · [Logistics](03-MODULES/Logistics.md) · [Achats](03-MODULES/Achats.md)

**Pilotage et Reporting**
[BI](03-MODULES/BI.md) · [Analytics](03-MODULES/Analytics.md) · [Reporting](03-MODULES/Reporting.md) · [Strategy](03-MODULES/Strategy.md)

**RH basique**
[HR](03-MODULES/HR.md) · [Payroll](03-MODULES/Payroll.md) · [Timesheets](03-MODULES/Timesheets.md) · [Projects](03-MODULES/Projects.md)

**Support**
[Helpdesk](03-MODULES/Helpdesk.md)

## 04 — API

- [Conventions transverses](04-API/CONVENTIONS.md) (authentification, rate limiting, pagination, format d'erreur)

## 05 — Frontend

- [Configuration](05-FRONTEND/01-configuration.md)

## 06 — Base de données

- [Schéma général](06-BASE-DE-DONNEES/SCHEMA-GENERAL.md) — deux niveaux de migrations, phénomène des tables stub, tables hors périmètre, chiffres clés (~803 tables)
- Inventaire des tables par module (généré depuis les migrations réelles, pas depuis la mémoire) :
  - [Socle CORE / système](06-BASE-DE-DONNEES/INVENTAIRE-01-SOCLE.md) — Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar
  - [Compta/Finance et Commercial/CRM](06-BASE-DE-DONNEES/INVENTAIRE-02-FINANCE-COMMERCIAL.md) — Accounting, CRM, Sales
  - [Stock et Logistique](06-BASE-DE-DONNEES/INVENTAIRE-03-STOCK-LOGISTIQUE.md) — Inventory, Logistics, Achats
  - [Pilotage et Reporting](06-BASE-DE-DONNEES/INVENTAIRE-04-PILOTAGE.md) — BI, Analytics, Reporting, Strategy
  - [RH basique et Support](06-BASE-DE-DONNEES/INVENTAIRE-05-RH-SUPPORT.md) — HR, Payroll, Timesheets, Projects, Helpdesk
- [Dépendances entre modules (niveau données)](06-BASE-DE-DONNEES/DEPENDANCES-MODULES.md)

## 07 — Déploiement

- [**Guide de déploiement simple (clone → HTTPS en une commande)**](07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md)
- [Vue d'ensemble (CI/CD, Docker, déploiement production)](07-DEPLOIEMENT/README.md)
- [Variables d'environnement — production](07-DEPLOIEMENT/ENV-PRODUCTION.md)
- [Checklist go-live](07-DEPLOIEMENT/CHECKLIST-GO-LIVE.md)
- [IA auto-hébergée (DeepSeek via Ollama)](07-DEPLOIEMENT/IA-AUTOHEBERGEE.md)

## 08 — Tests

- [Stratégie de tests](08-TESTS/STRATEGIE-TESTS.md)

## 09 — RBAC et sécurité

- [Matrice RBAC (22 rôles)](09-RBAC-SECURITE/MATRICE-RBAC.md)
- [Sécurité et conformité](09-RBAC-SECURITE/SECURITE.md)

## 10 — Contribution

- [Guide de contribution](10-CONTRIBUTION/GUIDE.md)

---

## Documents racine associés

- [`../CLAUDE.md`](../CLAUDE.md) — périmètre des 27 modules, décisions de trim documentées, gaps connus
- [`../README.md`](../README.md) — démarrage rapide
- [`../SECURITY.md`](../SECURITY.md) — politique de signalement de vulnérabilité
