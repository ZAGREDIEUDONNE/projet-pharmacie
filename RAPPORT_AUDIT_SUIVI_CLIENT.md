# Audit des permissions - Suivi Client

Date : 14 juillet 2026

## Resultat de l'audit

| Controle | Resultat | Correction |
|---|---|---|
| Routes du module | Les 11 routes metier existaient et visaient `SuiviClientController` | Ajout des routes REST de compatibilite (`create`, `store`, `edit`, `update`, `destroy`, `solde-client`, `releve-client`) |
| Controle d'acces | Le controleur demandait `suivi_client.lister` et `suivi_client.saisie_reglement` | Remplacement par les permissions canoniques demandees |
| Permissions | Les 7 permissions `suivi_client.*` demandees etaient absentes des migrations et les anciens codes etaient encore declares par un service secondaire | Migration 019 idempotente ajoutee et liste secondaire alignee |
| Roles | Aucune attribution Suivi Client n'etait versionnee | La migration attribue les droits adaptes a Administrateur, Pharmacien/Assistant, Vendeur et Charge de commande |
| Administrateur | `RBACService` ne reconnaissait que le code `ADMIN` | `ADMINISTRATEUR` est maintenant aussi traite comme administrateur |
| Menus | Les sous-menus Suivi Client etaient affiches sans verification | Les menus Vente et Assistant sont masques sans `suivi_client.view`; les actions restent protegees cote serveur |
| Vues | Toutes les vues ciblees par les routes existaient | Ajout du `return_to` transmis par le controleur |
| Middleware | Le routeur n'attache aucun middleware de permission par route | Les controles sont effectues dans le controleur, avec une permission precise par action |

## Matrice route / permission

| Route | Permission requise |
|---|---|
| `GET /suivi-client`, `liste-clients` | `suivi_client.view` |
| `GET/POST saisie-reglement`, `ouvrir-saisie` | `suivi_client.reglement` |
| `recu-reglement`, `releve-*` | `suivi_client.releve` |
| `solde-*` | `suivi_client.solde` |
| Routes REST `create/store/edit/update/destroy` | `create`, `update`, `delete` respectivement (et controles metier delegues) |

## Inventaire RBAC observe

- Tables actives definies : `utilisateurs`, `roles`, `permissions`, `role_permissions`; `user_permissions` est l'equivalent de permissions individuelles.
- Les tables `users` et `user_roles` ne sont pas utilisees par ce projet.
- Aucun dossier de seeders n'existe. La migration 019 est donc le mecanisme versionne de peuplement des permissions.
- `PermissionService` est un ancien chemin RBAC incompatible avec le schema actif (`code`/`utilisateur_permissions` au lieu de `nom`/`user_permissions`). Il n'est pas branche aux routes du module et doit etre refactorise dans un audit RBAC global separe.

## Limite de verification

Le serveur MySQL local n'etait pas en ecoute pendant l'audit (connexion refusee sur `localhost:3306`). Les donnees reelles des roles, permissions et utilisateurs n'ont donc pas pu etre listees ni modifiees dans cette execution. Executer `019_suivi_client_permissions.sql` sur la base active applique les corrections sans doublon.
