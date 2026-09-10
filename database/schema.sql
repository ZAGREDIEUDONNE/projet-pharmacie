-- =============================================
-- BASE DE DONNÉES COMPLÈTE GESTION PHARMACIE
-- SYSTÈME ERP SYSCOA/OHADA
-- =============================================

-- =============================================
-- TABLES UTILISATEURS ET SÉCURITÉ
-- =============================================

-- Utilisateurs du système
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    tentative_echec INT DEFAULT 0,
    date_blocage DATETIME NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification_role DATETIME NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Rôles utilisateurs
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Permissions système
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    module VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Permissions des rôles (jointure)
CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);

-- =============================================
-- TABLES PRODUITS ET STOCK
-- =============================================

-- Produits et médicaments
CREATE TABLE produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_cip VARCHAR(13) UNIQUE,
    code_barre VARCHAR(50) UNIQUE,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    categorie_id INT,
    fournisseur_id INT,
    prix_achat DECIMAL(10,2) NOT NULL,
    prix_vente DECIMAL(10,2) NOT NULL,
    prix_vente_assure DECIMAL(10,2),
    unite_mesure VARCHAR(20) DEFAULT 'unité',
    stock_securite INT DEFAULT 0,
    stock_alerte INT DEFAULT 0,
    is_actif BOOLEAN DEFAULT TRUE,
    requires_prescription BOOLEAN DEFAULT FALSE,
    date_peremption_default DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (categorie_id) REFERENCES categories(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id)
);

-- Catégories de produits
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id)
);

-- Fournisseurs
CREATE TABLE fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(200) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    registre_commerce VARCHAR(50),
    compte_bancaire VARCHAR(50),
    delai_livraison INT DEFAULT 7,
    is_actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Lots de produits (traçabilité)
CREATE TABLE lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    numero_lot VARCHAR(50) NOT NULL,
    date_fabrication DATE,
    date_peremption DATE NOT NULL,
    quantite_initiale INT NOT NULL,
    quantite_restante INT NOT NULL,
    prix_achat_unitaire DECIMAL(10,2) NOT NULL,
    fournisseur_id INT,
    commande_id INT,
    is_actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    UNIQUE KEY unique_lot_produit (produit_id, numero_lot)
);

-- Stock global par produit
CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT UNIQUE NOT NULL,
    quantite_disponible INT DEFAULT 0,
    quantite_theorique INT DEFAULT 0,
    quantite_reservee INT DEFAULT 0,
    valeur_stock DECIMAL(12,2) DEFAULT 0,
    dernier_mouvement DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id)
);

-- Mouvements de stock
CREATE TABLE mouvements_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    lot_id INT,
    type_mouvement ENUM('ENTREE', 'SORTIE', 'TRANSFERT', 'AJUSTEMENT', 'PERTE') NOT NULL,
    quantite INT NOT NULL,
    quantite_avant INT NOT NULL,
    quantite_apres INT NOT NULL,
    motif VARCHAR(200),
    reference_type ENUM('VENTE', 'COMMAND', 'INVENTAIRE', 'AJUSTEMENT', 'TRANSFERT') NOT NULL,
    reference_id INT,
    utilisateur_id INT NOT NULL,
    date_mouvement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Entrées de stock (traçabilité détaillée)
CREATE TABLE stock_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    fournisseur_id INT,
    utilisateur_id INT NOT NULL,
    reception_id INT,
    quantite INT NOT NULL,
    prix_achat DECIMAL(12,2) NOT NULL,
    date_reception DATE NOT NULL,
    numero_facture VARCHAR(100),
    observations TEXT,
    stock_avant INT NOT NULL,
    stock_apres INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (reception_id) REFERENCES receptions(id)
);

-- =============================================
-- TABLES CLIENTS
-- =============================================

-- Clients (ordinaires et assurés)
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    matricule VARCHAR(20),
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),
    telephone VARCHAR(20),
    email VARCHAR(100),
    adresse TEXT,
    type_client ENUM('ORDINAIRE', 'ASSURE', 'ENTREPRISE') DEFAULT 'ORDINAIRE',
    numero_assurance VARCHAR(50),
    compagnie_assurance VARCHAR(100),
    plafond_credit DECIMAL(10,2) DEFAULT 0,
    solde_credit DECIMAL(10,2) DEFAULT 0,
    is_actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- =============================================
