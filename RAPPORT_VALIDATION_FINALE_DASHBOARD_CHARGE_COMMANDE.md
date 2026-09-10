# Validation finale — Dashboard Chargé de commande

Date : 21 août 2026

## Périmètre et sécurité

Le scénario a été exécuté uniquement sur une base temporaire `medecin_test`, créée avec `CREATE TABLE ... LIKE medecin...` à partir du schéma réel, puis supprimée automatiquement. La base `medecin` n'a reçu aucune écriture de données de test.

La validation a révélé une anomalie réelle de transaction. Le dashboard ne peut donc pas être déclaré terminé.

## Résultats

| Test | Valeurs contrôlées | Statut |
|---|---|---|
| Création commande | `supplier_orders` créé avec utilisateur, fournisseur, date et statut `VALIDEE`; `supplier_order_items.quantite_commandee = 10` | PASS |
| Réception partielle | Stock `26 → 31`; reçu `0 → 5`; 1 réception, 1 entrée et 1 mouvement `ENTREE` | PASS |
| Protection du reliquat | Réception de 6 pour un reliquat de 5 refusée par `ChargeCommandeService::receiveOrder()` | PARTIEL |
| État après dépassement | Stock = 31, reçu = 5, entrées = 1, mouvements = 1, mais réceptions `1 → 2` | FAIL |
| Réception du reliquat | Stock `31 → 36`; reçu total = 10; 2 entrées, 2 mouvements; statut `RECEPTION_COMPLETE` | PASS |
| Intégrité SQL | 0 ligne orpheline dans les relations commande, réception, entrée et mouvement | PASS |
| Erreur forcée pendant réception | Exception `Ligne reception invalide.`; une nouvelle réception est néanmoins conservée | FAIL |
| Données dashboard de recette | Le service affiche le stock et les entrées; les 4 réceptions visibles incluent les 2 en-têtes indûment conservés | FAIL |

## Cause identifiée

Fichier : `app/Services/ChargeCommandeService.php`  
Méthode : `receiveOrder()`  
Route concernée : `POST /commande/reception`  
Tables concernées : `supplier_orders`, `supplier_order_items`, `receptions`, `reception_items`, `stock_entries`.

Le code ouvre bien une transaction et appelle `rollBack()` lorsque la quantité dépasse le reliquat ou qu'une ligne est invalide. Toutefois, les tables réelles suivantes utilisent le moteur **MyISAM**, qui ne prend pas en charge les transactions :

- `supplier_orders`
- `supplier_order_items`
- `receptions`
- `reception_items`
- `stock_entries`

Ainsi, le rollback ne peut pas annuler l'en-tête de réception déjà inséré. Les tables `stock` et `mouvements_stock` sont InnoDB, d'où le maintien du stock à 31 et l'absence de mouvement supplémentaire.

## Corrections de code appliquées

- `createSupplierOrder()` renseigne désormais `supplier_orders.montant_total = 0` à l'insertion. Sous MySQL strict, son omission provoquait l'erreur `Field 'montant_total' doesn't have a default value`.
- L'accès à `date_livraison_prevue` est rendu sûr lorsque cette donnée est absente.

## Correction proposée avant nouvelle recette

Convertir les cinq tables MyISAM ci-dessus en **InnoDB** dans une fenêtre de maintenance, après sauvegarde et vérification des contraintes/index. Puis relancer `scripts/validate_final_charge_commande.php` sur un clone du schéma mis à jour.

## Décision

**DASHBOARD CHARGÉ DE COMMANDE — NON VALIDÉ À 100/100.**

Le blocage est l'atomicité des réceptions : une réception invalide ne doit jamais laisser d'en-tête persistant. Aucune donnée de production n'a été modifiée durant cette validation.
