-- =============================================
-- ROLE METIER : CHARGE DE COMMANDE
-- =============================================

INSERT IGNORE INTO roles (nom, description)
VALUES ('charge_commande', 'Charge de commande - stock, receptions et commandes fournisseurs');

INSERT IGNORE INTO permissions (nom, description, module) VALUES
('view_stock', 'Voir le stock', 'stock'),
('add_stock', 'Ajouter du stock', 'stock'),
('receive_products', 'Receptionner les produits fournisseurs', 'commandes'),
('create_supplier_orders', 'Creer des commandes fournisseurs', 'commandes'),
('view_stock_movements', 'Voir les mouvements de stock', 'stock');

DELETE rp FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE LOWER(r.nom) IN ('commande', 'charge_commande', 'charge de commande')
AND p.nom NOT IN (
    'view_stock',
    'add_stock',
    'receive_products',
    'create_supplier_orders',
    'view_stock_movements',
    'stock.view',
    'stock.create',
    'commande.view',
    'commande.create'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'view_stock',
    'add_stock',
    'receive_products',
    'create_supplier_orders',
    'view_stock_movements'
)
WHERE LOWER(r.nom) IN ('commande', 'charge_commande', 'charge de commande');

CREATE TABLE IF NOT EXISTS supplier_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) NOT NULL UNIQUE,
    fournisseur_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    date_commande DATE NOT NULL,
    date_livraison_prevue DATE NULL,
    statut ENUM('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE', 'RECEPTION_COMPLETE', 'ANNULEE') NOT NULL DEFAULT 'BROUILLON',
    montant_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    observations TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_supplier_orders_fournisseur (fournisseur_id),
    INDEX idx_supplier_orders_statut (statut),
    INDEX idx_supplier_orders_date (date_commande)
);

CREATE TABLE IF NOT EXISTS supplier_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_order_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite_commandee INT NOT NULL,
    quantite_recue INT NOT NULL DEFAULT 0,
    prix_achat DECIMAL(12,2) NOT NULL,
    montant_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_order_id) REFERENCES supplier_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    UNIQUE KEY unique_supplier_order_product (supplier_order_id, produit_id),
    INDEX idx_supplier_order_items_produit (produit_id)
);

CREATE TABLE IF NOT EXISTS receptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_order_id INT NULL,
    fournisseur_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    numero_reception VARCHAR(50) NOT NULL UNIQUE,
    date_reception DATE NOT NULL,
    numero_facture VARCHAR(100) NULL,
    statut ENUM('EN_ATTENTE', 'PARTIELLEMENT_RECU', 'RECU_COMPLET') NOT NULL DEFAULT 'EN_ATTENTE',
    ecart_detecte BOOLEAN NOT NULL DEFAULT FALSE,
    observations TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_order_id) REFERENCES supplier_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_receptions_order (supplier_order_id),
    INDEX idx_receptions_fournisseur (fournisseur_id),
    INDEX idx_receptions_date (date_reception)
);

CREATE TABLE IF NOT EXISTS reception_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reception_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite_attendue INT NOT NULL DEFAULT 0,
    quantite_recue INT NOT NULL,
    prix_achat DECIMAL(12,2) NOT NULL,
    ecart INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reception_id) REFERENCES receptions(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    INDEX idx_reception_items_produit (produit_id)
);

CREATE TABLE IF NOT EXISTS stock_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    fournisseur_id INT NULL,
    utilisateur_id INT NOT NULL,
    reception_id INT NULL,
    quantite INT NOT NULL,
    prix_achat DECIMAL(12,2) NOT NULL,
    date_reception DATE NOT NULL,
    numero_facture VARCHAR(100) NULL,
    observations TEXT NULL,
    stock_avant INT NOT NULL,
    stock_apres INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (reception_id) REFERENCES receptions(id) ON DELETE SET NULL,
    INDEX idx_stock_entries_produit (produit_id),
    INDEX idx_stock_entries_date (date_reception)
);

CREATE INDEX IF NOT EXISTS idx_mouvements_stock_type_date ON mouvements_stock(type_mouvement, date_mouvement);
CREATE INDEX IF NOT EXISTS idx_mouvements_stock_user_date ON mouvements_stock(utilisateur_id, date_mouvement);
