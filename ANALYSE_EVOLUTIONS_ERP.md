# ANALYSE - Évolutions ERP Pharmacie

**Date:** 18 juillet 2026  
**Statut:** Analyse en cours

---

## 1. STRUCTURE EXISTANTE DU PROJET

### 1.1 Contrôleurs (24 fichiers)
- `AdminController.php` - Administration
- `AssistantController.php` - Assistant
- `AuditController.php` - Audit
- `AuthController.php` - Authentification
- `CaisseController.php` - Caisse
- `ChargeCommandeController.php` - Commandes fournisseurs
- `ClientController.php` - Clients
- `ComptabiliteController.php` - Comptabilité
- `DashboardController.php` - Dashboard
- `FinanceController.php` - Finance
- `HomeController.php` - Accueil
- `InventaireController.php` - Inventaire
- `ProduitController.php` - Produits
- `StockController.php` - Stock
- `SuiviClientController.php` - Suivi clients
- `SystemController.php` - Système
- `VenteController.php` - Ventes
- `VenteAuthController.php` - Auth Vente
- `VentesController.php` - Ventes
- `RBACTestController.php` - Tests RBAC
- `RoleManagementController.php` - Gestion rôles
- `SecurityController.php` - Sécurité
- `CommandeController.php` - Commandes
- `AuditController_clean.php` - Audit (backup)

### 1.2 Modèles (7 fichiers)
- `CaisseSession.php` - Sessions caisse
- `Client.php` - Clients
- `CommandeFournisseur.php` - Commandes fournisseurs
- `Lot.php` - Lots
- `PaiementDetails.php` - Détails paiements
- `Stock.php` - Stock
- `Vente.php` - Ventes

### 1.3 Services (61 fichiers)
- `AuditService.php` - Audit
- `CaisseService.php` - Caisse
- `ChargeCommandeService.php` - Commandes fournisseurs
- `ClientService.php` - Clients
- `ComptabiliteService.php` - Comptabilité
- `DashboardService.php` - Dashboard
- `DiscountLimitService.php` - Gestion des remises
- `InventaireService.php` - Inventaire
- `PeremptionService.php` - Péremptions
- `StockAvanceService.php` - Stock avancé
- `PharmacyDashboardService.php` - Dashboard pharmacie
- `PharmacyProductService.php` - Produits pharmacie
- `ReglementTiersService.php` - Règlements tiers
- `CommandeAutomatiqueService.php` - Commandes automatiques
- `AnalyticsStockService.php` - Analytics stock
- `AnalyticsVentesService.php` - Analytics ventes
- `AlertesService.php` - Alertes
- Et 44 autres services...

### 1.4 Routes existantes (264 routes)
- Routes Vente: `/vente/*`
- Routes Clients: `/clients/*`
- Routes Caisse: `/caisse/*`
- Routes Admin: `/admin/*`
- Routes Stock: `/stock/*`
- Routes Produits: `/produits/*`
- Routes Inventaire: `/inventaire/*`
- Routes Finance: `/finance/*`
- Routes Suivi Client: `/suivi-client/*`
- Routes Comptabilité: `/comptabilite/*`
- Routes Commandes: `/commande/*`

---

## 2. ANALYSE PAR ÉVOLUTION DEMANDÉE

### 2.1 GESTION DES REMISES (Plafond 25% pour Admin)

**EXISTANT:**
- Service: `DiscountLimitService.php` existe déjà
- Routes: `/finance/remises-limites` existe déjà
- Contrôleur: `FinanceController@remisesLimites` existe déjà

**STATUT:** ✅ Infrastructure existante - À modifier pour plafond 25%

---

### 2.2 GESTION DES ORDONNANCES (Par type de produit)

**EXISTANT:**
- Table `produits` - Structure à vérifier pour champ type_ordonnance
- Contrôleur Vente: `VenteController.php` - À modifier pour validation

**STATUT:** ⚠️ Infrastructure partielle - À développer

---

### 2.3 ENTRÉE DIRECTE EN STOCK (Simplification)

**EXISTANT:**
- Route: `/stock/entree-directe` - Déjà implémentée
- Contrôleur: `StockController@entreeDirecte` - Déjà implémentée
- Vue: `app/Views/stock/ajouter.php` - Déjà modifiée
- **Déjà fait:** Suppression lots, ajout Forme Pharmaceutique

**MANQUANT:**
- Champ DCI (Dénomination Commune Internationale)
- Champ Rayon (liste déroulante)
- Champ Stock minimum
- Rendre fournisseur facultatif

**STATUT:** ⚠️ Partiellement fait - À compléter

---

### 2.4 RAYON (Champ obligatoire)

**EXISTANT:**
- À vérifier: Table `produits` - Champ `rayon` ?
- À créer: Table `rayons` ou valeurs prédéfinies

**VALEURS AUTORISÉES:**
A, B, C, D, E, F, G, H, I, J, K, L, M, N, O, Frigo, Armoire à clé, Vitrine, Comptoir, Magasin, Autres

**STATUT:** ❌ À créer

---

### 2.5 DCI (Dénomination Commune Internationale)

**EXISTANT:**
- À vérifier: Table `produits` - Champ `dci` ?

**STATUT:** ❌ À créer

---

### 2.6 FORME PHARMACEUTIQUE (Supprimer Classe)

**EXISTANT:**
- Table: `formes_pharmaceutiques` - Déjà utilisée
- **Déjà fait:** Remplacement Catégorie par Forme Pharmaceutique

