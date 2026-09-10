# Rapport d'audit — Ordonnances vendeur

Date : 15 août 2026. Audit effectué avant les modifications fonctionnelles.

## Routes et contrôleur

- `GET /ordonnances` et `GET /ordonnances/liste` pointaient vers `OrdonnanceController@index|liste`.
- Le contrôleur héritait correctement de `App\Core\BaseController`, pas d'une classe `App\Core\Controller` inexistante.
- Les seules méthodes étaient `index` et `liste`; elles rendaient la même page statique.
- Il n'existait ni route de détail, ni route de traitement.

## Services, modèles et vues

- Aucun `OrdonnanceService` ni modèle `Ordonnance` n'existait.
- `VenteService`, `StockService`, `CaisseService` et `AuditService` existent et constituent le flux métier à réutiliser.
- `app/Views/ordonnances/index.php` affichait « en cours de développement ».

## Base de données réelle

Tables présentes et conservées :

- `ordonnances` : `id`, numéro, date, médecin, structure, patient, téléphone, observation et dates techniques (2 lignes).
- `vente_ordonnances` : liaison `vente_id` / `ordonnance_id` (2 lignes).
- `vente_ordonnance_items` : liaison ordonnance, vente et produit (2 lignes).

Il n'existe pas de table de lignes prescrites, de quantité prescrite, de posologie ou de statut d'ordonnance. Ces informations ne peuvent donc pas être inventées. Les produits et quantités disponibles proviennent exclusivement des ventes déjà liées.

## Permissions et erreurs relevées

- Aucune permission ordonnance n'était présente.
- Le routeur et `BaseController` existent : l'erreur historique « App\Core\Controller introuvable » ne doit pas être reproduite.
- La route `/ordonnances/liste` existait, mais ne réalisait aucune opération utile.
- La contrainte `unique_ordonnance_produit` empêchait de tracer le même produit sur plusieurs délivrances partielles.

## Fonctionnel avant correction

Infrastructure de liaison vente/ordonnance et saisie d'une ordonnance dans la vente : présentes. Liste, recherche, détail, contrôle d'accès dédié, traitement et traçabilité de consultation : absents.
