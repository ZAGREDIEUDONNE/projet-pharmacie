# Audit Phase 2 — État avant correction

Date : 15 août 2026. Contrôle effectué en lecture seule avant toute modification.

## État vérifié

- Le rapport de phase 1, les routes, `AdminController`, `BaseController`, `RBACService`, `VenteService`, `CaisseService`, `EcritureComptableService`, `AuditService` et les vues admin ont été relus.
- Base MySQL réelle : `medecin`, MySQL 8.4.7 ; les colonnes utilisées existent (`ventes`, `stock`, `mouvements_stock`, `mouvements_caisse`, `ecritures_comptables`, `lignes_ecritures`, RBAC et audit).
- Authentification : session PHP `$_SESSION['user']` alimentée par `AuthController`; les routes non publiques sont bloquées par le routeur sans session.
- Les 15 routes admin sont déclarées et leurs contrôleurs/méthodes/vues existent.

## Constats confirmés

1. Les POST admin et le POST d'annulation ne passent par aucun middleware CSRF dans le routeur et les formulaires n'ont pas de jeton.
2. `EcritureComptableService::annulerEcritureVente()` supprime les lignes et l'entête d'écriture. Une méthode générique de génération d'écriture inverse existe, mais n'est pas utilisée par l'annulation de vente.
3. La restitution stock d'une annulation historique n'est pas normalisée à `ANNULATION_VENTE`; l'annulation caisse ne passe pas `vente_id` à l'insertion.
4. Le RBAC retourne vrai pour un administrateur avant lecture des permissions relationnelles.
5. Le rôle id 6 `COMPTABLE` existe avec `is_actif=1` mais `statut=0`, 0 permission associée. La migration historique 023 prévoit 15 permissions comptables mais elles sont absentes de la base.
6. Créations/modifications d'utilisateurs non auditées; recherche absente.
7. Les APIs live redirigent vers HTML login sans session au lieu d'un JSON 401; la vue recharge 100 logs sans employer `since_id`.

## Décision de correction

Les corrections seront limitées aux modules concernés par ces constats. Toute donnée de test métier sera créée et vérifiée dans une transaction annulée par `ROLLBACK`. Les permissions/relations RBAC et le rétablissement du rôle comptable nécessitent une migration idempotente, car les données de référence réelles sont incomplètes.