-- TABLES RÈGLEMENTS TIERS
-- =============================================

-- Règlements clients
CREATE TABLE client_reglements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_brouillon VARCHAR(32),
    client_id INT NOT NULL,
    vente_id INT,
    type_mouvement ENUM('DEBIT', 'CREDIT', 'RISTOURNE', 'ESCOMPTE') NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    mode_paiement VARCHAR(40),
    reference VARCHAR(120),
    notes TEXT,
    utilisateur_id INT,
    date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('BROUILLON', 'VALIDE', 'SUPPRIME') NOT NULL DEFAULT 'VALIDE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    validated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vente_id) REFERENCES ventes(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    UNIQUE KEY uq_client_reglements_numero_brouillon (numero_brouillon)
);

-- Règlements fournisseurs
CREATE TABLE fournisseur_reglements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fournisseur_id INT NOT NULL,
    reception_id INT,
    type_mouvement ENUM('DEBIT', 'CREDIT', 'RISTOURNE', 'ESCOMPTE') NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    mode_paiement VARCHAR(40),
    reference VARCHAR(120),
    notes TEXT,
    utilisateur_id INT,
    date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (reception_id) REFERENCES receptions(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Remises commerciales
CREATE TABLE remises_commerciales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tiers_type ENUM('CLIENT', 'FOURNISSEUR') NOT NULL,
    tiers_id INT NOT NULL,
    reference_type VARCHAR(60),
    reference_id INT,
    type_avantage ENUM('RISTOURNE', 'ESCOMPTE') NOT NULL,
    montant DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pourcentage DECIMAL(5,2),
    motif TEXT,
    utilisateur_id INT,
    date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- TABLES VENTES ET FACTURATION
-- =============================================

-- Ventes (entêtes)
CREATE TABLE ventes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_facture VARCHAR(50) UNIQUE NOT NULL,
    client_id INT,
    utilisateur_id INT NOT NULL,
    caisse_session_id INT,
    date_vente DATETIME NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    montant_ht DECIMAL(10,2) DEFAULT 0.00,
    montant_tva DECIMAL(10,2) DEFAULT 0.00,
    montant_ttc DECIMAL(10,2) DEFAULT 0.00,
    montant_remise DECIMAL(10,2) DEFAULT 0,
    montant_net DECIMAL(10,2) NOT NULL,
    montant_paye DECIMAL(10,2) DEFAULT 0,
    montant_restant DECIMAL(10,2) DEFAULT 0,
    type_paiement ENUM('ESPECE', 'CARTE', 'CHEQUE', 'CREDIT', 'MOBILE_MONEY') DEFAULT 'ESPECE',
    statut_vente ENUM('EN_COURS', 'PAYEE', 'PARTIELLEMENT_PAYEE', 'ANNULEE') DEFAULT 'EN_COURS',
    statut_paiement ENUM('en_attente', 'partiel', 'paye', 'impaye') DEFAULT 'en_attente',
    is_credit TINYINT(1) DEFAULT 0,
    echeance_credit DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    ecriture_id INT,
    remise DECIMAL(5,2) DEFAULT 0.00,
    type_vente ENUM('COMPTANT', 'CREDIT', 'ASSURANCE') DEFAULT 'COMPTANT',
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (caisse_session_id) REFERENCES caisse_sessions(id)
);

-- Détails des ventes
CREATE TABLE ventes_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    lot_id INT,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    remise DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id)
);

CREATE TABLE product_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    old_prix_achat DECIMAL(12,2) NOT NULL,
    new_prix_achat DECIMAL(12,2) NOT NULL,
    old_prix_vente DECIMAL(12,2) NOT NULL,
    new_prix_vente DECIMAL(12,2) NOT NULL,
    motif TEXT NOT NULL,
    utilisateur_id INT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

CREATE TABLE vente_ordonnances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    numero_ordonnance VARCHAR(100) NOT NULL,
    date_ordonnance DATE NOT NULL,
    nom_medecin VARCHAR(200) NOT NULL,
    structure_sanitaire VARCHAR(200) NOT NULL,
    nom_patient VARCHAR(200) NOT NULL,
    telephone_patient VARCHAR(30) NOT NULL,
    observation TEXT,
    utilisateur_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

