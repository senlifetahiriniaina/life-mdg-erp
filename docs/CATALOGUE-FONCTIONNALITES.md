# Catalogue des fonctionnalités — Life MDG ERP

> Document de référence destiné à la conception du site web commercial de Life MDG ERP. Il recense, module par module, les fonctionnalités réellement disponibles et testées dans l'application, avec une description de ce qu'elles permettent de faire et comment les utiliser. La dernière section liste les extensions envisageables pour les prochaines versions.

## Sommaire

1. [Présentation générale](#présentation-générale)
2. [Comptabilité, Finance & Trésorerie](#1-comptabilité-finance--trésorerie)
3. [Commercial, CRM & Ventes](#2-commercial-crm--ventes)
4. [Achats & Fournisseurs](#3-achats--fournisseurs)
5. [Stock & Logistique](#4-stock--logistique)
6. [Ressources Humaines, Paie & Temps de travail](#5-ressources-humaines-paie--temps-de-travail)
7. [Gestion de Projets](#6-gestion-de-projets)
8. [Support Client (Helpdesk)](#7-support-client-helpdesk)
9. [Pilotage, Business Intelligence & Stratégie](#8-pilotage-business-intelligence--stratégie)
10. [Plateforme, Automatisation & Intégrations](#9-plateforme-automatisation--intégrations)
11. [Intelligence Artificielle intégrée](#10-intelligence-artificielle-intégrée)
12. [Sécurité, Conformité & Gouvernance](#11-sécurité-conformité--gouvernance)
13. [Roadmap — Extensions futures](#roadmap--extensions-futures)

---

## Présentation générale

**Life MDG ERP** est un progiciel de gestion intégré (ERP) modulaire, conçu pour les PME et entreprises de Madagascar et d'Afrique francophone. Il réunit sur une seule plateforme web la comptabilité, le commercial, les achats, le stock, la logistique, les ressources humaines, la gestion de projets, le support client et le pilotage stratégique — avec une intelligence artificielle intégrée à chaque étape du travail quotidien.

L'application est construite autour de sept principes fondateurs :

| Principe | Ce que cela signifie concrètement |
|---|---|
| **Africa First** | Normes comptables OHADA/SYSCOHADA, mobile money (Orange Money, MTN MoMo, Mvola, Airtel Money), Franc CFA/Ariary, interface disponible en plusieurs langues africaines |
| **Asia First** | Multi-devises, multi-fuseaux horaires, support des marchés asiatiques |
| **API First** | Chaque fonctionnalité métier est d'abord exposée en API REST avant d'avoir une interface — l'application peut être pilotée, intégrée ou automatisée de bout en bout |
| **Compliance First** | Conformité RGPD, protection des données personnelles, respect des normes OHADA, sécurité applicative (OWASP) |
| **Simplicity First** | Assistant de démarrage guidé, valeurs par défaut intelligentes selon le pays, import de données assisté par IA |
| **AI Assisted First** | Un assistant IA contextuel accompagne l'utilisateur sur chaque écran, chaque action, dans sa langue |
| **Strategy First** | Un tableau de bord d'indicateurs stratégiques (KPI/ratios) est présent dans chaque module, comparé aux repères du secteur, avec des recommandations générées par IA |

L'application couvre **28 modules interconnectés**, organisés en huit grands domaines fonctionnels décrits ci-dessous. Chaque module communique nativement avec les autres : une facture de vente peut déclencher un mouvement de stock, une opportunité commerciale peut ouvrir un ticket support, une commande d'achat peut alimenter automatiquement la comptabilité.

---

## 1. Comptabilité, Finance & Trésorerie

Le cœur financier de l'application, pensé pour les cabinets comptables et directions financières travaillant sous référentiel **OHADA/SYSCOHADA**, avec une adaptation aux spécificités de Madagascar (IRSA, CNaPS, OSTIE, Ariary).

**Ce que l'on peut faire :**

- **Plan comptable et écritures** : un plan comptable structuré selon la nomenclature SYSCOHADA (classes 1 à 7) est prêt à l'emploi dès l'installation, avec les comptes de trésorerie mobile money (Mvola, Airtel Money) déjà intégrés. Chaque opération se traduit par une écriture comptable en partie double, réellement équilibrée et vérifiable.
- **Facturation clients** : création de factures avec lignes de détail, calcul automatique des totaux, export PDF/Excel, suivi des statuts (brouillon, envoyée, payée, en retard), rappels sur factures échues.
- **Rapprochement bancaire** : import de relevés bancaires, suggestion automatique de rapprochements entre écritures et transactions bancaires, connexion à des services d'open banking.
- **Immobilisations & amortissements** : suivi des actifs immobilisés, calcul et planification des amortissements, gestion des dépréciations exceptionnelles.
- **Budgets & analyse des écarts** : construction de budgets par département/catégorie, comparaison budget réalisé vs prévu, alertes de dépassement, projection de scénarios ("et si les ventes baissaient de 10 % ?").
- **Consolidation multi-société** : regroupement des comptes de plusieurs entités liées (filiales, holding), avec élimination des opérations intragroupe et hiérarchie de périmètres de consolidation.
- **Import simplifié des opérations de caisse et banque** : un fichier CSV/Excel d'opérations de caisse ou de relevé bancaire est analysé automatiquement, chaque ligne se voit proposer un modèle d'écriture comptable adapté (vente comptant, paiement fournisseur, loyer, charges sociales…) que l'utilisateur valide en un clic — l'écriture équilibrée est générée et, si un compte bancaire est renseigné, directement rapprochée.
- **Simulation financière et prévisions "façon business plan"** : à la manière d'un outil de prévisionnel financier, l'utilisateur saisit des hypothèses de ventes et d'achats futurs (uniques, hebdomadaires ou mensuelles, avec un taux de croissance) et obtient une projection semaine après semaine ou mois après mois du compte de résultat, de la trésorerie et d'un bilan simplifié. Chaque ligne simulée peut ensuite être « réalisée » en un clic : elle devient une vraie commande client ou fournisseur et une vraie écriture comptable.
- **États financiers réglementaires OHADA** : génération du Bilan et du Compte de Résultat selon la présentation SYSCOHADA (actif immobilisé, stocks, créances, trésorerie active / capitaux propres, dettes financières, passif circulant, trésorerie passive ; chiffre d'affaires → marge → résultat d'exploitation → résultat financier → résultat net), directement exploitables pour les obligations déclaratives.
- **Déclaration de TVA**, gestion multi-devises avec taux de conversion, moteur de coûts de revient (CAPEX/OPEX) pour les projets et composants.
- **Chaîne d'approbation des factures** : circuit de validation à plusieurs niveaux selon des seuils de montant configurables, avec traçabilité complète des décisions.

**Comment on l'utilise :** un comptable enregistre ses opérations au fil de l'eau (ou les importe en masse), suit sa trésorerie en temps réel, produit ses états financiers en quelques clics et peut projeter l'impact financier de décisions futures avant de les prendre.

---

## 2. Commercial, CRM & Ventes

Un CRM complet couplé à un module de gestion des ventes, pour piloter tout le cycle du prospect jusqu'à la commande.

**Ce que l'on peut faire :**

- **Gestion des contacts, comptes et prospects (leads)** : fiche complète par contact et par entreprise cliente, historique de statut des prospects, qualification et conversion en opportunités.
- **Pipeline commercial visuel** : vue Kanban des opportunités par étape de vente, glisser-déposer, filtres par commercial/territoire, prévision de closing.
- **Devis et conversion en commande** : création de devis multi-lignes, envoi au client, conversion directe en commande de vente sans ressaisie.
- **Scoring intelligent des opportunités et des prospects** : un score de probabilité de conversion est calculé automatiquement à partir de critères pondérés configurables, avec un classement des meilleures opportunités et une prévision de pipeline.
- **Territoires commerciaux & quotas** : découpage du portefeuille client par zone géographique ou secteur, suivi de l'atteinte des quotas, rééquilibrage automatique des territoires, analyse des zones de couverture.
- **Prévisions de ventes** : plusieurs niveaux de prévision (par représentant, par équipe), calcul de la confiance de prévision à partir de l'historique de conversion et de la vélocité des affaires.
- **Campagnes commerciales internes** : séquences d'e-mails automatisées (relance, bienvenue, personnalisées), formulaires web publics de capture de prospects, suivi des inscriptions et de la performance des campagnes.
- **Gestion des commandes et devis de vente** (module Ventes) : suivi du cycle commande → confirmation → livraison → facturation, avec tableau de bord des indicateurs clés (chiffre d'affaires, commandes en cours, top clients).
- **Assistant IA commercial** : suggestion de la prochaine action à mener sur une opportunité, rédaction assistée de relances et d'e-mails de prospection, détection de doublons de contacts, analyse de sentiment sur les échanges.

**Comment on l'utilise :** une équipe commerciale saisit ses prospects, les fait progresser visuellement dans le pipeline, envoie des devis, les convertit en commandes, et suit en permanence ses objectifs et prévisions sans quitter l'application.

---

## 3. Achats & Fournisseurs

Un module complet de gestion des achats, du référencement fournisseur jusqu'à la réception des marchandises.

**Ce que l'on peut faire :**

- **Fiche fournisseur** : coordonnées, conditions de paiement, devise, historique des commandes passées.
- **Commandes d'achat (bons de commande)** : création multi-lignes, suivi du statut (brouillon, envoyée, approuvée, reçue), export.
- **Appels d'offres et comparaison de devis (RFQ)** : envoi d'une demande de prix à plusieurs fournisseurs, réception et comparaison de leurs devis côte à côte, acceptation ou refus en un clic.
- **Réception des marchandises** : enregistrement des réceptions de commande, avec écart entre quantité commandée et quantité livrée, signalement des anomalies qualité.
- **Chaîne d'approbation des achats** : circuit de validation configurable selon les seuils de montant, avec droits d'approbation/rejet dédiés.
- **Analyse des dépenses (Spend Analytics)** : répartition des dépenses par fournisseur et par mois, suivi des factures fournisseurs en retard.
- **Catalogue de templates de produits** : bibliothèque de modèles de produits pré-configurés par famille (matières premières, accessoires, produits semi-finis, produits finis), avec routage comptable suggéré à la création.
- **Veille des prix fournisseurs** : journal de comparaison entre le coût interne d'un article et les prix observés chez différents fournisseurs de référence, avec historique et tendance mensuelle — utile pour objectiver une négociation ou une décision de sourcing.
- **Fiche de chiffrage (nomenclature de coût)** : reproduit dans l'application le calcul de devis fait à la main pour un produit sur mesure (vêtement, EPI…) — matière, accessoires de montage, accessoires de finition, valeur ajoutée (impression, broderie), main-d'œuvre (temps de gamme × coût minute) et frais fixes, chaque ligne pouvant être libellée dans sa propre devise (converti automatiquement). Le coût de revient et un prix de vente suggéré (marge cible) sont recalculés et figés à chaque enregistrement, et une fiche déjà envoyée au client peut être dupliquée en nouvelle révision sans jamais modifier l'original — utile quand le client demande une modification après un premier chiffrage.

**Comment on l'utilise :** un service achats crée ses demandes de prix, compare les offres reçues, transforme la meilleure en commande, suit la réception physique des marchandises et garde une vision consolidée de ses dépenses fournisseurs ; l'équipe avant-vente chiffre un nouveau produit sur mesure à partir d'un template et obtient un prix de vente proposé en quelques minutes au lieu d'un calcul Excel manuel.

---

## 4. Stock & Logistique

Une gestion d'entrepôt multi-sites complète, de la réception à l'expédition, avec un module de transport et douane dédié.

**Ce que l'on peut faire :**

- **Catalogue produits et catégories** : fiches produits (stockable, consommable, service), unités de mesure, catégories avec compte comptable de rattachement.
- **Multi-entrepôts et emplacements** : suivi du stock par entrepôt et par emplacement précis (allée, étagère, casier), mouvements de stock tracés (entrée, sortie, ajustement, transfert).
- **Import de mouvements de stock avec création automatique des produits** : un fichier d'inventaire ou de réception est importé en masse ; tout article déjà connu est associé par référence ou par nom, et tout article inconnu est automatiquement créé dans le catalogue au moment de l'import — aucune ressaisie manuelle nécessaire.
- **Transferts inter-entrepôts, comptages tournants (cycle counts), préparation de commandes (picking)** avec file d'attente de tâches, mise en attente automatique de la prochaine commande à préparer.
- **Traçabilité par lot** : suivi des lots de fabrication ou de réception, avec transfert de lot entre entrepôts.
- **Retours (RMA)**, prévisions de la demande, valorisation du stock (coût moyen pondéré / FIFO par entrepôt), facteurs saisonniers.
- **Recherche par code-barres** : identification instantanée d'un produit ou d'un emplacement, enregistrement direct d'un mouvement de stock.
- **Canaux de vente en ligne** : connexion et synchronisation avec des places de marché externes.
- **Transport et logistique (module dédié)** : gestion des transporteurs et de leurs grilles tarifaires, expéditions avec suivi, tournées de livraison avec optimisation d'itinéraire, factures de fret, gestion douanière (déclarations, codes SH, calcul des droits).

**Comment on l'utilise :** un responsable d'entrepôt réceptionne les livraisons (manuellement ou par import de masse), suit le niveau de stock en temps réel par emplacement, prépare et expédie les commandes, et un responsable transport organise et suit les tournées de livraison jusqu'au client final.

---

## 5. Ressources Humaines, Paie & Temps de travail

Une gestion RH « essentielle » centrée sur l'administration du personnel, la paie et le temps de travail — sans les fonctions de recrutement/évaluation avancées, pour rester simple et rapide à déployer.

**Ce que l'on peut faire :**

- **Dossier employé** : fiche complète (poste, département, documents), organigramme par département.
- **Congés et absences** : demande de congé en libre-service, workflow d'approbation manager, calcul du solde de congés restant, types de congés paramétrables.
- **Présence & pointage** : pointage en libre-service, gestion des plannings d'équipes (shifts) récurrents, intégration de badgeuses biométriques, gestion des exceptions de présence.
- **Rémunération et bandes salariales** : historique de rémunération par employé, grilles salariales par poste avec analyse d'équité interne.
- **Paie multi-pays africains** : génération des bulletins de paie avec calcul réel de l'impôt sur le revenu progressif et des cotisations sociales, selon les barèmes officiels propres à chaque pays pris en charge (dont Madagascar — IRSA, CNaPS, OSTIE), avec gestion des heures supplémentaires à partir des feuilles de temps réelles.
- **Feuilles de temps (Timesheets)** : saisie du temps passé par projet/tâche, minuteur intégré, soumission hebdomadaire et approbation manager, facturation du temps aux clients, rapports de rentabilité par employé/projet.
- **Portail libre-service employé** : accès personnel aux bulletins de paie, à ses congés, à ses informations (avec masquage des données sensibles comme les coordonnées bancaires).

**Comment on l'utilise :** un gestionnaire RH administre les dossiers du personnel et les congés, un responsable planning organise les présences, et le service paie génère chaque mois les bulletins avec le bon calcul d'impôts et de cotisations selon le pays — le tout alimenté automatiquement par les feuilles de temps réelles des employés.

---

## 6. Gestion de Projets

Un module de gestion de projets complet, du planning à la facturation, connecté aux ressources humaines et à la comptabilité.

**Ce que l'on peut faire :**

- **Vues multiples** : diagramme de Gantt interactif, vue Kanban des tâches, calendrier de projet.
- **Epics & Sprints** : organisation du travail en grandes fonctionnalités (epics) découpées en sprints, pour les équipes travaillant en méthode agile.
- **Budget projet et suivi financier (EVM)** : suivi budgétaire par ligne, courbe de valeur acquise (CAPEX/OPEX), alerte de dépassement.
- **Facturation multi-modes** : facturation au jalon, au pourcentage d'avancement, en régie (temps passé), ou au forfait.
- **Feuilles de temps intégrées** : chaque heure enregistrée sur une tâche alimente directement le suivi budgétaire et la facturation du projet.
- **Automatisations de projet** : déclenchement d'actions automatiques selon l'avancement (ex. notification à l'approche d'une échéance).
- **Vue portefeuille** : feuille de route consolidée de tous les projets en cours.

**Comment on l'utilise :** un chef de projet planifie ses tâches et son équipe, suit l'avancement visuellement (Gantt ou Kanban), contrôle le budget en temps réel, et facture le client selon le mode contractuel choisi — jalon, régie ou forfait.

---

## 7. Support Client (Helpdesk)

Un module de support client qui a la particularité d'être **connecté nativement à tous les autres modules** de l'application.

**Ce que l'on peut faire :**

- **Tickets liés à n'importe quel enregistrement de l'ERP** : depuis une facture, un contact CRM, une commande d'achat, un produit, un projet ou une fiche employé, l'utilisateur peut ouvrir un ticket de support directement rattaché à cet enregistrement — et consulter tous les tickets liés à un client ou un produit donné en un coup d'œil.
- **Bouton "Signaler un incident" universel** : accessible depuis n'importe quel écran de l'application pour tout utilisateur connecté.
- **Automatisation des SLA** : application automatique d'un délai de réponse/résolution garanti dès la création du ticket, avec suivi des dépassements.
- **Base de connaissances** : articles d'aide organisés par catégorie, portail public consultable sans connexion, widget de chat autonome intégrable sur un site externe.
- **Assistance IA au support** : analyse de sentiment et d'urgence des messages entrants, suggestions de réponses et de modèles, prédiction du risque d'escalade, chatbot de réponse automatique aux questions fréquentes.
- **Performance des agents** : tableau de bord de productivité par agent, recommandations de coaching, objectifs individuels.
- **Escalade et règles métier** configurables selon la priorité, le canal ou le type de client.

**Comment on l'utilise :** un client ou un collaborateur signale un problème directement depuis l'écran où il se trouve (une facture, un produit…) ; le ticket est automatiquement rattaché au bon contexte, priorisé, et suivi jusqu'à sa résolution dans le respect du délai garanti.

---

## 8. Pilotage, Business Intelligence & Stratégie

Une suite décisionnelle complète pour transformer les données de l'ERP en tableaux de bord, rapports et recommandations stratégiques.

**Ce que l'on peut faire :**

- **Tableaux de bord personnalisables** : constructeur de dashboard par glisser-déposer, widgets connectés aux données réelles (stock, ventes, RH, finance…), partage et export.
- **Éditeur SQL** : requêtes personnalisées directement sur les données de l'entreprise, avec sauvegarde et réutilisation des requêtes favorites.
- **Alertes intelligentes** : déclenchement d'une alerte automatique lorsqu'un indicateur franchit un seuil défini (rupture de stock imminente, trésorerie sous un seuil critique, retard de production…).
- **Analyses prédictives** : tendance et taux de croissance du chiffre d'affaires, prévisions basées sur l'historique réel.
- **Rapports OHADA prêts à l'emploi** : bilan, compte de résultat, balance générale, journal, TVA, impôt sur les sociétés, créances/dettes échues — générés directement au format réglementaire, avec export.
- **Requêtes en langage naturel** : poser une question en français directement au système et obtenir une requête de données correspondante.
- **Cockpit stratégique (KPI/Ratios)** : plus de 30 ratios de pilotage couvrant tous les métiers de l'entreprise (finance, commercial, stock, RH, ventes, support), comparés à des repères de référence par secteur, avec sparklines d'évolution.
- **Benchmarks & analyse de corrélation** : comparaison de la performance de l'entreprise à des références du secteur, mise en évidence des liens statistiques entre indicateurs (ex. « le temps de première réponse support est corrélé à la satisfaction client »), avec interprétation automatique.
- **Objectifs stratégiques (OKR)** : arborescence Objectifs → Résultats clés, reliés aux ratios de pilotage réels, suivi de progression.
- **Carte de cascade stratégique** : visualisation de l'alignement entre objectifs d'entreprise et objectifs opérationnels.
- **Recommandations stratégiques par IA** : synthèse automatique de l'état de santé de l'entreprise et suggestions d'actions prioritaires, en langage clair.

**Comment on l'utilise :** un dirigeant ou un contrôleur de gestion configure ses tableaux de bord clés, consulte en un clin d'œil les ratios de pilotage de l'entreprise comparés au secteur, reçoit une alerte dès qu'un seuil critique est franchi, et suit l'avancement des objectifs stratégiques de l'entreprise dans le temps.

---

## 9. Plateforme, Automatisation & Intégrations

Le socle technique et fonctionnel qui rend l'ensemble cohérent, simple à démarrer et connectable à l'extérieur.

**Ce que l'on peut faire :**

- **Assistant de démarrage guidé (onboarding)** : configuration de l'entreprise en quelques étapes (pays, secteur, import de données existantes, activation des modules, invitation de l'équipe) — moins de 5 minutes pour démarrer.
- **Valeurs par défaut intelligentes selon le pays** : taux de TVA, devise, méthodes de paiement locales (mobile money) et fuseau horaire pré-remplis automatiquement selon le pays choisi, pour plus d'une dizaine de pays africains et asiatiques.
- **Import de données assisté par IA** : import de fichiers Excel/CSV/PDF existants (contacts, produits, employés…) avec proposition automatique de correspondance des colonnes par intelligence artificielle.
- **Moteur d'automatisation de type « no-code »** : création de règles d'automatisation (« si telle condition alors telle action ») entre modules par un constructeur visuel glisser-déposer façon n8n, avec bibliothèque de modèles prêts à l'emploi et plus de 70 déclencheurs / 50 actions couvrant tous les modules.
- **Calendrier centralisé** : agenda unique agrégeant automatiquement les échéances de tous les modules (congés RH, tâches projet, échéances comptables, SLA support…), synchronisation bidirectionnelle avec Google Calendar, Outlook et Apple Calendar.
- **Messagerie interne d'équipe en temps réel** : discussions directes ou de groupe entre collègues, accessibles depuis n'importe quel écran via un bouton dans la barre supérieure, avec badge de messages non lus et réception instantanée (sans recharger la page) grâce au canal temps réel déjà utilisé par les notifications.
- **Notifications intelligentes et étendues à tous les modules** : chaque étape importante d'un processus (une facture qui attend une validation, une demande de congé, un ticket support réassigné, un commentaire…) notifie en temps réel toutes les personnes qui y sont réellement intervenues, ainsi que le supérieur hiérarchique direct de la personne à l'origine de l'action — pas seulement l'auteur ou le destinataire final. Centre de notifications accessible depuis n'importe quel écran, avec historique complet et marquage lu/non lu.
- **Intégrations externes** : connecteurs mobile money (Orange Money, MTN MoMo, Mvola, Airtel Money), API REST complète et documentée pour connecter des outils tiers, système de webhooks, mise en relation inter-entreprises (partenaires) sécurisée.
- **Paramétrage central** : gestion des paramètres de l'entreprise, activation/désactivation des modules à la demande, gestion multi-entreprises (multi-tenant).
- **Champs personnalisés** et modèles de workflow adaptables au métier de chaque client.

**Comment on l'utilise :** une entreprise s'inscrit, répond à quelques questions de configuration, importe ses données existantes, active les modules dont elle a besoin, et peut ensuite automatiser ses processus répétitifs sans écrire une ligne de code. Pour communiquer en interne, il suffit de cliquer sur l'icône de messagerie dans la barre supérieure, disponible partout dans l'application.

---

## 10. Intelligence Artificielle intégrée

L'IA n'est pas une fonctionnalité à part : elle est présente sur pratiquement chaque écran de l'application, dans la langue de l'utilisateur.

**Ce que l'on peut faire :**

- **Assistant contextuel universel** : sur chaque écran, un panneau d'assistance explique quoi faire, propose les prochaines étapes, affiche des indicateurs de décision et des avertissements de conformité — adapté au rôle et à la langue de l'utilisateur.
- **Résumés et narrations automatiques** : explication en langage clair d'un tableau de bord, d'une prévision ou d'un écart budgétaire, sans avoir à interpréter soi-même les chiffres.
- **Détection d'anomalies** : repérage automatique de transactions ou de mouvements de stock inhabituels.
- **Recherche en langage naturel** à travers les données de l'entreprise.
- **Fonctionne même sans connexion à un fournisseur d'IA externe** : en l'absence de configuration IA, l'application bascule automatiquement sur des conseils pré-écrits pertinents plutôt que d'afficher une erreur — la fonctionnalité reste toujours disponible, à un niveau simple ou avancé selon la configuration.

**Comment on l'utilise :** l'utilisateur travaille normalement dans l'application ; l'assistant IA apparaît spontanément pour l'aider sur l'écran où il se trouve, sans configuration supplémentaire de sa part.

---

## 11. Sécurité, Conformité & Gouvernance

Une plateforme pensée pour être exploitée en toute confiance par des entreprises soumises à des obligations réglementaires strictes.

**Ce que l'on peut faire :**

- **Gestion des droits d'accès (RBAC)** : plus de 20 rôles prédéfinis (comptable, responsable achats, gestionnaire RH, agent support…), permissions fines par module et par action.
- **Authentification renforcée** : double authentification (2FA), configurable par rôle sensible.
- **Coffre-fort de secrets chiffré** : stockage sécurisé des identifiants et clés d'intégration, chiffrement des données sensibles au repos.
- **Journal d'audit complet** : traçabilité de chaque création, modification et suppression, avec horodatage et utilisateur responsable — consultable et exportable.
- **Circuits d'approbation métier** : moteur de workflow d'approbation générique réutilisable dans les achats, la comptabilité, les RH, avec seuils, niveaux hiérarchiques et délégation.
- **Sauvegarde et restauration** : sauvegarde compressée programmée de la base de données avec réconciliation automatique de schéma lors d'une restauration.
- **Conformité RGPD et protection des données personnelles** : export et suppression des données personnelles sur demande, masquage des données sensibles (coordonnées bancaires, identifiants nationaux) dans les interfaces libre-service.

**Comment on l'utilise :** un administrateur configure les rôles et permissions de son équipe, active le 2FA pour les postes sensibles, et peut à tout moment retracer qui a fait quoi dans le système — une exigence clé pour les audits et la conformité réglementaire.

---

## Roadmap — Extensions futures

Life MDG ERP est issu d'une plateforme plus large (WideHalo ERP, 49 modules), volontairement recentrée sur les 27 modules essentiels au démarrage d'une PME africaine, complétés depuis par une messagerie interne d'équipe (28e module). Les extensions ci-dessous représentent des axes d'évolution réalistes, soit parce que la brique existe déjà sur la plateforme mère et peut être réintégrée, soit parce qu'elles complètent naturellement le périmètre actuel.

### Nouveaux modules métier envisageables

- **Production / Fabrication (MRP)** : planification des besoins matières, gammes de fabrication, ordres de production, gestion de postes de travail et de leur capacité, maintenance préventive des équipements, contrôle qualité de production, traçabilité amont-aval par lot.
- **Point de vente (POS)** : caisse tactile pour la vente en boutique, tickets de caisse, gestion de sessions de caisse, synchronisation stock en temps réel.
- **E-commerce B2B/B2C** : boutique en ligne connectée au catalogue produit et au stock, marketplace multi-vendeurs.
- **Messagerie WhatsApp Business** : communication client automatisée (confirmations de commande, rappels de paiement, notifications de livraison) directement sur WhatsApp — canal particulièrement pertinent pour les marchés africains.
- **Marketing par e-mail avancé** : campagnes d'e-mailing avec modèles visuels, suivi d'ouverture/clic, automatisation marketing multicanal.
- **Gestion électronique de documents (GED)** : coffre-fort documentaire centralisé, versioning, signature électronique.
- **Planification avancée** : planning de ressources multi-équipes, gestion des remplacements et rotations.
- **Gestion de la qualité (ISO)** : audits qualité, documents contrôlés, catalogue de normes applicables par secteur (textile, BTP, industrie).
- **Cycle de vie produit (PLM)** et nomenclatures techniques avancées.
- **Gestion des immobilisations physiques (Assets)** et **gestion des contrats** (suivi des échéances, alertes de renouvellement).
- **RH avancée** : recrutement (ATS) avec suivi de candidature façon Kanban, évaluations de performance à 360°, catalogue de formations, plans de succession et organigramme de mobilité interne.

### Application mobile

Une application mobile native (iOS/Android) reprenant les fonctions clés de terrain — pointage, validation de congés, suivi de commande, tableau de bord — existe sur la plateforme mère et pourrait être proposée comme extension de Life MDG ERP, en complément de l'expérience web déjà optimisée pour mobile.

### Extensions à court terme sur le périmètre actuel

- **Activation des langues locales supplémentaires** : l'application est déjà préparée pour l'arabe, le swahili, le malgache, le haoussa, le chinois et le hindi (dictionnaires de traduction déjà présents) — leur activation dans le sélecteur de langue de l'interface est une extension rapide.
- **Sauvegarde infonuagique (cloud)** : le mécanisme de sauvegarde compressée est prêt à fonctionner avec un stockage cloud (S3 ou équivalent) en complément du stockage local.
- **API GraphQL complète** : un socle GraphQL existe déjà pour le CRM ; son extension aux autres modules permettrait des intégrations tierces encore plus flexibles.
- **Déduplication et fusion assistées par IA des contacts CRM** : le moteur de similarité par IA existe déjà pour un contact donné ; une détection de masse à l'échelle de toute la base de contacts, avec proposition de fusion, est une extension naturelle.
- **Automatisation complète achats → comptabilité → stock** : le rapprochement entre une commande d'achat, sa réception en stock et sa comptabilisation peut être poussé jusqu'à la génération automatique de l'écriture comptable et de l'allocation budgétaire correspondante.
- **Gestion des avances et prêts employés** dans le module Paie.
- **Portails libre-service élargis** : portail fournisseur (suivi de commande, dépôt de facture) et portail client (self-service support et facturation) au-delà du portail employé déjà disponible.
- **Certification officielle des formulaires déclaratifs locaux** (déclaration de TVA CA20 à Madagascar, notamment) auprès d'un expert-comptable local, pour un usage en production sans supervision.

---

*Document généré à partir de l'état réel et vérifié de l'application (audits fonctionnels successifs, tests automatisés couvrant l'ensemble des 28 modules). À utiliser comme base de contenu pour la conception du site commercial — les formulations peuvent être adaptées au ton marketing souhaité, le contenu fonctionnel reflète les capacités réelles du produit.*