**MANQUANT:**
- Vérifier si champ `classe_pharmaceutique` existe et le supprimer
- Ajouter les nouvelles formes demandées

**STATUT:** ⚠️ Partiellement fait - À compléter

---

### 2.7 FOURNISSEUR (Facultatif dans Entrée Directe)

**EXISTANT:**
- Table: `fournisseurs`
- Déjà utilisé dans Entrée Directe

**STATUT:** ⚠️ À modifier (rendre facultatif)

---

### 2.8 DISPONIBILITÉ À LA VENTE (Automatisation)

**EXISTANT:**
- Déjà implémenté dans `StockController@entreeDirecte`
- Création produit + mise en stock + calcul prix + mouvement + disponibilité

**STATUT:** ✅ Déjà fait

---

### 2.9 FLUX DE STOCK (Historique complet)

**EXISTANT:**
- Route: `/stock/flux` - Déjà existe
- Contrôleur: `StockController@flux` - Déjà existe
- Table: `mouvements_stock` - Déjà existe

**STATUT:** ⚠️ À vérifier et compléter si nécessaire

---

### 2.10 VALEUR DU STOCK (Calculs)

**EXISTANT:**
- Route: `/stock/valeur` - Déjà existe
- Contrôleur: `StockController@valeurStock` - Déjà existe

**STATUT:** ⚠️ À vérifier et compléter (par rayon)

---

### 2.11 INVENTAIRE (Compléter)

**EXISTANT:**
- Contrôleur: `InventaireController.php`
- Service: `InventaireService.php`
- Routes: `/inventaire/*`

**MANQUANT:**
- Inventaire par rayon
- Inventaire par produit
- Calculs automatiques (écarts, manquants, excédents)

**STATUT:** ⚠️ Infrastructure existante - À compléter

---

### 2.12 TABLEAU DE BORD (Statistiques automatiques)

**EXISTANT:**
- Service: `DashboardService.php`
- Service: `PharmacyDashboardService.php`
- Service: `AnalyticsStockService.php`
- Service: `AnalyticsVentesService.php`
- Service: `AlertesService.php`

**STATUT:** ⚠️ Infrastructure existante - À vérifier et compléter

---

### 2.13 SUIVI CLIENTS (Suivi financier)

**EXISTANT:**
- Contrôleur: `SuiviClientController.php`
- Service: `ReglementTiersService.php`
- Routes: `/suivi-client/*`

**MANQUANT:**
- Ristourne, Escompte, Échéances

**STATUT:** ⚠️ Infrastructure existante - À compléter

---

### 2.14 SUIVI FOURNISSEURS (Suivi financier)

**EXISTANT:**
- Contrôleur: `FinanceController.php`
- Routes: `/finance/fournisseurs/*`

**STATUT:** ⚠️ Infrastructure existante - À vérifier et compléter

---

## 3. SYNTHÈSE

### 3.1 Déjà implémenté ✅
- Entrée Directe en Stock (base)
- Disponibilité à la vente (automatisation)
- Flux de Stock (base)
- Valeur du Stock (base)
- Gestion des remises (infrastructure)

### 3.2 Partiellement implémenté ⚠️
- Entrée Directe en Stock (manque DCI, Rayon, Stock min)
- Forme Pharmaceutique (manque nouvelles formes, suppression classe)
- Fournisseur (à rendre facultatif)
- Inventaire (manque par rayon/produit)
- Dashboard (à vérifier)
- Suivi Clients (à compléter)
- Suivi Fournisseurs (à vérifier)

### 3.3 À créer ❌
- Gestion des ordonnances par type de produit
- Champ Rayon (avec valeurs prédéfinies)
- Champ DCI

---

## 4. PROPOSITION D'ORDRE D'IMPLÉMENTATION

Compte tenu de l'ampleur, je propose de procéder par priorité:

**PHASE 1 - Modifications simples (1-2h)**
1. Gestion des remises (plafond 25% admin)
2. Rendre fournisseur facultatif dans Entrée Directe

**PHASE 2 - Ajout de champs (2-3h)**
3. Ajouter champ DCI dans produits et formulaire
4. Ajouter champ Rayon (avec valeurs prédéfinies)
5. Ajouter champ Stock minimum
6. Compléter Forme Pharmaceutique (nouvelles valeurs)

**PHASE 3 - Logique métier (3-4h)**
7. Gestion des ordonnances par type de produit
8. Compléter Inventaire (par rayon, par produit)
9. Compléter Valeur du Stock (par rayon)

**PHASE 4 - Dashboard et Suivi (2-3h)**
10. Compléter Dashboard (statistiques)
11. Compléter Suivi Clients
12. Compléter Suivi Fournisseurs

**TOTAL ESTIMÉ:** 8-12 heures de développement

---

## 5. QUESTIONS POUR L'UTILISATEUR

1. **Préférez-vous que je procède phase par phase** avec validation à chaque étape, ou **implémenter tout en une fois** ?

2. **Pour le champ Rayon**, préférez-vous:
   - Une table `rayons` en base de données ?
   - Ou des valeurs hardcoded dans le code ?

3. **Pour le champ DCI**, doit-il être:
   - Un champ texte libre ?
   - Ou une liste déroulante avec valeurs prédéfinies ?

4. **Pour la gestion des ordonnances**, comment identifier le type de produit:
   - Un champ `type_ordonnance` dans la table produits ?
   - Ou basé sur la forme pharmaceutique ?

---

**Document généré automatiquement par Cascade AI**
**Date de génération:** 18 juillet 2026
