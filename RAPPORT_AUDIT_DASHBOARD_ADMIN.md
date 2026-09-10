# RAPPORT D'AUDIT — DASHBOARD ADMINISTRATEUR

**Date :** 15 août 2026  
**Périmètre :** code PHP, routes, vues et base MySQL `medecin`, exclusivement en lecture.  
**Aucune migration, écriture SQL, correction de code ou modification de données n'a été effectuée.**

## 1. Score global

**Score réel : 63 % — PARTIEL.**

Le tableau de bord interroge bien la base MySQL réelle et les requêtes KPI auditées sont exécutables. Les routes et vues admin existent. En revanche, la protection CSRF est absente du routeur et des formulaires sensibles, et le chemin d'annulation détruit les écritures comptables au lieu de produire une contre-passation. Ces défauts empêchent toute validation de sécurité et de traçabilité comptable.

## 2. Inventaire réel

| Élément | Constat réel | Statut |
|---|---:|---|
| Tables MySQL | 84 | PASS |
| Utilisateurs | 12, dont 11 actifs | PASS |
| Rôles | 5 | PARTIEL |
| Permissions | 59 | PASS |
| Logs audit | 325 | PARTIEL |
| Ventes | 70 | PASS |
| Stock | 5 lignes, quantité 148, valeur 31 400 FCFA | PASS |
| Sessions caisse | 11, dont 1 ouverte | PASS |
| Commandes `supplier_orders` | 4, dont 1 ouverte | PASS |

MySQL réel : **8.4.7**. Les noms de colonnes consommés par le contrôleur pour utilisateurs, ventes, stock, audit, caisse et commandes existent dans le schéma réel.

## 3. Routes, contrôleur, vues et services

Les **15 routes** configurées `/admin` sont toutes présentes dans `config/routes.php`, pointent vers une méthode publique existante de `AdminController`, et la vue cible existe lorsque la route rend une vue.

| Route / méthode | Contrôle | Résultat |
|---|---|---|
| GET `/admin` → `adminDashboard` | vue `admin/index` | PASS |
| GET `/admin/dashboard` → `dashboard` | vue + KPI | PARTIEL |
| GET `/admin/statistiques` → `statistiques` | vue + données | PASS |
| GET `/admin/statistiques/live` → `statistiquesLive` | JSON en session admin | PARTIEL |
| GET `/admin/annulation-tickets` → `annulationTickets` | vue action + VenteService | PARTIEL |
| GET `/admin/users`, `/create`, `/{id}/edit` | vues existantes | PASS |
| POST `/store`, `/{id}/update`, `/{id}/delete` | méthodes, SQL préparé | PARTIEL |
| GET `/admin/roles` | vue + `role_permissions` | PASS |
| GET `/admin/audit`, `/audit/live` | vue/API audit | PARTIEL |
| GET `/admin/system` | vue informations système | PASS |

Test HTTP sans session : `/admin/dashboard` et `/admin/audit/live` redirigent vers `/login` (302), donc l'accès anonyme est bloqué. Les APIs live renvoient cependant une redirection HTML en cas d'expiration de session, alors que les vues appellent systématiquement `response.json()` : erreur JavaScript attendue et rafraîchissements inutiles dans ce cas.

Services effectivement utilisés : `RoleService`, `VenteService`, `PharmacyDashboardService`, `AuditService`, `StockService`, `CaisseService`, `ComptabiliteService`.

## 4. Dashboard, KPI et statistiques

| KPI / source SQL | Résultat MySQL au contrôle | Statut |
|---|---:|---|
| Utilisateurs — `utilisateurs` | 12 | PASS |
| Utilisateurs actifs — `is_active=1` | 11 | PASS |
| CA du jour — ventes non annulées, `montant_net` | 592 FCFA / 2 ventes | PASS |
| CA du mois — même source | 592 FCFA / 2 ventes | PASS |
| Stock — `SUM(stock.quantite_disponible)` | 148 | PASS |
| Valeur stock — `SUM(stock.valeur_stock)` | 31 400 FCFA | PASS |
| Alertes stock — `quantite_disponible <= stock_alerte/stock_securite` | 1 | PASS |
| Caisse ouverte — `caisse_sessions.statut_session='OUVERTE'` | 1 | PASS |
| Commandes ouvertes — `supplier_orders.statut` | 1 | PASS |
| Clients actifs | 3 | PASS |

Les calculs de ventes excluent correctement `deleted_at IS NOT NULL` et `statut_vente='ANNULEE'`. Les top produits réutilisent `ventes_items` joint à `ventes`, sans seconde logique divergente. Les rafraîchissements sont réellement câblés : statistiques toutes les 10 s, audit toutes les 3 s, activité récente dashboard toutes les 5 s.

