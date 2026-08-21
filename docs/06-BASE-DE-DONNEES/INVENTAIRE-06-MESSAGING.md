# Inventaire des tables — Messaging

Messagerie interne d'équipe en temps réel, ajoutée au Chantier 20 — voir `CLAUDE.md` § Chantier 20 pour le contexte complet (extension de la notification, service `ParticipantNotificationService`, wiring temps réel via Reverb/Echo). Contrairement aux 27 modules du périmètre initial (voir `CLAUDE.md` § Scope: 28 modules), Messaging a été construit pour de vrai en cours de session — 3 tables, un seul fichier de migration additif.

| Table | Rôle |
|---|---|
| `msg_conversations` | Une conversation (`type` direct/group), scopée `company_id`, `created_by` |
| `msg_conversation_participants` | Pivot participant↔conversation, `last_read_at` alimente le compteur non-lu |
| `msg_messages` | Un message d'une conversation (`body`, `attachment_path` — colonne réelle, migrée, mais sans aucun chemin d'écriture pour l'instant, voir `CLAUDE.md`) |

## Détail des colonnes (succinct)

Extrait directement du schéma SQLite réellement migré (`Schema::getColumns()`), pas des fichiers de migration. Format : `colonne:type` — `!` = non nullable (requis).

**`msg_conversations`** — `id:integer!,company_id:integer,type:varchar!,name:varchar,created_by:integer,created_at:datetime,updated_at:datetime`

**`msg_conversation_participants`** — `id:integer!,conversation_id:integer!,user_id:integer!,last_read_at:datetime,created_at:datetime,updated_at:datetime`

**`msg_messages`** — `id:integer!,conversation_id:integer!,sender_id:integer!,body:text!,attachment_path:varchar,created_at:datetime,updated_at:datetime`
