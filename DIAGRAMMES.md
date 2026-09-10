# 📊 Diagrammes Logiques des Modules

## 🏗️ Architecture Générale

```mermaid
graph TB
    subgraph "Couche Présentation"
        A[Views] --> B[Controllers]
    end
    
    subgraph "Couche Métier"
        B --> C[Services]
        C --> D[Business Logic]
    end
    
    subgraph "Couche Données"
        D --> E[Models]
        E --> F[Database]
    end
    
    subgraph "Support"
        G[Middleware] --> B
        H[Events] --> C
        I[Policies] --> B
    end
    
    subgraph "Modules Spécifiques"
        J[Ventes] --> C
        K[Stock] --> C
        L[Caisse] --> C
        M[Comptabilité] --> C
        N[Clients] --> C
        O[Audit] --> C
    end
```

## 🔄 Flux Vente Complet

```mermaid
sequenceDiagram
    participant C as Client
    participant CAISS as Caissier
    participant CTRL as VentesController
    participant SVC as VenteService
    participant STOCK as StockService
    participant CAISSE as CaisseService
    participant COMPTA as ComptabilitéService
    participant AUDIT as AuditService
    participant DB as Database

    C->>CAISS: Demande produits
    CAISS->>CTRL: Nouvelle vente()
    CTRL->>SVC: initVente()
    SVC->>DB: Vérifier stock disponible
    DB-->>SVC: Stock OK
    
    CAISS->>CTRL: Ajouter produits
    CTRL->>SVC: ajouterProduit(produit, quantité)
    SVC->>STOCK: reserverStock()
    STOCK->>DB: Mise à jour stock théorique
    
    CAISS->>CTRL: Valider vente
    CTRL->>SVC: validerVente()
    SVC->>CAISSE: encaisser()
    CAISSE->>DB: Mise à jour session caisse
    
    SVC->>STOCK: confirmerSortie()
    STOCK->>DB: Déduction stock réel
    
    SVC->>COMPTA: genererEcriture()
    COMPTA->>DB: Écriture SYSCOA
    
    SVC->>AUDIT: logAction()
    AUDIT->>DB: Audit trail
    
    DB-->>CTRL: Confirmation
    CTRL-->>CAISS: Facture générée
    CAISS-->>C: Ticket + Facture
```

## 📦 Module Stock

```mermaid
graph LR
    subgraph "Entrées Stock"
        A[Commande Fournisseur] --> B[Réception]
        B --> C[Contrôle Qualité]
        C --> D[Création Lots]
        D --> E[Mise en Stock]
    end
    
    subgraph "Sorties Stock"
        F[Ventes] --> G[Déduction FIFO]
        H[Inventaire] --> I[Ajustements]
        J[Pertes] --> K[Mouvements Sortie]
    end
    
    subgraph "Contrôles"
        L[Stock Sécurité] --> M[Alertes]
        N[Péremption] --> O[Notifications]
        P[Inventaire] --> Q[Validation]
    end
    
    E --> G
    E --> I
    E --> K
    
    G --> R[Stock Réel]
    I --> R
    K --> R
    
    R --> L
    R --> N
    R --> P
```

## 💰 Module Caisse

```mermaid
stateDiagram-v2
    [*] --> Ouverture
    Ouverture --> Ventes: Session active
    
    state Ventes {
        [*] --> Enregistrement
        Enregistrement --> Paiement
        Paiement --> Ticket
        Ticket --> Enregistrement: Continue
    }
    
    Ventes --> Fermeture: Fin journée
    Fermeture --> Contrôle
    Contrôle --> Validation: Écart OK
    Contrôle --> Justification: Écart ≠ 0
    Justification --> Validation
    Validation --> Z_Caisse
    Z_Caisse --> [*]
    
    state "Gestion Écarts" as Gestion {
        [*] --> Détection
        Détection --> Analyse
        Analyse --> Correction
        Correction --> [*]
    }
```

## 🏦 Module Comptabilité SYSCOA

```mermaid
graph TB
    subgraph "Écritures Automatiques"
        A[Ventes] --> B[Débit 52 Caisse]
        A --> C[Crédit 70 Ventes]
        D[Achats] --> E[Débit 60 Achats]
        D --> F[Crédit 401 Fournisseurs]
    end
    
    subgraph "Plan Comptable"
        G[Classe 1: Capitaux]
        H[Classe 2: Immobilisations]
        I[Classe 3: Stocks]
        J[Classe 4: Tiers]
        K[Classe 5: Trésorerie]
        L[Classe 6: Charges]
        M[Classe 7: Produits]
        N[Classe 8: Engagements]
    end
    
    subgraph "États Financiers"
        O[Bilan] --> G
        O --> H
        O --> I
        O --> J
        O --> K
        
        P[Compte Résultat] --> L
        P --> M
        
        Q[Tableau Flux] --> O
        Q --> P
    end
    
    B --> K
    C --> M
    E --> L
    F --> J
```

## 🔐 Sécurité et Permissions