Limite : la liste des ventes récentes n'exclut pas les ventes annulées dans son SQL. Elle peut donc afficher une vente annulée alors que les KPI et statistiques l'excluent.

## 5. Gestion utilisateurs

Les requêtes sont préparées et les champs référencent le schéma réel (`username`, `email`, `password_hash`, `nom`, `prenom`, `telephone`, `role_id`, `is_active`, `deleted_at`). La validation contrôle email, champs requis, unicité username/email, longueur et confirmation du mot de passe, et l'existence du rôle. L'auto-suppression est bloquée et la suppression est un soft-delete.

Points non conformes :

- Aucun formulaire `store`, `update` ou `delete` n'envoie de token CSRF ; le routeur n'applique aucun middleware CSRF.
- `roleExists()` accepte tout rôle existant, même inactif. Le rôle `COMPTABLE` (id 6) est présenté dans le formulaire malgré `statut=0` et sans permission.
- Les créations/modifications d'utilisateur ne sont pas journalisées par `AuditService`; seule la suppression l'est.
- La liste n'a pas de recherche, malgré l'attente fonctionnelle.

## 6. RBAC, rôles et permissions

Les relations réelles sont cohérentes : aucun utilisateur, rôle-permission ou permission-utilisateur orphelin. Comptages : administrateur 34, vendeur 14, assistant 16, chargé de commande 24, comptable 0.

| Rôle | État / permissions | Constat |
|---|---|---|
| ADMINISTRATEUR | actif, 34 | PARTIEL : accès admin par rôle, mais bypass RBAC |
| VENDEUR | actif, 14 | PASS : ne satisfait pas `requireRole(1)` |
| ASSISTANT | actif, 16 | PASS : ne satisfait pas `requireRole(1)` |
| CHARGE_COMMANDE | actif, 24 | PASS : ne satisfait pas `requireRole(1)` |
| COMPTABLE | `is_actif=1`, `statut=0`, 0 | FAIL : état contradictoire et rôle inutilisable |

Le dashboard admin contrôle l'identité de rôle par `requireRole(1)`, pas une permission métier. `BaseController::requirePermission()` et `RBACService::hasPermission()` accordent ensuite **toute** permission à un administrateur avant consultation de `role_permissions`. C'est un fallback explicite `admin => allow`, contraire au critère d'audit si l'objectif est un RBAC strict et vérifiable par base. Les permissions individuelles sont lues mais ne sont pas administrables dans ce dashboard.

La page Rôles affiche seulement le nombre de permissions, pas leur liste ni leur comparaison détaillée; le nombre affiché correspond néanmoins à `role_permissions`.

## 7. Audit et temps réel

`audit_logs` contient 325 entrées, avec action, table, référence et date. 4 logs n'ont pas d'utilisateur et 232 n'ont pas d'adresse IP : l'exploitation n'est donc que partielle. Les données détaillées ne sont pas exposées par la vue, seulement les colonnes date/utilisateur/action/module/référence/IP.

Les métriques à 30 jours sont calculées en SQL. Les catégories de suppression ne prennent pas en compte les actions `ANNULER_*`, ce qui sous-compte la carte « Suppressions / annulations ». Le paramètre `since_id` de l'API existe mais la vue ne l'emploie pas : elle recharge jusqu'à 100 lignes toutes les 3 secondes.

## 8. Ventes, stock, caisse et annulation de tickets

Les structures réelles sont partagées avec le vendeur : `ventes`, `ventes_items`, `clients`, `mouvements_stock`, `mouvements_caisse`, `ecritures_comptables`. Aucun orphelin n'a été trouvé dans `ventes_items`, ni dans les mouvements caisse possédant un `vente_id`.

Une vente annulée réelle (id 50) possède une sortie de stock et une entrée de restitution, mais la restitution a un `reference_type` vide au lieu de `ANNULATION_VENTE`; elle n'est donc pas traçable proprement par type. Aucun mouvement caisse `ANNULATION_VENTE` n'existe et cette vente n'a pas de session caisse. Le code d'annulation actuel crée lui-même un mouvement caisse sans renseigner `vente_id`, ce qui reproduit l'écart de liaison.

Plus grave, `EcritureComptableService::annulerEcritureVente()` supprime les lignes et l'entête comptables, puis met `ventes.ecriture_id` à `NULL`; ce n'est pas une contre-passation. Le contrôle actuel ne montre aucune écriture déséquilibrée, mais l'annulation future détruirait l'historique comptable lié à la vente. L'annulation n'a pas été exécutée durant cet audit.

