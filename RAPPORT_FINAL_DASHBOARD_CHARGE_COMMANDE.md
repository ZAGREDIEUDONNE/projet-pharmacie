# Rapport final — Dashboard Chargé de commande

Date : 21 août 2026

## État avant correction

Le flux de réception ouvrait bien une transaction dans `ChargeCommandeService::receiveOrder()`, mais `supplier_orders`, `supplier_order_items`, `receptions`, `reception_items` et `stock_entries` étaient en MyISAM. Un rollback pouvait donc laisser une réception persistante.

## Cause racine

MyISAM ne prend pas en charge les transactions. Les tables `stock` et `mouvements_stock` étaient InnoDB, d'où un rollback partiel : le stock était restauré, mais l'en-tête de réception ne l'était pas.

## Audit et migration

Audit réel via `information_schema.TABLES`, puis vérification des colonnes, index, clés étrangères, contraintes, triggers et vues dépendantes avant modification.

- Aucune clé étrangère n'était déployée sur les tables concernées.
- Les index et colonnes ont été conservés.
- Les seules contraintes CHECK du flux restent `chk_stock_positive` et `chk_quantite_mouvement`.
- Aucun trigger ne cible ces tables et aucune vue ne dépend des tables converties.

Migration appliquée : `database/migrations/035_receptions_innodb.sql`.

Tables converties conditionnellement vers InnoDB :

- `supplier_orders`
- `supplier_order_items`
- `receptions`
- `reception_items`
- `stock_entries`
- `fournisseur_reglements` — branche utilisée lorsqu'une facture fournisseur est renseignée.

La migration ne recrée aucune table, ne modifie pas les données métier et est idempotente. Après migration, `supplier_orders`, `supplier_order_items`, `receptions`, `reception_items`, `stock_entries`, `stock`, `mouvements_stock` et `fournisseur_reglements` sont tous en InnoDB.

## Correction de `receiveOrder()`

La méthode valide désormais la date, les lignes et les quantités avant toute écriture. Les quantités d'une même ligne sont agrégées pour empêcher un contournement du reliquat. La commande et chaque ligne sont verrouillées avec `SELECT ... FOR UPDATE`, puis le reliquat est contrôlé avant l'insert de `receptions`.

Les écritures de réception, stock, mouvement, quantité reçue et statut de commande restent dans une seule transaction ; toute `Throwable` entraîne un rollback suivi de la propagation de l'erreur.

## Tests transactionnels

`php scripts/validate_final_charge_commande.php` : PASS.

- Réception partielle : stock `26 → 31`, reçu `0 → 5`, 1 réception, 1 ligne, 1 entrée et 1 mouvement.
- Sur-reliquat : tentative de 6 pour un reliquat de 5 refusée ; tous les compteurs et le stock restent identiques.
- Réception complète : stock `31 → 36`, reçu `5 → 10`, statut `RECEPTION_COMPLETE`.
- Rollback forcé après création potentielle de l'en-tête : un trigger créé uniquement dans `medecin_test` fait échouer l'insert de `reception_items`. Les compteurs avant/après de `receptions`, `reception_items`, `stock_entries`, `mouvements_stock`, stock et quantité reçue sont strictement identiques.
- Intégrité SQL : 0 orphelin pour lignes commande, réceptions, lignes réception, entrées et mouvements.
- La base `medecin_test` est refusée si elle existe déjà et supprimée automatiquement après la recette.

## Non-régression

- `test_dashboard_vendeur_phase2.php` : PASS.
- `test_admin_phase2.php` : 82 PASS / 0 FAIL.
- `test_dashboard_assistant_final.php` : 33 PASS / 0 FAIL.
- `test_access_commandes_auto.php` : permissions Chargé de commande et policy PASS.

La recette administrateur a initialement révélé un bypass RBAC réel dans `RBACService::hasPermission()`. Il a été supprimé : les droits administrateur sont désormais obtenus via les permissions attribuées en base. La recette complète passe ensuite à 0 FAIL.

## Recette HTTP

Les routes demandées sont présentes dans `config/routes.php` : dashboard, saisie, historique, réception, mouvements, stock, sortie, ajustement, inventaire, alertes et péremptions.

Recette HTTP impossible dans cet environnement à cause du blocage du navigateur.

Le navigateur intégré bloque `http://localhost/medecin/commande/dashboard` avant chargement avec `ERR_BLOCKED_BY_CLIENT`, donc avant authentification avec le compte `CHARGE_COMMANDE`. Aucune des pages demandées n'a pu être chargée ; aucun test HTTP ne peut être présenté comme réussi sur cette base.

## Résultat final et limite restante

Le flux critique **COMMANDE → RÉCEPTION → STOCK** est transactionnel et validé par recette isolée, avec 0 échec transactionnel et 0 régression des scripts demandés.

La déclaration globale « TERMINÉ — VALIDÉ — PRÊT À PASSER AU DASHBOARD COMPTABLE » reste suspendue à la recette HTTP authentifiée avec un compte `CHARGE_COMMANDE`, qui doit être exécutée dans un navigateur local non bloqué. Aucune donnée métier de production n'a été créée par la recette transactionnelle.