CREATE TABLE vente_ordonnance_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordonnance_id INT NOT NULL,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ordonnance_id) REFERENCES vente_ordonnances(id) ON DELETE CASCADE,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    UNIQUE KEY unique_ordonnance_produit (ordonnance_id, produit_id)
);

-- =============================================
-- TABLES COMMANDES FOURNISSEURS
-- =============================================

-- Commandes fournisseurs
CREATE TABLE commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) UNIQUE NOT NULL,
    fournisseur_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    date_commande DATE NOT NULL,
    date_livraison_prevue DATE,
    date_livraison_reelle DATE,
    montant_ht DECIMAL(10,2) DEFAULT 0.00,
    montant_tva DECIMAL(10,2) DEFAULT 0.00,
    montant_total DECIMAL(10,2) NOT NULL,
    statut_commande ENUM('BROUILLON', 'VALIDEE', 'PARTIELLEMENT_LIVREE', 'LIVREE', 'ANNULEE') DEFAULT 'BROUILLON',
    conditions_paiement VARCHAR(200),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    ecriture_id INT,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Détails des commandes
CREATE TABLE commande_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite_commandee INT NOT NULL,
    quantite_livree INT DEFAULT 0,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id)
);

-- =============================================
-- TABLES COMMANDES FOURNISSEURS (NOUVEAU SYSTÈME)
-- =============================================

-- Commandes fournisseurs (système charge_commande)
CREATE TABLE supplier_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) UNIQUE NOT NULL,
    reference_commande VARCHAR(100),
    fournisseur_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    date_commande DATE NOT NULL,
    date_livraison_prevue DATE,
    statut ENUM('BROUILLON', 'EN_ATTENTE', 'VALIDEE', 'EN_COURS', 'LIVREE', 'ANNULEE', 'RECEPTION_PARTIELLE', 'RECEPTION_COMPLETE') DEFAULT 'BROUILLON',
    remise_globale DECIMAL(5,2) DEFAULT 0,
    tva_globale DECIMAL(5,2) DEFAULT 0,
    montant_ht DECIMAL(12,2) DEFAULT 0,
    montant_ttc DECIMAL(12,2) DEFAULT 0,
    montant_total DECIMAL(12,2) DEFAULT 0,
    observations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Détails des commandes fournisseurs (système charge_commande)
CREATE TABLE supplier_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_order_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite_commandee INT NOT NULL,
    quantite_recue INT DEFAULT 0,
    prix_achat DECIMAL(12,2) NOT NULL,
    montant_total DECIMAL(12,2) NOT NULL,
    remise DECIMAL(5,2) DEFAULT 0,
    tva DECIMAL(5,2) DEFAULT 0,
    montant_ht DECIMAL(12,2) DEFAULT 0,
    montant_ttc DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_order_id) REFERENCES supplier_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id)
);

-- Réceptions de commandes fournisseurs
CREATE TABLE receptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_order_id INT NOT NULL,
    fournisseur_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    numero_reception VARCHAR(50) UNIQUE NOT NULL,
    date_reception DATE NOT NULL,
    numero_facture VARCHAR(100),
    date_facture DATE,
    reference_facture VARCHAR(100),
    montant_facture DECIMAL(12,2) DEFAULT 0,
    statut ENUM('EN_ATTENTE', 'RECU_COMPLET', 'PARTIELLEMENT_RECU', 'ANNULEE') DEFAULT 'EN_ATTENTE',
    observations TEXT,
    ecart_detecte TINYINT(1) DEFAULT 0,
    ecriture_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_order_id) REFERENCES supplier_orders(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Détails des réceptions
CREATE TABLE reception_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reception_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite_attendue INT NOT NULL,
    quantite_recue INT NOT NULL,
    prix_achat DECIMAL(12,2) NOT NULL,
    ecart INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reception_id) REFERENCES receptions(id),
    FOREIGN KEY (produit_id) REFERENCES produits(id)
);

-- =============================================
-- TABLES CAISSE
-- =============================================