## 9. Comptabilité, fournisseurs, commandes et système

- Comptabilité : aucune écriture existante déséquilibrée (`total_debit/total_credit`, `is_equilibree`, et sommes de `lignes_ecritures`) ; **PARTIEL** à cause de la suppression lors d'une annulation.
- Stock : colonne réelle `quantite_disponible` correctement utilisée, pas `stock.quantite`; aucune ligne de stock dupliquée par produit ; **PASS**.
- Caisse : 1 mouvement de vente est identifiable par `reference='VENTE_{id}'` mais son `vente_id` est NULL ; **PARTIEL**.
- Commandes/fournisseurs : le dashboard privilégie `supplier_orders` (4 lignes, 1 ouverte), conforme au module chargé de commande; `commandes` est vide. Réceptions : 4 ; **PASS**.
- Système : affiche versions PHP/MySQL, mémoire, timezone et nom de base; aucune clé ou mot de passe n'est rendu par la vue ; **PASS**.

## 10. Sécurité, schéma et erreurs

Les requêtes du contrôleur sont préparées lorsqu'elles reçoivent des données utilisateur. Les identifiants de routes utilisateurs sont cherchés avant modification et les sorties de vues sont échappées.

| Contrôle | Résultat |
|---|---|
| Authentification routes admin | PASS — 302 vers login sans session |
| Autorisation rôle admin | PARTIEL — identité de rôle uniquement |
| SQL préparé | PASS |
| Validation utilisateur | PARTIEL |
| CSRF POST admin | FAIL |
| IDOR utilisateurs | PARTIEL — admin-only, pas de politique granulaire |
| Endpoints AJAX après expiration session | PARTIEL — redirection HTML non gérée |
| Exposition de secrets via système | PASS |
| Erreurs SQL dans les requêtes KPI auditées | PASS — aucune |
| Cohérence code / colonnes MySQL | PASS pour les tables admin auditées |

Le routeur appelle directement les contrôleurs et ne branche aucun `CSRFMiddleware` ni `RouteMiddleware::requireCSRF()`. Les formulaires admin et celui d'annulation ne contiennent pas de champ CSRF : les POST sensibles sont forgeables depuis un site tiers pour une session administrateur active.

## 11. Classement

### CRITIQUE

1. **CSRF absente** sur création, modification, suppression d'utilisateur et annulation de vente.
2. **Annulation comptable destructive** : suppression d'écritures et de leurs lignes au lieu d'une contre-passation, en rupture avec l'exigence d'audit SYSCOHADA.

### IMPORTANT

1. Bypass RBAC global pour administrateur (`admin => allow`) sans lecture de `role_permissions`.
2. Annulation caisse sans `vente_id` et mouvement de restitution stock au `reference_type` vide.
3. Rôle COMPTABLE incohérent (actif techniquement, statut inactif, 0 permission) mais disponible à l'attribution.
4. Création/modification utilisateur non journalisées.

### MOYEN

1. APIs live retournent HTML/302 après expiration de session, provoquant une erreur JSON répétée.
2. Audit : 232/325 entrées sans IP, 4 sans utilisateur; `ANNULER_*` exclu du KPI d'annulations.
3. Audit live recharge 100 lignes sans employer `since_id`.
4. Ventes récentes peut afficher des ventes annulées.
5. Recherche utilisateurs absente.

### FAIBLE

1. La page rôles affiche des compteurs, pas le détail des permissions.
2. Certains liens de navigation hérités nécessitent une recette métier par rôle, hors parcours admin authentifié disponible durant cet audit.

## 12. Corrections prioritaires (à ne pas appliquer en phase 1)

1. Brancher et tester une protection CSRF centralisée sur tous les POST, puis inclure le token dans chaque formulaire/AJAX.
2. Remplacer la suppression des écritures d'annulation par des écritures de contre-passation équilibrées et auditables.
3. Propager `vente_id` dans les mouvements de caisse d'annulation et normaliser `reference_type='ANNULATION_VENTE'` dans le mouvement stock.
4. Supprimer le bypass admin non justifié ou le rendre explicitement cohérent avec des permissions DB complètes; corriger le rôle comptable avant toute attribution.
5. Ajouter l'audit des créations/modifications utilisateurs et traiter proprement le 401/403 JSON des APIs live.

## Conclusion

**DASHBOARD ADMIN — AUDIT TERMINÉ**  
**Score réel : 63 %**

Le dashboard est connecté à la vraie base, les KPI principaux sont réels et les routes sont présentes. Il n'est toutefois pas validable comme sécurisé ni comptablement conforme avant traitement des deux problèmes critiques ci-dessus.
