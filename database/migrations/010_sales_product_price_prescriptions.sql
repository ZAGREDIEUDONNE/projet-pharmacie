-- =============================================
-- VENTES ADMIN, PRODUITS, PRIX ET ORDONNANCES
-- =============================================

INSERT IGNORE INTO permissions (nom, description, module) VALUES
('vente.view', 'Consulter le module de vente', 'vente'),
('vente.create', 'Creer et encaisser une vente', 'vente'),
('vente.cancel', 'Annuler une vente', 'vente'),
('vente.print', 'Imprimer un ticket de vente', 'vente'),
('vente.history', 'Consulter l historique des ventes', 'vente'),
('apply_discount', 'Appliquer une remise', 'vente'),
('cancel_ticket', 'Annuler un ticket de vente', 'vente'),
('stock.create_product', 'Creer un nouveau produit', 'stock'),
('product.price.update', 'Modifier les prix produit', 'stock'),
('product.price.history', 'Consulter l historique des prix', 'stock');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN (
    'vente.view',
    'vente.create',
    'vente.cancel',
    'vente.print',
    'vente.history',
    'apply_discount',
    'cancel_ticket',
    'stock.create_product',
    'product.price.update',
    'product.price.history'
)
WHERE LOWER(r.nom) IN ('admin', 'administrateur');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN ('stock.create_product')
WHERE LOWER(r.nom) IN ('assistant', 'charge_commande', 'charge de commande', 'commande');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.nom IN ('product.price.update')
WHERE LOWER(r.nom) IN ('charge_commande', 'charge de commande', 'commande');

CREATE TABLE IF NOT EXISTS product_price_history (
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
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_product_price_history_produit (produit_id),
    INDEX idx_product_price_history_changed_at (changed_at),
    INDEX idx_product_price_history_user (utilisateur_id)
);

CREATE TABLE IF NOT EXISTS vente_ordonnances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    numero_ordonnance VARCHAR(100) NOT NULL,
    date_ordonnance DATE NOT NULL,
    nom_medecin VARCHAR(200) NOT NULL,
    structure_sanitaire VARCHAR(200) NOT NULL,
    nom_patient VARCHAR(200) NOT NULL,
    telephone_patient VARCHAR(30) NOT NULL,
    observation TEXT NULL,
    utilisateur_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_vente_ordonnances_vente (vente_id),
    INDEX idx_vente_ordonnances_numero (numero_ordonnance),
    INDEX idx_vente_ordonnances_patient (nom_patient)
);

CREATE TABLE IF NOT EXISTS vente_ordonnance_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordonnance_id INT NOT NULL,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ordonnance_id) REFERENCES vente_ordonnances(id) ON DELETE CASCADE,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    UNIQUE KEY unique_ordonnance_produit (ordonnance_id, produit_id),
    INDEX idx_vente_ordonnance_items_vente (vente_id),
    INDEX idx_vente_ordonnance_items_produit (produit_id)
);