-- Sessions de caisse
CREATE TABLE caisse_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_session VARCHAR(20) UNIQUE NOT NULL,
    caissier_id INT NOT NULL,
    date_ouverture DATETIME NOT NULL,
    date_fermeture DATETIME,
    montant_ouverture DECIMAL(10,2) DEFAULT 0,
    montant_fermeture DECIMAL(10,2) DEFAULT 0,
    montant_ventes DECIMAL(10,2) DEFAULT 0,
    montant_theorique DECIMAL(10,2) DEFAULT 0,
    ecart DECIMAL(10,2) DEFAULT 0,
    statut_session ENUM('OUVERTE', 'FERMEE', 'CONTROLEE') DEFAULT 'OUVERTE',
    notes_controle TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id)
);

-- Mouvements de caisse
CREATE TABLE mouvements_caisse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caisse_session_id INT NOT NULL,
    type_mouvement ENUM('VENTE', 'REMBOURSEMENT', 'APPROVISIONNEMENT', 'RETRAIT', 'AJUSTEMENT') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    moyen_paiement ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE_MONEY') DEFAULT 'ESPECE',
    reference VARCHAR(100),
    description TEXT,
    utilisateur_id INT NOT NULL,
    date_mouvement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ecriture_id INT,
    supprime TINYINT(1) DEFAULT 0,
    FOREIGN KEY (caisse_session_id) REFERENCES caisse_sessions(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- =============================================
-- TABLES COMPTABILITÉ SYSCOA
-- =============================================

-- Plan comptable SYSCOA
CREATE TABLE plan_comptable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_compte VARCHAR(20) UNIQUE NOT NULL,
    nom_compte VARCHAR(200) NOT NULL,
    classe INT NOT NULL,
    type_compte ENUM('ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT') NOT NULL,
    solde_initial DECIMAL(12,2) DEFAULT 0,
    solde_actuel DECIMAL(12,2) DEFAULT 0,
    is_actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Journal comptable
CREATE TABLE journal_comptable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_ecriture VARCHAR(50) UNIQUE NOT NULL,
    date_ecriture DATE NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    reference_type ENUM('VENTE', 'ACHAT', 'CAISSE', 'BANQUE', 'OD') NOT NULL,
    reference_id INT,
    utilisateur_id INT NOT NULL,
    is_validated BOOLEAN DEFAULT FALSE,
    date_validation DATETIME,
    validated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (validated_by) REFERENCES utilisateurs(id)
);

-- Journaux comptables (configuration)
CREATE TABLE journaux_comptables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    libelle VARCHAR(255),
    type_journal ENUM('achats', 'ventes', 'banque', 'caisse', 'operations_diverses', 'ouverture', 'cloture') DEFAULT 'operations_diverses',
    description TEXT,
    is_actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    type VARCHAR(30),
    couleur VARCHAR(20),
    is_systeme TINYINT(1) DEFAULT 0
);

-- Écritures comptables (détail)
CREATE TABLE ecritures_comptables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_id INT NOT NULL,
    compte_id INT NOT NULL,
    debit DECIMAL(12,2) DEFAULT 0,
    credit DECIMAL(12,2) DEFAULT 0,
    libelle VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journal_id) REFERENCES journal_comptable(id) ON DELETE CASCADE,
    FOREIGN KEY (compte_id) REFERENCES plan_comptable(id)
);

-- Lignes d'écritures comptables
CREATE TABLE lignes_ecritures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ecriture_id INT NOT NULL,
    compte_id INT,
    libelle VARCHAR(255),
    debit DECIMAL(12,2) DEFAULT 0.00,
    credit DECIMAL(12,2) DEFAULT 0.00,
    sens ENUM('debit', 'credit'),
    montant DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    compte_code VARCHAR(20),
    reference_type VARCHAR(50),
    reference_id INT,
    tiers_id INT,
    FOREIGN KEY (ecriture_id) REFERENCES ecritures_comptables(id) ON DELETE CASCADE,
    FOREIGN KEY (compte_id) REFERENCES plan_comptable(id)
);

-- =============================================
-- TABLES AUDIT ET TRAÇABILITÉ
-- =============================================

-- Logs d'audit (TRÈS IMPORTANT)
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Événements système
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    event_name VARCHAR(200) NOT NULL,
    description TEXT,
    data JSON,
    utilisateur_id INT,
    date_event TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- =============================================
-- INDEX ET CONTRAINTES
-- =============================================

