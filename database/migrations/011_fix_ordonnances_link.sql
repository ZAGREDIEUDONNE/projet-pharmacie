-- Alignement schema ordonnances : table ordonnances + liaison vente_ordonnances
-- La table vente_ordonnance_items reference ordonnances(id), pas vente_ordonnances(id)

CREATE TABLE IF NOT EXISTS ordonnances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_ordonnance VARCHAR(100) NOT NULL,
    date_ordonnance DATE NOT NULL,
    nom_medecin VARCHAR(255) NULL,
    structure_sanitaire VARCHAR(255) NULL,
    nom_patient VARCHAR(255) NULL,
    telephone_patient VARCHAR(50) NULL,
    observation TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ordonnances_numero (numero_ordonnance),
    INDEX idx_ordonnances_patient (nom_patient)
);

CREATE TABLE IF NOT EXISTS vente_ordonnances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    ordonnance_id INT NOT NULL,
    INDEX idx_vente_ordonnances_vente (vente_id),
    INDEX idx_vente_ordonnances_ordonnance (ordonnance_id),
    UNIQUE KEY unique_vente_ordonnance (vente_id, ordonnance_id)
);

CREATE TABLE IF NOT EXISTS vente_ordonnance_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordonnance_id INT NOT NULL,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ordonnance_produit (ordonnance_id, produit_id),
    INDEX idx_vente_ordonnance_items_vente (vente_id),
    INDEX idx_vente_ordonnance_items_produit (produit_id)
);
