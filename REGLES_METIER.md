# 📋 Règles Métier - Gestion Pharmacie ERP

## 🎯 Principes Fondamentaux

### 1. **Intégrité des Données**
- **Suppression interdite** : Utilisation de `deleted_at` pour annulation logique
- **Traçabilité obligatoire** : Toute action doit être loguée dans `audit_logs`
- **Validation en temps réel** : Stocks, prix, et disponibilité vérifiés à chaque transaction

### 2. **Gestion des Stocks**
- **Stock théorique** = Stock réel - Commandes en cours - Réservations
- **FIFO obligatoire** : First In, First Out pour rotation produits
- **Stock de sécurité** : Alerte automatique si stock ≤ stock_sécurité
- **Gestion lots** : Traçabilité complète par numéro de lot

### 3. **Contrôle d'Accès**
- **Rôles hiérarchiques** : Admin > Gérant > Pharmacien > Caissier
- **Permissions granulaires** : Par module et par action
- **Audit complet** : IP, user_agent, date, action, valeurs modifiées

## 🔄 Flux Métier Détaillés

### 🛒 Flux Vente Complet

```
1. SÉLECTION PRODUIT
   ├─ Vérification disponibilité stock
   ├─ Vérification péremption
   └─ Calcul prix selon type client

2. CRÉATION VENTE
   ├─ Génération numéro_facture unique
   ├─ Réservation stock temporaire
   └─ Vérification crédit client (si applicable)

3. VALIDATION PAIEMENT
   ├─ Enregistrement paiement caisse
   ├─ Mise à jour session caisse
   └─ Génération ticket/facture

4. MISE À JOUR STOCK
   ├─ Déduction quantité définitive
   ├─ Mise à jour lot (FIFO)
   └─ Log mouvement stock

5. COMPTABILITÉ
   ├─ Écriture comptable SYSCOA
   ├─ Mise à jour comptes clients
   └─ Validation journal

6. AUDIT
   ├─ Log action utilisateur
   ├─ Sauvegarde valeurs modifiées
   └─ Notification système
```

### 📦 Flux Commande Fournisseur

```
1. CRÉATION COMMANDE
   ├─ Sélection fournisseur
   ├─ Choix produits et quantités
   └─ Validation gestionnaire

2. VALIDATION
   ├─ Vérification budget
   ├─ Validation pharmacien
   └─ Envoi commande fournisseur

3. RÉCEPTION
   ├─ Contrôle qualité
   ├─ Vérification conformité
   └─ Signature bon de livraison

4. MISE EN STOCK
   ├─ Création lots
   ├─ Mise à jour quantités
   └─ Génération mouvements

5. FACTURATION
   ├─ Enregistrement facture fournisseur
   ├─ Écriture comptable achat
   └─ Planification paiement

6. AUDIT COMPLET
   └─ Traçabilité toutes étapes
```

### 💰 Flux Caisse Journalier

```
1. OUVERTURE SESSION
   ├─ Identification caissier
   ├─ Saisie fonds de caisse
   └─ Vérification Z précédent

2. VENTES JOURNÉE
   ├─ Enregistrement transactions
   ├─ Mise à jour solde session
   └─ Gestion paiements multiples

3. FERMETURE SESSION
   ├─ Comptage fonds physiques
   ├─ Calcul Z théorique
   ├─ Détection écarts
   └─ Validation manager

4. CONTRÔLE
   ├─ Justification écarts
   ├─ Validation comptable
   └─ Archivage session

5. REPORTING
   ├─ Bilan caisse
   ├─ État ventes
   └─ Audit trail
```

## ⚖️ Règles Comptabilité SYSCOA

### Plan Comptable Simplifié

``Classe 1 - Ressources Durables``
- 10 Capital
- 16 Emprunts
- 17 Dettes fournisseurs

``Classe 2 - Emplois Durables``
- 21 Immobilisations
- 24 Stocks

``Classe 3 - Stocks``
- 31 Marchandises
- 33 Produits finis

``Classe 4 - Créances et Dettes``
- 41 Clients
- 44 État
- 48 Dettes sociales

``Classe 5 - Trésorerie``
- 51 Banques
- 52 Caisse
- 57 Chèques postaux

``Classe 6 - Charges``
- 60 Achats
- 61 Services extérieurs
- 63 Impôts et taxes
- 64 Charges de personnel

``Classe 7 - Produits``
- 70 Ventes marchandises
- 71 Production vendue
- 76 Produits financiers