-- Index pour optimisation
CREATE INDEX idx_produits_nom ON produits(nom);
CREATE INDEX idx_produits_cip ON produits(code_cip);
CREATE INDEX idx_clients_nom ON clients(nom);
CREATE INDEX idx_ventes_date ON ventes(date_vente);
CREATE INDEX idx_ventes_client ON ventes(client_id);
CREATE INDEX idx_mouvements_stock_produit ON mouvements_stock(produit_id);
CREATE INDEX idx_mouvements_stock_date ON mouvements_stock(date_mouvement);
CREATE INDEX idx_lots_peremption ON lots(date_peremption);
CREATE INDEX idx_audit_utilisateur ON audit_logs(utilisateur_id);
CREATE INDEX idx_audit_date ON audit_logs(date_action);
CREATE INDEX idx_product_price_history_produit ON product_price_history(produit_id);
CREATE INDEX idx_product_price_history_changed_at ON product_price_history(changed_at);
CREATE INDEX idx_vente_ordonnances_vente ON vente_ordonnances(vente_id);
CREATE INDEX idx_vente_ordonnance_items_vente ON vente_ordonnance_items(vente_id);
CREATE INDEX idx_ecritures_comptable_journal ON ecritures_comptables(journal_id);
CREATE INDEX idx_ecritures_comptable_compte ON ecritures_comptables(compte_id);

-- Contraintes CHECK
ALTER TABLE produits ADD CONSTRAINT chk_prix_vente_superieur_achat 
    CHECK (prix_vente >= prix_achat);
ALTER TABLE lots ADD CONSTRAINT chk_quantite_positive 
    CHECK (quantite_restante >= 0);
ALTER TABLE stock ADD CONSTRAINT chk_stock_positive 
    CHECK (quantite_disponible >= 0);
ALTER TABLE mouvements_stock ADD CONSTRAINT chk_quantite_mouvement 
    CHECK (quantite > 0);
ALTER TABLE ventes ADD CONSTRAINT chk_montant_total_positive 
    CHECK (montant_total >= 0);
ALTER TABLE caisse_sessions ADD CONSTRAINT chk_montant_ouverture_positive 
    CHECK (montant_ouverture >= 0);

-- =============================================
-- VUES UTILES
-- =============================================

-- Vue stock complet
CREATE VIEW vue_stock_complet AS
SELECT 
    p.id, p.nom, p.code_cip, p.prix_vente,
    s.quantite_disponible, s.quantite_theorique, s.valeur_stock,
    c.nom as categorie,
    f.nom as fournisseur,
    CASE 
        WHEN s.quantite_disponible <= p.stock_securite THEN 'CRITIQUE'
        WHEN s.quantite_disponible <= p.stock_alerte THEN 'ALERTE'
        ELSE 'NORMAL'
    END as niveau_stock
FROM produits p
LEFT JOIN stock s ON p.id = s.produit_id
LEFT JOIN categories c ON p.categorie_id = c.id
LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
WHERE p.deleted_at IS NULL AND p.is_actif = TRUE;

-- Vue ventes du jour
CREATE VIEW vue_ventes_jour AS
SELECT 
    v.id, v.numero_facture, v.date_vente, v.montant_net,
    c.nom as client_nom,
    u.username as vendeur,
    CASE 
        WHEN v.statut_vente = 'PAYEE' THEN 'Payée'
        WHEN v.statut_vente = 'PARTIELLEMENT_PAYEE' THEN 'Partiellement payée'
        ELSE 'En cours'
    END as statut
FROM ventes v
LEFT JOIN clients c ON v.client_id = c.id
LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
WHERE DATE(v.date_vente) = CURDATE() AND v.deleted_at IS NULL;

-- Vue produits en péremption
CREATE VIEW vue_produits_peremption AS
SELECT 
    p.id, p.nom, p.code_cip,
    l.numero_lot, l.date_peremption,
    l.quantite_restante,
    DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
    CASE 
        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'URGENT'
        WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 180 THEN 'ALERTE'
        ELSE 'NORMAL'
    END as niveau_peremption
FROM produits p
JOIN lots l ON p.id = l.produit_id
WHERE l.is_actif = TRUE AND l.quantite_restante > 0
ORDER BY l.date_peremption ASC;
