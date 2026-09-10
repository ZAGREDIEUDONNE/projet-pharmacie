# Architecture Logicielle - Gestion Pharmacie

## 🏗️ Structure Globale du Projet

```
pharmacie-erp/
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── VentesController.php
│   │   ├── StockController.php
│   │   ├── ClientsController.php
│   │   ├── CaisseController.php
│   │   ├── ComptabiliteController.php
│   │   └── RapportsController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Client.php
│   │   ├── Produit.php
│   │   ├── Vente.php
│   │   ├── MouvementStock.php
│   │   ├── CaisseSession.php
│   │   ├── EcritureComptable.php
│   │   └── AuditLog.php
│   ├── Services/
│   │   ├── VenteService.php
│   │   ├── StockService.php
│   │   ├── CaisseService.php
│   │   ├── ComptabiliteService.php
│   │   ├── ClientService.php
│   │   └── AuditService.php
│   ├── Views/
│   │   ├── layouts/
│   │   ├── ventes/
│   │   ├── stock/
│   │   ├── clients/
│   │   ├── caisse/
│   │   └── comptabilite/
│   └── Middleware/
│       ├── AuthMiddleware.php
│       ├── RoleMiddleware.php
│       ├── AuditMiddleware.php
│       └── CsrfMiddleware.php
├── modules/
│   ├── Ventes/
│   │   ├── VenteManager.php
│   │   ├── FactureGenerator.php
│   │   └── TicketCaisse.php
│   ├── Stock/
│   │   ├── StockManager.php
│   │   ├── PeremptionTracker.php
│   │   └── InventaireManager.php
│   ├── Clients/
│   │   ├── ClientManager.php
│   │   ├── AssuranceManager.php
│   │   └── CreditManager.php
│   ├── Caisse/
│   │   ├── SessionManager.php
│   │   ├── PaiementProcessor.php
│   │   └── ZJournalManager.php
│   ├── Comptabilite/
│   │   ├── SyscoaManager.php
│   │   ├── PlanComptable.php
│   │   └── BilanGenerator.php
│   └── Audit/
│       ├── AuditLogger.php
│       ├── EventDispatcher.php
│       └── ReportGenerator.php
├── database/
│   ├── migrations/
│   │   ├── 001_create_users_table.php
│   │   ├── 002_create_produits_table.php
│   │   ├── 003_create_clients_table.php
│   │   ├── 004_create_ventes_table.php
│   │   ├── 005_create_stock_table.php
│   │   ├── 006_create_caisse_table.php
│   │   ├── 007_create_comptabilite_table.php
│   │   └── 008_create_audit_table.php
│   ├── seeders/
│   │   ├── UsersSeeder.php
│   │   ├── ProduitsSeeder.php
│   │   ├── PlanComptableSeeder.php
│   │   └── RolesSeeder.php
│   └── schema.sql
├── config/
│   ├── database.php
│   ├── app.php
│   ├── auth.php
│   └── comptabilite.php
├── routes/
│   ├── web.php
│   ├── api.php
│   └── admin.php
├── Policies/
│   ├── VentePolicy.php
│   ├── StockPolicy.php
│   ├── ClientPolicy.php
│   └── ComptabilitePolicy.php
├── Events/
│   ├── VenteCreated.php
│   ├── StockUpdated.php
│   ├── CaisseSessionClosed.php
│   └── UserActionLogged.php
├── Listeners/
│   ├── UpdateStockOnVente.php
│   ├── LogAuditTrail.php
│   ├── GenerateEcritureComptable.php
│   └── SendNotification.php
├── storage/
│   ├── logs/
│   ├── uploads/
│   └── cache/
├── public/
│   ├── assets/
│   ├── images/
│   └── uploads/
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Integration/
├── docs/
│   ├── api/
│   ├── database/
│   └── user-guide/
├── vendor/
├── .env.example
├── .gitignore
├── composer.json
├── README.md
└── LICENSE
```

## 🏛️ Architecture MVC Stricte

### Controllers
- **AuthController**: Gestion authentification et sessions
- **VentesController**: Interface ventes et facturation
- **StockController**: Gestion stocks et mouvements
- **ClientsController**: Gestion clients et assurances
- **CaisseController**: Sessions caisse et paiements
- **ComptabiliteController**: Écritures et rapports SYSCOA

### Models
- **User**: Utilisateurs et rôles
- **Produit**: Médicaments et produits
- **Client**: Clients ordinaires et assurés
- **Vente**: Transactions et factures
- **MouvementStock**: Entrées/sorties stocks
- **CaisseSession**: Sessions caissiers
- **EcritureComptable**: Écritures SYSCOA
- **AuditLog**: Traçabilité complète

### Services (Couche métier)
- **VenteService**: Logique ventes et facturation
- **StockService**: Gestion stocks et péremptions
- **CaisseService**: Sessions et Z caisses
- **ComptabiliteService**: Écritures comptables
- **ClientService**: Gestion clients et crédits
- **AuditService**: Traçabilité et logs

## 🔐 Middleware et Sécurité

### AuthMiddleware
- Vérification session utilisateur
- Gestion timeout et reconnexion

### RoleMiddleware
- Contrôle accès par rôles
- Vérification permissions module

### AuditMiddleware
- Log automatique actions
- Traçabilité modifications

### CsrfMiddleware
- Protection CSRF
- Validation tokens

## 🎯 Modules Principaux

### Module Ventes
- Caisse rapide
- Facturation
- Gestion crédits
- Tickets et factures

### Module Stock
- Gestion lots
- Stock théorique/réel
- Péremptions
- Inventaires

### Module Clients
- Fiches clients
- Assurances
- Crédits et règlements

### Module Caisse
- Sessions 1-2-3
- Z caisse journalier
- Paiements multiples

### Module Comptabilité
- SYSCOA/OHADA
- Plan comptable
- Bilans et états

### Module Audit
- Traçabilité complète
- Logs système
- Rapports d'activité

## 🔄 Flux Principaux

### Flux Vente → Stock → Caisse → Compta
```
Vente initiée
    ↓
Vérification stock disponible
    ↓
Mise à jour stock immédiate
    ↓
Génération facture
    ↓
Encaissement caisse
    ↓
Écriture comptable SYSCOA
    ↓
Log audit complet
```

### Flux Commande Fournisseur
```
Commande créée
    ↓
Validation gestionnaire
    ↓
Réception marchandise
    ↓
Mise à jour stock
    ↓
Facture fournisseur
    ↓
Écriture comptable
    ↓
Log audit
```

## 📊 Règles Métier

### Gestion Stock
- **Stock théorique** = Stock réel - Commandes en cours
- **FIFO** pour rotation produits
- **Alerte péremption** 3 mois avant

### Traçabilité
- **Toute action** loguée avec user + date + action
- **Modification** interdite → annulation logique
- **Audit trail** non modifiable

### Comptabilité SYSCOA
- **Classe 1-5** : Bilan
- **Classe 6-7** : Compte résultat
- **Classe 8** : Engagements

### Sécurité
- **Rôles** : Admin, Gérant, Caissier, Pharmacien
- **Permissions** granulaires par module
- **Sessions** avec timeout configurable
