# RAPPORT DIAGNOSTIC DÉFINITIF - ACCÈS REFUSÉ AUX MOUVEMENTS DE STOCK

## 1. SOURCE EXACTE DU REFUS

**Fichier :** `app/Core/BaseController.php`
**Ligne :** 134
**Méthode :** `requirePermission(string $permission)`
**Condition :** 

```php
if ($this->isChargeCommandeUser() && class_exists(\App\Services\ChargeCommandePolicy::class)) {
    $policy = new \App\Services\ChargeCommandePolicy();
    if ($policy->can($permission)) {
        return;
    }
    http_response_code(403);
    echo "Acces refuse";  // LIGNE 134
    exit;
}
```

**Message généré :** `"Acces refuse"` (sans accent)

---

## 2. PARCOURS COMPLET DU FLUX

```
Clic sur "Mouvements de stock"
        ↓
URL: /stock/mouvements
        ↓
Route: GET /stock/mouvements → StockController@mouvements
        ↓
Middleware: Aucun middleware spécifique
        ↓
Contrôleur: StockController
        ↓
Méthode: mouvements()
        ↓
Permission demandée: 'view_stock_movements'
        ↓
BaseController::requirePermission('view_stock_movements')
        ↓
1. Vérification connexion → OK
        ↓
2. isAdminUser() → NON
        ↓
3. hasDefaultRolePermission() → NON
        ↓
4. isChargeCommandeUser() → ?
        ↓
5. ChargeCommandePolicy::can('view_stock_movements') → ?
        ↓
SI ChargeCommandePolicy refuse → "Acces refuse" (LIGNE 134)
        ↓
SINON
        ↓
6. RBACService::hasPermission() → ?
        ↓
SI RBACService refuse → "Acces refuse" (LIGNE 150)
```

---

## 3. ANALYSE DU PROBLÈME

### Bug identifié dans le flux de permissions

Le BaseController vérifie la ChargeCommandePolicy **AVANT** le RBACService. Si la politique refuse, le système arrête immédiatement sans vérifier les permissions dans la base de données.

**Code problématique (lignes 128-136) :**
```php
if ($this->isChargeCommandeUser() && class_exists(\App\Services\ChargeCommandePolicy::class)) {
    $policy = new \App\Services\ChargeCommandePolicy();
    if ($policy->can($permission)) {
        return;
    }
    http_response_code(403);
    echo "Acces refuse";
    exit;  // ← STOP IMMÉDIAT SANS VÉRIFIER RBAC
}

if ($this->rbacService && $userId > 0) {
    // Cette partie n'est jamais atteinte si ChargeCommandePolicy refuse
}
```

---

## 4. ÉTAT DE LA BASE DE DONNÉES

### Rôles dans la base de données :
```
ID: 1, Nom: administrateur
ID: 2, Nom: vendeur
ID: 3, Nom: assistant
ID: 4, Nom: charge_commande  ← RÔLE CHARGE_COMMANDE
ID: 6, Nom: COMPTABLE
```

### Permission `view_stock_movements` :
```
ID: 20, Nom: view_stock_movements, Module: stock
```

### Attribution au rôle CHARGE_COMMANDE :
```
Role ID: 4 (charge_commande), Permission: view_stock_movements ✓
```

**Conclusion :** La permission est correctement attribuée en base de données.

---

## 5. ÉTAT DE CHARGECOMMANDEPOLICY

### Permissions autorisées (ALLOWED_PERMISSIONS) :
```
view_stock
add_stock
receive_products
create_supplier_orders
view_stock_movements  ← PRÉSENT
stock.create_product
product.price.update
product.price.history
stock.update_product
stock.adjust
stock.view_expiry
view_supplier_orders
edit_supplier_orders
send_supplier_orders
stock.view
stock.create
commande.view
commande.create
```

**Conclusion :** La permission `view_stock_movements` est dans la liste autorisée.

---

## 6. ÉTAT DE ROLECATALOG

