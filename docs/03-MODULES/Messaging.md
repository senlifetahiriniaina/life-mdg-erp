# Messaging

## Rôle

Le module Messaging est la messagerie interne temps réel de Life MDG ERP — conversations directes (1-à-1) et de groupe entre utilisateurs d'une même société, avec diffusion en temps réel via Laravel Reverb/Echo, compteur de messages non lus, et une assistance IA contextuelle. Construit pour de vrai au Chantier 20 (voir `CLAUDE.md`) — pas issu de l'extraction WideHalo-ERP d'origine, ajouté à part au périmètre 28 modules de Life MDG (« Communication » dans le tableau de scope de `CLAUDE.md`).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Conversation` | `msg_conversations` | Une conversation `direct` (exactement 2 participants) ou `group` (3+), avec `company_id`, `name` (optionnel, surtout utile pour un groupe), `created_by` |
| `ConversationParticipant` | `msg_conversation_participants` | Table pivot (`conversation_id`+`user_id`, unique) portant `last_read_at`, la seule source de vérité pour le calcul du nombre de messages non lus |
| `Message` | `msg_messages` | Un message (`sender_id`, `body`, `attachment_path` — colonne réelle migrée, aucun chemin d'écriture construit, voir Particularités) |

## Endpoints principaux

Toutes les routes API sont sous `auth:sanctum`, `session.security`, `tenancy.user`, `module:Messaging`, préfixe `v1/messaging` (`Modules/Messaging/routes/api.php`). **Aucun gate `role:` depuis le Chantier 32.28** (voir Sécurité/RBAC) — la messagerie interne est une fonctionnalité universelle, pas scopée par métier.

| Méthode | Route | Description |
|---|---|---|
| GET | `users` | Annuaire des utilisateurs de la même société (pour le sélecteur de destinataires) |
| GET/POST | `conversations` | Lister mes conversations (avec `unread_count`) / en créer une nouvelle |
| GET | `conversations/{conversation}` | Détail d'une conversation — réel, testé, autorisé, mais aucun appelant frontend actuel (voir Particularités) |
| GET/POST | `conversations/{conversation}/messages` | Lister les messages (marque la conversation comme lue) / en poster un |
| POST | `ai/assist` | Guidance IA contextuelle — voir IA |

Route web (`auth` + `module:Messaging`, pas de gate `role:`) : `GET /messaging` → `Inertia::render('Messaging/Index')`.

## Contrôleurs

- **`Api\ConversationController`** — `users()`/`index()`/`store()`/`show()`. Corrigé en profondeur au Chantier 32.28 (voir Sécurité et Format de données ci-dessous).
- **`Api\MessageController`** — `index()` (pagine 50 messages, marque la conversation lue pour l'appelant) / `store()` (valide `body` ≤5000 caractères, diffuse `MessageSent`).
- **`Api\MessagingAiAssistController`** — délègue à `AiContextualAssistantService::getGuidance(module: 'Messaging', ...)`.

## Vues (Vue/Inertia)

- **`resources/js/Pages/Messaging/Index.vue`** (racine, pas sous `Modules/`) — page complète : liste de conversations + fil actif + modal « Nouvelle conversation », abonnement Echo temps réel par conversation active. **Corrigé au Chantier 32.28** : n'appelait jamais `useAiAssistant()` malgré `MessagingAiAssistController` existant depuis le Chantier 20 — même motif « page réelle, jamais câblée à l'IA » déjà trouvé et corrigé pour Strategy (Chantier 30) et une dizaine d'autres modules cette session.
- **`resources/js/Components/Messaging/MessagingLauncher.vue`** — widget de notification/accès rapide monté dans `AppLayout.vue` (topbar, à côté de `NotificationBell`), badge de messages non lus agrégé sur toutes les conversations, sondage 30s en secours + abonnement Echo par conversation connue.

## Format de données (Chantier 32.28)

`$fillable` des 3 modèles confirmé correspondre exactement aux colonnes réellement migrées (`Schema::getColumnListing()`) — aucune colonne fantôme trouvée, contrairement à la plupart des autres modules audités cette session (le module est réellement neuf, pas hérité de WideHalo-ERP).

**`Conversation::lastMessage()` corrigé** — était un `hasMany(...)->latest()` chargé en intégralité via `->with(['lastMessage'])` puis réduit à une seule ligne via `->first()` en PHP : confirmé empiriquement que la requête SQL générée n'avait **aucune clause `LIMIT`**, chargeant en mémoire l'intégralité de l'historique de chaque conversation juste pour en garder le dernier message. Remplacé par `hasOne(Message::class)->latestOfMany()`, une vraie requête « une ligne par parent » (sous-requête `MAX(id)` + jointure), confirmée via un test avec 20 messages.

## Sécurité (Chantier 32.28)

**IDOR cross-tenant confirmé et corrigé** : `ConversationController::store()` validait chaque `user_ids.*` avec `exists:users,id` mais **aucune vérification d'appartenance à la même société** — l'endpoint `GET users` ne fait que restreindre les *options* proposées au sélecteur, il ne contraint en rien ce que l'API accepte réellement en écriture. Confirmé empiriquement qu'un utilisateur authentifié pouvait créer une conversation privée réelle et persistante avec un utilisateur d'une **société totalement différente**, en lui envoyant simplement son id. Corrigé avec une règle de validation par closure comparant `(int)(destinataire->company_id ?? 0)` à `(int)(appelant->company_id ?? 0)` — même convention de sentinel déjà établie ailleurs dans l'app pour ce type de comparaison.

**Plantage fatal confirmé et corrigé** : POSTer `user_ids: [monPropreId]` (seul) faisait planter `store()` avec un `ErrorException` réel (« Undefined array key 1 ») sur la logique de réutilisation de conversation directe, qui suppose toujours exactement 2 participants — confirmé via une vraie requête HTTP (500, pas une hypothèse de lecture de code). Corrigé en excluant explicitement l'id de l'appelant de la liste de destinataires *avant* de décider direct/groupe, et en rejetant proprement (422 « Vous ne pouvez pas démarrer une conversation avec vous-même ») si la liste de destinataires devient vide après cette exclusion.

**XSS/CSRF** : le corps d'un message est affiché via l'interpolation Vue `{{ }}` (échappement automatique, pas de `v-html`) — pas d'injection possible. Toutes les requêtes mutantes du frontend passent par `axios` (jamais un `fetch()` brut sans en-tête CSRF, contrairement au bug déjà trouvé et corrigé ~9 fois cette session sur d'autres modules) — confirmé par relecture des 2 fichiers Vue du module.

## Permissions RBAC (Chantier 32.28 — corrigé)

**Gate de rôle trop restrictif, confirmé et corrigé** : la route API portait `role:employee,manager,admin,super-admin` — confirmé empiriquement (vraie requête HTTP) qu'un utilisateur portant uniquement un rôle métier spécialisé (`sales-rep`, `hr-manager`, etc.) recevait un 403 systématique sur toute la messagerie. Cette app attribue un seul rôle par utilisateur réel (`DemoSeeder::syncRoles([$d['role']])`, jamais de cumul avec `employee`), donc la quasi-totalité de l'effectif réel de l'application n'aurait jamais pu utiliser la messagerie interne — en contradiction directe avec son propre objectif affiché (« internal team messaging »). Corrigé en retirant entièrement le gate `role:` (ne conserve que `module:Messaging`, qui permet toujours à un administrateur de désactiver le module par tenant) — même précédent que les routes universelles `notifications/*` de Core (`auth:sanctum`+`tenancy.user` seuls, aucun gate de rôle). `ConversationPolicy::viewAny()`/`create()` restent inconditionnellement `true` pour tout utilisateur authentifié, cohérent avec ce changement — `view()`/`post()` restent correctement scopés à une vérification réelle d'appartenance à la conversation (`participants()->where('user_id', ...)->exists()`).

Aucune entrée dans `RolesAndPermissionsSeeder::MODULES` (confirmé inchangé, aucune permission granulaire `messaging.*.*` n'est seedée) — cohérent avec le choix ci-dessus : l'accès n'est pas un droit métier fin, seulement « le module est activé pour ce tenant ».

## Format de réponse API (couche 12, Chantier 32.28)

`ConversationController::index()` renvoie `{"data": [...]}` — chaque ligne portant `id`/`type`/`name`/`users`/`last_message`/`unread_count` — exactement ce que consomment `Index.vue` et `MessagingLauncher.vue` via `data.data`. `MessageController::index()` renvoie le paginateur brut (`data`/`links`/`meta` standard Laravel), et le frontend lit correctement `data.data`. `MessageController::store()` renvoie `{"data": {...}}`. Aucune divergence trouvée entre la forme réellement renvoyée et ce que les 2 fichiers Vue du module lisent — confirmé par grep des deux fichiers avant correction, pas supposé.

## Performance (couche 14f, Chantier 32.28)

**N+1 confirmé et corrigé** : `ConversationController::index()` exécutait, pour chaque conversation retournée, une requête `participants()->where('user_id', ...)->first()` (entièrement redondante — le pivot `last_read_at` est déjà chargé gratuitement par `User::conversations()`'s propre `withPivot('last_read_at')`) **et** une requête `COUNT` séparée pour le nombre de messages non lus. Mesuré empiriquement : 5 conversations coûtaient 16 requêtes SQL au total, dont 10 formant exactement cette paire N+1. Remplacé par une seule requête agrégée (jointure `msg_messages`/`msg_conversation_participants`, groupée par conversation) couvrant toutes les conversations de la page en un seul aller — confirmé après correctif : 7 requêtes pour les 5 mêmes conversations. Ce correctif a aussi réglé un bug de justesse trouvé au passage : l'ancien COUNT ne excluait jamais les messages envoyés par l'appelant lui-même, donc une conversation tout juste créée contenant uniquement un message que l'appelant venait d'envoyer lui-même (ligne participant créée avec `last_read_at` encore `NULL`) s'affichait comme non lue... à ses propres yeux.

## AI Assisted First (couche 13, Chantier 32.28)

**`'Messaging'` n'était enregistré nulle part dans `AiContextualAssistantService::supportedModules()`** malgré `MessagingAiAssistController` réel et routé depuis le Chantier 20 — chaque appel se résolvait silencieusement vers `emptyGuidance()` (`enabled:false`, tous les champs vides), et le test préexistant qui couvrait cet endpoint ne vérifiait que `enabled === false`, ce qui passe indifféremment qu'un module soit enregistré avec un vrai contenu de repli ou totalement absent — donnant une fausse impression de couverture. Même double lacune que Strategy au Chantier 30. Corrigé : `'Messaging' => ['view_dashboard', 'start_conversation']` enregistré, avec un vrai texte de repli en français et en anglais pour les deux actions, et `Index.vue` câblé pour de vrai avec `useAiAssistant('Messaging', 'view_dashboard')` + `<AIAssistantPanel>` — jusqu'ici la seule page réelle du module n'affichait jamais de panneau d'assistance.

## Relationnel (couche 10, Chantier 32.28)

`msg_conversation_participants.user_id`/`msg_messages.sender_id` portent tous deux une vraie contrainte FK vers `users` avec `cascadeOnDelete()` — délibérément agressif (`User` utilise `SoftDeletes`, donc ce cascade ne se déclenche que sur une vraie suppression définitive, typiquement un effacement RGPD/purge de tenant, où purger aussi le contenu de messagerie de l'utilisateur purgé est cohérent). `msg_conversations.created_by` n'avait en revanche **aucune contrainte FK du tout** — incohérent avec le précédent déjà établi ailleurs dans l'app pour une colonne « créé par » (ex. `CostingSheet`/`ProductionOrder` : `foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()`). Corrigé par une migration additive (`nullOnDelete()`, pas `cascadeOnDelete()` — contrairement à un message/participant, une conversation elle-même doit survivre à la purge de son créateur, simplement avec `created_by` remis à `NULL`, pour les participants restants).

## Fake/Dead (couche 9, Chantier 32.28)

Aucun code mort trouvé dans ce module — cohérent avec le fait qu'il est réellement neuf (construit au Chantier 20, jamais hérité de WideHalo-ERP). Une seule zone classée explicitement : **`ConversationController::show()` (`GET conversations/{id}`) — réel, testé, correctement autorisé, mais confirmé sans aucun appelant frontend actuel** (ni `Index.vue` ni `MessagingLauncher.vue` ne l'appellent jamais — les deux se contentent de la liste déjà chargée par `index()`). Classé **« à conserver »**, pas « mort à supprimer » : c'est un point d'accès REST générique et correct (principe API First — un client futur, mobile ou autre, peut légitimement vouloir consulter une seule conversation par id), le même traitement déjà accordé à d'autres endpoints génériques « réels mais sans appelant frontend actuel » ailleurs dans cette session (ex. Projects' `create_project`/`assign_task`).

## Particularités du périmètre life-mdg-erp

**`attachment_path` — colonne réellement migrée, aucun chemin d'écriture construit, confirmé toujours vrai au Chantier 32.28** : documenté dès le Chantier 20 comme la seule pièce délibérément différée du module (« pas de flux de téléversement de pièce jointe construit... bien que `attachment_path` soit une vraie colonne migrée sans aucun rédacteur »). Re-confirmé empiriquement : `MessageController::store()` ne valide/n'écrit que `body`, donc poster un `attachment_path` depuis le client est silencieusement ignoré (le champ reste `NULL` en base) plutôt que d'ouvrir une faille (ex. un chemin de fichier arbitraire côté serveur) — la colonne existe, prête pour une future fonctionnalité de pièce jointe, sans rien de vulnérable en attendant.

**Ajout/retrait de participants après création — non construit, jugé hors périmètre d'un audit de bugs** : une fois une conversation de groupe créée, aucun endpoint ne permet d'y ajouter ou d'en retirer un participant — c'est une vraie limitation fonctionnelle, pas un bug, et construire cette gestion serait une nouvelle fonctionnalité (pas une correction), documentée ici plutôt que devinée silencieusement.

**Aucune utilisation de `Modules\Core\Services\ParticipantNotificationService` (couche 11, CORE)** — délibéré, pas un gap : `ParticipantNotificationService::notifyProcess()` (Chantier 20) alimente la table `notifications` pour des flux métier asynchrones (approbations, congés, tickets) où l'utilisateur n'est pas forcément en train de regarder l'écran concerné. La messagerie a déjà son propre mécanisme temps réel dédié (diffusion `MessageSent` sur un canal privé par conversation + badge de non-lus recalculé à chaque chargement) qui couvre exactement le même besoin de façon plus adaptée à un usage de chat — dupliquer via une notification stockée en base serait redondant, pas un renforcement.

**Aucun export PDF/Excel, aucune alimentation KPI/OKR** — sans objet pour une fonctionnalité de messagerie interne (pas un rapport de pilotage, pas une donnée qu'un ratio Strategy First aurait vocation à suivre) — confirmé délibérément hors périmètre, pas un oubli.
