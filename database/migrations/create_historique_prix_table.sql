-- Migration pour créer la table historique_prix
-- Cette table permet de tracer toutes les modifications de prix des produits

CREATE TABLE IF NOT EXISTS `historique_prix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produit_id` int(11) NOT NULL,
  `produit_nom` varchar(200) NOT NULL,
  `code_cip` varchar(13) DEFAULT NULL,
  `ancien_prix_achat` decimal(10,2) NOT NULL,
  `nouveau_prix_achat` decimal(10,2) DEFAULT NULL,
  `ancien_prix_vente` decimal(10,2) NOT NULL,
  `nouveau_prix_vente` decimal(10,2) NOT NULL,
  `ancien_prix_vente_assure` decimal(10,2) DEFAULT NULL,
  `nouveau_prix_vente_assure` decimal(10,2) DEFAULT NULL,
  `date_application` date NOT NULL,
  `motif` varchar(100) NOT NULL,
  `observation` text DEFAULT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `utilisateur_nom` varchar(100) NOT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_application` (`date_application`),
  KEY `idx_utilisateur_id` (`utilisateur_id`),
  KEY `idx_motif` (`motif`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_historique_prix_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historique_prix_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Créer la permission produit.modifier_prix
INSERT INTO `permissions` (`nom`, `code`, `description`, `module`, `action`, `statut`, `date_creation`, `created_at`)
VALUES ('Modifier prix produits', 'produit.modifier_prix', 'Permet de modifier les prix d\'achat et de vente des produits', 'produit', 'modifier_prix', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `description` = 'Permet de modifier les prix d\'achat et de vente des produits';

-- Attribuer la permission aux rôles Administrateur et Chargé de commande
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `date_attribution`)
SELECT r.id, p.id, NOW()
FROM roles r
CROSS JOIN permissions p
WHERE r.nom IN ('Administrateur', 'Chargé de commande')
AND p.code = 'produit.modifier_prix'
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);