``Classe 8 - Engagements``
- 81 Engagements hors bilan
```

### Écritures Types

#### Vente Comptant
```
Débit 52 Caisse          [Montant TTC]
Débit 44 État/TVA        [TVA]
Crédit 70 Ventes        [Montant HT]
Crédit 41 Clients       [Si crédit]
```

#### Achat Marchandise
```
Débit 60 Achats         [Montant HT]
Débit 44 État/TVA       [TVA déductible]
Crédit 401 Fournisseurs [Montant TTC]
```

#### Paiement Fournisseur
```
Débit 401 Fournisseurs  [Montant]
Crédit 52 Caisse        [Espèces]
ou Crédit 51 Banques    [Virement]
```

## 🔐 Règles de Sécurité

### Gestion Utilisateurs
- **Password hash** obligatoire (bcrypt/argon2)
- **Session timeout** : 30 minutes inactivité
- **Double authentification** pour actions sensibles
- **Historique connexions** conservé 2 ans

### Permissions par Rôle

#### Administrateur
- Accès total système
- Gestion utilisateurs
- Configuration globale
- Validation comptabilité

#### Gérant
- Gestion complète opérations
- Validation commandes
- Contrôle caisse
- Rapports management

#### Pharmacien
- Gestion stocks
- Validation prescriptions
- Contrôle qualité
- Formation produits

#### Caissier
- Ventes et encaissements
- Gestion session caisse
- Consultation stocks
- Impression tickets

### Audit Obligatoire

#### Actions Loguées
- Connexions/Déconnexions
- Création/Modification/Suppression
- Transactions financières
- Changements de statut
- Export de données

#### Format Log
```json
{
    "utilisateur_id": 123,
    "action": "CREATE_VENTE",
    "table": "ventes",
    "record_id": 456,
    "old_values": null,
    "new_values": {
        "client_id": 78,
        "montant_total": 15000,
        "statut": "EN_COURS"
    },
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "date_action": "2024-01-15 14:30:22"
}
```

## ⚡ Règles Performance

### Optimisation
- **Index automatiques** sur clés étrangères
- **Cache Redis** pour données fréquemment accédées
- **Pagination** pour listes > 100 enregistrements
- **Transactions** pour opérations multiples

### Limites Système
- **Session caisse** : Max 8h consécutives
- **Commande fournisseur** : Max 50 lignes
- **Vente** : Max 100 articles par transaction
- **Export** : Max 10 000 enregistrements

## 🚨 Règles Gestion Erreurs

### Validation Entrées
- **Codes CIP** : 13 digits obligatoires
- **Emails** : Format valide requis
- **Téléphones** : Format international accepté
- **Montants** : 2 décimales maximum

### Gestion Conflits
- **Stock concurrent** : Lock pessimiste
- **Double vente** : Contrainte unique facture
- **Session caisse** : Une seule par caissier
- **Écriture comptable** : Numéro unique garanti

### Notifications Automatiques
- **Stock critique** : Email pharmacien + SMS gérant
- **Péremption proche** : Alertes 90/30/7 jours avant
- **Écart caisse** : Notification immédiate manager
- **Crédit dépassement** : Blocage automatique

## 📊 Indicateurs Clés (KPIs)

### Ventes
- **Panier moyen** : Montant total / Nombre ventes
- **Taux conversion** : Ventes / Visites
- **Marge brute** : (PV - PA) / PV
- **Rotation stock** : Coût ventes / Stock moyen

### Stock
- **Taux rupture** : Produits en rupture / Total produits
- **Valeur stock** : Somme quantités × PA
- **Couverture** : Stock / Consommation mensuelle
- **Taux péremption** : Produits périmés / Total

### Caisse
- **Écart moyen** : |Théorique - Réel| / Théorique
- **Transactions/jour** : Nombre ventes / Jours ouvrés
- **Ticket moyen** : Montant total / Nombre transactions
- **Taux crédit** : Ventes crédit / Total ventes

## 🔄 Intégrations Externes

### Obligatoires
- **CNAM** : Vérification couverture assurance
- **ANSM** : Base médicaments et autorisations
- **Douanes** : Déclarations import/export
- **Banque** : Rapprochements bancaires

### Optionnelles
- **Laboratoires** : Commandes automatiques
- **Mutuelles** : Télétransmission feuilles soins
- **Fournisseurs** : Portail B2B
- **Analytics** : Tableaux de bord avancés
