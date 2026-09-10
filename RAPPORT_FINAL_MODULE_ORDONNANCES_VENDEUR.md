# Rapport final — Module Ordonnances vendeur

Date : 15 août 2026

## Corrections réalisées

- Remplacement de la page statique par une liste réellement alimentée, avec recherche par numéro, patient, prescripteur et date.
- Ajout du détail `/ordonnances/voir/{id}` : données existantes, produits issus des délivrances, quantités, montant et stock réel.
- Ajout du traitement `/ordonnances/traiter/{id}` : ouvre le flux existant de création de vente avec l'ordonnance existante préchargée.
- Création de `Ordonnance` (modèle) et `OrdonnanceService`. Le service lit les données, calcule le statut à partir des ventes liées et utilise `StockService` pour la disponibilité.
- `VenteService` lie désormais une ordonnance existante à la vente créée. La création de vente reste le seul point qui déduit le stock, encaisse et génère l'écriture comptable.
- Ajout de la journalisation `VIEW_ORDONNANCE` et `LINK_ORDONNANCE_VENTE` via `AuditService`.
- Ajout des permissions ciblées `ordonnance.view` et `ordonnance.process`, attribuées au seul rôle Vendeur (l'administrateur conserve son accès standard).
- Correction de l'index de traçabilité : une même ordonnance peut désormais comporter le même produit dans plusieurs ventes (`ordonnance_id`, `vente_id`, `produit_id`), nécessaire aux délivrances partielles. Aucune table, colonne ou donnée n'a été supprimée.

## Migrations appliquées

- `029_ordonnances_vendeur_permissions.sql` : permissions et attribution Vendeur.
- `030_ordonnance_items_partial_deliveries.sql` : index de délivrance partielle.

Les scripts idempotents `scripts/run_migration_029.php` et `scripts/run_migration_030.php` ont été exécutés sur la base locale.

## Statuts et limites du schéma

La table `ordonnances` ne possède pas de statut. L'interface affiche donc `DISPENSEE` lorsqu'une vente liée est active, `ANNULEE` si toutes les ventes liées sont annulées, sinon `EN_ATTENTE`. Aucun statut n'a été ajouté arbitrairement.

Le schéma ne stocke ni lignes prescrites indépendantes, ni posologie. Le détail affiche uniquement les produits effectivement liés à une vente et indique explicitement l'absence de produit lorsque c'est le cas.

## Vérifications réalisées

- Lint PHP valide sur les contrôleurs, services, modèle et vues modifiés.
- Les quatre routes Ordonnances sont enregistrées.
- Lecture réelle de la base : 2 ordonnances listées, détail de l'ordonnance 1 obtenu, stock de son produit contrôlé.
- Cas ordonnance inexistante : retourne `null` côté service / vue 404 côté contrôleur.
- Permissions créées et associées au rôle `vendeur` vérifiées en base.
- Index de délivrance partielle vérifié en base.

Le test HTTP direct sur `http://localhost/medecin/ordonnances` a retourné 404 car ce point d'entrée ne correspond pas au serveur web configuré dans cette session; les routes ont été vérifiées au niveau du routeur. Le parcours authentifié final doit être validé dans l'instance WAMP utilisée par le vendeur.
