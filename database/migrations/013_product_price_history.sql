-- Historique des modifications de prix produit

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
    INDEX idx_product_price_history_produit (produit_id),
    INDEX idx_product_price_history_changed_at (changed_at),
    INDEX idx_product_price_history_user (utilisateur_id)
);