```mermaid
graph TD
    subgraph "Utilisateurs"
        A[Admin] --> P1[Toutes permissions]
        B[Gérant] --> P2[Gestion opérations]
        C[Pharmacien] --> P3[Stock + Qualité]
        D[Caissier] --> P4[Ventes + Caisse]
    end
    
    subgraph "Modules"
        E[Ventes] --> R1[Créer, Lire, Mettre à jour]
        F[Stock] --> R2[CRUD complet]
        G[Caisse] --> R3[Gérer sessions]
        H[Comptabilité] --> R4[Lire, Valider]
        I[Admin] --> R5[Configuration]
    end
    
    subgraph "Contrôles"
        J[Auth] --> K[Session valide]
        L[Roles] --> M[Permission vérifiée]
        N[Audit] --> O[Action loguée]
    end
    
    P2 --> E
    P2 --> G
    P3 --> F
    P4 --> E
    P4 --> G
    P1 --> I
    
    K --> L
    L --> M
    M --> O
```

## 📊 Flux Données Complet

```mermaid
flowchart TD
    A[Client] -->|Achat| B[Vente]
    B -->|Déduction| C[Stock]
    B -->|Encaissement| D[Caisse]
    B -->|Facturation| E[Comptabilité]
    
    F[Fournisseur] -->|Livraison| G[Commande]
    G -->|Réception| C
    G -->|Facture| E
    
    H[Pharmacien] -->|Gestion| C
    H -->|Contrôle| I[Qualité]
    
    J[Gérant] -->|Supervision| D
    J -->|Validation| E
    J -->|Contrôle| K[Audit]
    
    C -->|Alertes| L[Notifications]
    D -->|Z Caisse| M[Rapports]
    E -->|Bilan| N[États financiers]
    
    K -->|Traçabilité| O[Logs système]
    L -->|Alertes| P[Utilisateurs]
```

## 🔄 Cycle de Vie Produit

```mermaid
stateDiagram-v2
    [*] --> Commande
    Commande --> Reception: Livraison fournisseur
    Reception --> ControleQualite: Contrôle réception
    ControleQualite --> Stockage: Conforme
    ControleQualite --> RetourFournisseur: Non conforme
    
    Stockage --> Vente: Vente client
    Stockage --> Peremption: Date limite atteinte
    Stockage --> Inventaire: Contrôle périodique
    Stockage --> Ajustement: Écart détecté
    
    Vente --> Stockage: Retour client
    Vente --> [*]: Produit vendu
    
    Peremption --> Destruction: Produit périmé
    Inventaire --> Stockage: Ajustement effectué
    Ajustement --> Stockage: Correction validée
    
    Destruction --> [*]
    RetourFournisseur --> [*]
```

## 🎯 Matrice des Dépendances

```mermaid
graph LR
    subgraph "Core Modules"
        A[Auth] --> B[Users]
        A --> C[Roles]
        B --> D[Audit]
    end
    
    subgraph "Business Modules"
        E[Clients] --> F[Ventes]
        G[Produits] --> F
        G --> H[Stock]
        H --> F
        F --> I[Caisse]
        F --> J[Comptabilité]
    end
    
    subgraph "Support Modules"
        K[Notifications] --> L[Reporting]
        D --> L
        M[Settings] --> A
        M --> N[Backup]
    end
    
    E --> D
    G --> D
    F --> D
    I --> D
    J --> D
    
    L --> O[Dashboard]
```

## 📈 Architecture Événementielle

```mermaid
sequenceDiagram
    participant E as Event Dispatcher
    participant S as Stock Service
    participant C as Comptabilité Service
    participant N as Notification Service
    participant A as Audit Service
    participant L as Listeners

    E->>S: VenteCreated Event
    S->>L: UpdateStock
    S-->>E: StockUpdated Event
    
    E->>C: VenteCreated Event
    C->>L: CreateEcriture
    C-->>E: EcritureCreated Event
    
    E->>N: StockUpdated Event
    N->>L: SendAlert
    N-->>E: NotificationSent Event
    
    E->>A: All Events
    A->>L: LogAction
    A-->>E: AuditLogged Event
    
    E->>L: Process all listeners
    L-->>E: Events processed
```

## 🔍 Architecture de Persistance

```mermaid
graph TB
    subgraph "Application Layer"
        A[Controllers] --> B[Services]
        B --> C[Repositories]
    end
    
    subgraph "Data Access Layer"
        C --> D[MySQL Database]
        C --> E[Redis Cache]
        C --> F[File Storage]
    end
    
    subgraph "External Services"
        G[CNAM API] --> B
        H[ANSM Database] --> B
        I[Banking API] --> B
    end
    
    subgraph "Backup & Analytics"
        J[Daily Backup] --> D
        K[Analytics Engine] --> E
        L[Report Generator] --> D
    end
    
    D --> M[Primary DB]
    D --> N[Read Replica]
    
    E --> O[Session Cache]
    E --> P[Query Cache]
    
    F --> Q[Documents]
    F --> R[Images]
    F --> S[Exports]
```

Ces diagrammes illustrent l'architecture complète du système avec :
- **Séparation des responsabilités** claire entre couches
- **Flux de données** optimisés et sécurisés
- **Gestion des événements** pour réactivité
- **Persistance robuste** avec cache et backup
- **Intégrations externes** pour conformité réglementaire
