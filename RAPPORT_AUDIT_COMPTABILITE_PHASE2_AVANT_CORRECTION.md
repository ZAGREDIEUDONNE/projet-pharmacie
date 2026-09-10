# RAPPORT D'AUDIT COMPTABILITE — PHASE 2 — AVANT CORRECTION

Date : 21 août 2026

## Statut

AUDIT TERMINE — AUCUNE CORRECTION APPLIQUEE

## Périmètre vérifié

Lecture des contrôleurs, services, routeur, contrôleur de base, middleware CSRF,
routes, vues comptables et migrations comptables. Contrôles SQL en lecture sur
la base `medecin` via `information_schema` et les tables métier concernées.

## Routes réellement enregistrées

`config/routes.php` déclare quinze routes comptables : quatorze GET et une POST.

| Méthode | URI | Action |
| --- | --- | --- |
| GET | `/comptabilite` | `ComptabiliteController@index` |
| GET | `/comptabilite/plan-comptable` | `planComptable` |
| GET | `/comptabilite/journaux` | `journaux` |
| GET | `/comptabilite/journaux/ventes` | `journalVentes` |
| GET | `/comptabilite/journaux/achats` | `journalAchats` |
| GET | `/comptabilite/journaux/caisse` | `journalCaisse` |
| GET | `/comptabilite/grand-livre` | `grandLivre` |
| GET | `/comptabilite/balance` | `balance` |
| GET | `/comptabilite/etats-financiers` | `etatsFinanciers` |
| GET | `/comptabilite/suivi-tiers` | `suiviTiers` |
| GET | `/comptabilite/tva` | `tva` |
| GET | `/comptabilite/integration` | `integration` |
| GET | `/comptabilite/exporter` | `exporter` |
| GET/POST | `/comptabilite/api` | `api` |

Le routeur impose l'authentification sur ces routes. Une requête HTTP sans
session sur `/comptabilite` a retourné réellement `302 Location: /login`.

## RBAC réel

`BaseController::requirePermission()` utilise bien `RBACService` et la base.
Les contrôleurs Admin et Charge de commande s'en servent par action. En
revanche, `ComptabiliteController::requireComptabiliteAccess()` appelle
seulement `requireAuth()` et exclut le rôle `CHARGE_COMMANDE` : aucune
permission n'est exigée.

Permissions constatées :

- COMPTABLE : 15 permissions comptables, dont `comptabilite_view`,
  `balance_view`, `tva_view`, `integration_view` et
  `etats_financiers_view`.
- VENDEUR, ASSISTANT et CHARGE_COMMANDE : aucune permission comptable.
- ADMINISTRATEUR : aucune permission comptable actuellement attribuée.

Conséquence prouvée par le code : tout utilisateur authentifié hors
`CHARGE_COMMANDE`, y compris VENDEUR ou ASSISTANT, peut atteindre les actions
comptables. À l'inverse, l'administrateur devra être refusé après la correction
tant que ses permissions réelles ne seront pas attribuées.

## CSRF et opérations mutatives

`CsrfService::isValid()` est utilisé par le routeur seulement pour certaines
actions POST Admin et Vente. `ComptabiliteController` n'est pas inclus dans
`Router::requiresCsrfProtection()`.

Des appels GET peuvent actuellement déclencher `integrerVentes`,
`integrerCommandes`, `integrerMouvementsCaisse`, `integrerStock`,
`executerToutesIntegrations`, `forcerSynchronisation`,
`initialiserPlanComptable`, `creerJournauxPrincipaux` et `initialiserTVA`.
Ce sont des opérations mutatives non protégées par CSRF.

## Dashboard et vues

Les treize vues comptables existent. Le dashboard utilise des services réels
pour la balance, les états, les tiers et les activités récentes, mais
`ComptabiliteController::index()` impose artificiellement :

- `exercices.total_exercices = 0`;
- `exercices.ouverts = 0`;
- `rapports.total_rapports = 0`.

Les KPI chiffre d'affaires, achats, trésorerie, TVA, créances et dettes ne sont
pas tous présentés comme indicateurs indépendants et sourcés.

## Schéma réel et moteurs

Les tables contrôlées sont InnoDB : `plan_comptable`, `ecritures_comptables`,
`lignes_ecritures`, `journaux_comptables`, `exercices_comptables`, `ventes`,
`mouvements_caisse`, `receptions`, `supplier_orders` et
`fournisseur_reglements`.

`exercices_comptables` existe mais est vide. Elle contient `id`, `exercice`
(unique), `date_debut`, `date_fin`, `statut`, les totaux et données de clôture.
La seule relation réelle est `utilisateur_cloture_id -> utilisateurs.id`.
Elle n'a pas de contrainte empêchant plusieurs exercices ouverts, ni de lien
avec les écritures. Son statut réel est l'enum minuscule
`ouvert|cloture|cloture_provisoire|archive`.

La migration `007_create_comptabilite_tables.sql` ne correspond pas totalement
au schéma réel : elle attend notamment un exercice entier et un enum en
majuscules, ainsi que des colonnes d'écritures non présentes dans le schéma
actuel. Elle ne doit donc pas être appliquée telle quelle.

## Écritures, intégrations et annulations

La base contient 76 écritures et 156 lignes. Le total des entêtes est équilibré
(70 748,75 débit et crédit) et aucune écriture ou groupe de lignes n'est
déséquilibré. Les références ne présentent aucun doublon.

Une écriture de recette subsiste : id 109, référence `TEST/999001`, journal 2,
pièce `VT202606230002`, date 2026-06-23, montant 1 000,00, libellé
« Test SYSCOHADA ». Elle n'est pas supprimée pendant cet audit.

Une vente annulée n'a pas d'écriture liée et aucune écriture
`reference_type LIKE 'ANNULATION%'` n'existe. Le code possède
`annulerEcritureVente()`, qui inverse les lignes et renseigne
`ecriture_origine_id`, mais sa protection contre une seconde contre-passation
reste à vérifier et formaliser dans les corrections.

## Plan comptable

Les comptes utilisés par les services existent et sont actifs : 31, 311, 401,
411, 44561, 44571, 521, 571, 581, 701, 75 et 6031. Les sens configurés sont
cohérents avec leurs types. Les comptes 44561, 44571, 581, 75 et 6031 ont
encore `code` et `libelle` à NULL, malgré un `numero_compte` et un nom présents.

## Restitutions et tiers

TVA, grand livre, balance et états reposent tous sur les lignes d'écritures,
mais n'ont pas encore une règle partagée explicitement liée à l'exercice et aux
contre-passations. Les suivis tiers comptables 411/401 coexistent avec des
suivis fonctionnels basés sur ventes, commandes et `solde_credit`.

## Tests existants

`scripts/audit_comptabilite_phase1.php`, `scripts/show_compta_schema.php`,
`scripts/test_grand_livre.php` et `scripts/validate_compta.php` existent.
`test_grand_livre.php` a été exécuté en lecture : PASS, 73 comptes du plan et
5 comptes avec soldes retournés. Aucun scénario créant des données n'a été
exécuté sur `medecin`.

## Décisions pour la correction

1. Utiliser `BaseController::requirePermission()` dans chaque action comptable.
2. Déplacer les actions mutatives vers POST, avec CSRF appliqué par le routeur.
3. Créer une migration additive, après contrôle du schéma, pour les règles
   d'exercice et les métadonnées strictement nécessaires.
4. Utiliser `medecin_test` pour toutes les recettes transactionnelles, sans
   écraser une base existante.
5. Ne pas appliquer les migrations historiques incompatibles et ne pas modifier
   les données métier de `medecin` pendant les tests.