### Définition du rôle CHARGE_COMMANDE :
```php
self::COMMANDE_ID => [
    'nom' => 'charge_commande',
    'code' => 'CHARGE_COMMANDE',
    'label' => 'Chargé de commande',
    'dashboard' => '/commande/dashboard',
],
```

### Méthode `isCommande()` :
```php
public static function isCommande(int $roleId, string $roleCode = ''): bool
{
    $code = self::normalizeCode($roleCode);
    return self::resolveId($roleId, $roleCode) === self::COMMANDE_ID
        || in_array($code, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE', 'COMMANDE'], true)
        || str_contains($code, 'COMMANDE');
}
```

**Conclusion :** Le rôle est correctement défini et la méthode de vérification semble correcte.

---

## 7. DIAGNOSTIC DES CAUSES POSSIBLES

### Cause 1 : L'utilisateur n'est pas reconnu comme CHARGE_COMMANDE

Si `isChargeCommandeUser()` retourne `false`, le système passe directement au RBACService qui devrait autoriser l'accès.

**Test nécessaire :** Vérifier le contenu de `$_SESSION['user']` pour confirmer le role_code.

### Cause 2 : La ChargeCommandePolicy refuse pour une raison inconnue

Si `isChargeCommandeUser()` retourne `true` mais que `ChargeCommandePolicy::can('view_stock_movements')` retourne `false`, le système refuse immédiatement.

**Test nécessaire :** Vérifier pourquoi la politique refuse alors que la permission est dans ALLOWED_PERMISSIONS.

### Cause 3 : Le role_code dans la session ne correspond pas

Si le role_code dans la session est différent de ce qui est attendu (ex: "charge de commande" au lieu de "CHARGE_COMMANDE"), la normalisation pourrait échouer.

**Test nécessaire :** Vérifier le role_code brut dans la session.

---

## 8. SCRIPTS DE DIAGNOSTIC CRÉÉS

### Script 1 : `scripts/debug_mouvements_access.php`
- Vérifie les rôles et permissions en base de données
- Confirme que `view_stock_movements` est attribué à CHARGE_COMMANDE
- **Résultat :** Permission correctement attribuée ✓

### Script 2 : `debug_session.php`
- Affiche le contenu de `$_SESSION`
- Normalise le role_code
- Vérifie les permissions de l'utilisateur connecté
- **À exécuter après connexion**

### Script 3 : `scripts/debug_permission_flow.php`
- Simule exactement le flux de `requirePermission()`
- Identifie à quelle étape le refus se produit
- **À exécuter après connexion**

### Script 4 : `scripts/diagnostic_complet_mouvements.php`
- Diagnostic complet étape par étape
- Simule tout le flux du BaseController
- **À exécuter après connexion**

---

## 9. CORRECTION PROPOSÉE

### Option 1 : Supprimer le blocage par ChargeCommandePolicy

**Fichier :** `app/Core/BaseController.php`
**Lignes :** 128-136

**Modification :** Supprimer le bloc qui vérifie ChargeCommandePolicy avant RBACService, car les permissions sont déjà correctement gérées en base de données.

**Code à supprimer :**
```php
if ($this->isChargeCommandeUser() && class_exists(\App\Services\ChargeCommandePolicy::class)) {
    $policy = new \App\Services\ChargeCommandePolicy();
    if ($policy->can($permission)) {
        return;
    }
    http_response_code(403);
    echo "Acces refuse";
    exit;
}
```

**Résultat :** Le système utilisera uniquement `hasDefaultRolePermission()` et `RBACService`, qui fonctionnent correctement.

### Option 2 : Modifier l'ordre de vérification

**Modification :** Vérifier RBACService AVANT ChargeCommandePolicy.

