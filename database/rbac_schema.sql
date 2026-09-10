-- =============================================
-- SYSTÈME RBAC (ROLE BASED ACCESS CONTROL)
-- =============================================

-- Table des permissions (fine-grained)
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    statut ENUM('ACTIF', 'INACTIF') DEFAULT 'ACTIF',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_module (module),
    INDEX idx_action (action),
    INDEX idx_code (code)
);

-- Table des rôles (mise à jour)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    level INT NOT NULL DEFAULT 1,
    statut ENUM('ACTIF', 'INACTIF') DEFAULT 'ACTIF',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_level (level)
);

-- Table de liaison entre rôles et permissions
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    statut ENUM('ACTIF', 'INACTIF') DEFAULT 'ACTIF',
    date_attribution DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id, statut),
    INDEX idx_role_id (role_id),
    INDEX idx_permission_id (permission_id)
);

-- Table des permissions utilisateur (pour les permissions spécifiques)
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    statut ENUM('ACTIF', 'SUPPRIME') DEFAULT 'ACTIF',
    date_attribution DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_suppression DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (user_id, permission_id, statut),
    INDEX idx_user_id (user_id),
    INDEX idx_permission_id (permission_id)
);

-- Table d'audit des accès refusés
CREATE TABLE IF NOT EXISTS access_denied_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    permission_code VARCHAR(100) NOT NULL,
    route VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    date_refus DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_permission_code (permission_code),
    INDEX idx_date_refus (date_refus)
);

-- Insertion des rôles par défaut
INSERT IGNORE INTO roles (code, name, description, level) VALUES
('ADMIN', 'Administrateur', 'Accès complet au système', 1),
('VENDEUR', 'Vendeur', 'Gestion des ventes et caisse', 2),
('ASSISTANT', 'Assistant', 'Support ventes et opérations', 3),
('CHARGE_COMMANDE', 'Chargé de Commande', 'Gestion des commandes fournisseurs', 4);

-- Insertion des permissions fine-grained
INSERT IGNORE INTO permissions (code, name, description, module, action) VALUES
-- Module Ventes
('vente.view', 'Voir les ventes', 'Permet de voir la liste des ventes', 'vente', 'view'),
('vente.create', 'Créer une vente', 'Permet de créer une nouvelle vente', 'vente', 'create'),
('vente.update', 'Modifier une vente', 'Permet de modifier une vente existante', 'vente', 'update'),
('vente.cancel', 'Annuler une vente', 'Permet d\'annuler une vente', 'vente', 'cancel'),
('vente.delete', 'Supprimer une vente', 'Permet de supprimer définitivement une vente', 'vente', 'delete'),
('vente.report', 'Rapports ventes', 'Permet de voir les rapports de ventes', 'vente', 'report'),

-- Module Caisse
('caisse.open', 'Ouvrir la caisse', 'Permet d\'ouvrir une session de caisse', 'caisse', 'open'),
('caisse.close', 'Fermer la caisse', 'Permet de fermer une session de caisse', 'caisse', 'close'),
('caisse.view', 'Voir la caisse', 'Permet de voir l\'état de la caisse', 'caisse', 'view'),
('caisse.mouvement', 'Gérer les mouvements', 'Permet de gérer les mouvements de caisse', 'caisse', 'mouvement'),
('caisse.report', 'Rapports caisse', 'Permet de voir les rapports de caisse', 'caisse', 'report'),

-- Module Stock
('stock.view', 'Voir le stock', 'Permet de voir l\'état du stock', 'stock', 'view'),
('stock.update', 'Modifier le stock', 'Permet de modifier les quantités en stock', 'stock', 'update'),
('stock.create', 'Ajouter au stock', 'Permet d\'ajouter des produits au stock', 'stock', 'create'),
('stock.delete', 'Supprimer du stock', 'Permet de supprimer des produits du stock', 'stock', 'delete'),
('stock.inventaire', 'Faire inventaire', 'Permet de faire un inventaire', 'stock', 'inventaire'),
('stock.report', 'Rapports stock', 'Permet de voir les rapports de stock', 'stock', 'report'),

-- Module Commandes
('commande.view', 'Voir les commandes', 'Permet de voir la liste des commandes', 'commande', 'view'),
('commande.create', 'Créer une commande', 'Permet de créer une nouvelle commande', 'commande', 'create'),
('commande.update', 'Modifier une commande', 'Permet de modifier une commande existante', 'commande', 'update'),
('commande.cancel', 'Annuler une commande', 'Permet d\'annuler une commande', 'commande', 'cancel'),
('commande.validate', 'Valider une commande', 'Permet de valider une commande', 'commande', 'validate'),

