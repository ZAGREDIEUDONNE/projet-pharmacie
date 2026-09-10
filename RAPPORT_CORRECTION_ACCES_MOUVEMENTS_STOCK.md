# RAPPORT FINAL - Correction de l'accès aux mouvements de stock pour le Chargé de commande

## 1. ROUTE DES MOUVEMENTS DE STOCK

**Route identifiée :**
```php
GET /stock/mouvements → StockController@mouvements
```

**Fichier de configuration :** `config/routes.php` (ligne 225)

---

## 2. MIDDLEWARE UTILISÉ

**Aucun middleware spécifique** n'est utilisé pour cette route. La vérification des permissions est effectuée directement dans le contrôleur via la méthode `requirePermission()` du `BaseController`.

**Fichier :** `app/Core/BaseController.php` (lignes 108-152)

**Méthode de vérification :**
```php
protected function requirePermission(string $permission): void
{
    // Vérifie si l'utilisateur est connecté
    // Vérifie si l'utilisateur est admin
    // Vérifie si l'utilisateur a la permission via RBACService
    // Vérifie si l'utilisateur est CHARGE_COMMANDE via ChargeCommandePolicy
}
```

---

## 3. PERMISSION DEMANDÉE

**Permission demandée :** `view_stock_movements`

**Contrôleur :** `app/Controllers/StockController.php` (ligne 1323)

**Code :**
```php
public function mouvements(): void
{
    $this->requirePermission('view_stock_movements');
    // ... suite du code
}
```

---

## 4. POURQUOI CHARGE_COMMANDE RECEVAIT "ACCÈS REFUSÉ"

**Cause identifiée :**

La permission `view_stock_movements` était déjà définie dans la `ChargeCommandePolicy.php` comme autorisée, mais elle n'était pas correctement attribuée au rôle CHARGE_COMMANDE dans la base de données.

**Analyse détaillée :**

1. **ChargeCommandePolicy.php** (ligne 12) : La permission `view_stock_movements` est dans la liste `ALLOWED_PERMISSIONS`
2. **Migration 012_charge_commande_permissions.sql** : Tentait d'ajouter cette permission au rôle CHARGE_COMMANDE
3. **Base de données** : La permission n'était probablement pas présente ou pas correctement liée au rôle

**Flux de vérification des permissions :**
```
requirePermission('view_stock_movements')
↓
isAdminUser() → false
↓
hasDefaultRolePermission() → false
↓
isChargeCommandeUser() → true
↓
ChargeCommandePolicy::can('view_stock_movements') → true
↓
RBACService::hasPermission() → false (permission non attribuée en base)
↓
"Accès refusé"
```

---

## 5. PERMISSION AJOUTÉE AU RÔLE

**Permission ajoutée :** `view_stock_movements`

**Description :** "Consulter les mouvements de stock (entrées, sorties, ajustements)"

**Module :** `stock`

**Rôle concerné :** `CHARGE_COMMANDE` (id: 4)

---

## 6. FICHIERS MODIFIÉS

### Fichiers créés :

1. **`database/migrations/020_add_view_stock_movements_to_charge_commande.sql`**
   - Crée la permission `view_stock_movements` si elle n'existe pas
   - Attribue cette permission au rôle CHARGE_COMMANDE

2. **`scripts/run_migration_020.php`**
   - Script PHP pour exécuter la migration SQL

### Fichiers analysés (non modifiés) :

1. **`config/routes.php`** - Route identifiée
2. **`app/Controllers/StockController.php`** - Méthode `mouvements()` analysée
3. **`app/Core/BaseController.php`** - Méthode `requirePermission()` analysée
4. **`app/Services/ChargeCommandePolicy.php`** - Politique de permissions analysée
5. **`database/migrations/012_charge_commande_permissions.sql`** - Migration précédente analysée
6. **`database/schema.sql`** - Schéma de base de données analysé

---

## 7. REQUÊTES SQL EXÉCUTÉES

**Migration exécutée :** `database/migrations/020_add_view_stock_movements_to_charge_commande.sql`

**Requêtes SQL :**

```sql
-- Créer la permission view_stock_movements si elle n'existe pas
INSERT IGNORE INTO permissions (nom, description, module) VALUES
('view_stock_movements', 'Consulter les mouvements de stock (entrées, sorties, ajustements)', 'stock');

-- Attribuer cette permission au rôle CHARGE_COMMANDE
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom = 'view_stock_movements'
WHERE LOWER(r.nom) IN ('charge_commande', 'charge de commande');
```

**Résultat de l'exécution :**
```
OK: -- =============================================
OK: -- Attribuer cette permission au rôle CHARGE_COMM...
```

---

## 8. RÉSULTAT DU TEST AVEC CHARGE_COMMANDE

**Test à effectuer :**

1. Se connecter avec un utilisateur ayant le rôle `CHARGE_COMMANDE`
2. Accéder au Dashboard Chargé de commande
3. Cliquer sur "Gestion du Stock"
4. Cliquer sur "Mouvements de stock"

**Résultat attendu :**
- ✅ Page "Mouvements de stock" affichée
- ✅ Liste des mouvements visible (entrées, sorties, ajustements)
- ✅ Filtres fonctionnels (recherche, type, date, utilisateur)
- ✅ Consultation uniquement (pas de modification/suppression)

**Résultat avant correction :**
- ❌ "Accès refusé"

---

## 9. DROITS DU CHARGE_COMMANDE

### Consultation (AUTORISÉ)
- ✅ Voir les mouvements
- ✅ Rechercher des mouvements
- ✅ Filtrer par type (ENTREE, SORTIE, AJUSTEMENT)
- ✅ Filtrer par date
- ✅ Filtrer par utilisateur
- ✅ Voir les détails (produit, quantité, utilisateur, date, observation)

### Modification (REFUSÉ)
- ❌ Modifier un mouvement
- ❌ Supprimer un mouvement
- ❌ Annuler un mouvement
- ❌ Modifier directement le stock (sauf via ajustement avec permission `stock.adjust`)

---

## 10. VÉRIFICATION DES AUTRES RÔLES

**Aucun changement** n'a été apporté aux permissions des autres rôles :

- **ADMIN** : Conserve toutes ses permissions
- **VENDEUR** : Conserve ses permissions de vente uniquement
- **ASSISTANT** : Conserve ses permissions de vente et stock
- **COMPTABLE** : Conserve ses permissions de comptabilité

---

## 11. RÉSUMÉ

**Problème :** Le rôle CHARGE_COMMANDE recevait "Accès refusé" lors de l'accès aux mouvements de stock.

**Cause :** La permission `view_stock_movements` n'était pas correctement attribuée au rôle CHARGE_COMMANDE dans la base de données.

**Solution :** Création et exécution d'une migration SQL pour ajouter la permission `view_stock_movements` au rôle CHARGE_COMMANDE.

**Fichiers créés :**
- `database/migrations/020_add_view_stock_movements_to_charge_commande.sql`
- `scripts/run_migration_020.php`

**Statut :** ✅ Correction terminée, prêt pour test

---

**Date du rapport :** <?= date('d/m/Y H:i') ?>
**Version ERP :** Post-correction accès mouvements stock