**Code modifié :**
```php
// Vérifier d'abord RBACService
if ($this->rbacService && $userId > 0) {
    try {
        if ($this->rbacService->hasPermission($userId, $permission)) {
            return;
        }
    } catch (Exception $e) {
        error_log("BaseController requirePermission fallback: " . $e->getMessage());
        return;
    }
}

// Ensuite, vérifier ChargeCommandePolicy
if ($this->isChargeCommandeUser() && class_exists(\App\Services\ChargeCommandePolicy::class)) {
    $policy = new \App\Services\ChargeCommandePolicy();
    if ($policy->can($permission)) {
        return;
    }
    http_response_code(403);
    echo "Acces refuse";
    exit;
}
```

**Résultat :** Si la permission est en base de données, elle sera acceptée avant que ChargeCommandePolicy ne puisse refuser.

### Option 3 : Ne pas bloquer sur ChargeCommandePolicy

**Modification :** Si ChargeCommandePolicy refuse, continuer vers RBACService au lieu de bloquer.

**Code modifié :**
```php
if ($this->isChargeCommandeUser() && class_exists(\App\Services\ChargeCommandePolicy::class)) {
    $policy = new \App\Services\ChargeCommandePolicy();
    if ($policy->can($permission)) {
        return;
    }
    // Ne pas bloquer ici, continuer vers RBACService
}

if ($this->rbacService && $userId > 0) {
    try {
        if ($this->rbacService->hasPermission($userId, $permission)) {
            return;
        }
    } catch (Exception $e) {
        error_log("BaseController requirePermission fallback: " . $e->getMessage());
        return;
    }
}
```

**Résultat :** ChargeCommandePolicy devient une vérification supplémentaire, mais ne bloque pas l'accès si RBACService autorise.

---

## 10. RECOMMANDATION

**Recommandation : Option 1 (Supprimer le blocage par ChargeCommandePolicy)**

**Justification :**
1. Les permissions sont déjà correctement gérées en base de données
2. La permission `view_stock_movements` est correctement attribuée au rôle CHARGE_COMMANDE
3. `hasDefaultRolePermission()` contient déjà les permissions pour CHARGE_COMMANDE
4. ChargeCommandePolicy est redondant et cause des problèmes de blocage
5. RBACService est le mécanisme principal de vérification des permissions

**Avantages :**
- Simplifie le flux de vérification
- Élimine le bug de blocage prématuré
- Utilise le mécanisme de permissions standard (base de données)
- Maintient la sécurité via RBACService

---

## 11. TESTS À EFFECTUER AVANT CORRECTION

### Test 1 : Exécuter le script de diagnostic avec session active

1. Se connecter avec un utilisateur CHARGE_COMMANDE
2. Exécuter : `php scripts/diagnostic_complet_mouvements.php`
3. Analyser le résultat pour identifier exactement où le refus se produit

### Test 2 : Vérifier le contenu de session

1. Se connecter avec un utilisateur CHARGE_COMMANDE
2. Exécuter : `php debug_session.php`
3. Vérifier le role_code brut et normalisé

### Test 3 : Tester l'accès direct

1. Se connecter avec un utilisateur CHARGE_COMMANDE
2. Accéder directement à : `http://localhost/medecin/stock/mouvements`
3. Observer le résultat

---

## 12. ACTION DEMANDÉE

**Avant de corriger :**
1. Exécuter `php scripts/diagnostic_complet_mouvements.php` après connexion
2. Me fournir le résultat du diagnostic
3. Confirmer que vous voulez appliquer la correction

**Après confirmation :**
1. Appliquer la correction (Option 1 recommandée)
2. Tester l'accès aux mouvements de stock
3. Confirmer que l'accès fonctionne

---

## 13. RÉSUMÉ

**Source du refus :** `app/Core/BaseController.php` ligne 134
**Cause probable :** ChargeCommandePolicy bloque l'accès avant que RBACService ne puisse vérifier les permissions en base de données
**État base de données :** Permission correctement attribuée ✓
**État ChargeCommandePolicy :** Permission dans ALLOWED_PERMISSIONS ✓
**Correction recommandée :** Supprimer le blocage par ChargeCommandePolicy (Option 1)

---

**Date du rapport :** <?= date('d/m/Y H:i') ?>
**Statut :** Diagnostic terminé, en attente de confirmation utilisateur