-- Module Remises
('remise.apply', 'Appliquer une remise', 'Permet d\'appliquer des remises', 'remise', 'apply'),
('remise.view', 'Voir les remises', 'Permet de voir les remises appliquées', 'remise', 'view'),
('remise.manage', 'Gérer les remises', 'Permet de gérer les types de remises', 'remise', 'manage'),

-- Module Clients
('client.view', 'Voir les clients', 'Permet de voir la liste des clients', 'client', 'view'),
('client.create', 'Créer un client', 'Permet de créer un nouveau client', 'client', 'create'),
('client.update', 'Modifier un client', 'Permet de modifier un client existant', 'client', 'update'),
('client.delete', 'Supprimer un client', 'Permet de supprimer un client', 'client', 'delete'),

-- Module Utilisateurs
('user.view', 'Voir les utilisateurs', 'Permet de voir la liste des utilisateurs', 'user', 'view'),
('user.create', 'Créer un utilisateur', 'Permet de créer un nouvel utilisateur', 'user', 'create'),
('user.update', 'Modifier un utilisateur', 'Permet de modifier un utilisateur existant', 'user', 'update'),
('user.delete', 'Supprimer un utilisateur', 'Permet de supprimer un utilisateur', 'user', 'delete'),
('user.manage', 'Gérer les utilisateurs', 'Permet de gérer les comptes utilisateurs', 'user', 'manage'),

-- Module Rôles et Permissions
('role.view', 'Voir les rôles', 'Permet de voir la liste des rôles', 'role', 'view'),
('role.create', 'Créer un rôle', 'Permet de créer un nouveau rôle', 'role', 'create'),
('role.update', 'Modifier un rôle', 'Permet de modifier un rôle existant', 'role', 'update'),
('role.delete', 'Supprimer un rôle', 'Permet de supprimer un rôle', 'role', 'delete'),
('role.assign', 'Assigner des permissions', 'Permet d\'assigner des permissions aux rôles', 'role', 'assign'),

-- Module Audit
('audit.view', 'Voir les audits', 'Permet de voir les logs d\'audit', 'audit', 'view'),
('audit.export', 'Exporter les audits', 'Permet d\'exporter les logs d\'audit', 'audit', 'export'),

-- Module Système
('system.config', 'Configurer le système', 'Permet de configurer les paramètres système', 'system', 'config'),
('system.backup', 'Faire des sauvegardes', 'Permet de faire des sauvegardes', 'system', 'backup'),
('system.restore', 'Restaurer les données', 'Permet de restaurer les données', 'system', 'restore'),
('system.logs', 'Voir les logs système', 'Permet de voir les logs système', 'system', 'logs'),

-- Module Session
('session.change', 'Changer de session', 'Permet de changer de session de caisse', 'session', 'change'),
('session.view', 'Voir les sessions', 'Permet de voir les sessions actives', 'session', 'view'),

-- Module Date
('date.change', 'Changer la date', 'Permet de modifier la date système', 'date', 'change'),

-- Module Facturation
('facture.create', 'Créer une facture', 'Permet de créer une facture', 'facture', 'create'),
('facture.view', 'Voir les factures', 'Permet de voir les factures', 'facture', 'view'),
('facture.print', 'Imprimer une facture', 'Permet d\'imprimer une facture', 'facture', 'print'),

-- Module Statistiques
('statistique.view', 'Voir les statistiques', 'Permet de voir les statistiques', 'statistique', 'view'),
('statistique.export', 'Exporter les statistiques', 'Permet d\'exporter les statistiques', 'statistique', 'export');

-- Attribution des permissions par défaut aux rôles
-- ADMIN : Toutes les permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.code = 'ADMIN' AND p.statut = 'ACTIF';

-- VENDEUR : Ventes + Caisse + Clients + Session + Date
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.code = 'VENDEUR' 
AND p.statut = 'ACTIF'
AND p.module IN ('vente', 'caisse', 'client', 'session', 'date', 'facture');

-- ASSISTANT : Ventes + Caisse + Stock (lecture) + Commandes + Session + Date + Statistiques
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.code = 'ASSISTANT' 
AND p.statut = 'ACTIF'
AND (
    p.module IN ('vente', 'caisse', 'client', 'session', 'date', 'facture', 'commande', 'statistique') 
    OR (p.module = 'stock' AND p.action IN ('view', 'report'))
);

-- CHARGE_COMMANDE : Commandes + Remises + Ventes + Session + Date
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.code = 'CHARGE_COMMANDE' 
AND p.statut = 'ACTIF'
AND p.module IN ('commande', 'remise', 'vente', 'session', 'date');
