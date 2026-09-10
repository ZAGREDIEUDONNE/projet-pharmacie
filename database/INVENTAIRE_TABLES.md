# Inventaire de la base de données medecin

Généré automatiquement à partir des scripts SQL et migrations présents dans le dépôt.

Nombre de tables répertoriées : 57

## `access_denied_audit` (7 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `user_id` | INT | YES | MUL | — | — |
| `permission_code` | VARCHAR(100) | NO | — | NULL | — |
| `route` | VARCHAR(255) | NO | — | NULL | — |
| `ip_address` | VARCHAR(45) | YES | — | — | — |
| `user_agent` | TEXT | YES | — | — | — |
| `date_refus` | DATETIME | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `assistant_auth_codes` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `code_type` | ENUM('CAISSE', 'AVANCE') | NO | — | NULL | — |
| `code_secret` | VARCHAR(10) | NO | — | NULL | — |
| `is_active` | BOOLEAN | YES | — | TRUE | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `expires_at` | TIMESTAMP | YES | — | NULL | — |
| `last_used` | TIMESTAMP | YES | — | NULL | — |
| `usage_count` | INT | YES | — | 0 | — |
| `max_usage` | INT | YES | — | 100 | — |

## `audit_context` (6 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `audit_log_id` | INT | NO | MUL | NULL | — |
| `machine` | VARCHAR(120) | YES | — | NULL | — |
| `request_uri` | VARCHAR(255) | YES | — | NULL | — |
| `method` | VARCHAR(10) | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `audit_logs` (11 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `utilisateur_id` | INT | YES | MUL | — | — |
| `action` | VARCHAR(100) | NO | — | NULL | — |
| `table_name` | VARCHAR(50) | NO | — | NULL | — |
| `record_id` | INT | YES | MUL | — | — |
| `old_values` | JSON | YES | — | — | — |
| `new_values` | JSON | YES | — | — | — |
| `ip_address` | VARCHAR(45) | YES | — | — | — |
| `user_agent` | TEXT | YES | — | — | — |
| `date_action` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `bons` (28 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `type_bon` | ENUM('LIVRAISON', 'RETOUR', 'AVOIR', 'REMISE', 'GARANTIE') | NO | — | NULL | — |
| `numero_bon` | VARCHAR(50) | NO | UNI | NULL | — |
| `reference_id` | INT | YES | MUL | NULL | — |
| `reference_type` | ENUM('COMMANDE', 'VENTE', 'INVENTAIRE') | YES | — | NULL | — |
| `fournisseur_id` | INT | YES | MUL | NULL | — |
| `client_id` | INT | YES | MUL | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_emission` | DATETIME | NO | — | NULL | — |
| `date_echeance` | DATE | YES | — | NULL | — |
| `date_livraison_prevue` | DATE | YES | — | NULL | — |
| `montant_total` | DECIMAL(12,2) | YES | — | 0 | — |
| `statut_bon` | ENUM('EMIS', 'VALIDE', 'UTILISE', 'ANNULE', 'EXPIRE') | YES | — | 'EMIS' | — |
| `conditions_paiement` | VARCHAR(200) | YES | — | NULL | — |
| `notes` | TEXT | YES | — | NULL | — |
| `created_by` | INT | NO | — | NULL | — |
| `date_traitement` | DATETIME | YES | — | NULL | — |
| `utilisateur_traitement_id` | INT | YES | MUL | NULL | — |
| `notes_traitement` | TEXT | YES | — | NULL | — |
| `date_utilisation` | DATETIME | YES | — | NULL | — |
| `utilisateur_utilisation_id` | INT | YES | MUL | NULL | — |
| `vente_id` | INT | YES | MUL | NULL | — |
| `date_annulation` | DATETIME | YES | — | NULL | — |
| `utilisateur_annulation_id` | INT | YES | MUL | NULL | — |
| `motif_annulation` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `bons_articles` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `bon_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `lot_id` | INT | YES | MUL | NULL | — |
| `quantite` | INT | NO | — | NULL | — |
| `prix_unitaire` | DECIMAL(10,2) | NO | — | NULL | — |
| `montant_total` | DECIMAL(12,2) | NO | — | NULL | — |
| `remise` | DECIMAL(5,2) | YES | — | 0 | — |
| `notes` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `caisse_sessions` (14 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_session` | VARCHAR(20) | NO | UNI | NULL | — |
| `caissier_id` | INT | NO | MUL | NULL | — |
| `date_ouverture` | DATETIME | NO | — | NULL | — |
| `date_fermeture` | DATETIME | YES | — | — | — |
| `montant_ouverture` | DECIMAL(10,2) | YES | — | 0 | — |
| `montant_fermeture` | DECIMAL(10,2) | YES | — | 0 | — |
| `montant_ventes` | DECIMAL(10,2) | YES | — | 0 | — |
| `montant_theorique` | DECIMAL(10,2) | YES | — | 0 | — |
| `ecart` | DECIMAL(10,2) | YES | — | 0 | — |
| `statut_session` | ENUM('OUVERTE', 'FERMEE', 'CONTROLEE') | YES | — | 'OUVERTE' | — |
| `notes_controle` | TEXT | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `caisse_sessions_history` (14 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `session_number` | ENUM('1', '2', '3') | NO | — | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_ouverture` | DATETIME | NO | — | NULL | — |
| `date_fermeture` | DATETIME | NO | — | NULL | — |
| `montant_ouverture` | DECIMAL(12,2) | YES | — | 0 | — |
| `montant_fermeture` | DECIMAL(12,2) | YES | — | 0 | — |
| `ventes_count` | INT | YES | — | 0 | — |
| `total_ventes` | DECIMAL(12,2) | YES | — | 0 | — |
| `ecarts` | DECIMAL(12,2) | YES | — | 0 | — |
| `statut_fermeture` | ENUM('NORMALE', 'FORCEE', 'ANOMALIE') | YES | — | 'NORMALE' | — |
| `utilisateur_fermeture_id` | INT | YES | MUL | NULL | — |
| `notes_fermeture` | TEXT | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `categories` (6 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `nom` | VARCHAR(100) | NO | UNI | NULL | — |
| `description` | TEXT | YES | — | — | — |
| `parent_id` | INT | YES | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `client_reglements` (11 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `client_id` | INT | NO | MUL | NULL | — |
| `vente_id` | INT | YES | MUL | NULL | — |
| `type_mouvement` | ENUM('DEBIT','CREDIT','RISTOURNE','ESCOMPTE') | NO | — | NULL | — |
| `montant` | DECIMAL(12,2) | NO | — | NULL | — |
| `mode_paiement` | VARCHAR(40) | YES | — | NULL | — |
| `reference` | VARCHAR(120) | YES | — | NULL | — |
| `notes` | TEXT | YES | — | NULL | — |
| `utilisateur_id` | INT | YES | MUL | NULL | — |
| `date_mouvement` | DATETIME | NO | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `clients` (16 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `code` | VARCHAR(20) | NO | UNI | NULL | — |
| `nom` | VARCHAR(100) | NO | — | NULL | — |
| `prenom` | VARCHAR(100) | YES | — | — | — |
| `telephone` | VARCHAR(20) | YES | — | — | — |
| `email` | VARCHAR(100) | YES | — | — | — |
| `adresse` | TEXT | YES | — | — | — |
| `type_client` | ENUM('ORDINAIRE', 'ASSURE', 'ENTREPRISE') | YES | — | 'ORDINAIRE' | — |
| `numero_assurance` | VARCHAR(50) | YES | — | — | — |
| `compagnie_assurance` | VARCHAR(100) | YES | — | — | — |
| `plafond_credit` | DECIMAL(10,2) | YES | — | 0 | — |
| `solde_credit` | DECIMAL(10,2) | YES | — | 0 | — |
| `is_actif` | BOOLEAN | YES | — | TRUE | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `commande_items` (8 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `commande_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `quantite_commandee` | INT | NO | — | NULL | — |
| `quantite_livree` | INT | YES | — | 0 | — |
| `prix_unitaire` | DECIMAL(10,2) | NO | — | NULL | — |
| `montant_total` | DECIMAL(10,2) | NO | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `commandes` (14 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_commande` | VARCHAR(50) | NO | UNI | NULL | — |
| `fournisseur_id` | INT | NO | MUL | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_commande` | DATE | NO | — | NULL | — |
| `date_livraison_prevue` | DATE | YES | — | — | — |
| `date_livraison_reelle` | DATE | YES | — | — | — |
| `montant_total` | DECIMAL(10,2) | NO | — | NULL | — |
| `statut_commande` | ENUM('BROUILLON', 'VALIDEE', 'PARTIELLEMENT_LIVREE', 'LIVREE', 'ANNULEE') | YES | — | 'BROUILLON' | — |
| `conditions_paiement` | VARCHAR(200) | YES | — | — | — |
| `notes` | TEXT | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `ecritures_comptables` (7 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `journal_id` | INT | NO | MUL | NULL | — |
| `compte_id` | INT | NO | MUL | NULL | — |
| `debit` | DECIMAL(12,2) | YES | — | 0 | — |
| `credit` | DECIMAL(12,2) | YES | — | 0 | — |
| `libelle` | VARCHAR(200) | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `events` (8 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `event_type` | VARCHAR(100) | NO | — | NULL | — |
| `event_name` | VARCHAR(200) | NO | — | NULL | — |
| `description` | TEXT | YES | — | — | — |
| `data` | JSON | YES | — | — | — |
| `utilisateur_id` | INT | YES | MUL | — | — |
| `date_event` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `exercices_comptables` (14 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `exercice` | INT | NO | UNI | NULL | — |
| `date_debut` | DATE | NO | — | NULL | — |
| `date_fin` | DATE | NO | — | NULL | — |
| `statut` | ENUM('OUVERT', 'CLOTURE') | YES | — | 'OUVERT' | — |
| `solde_precedent` | DECIMAL(15,2) | YES | — | 0 | — |
| `total_debit` | DECIMAL(15,2) | YES | — | 0 | — |
| `total_credit` | DECIMAL(15,2) | YES | — | 0 | — |
| `solde_final` | DECIMAL(15,2) | YES | — | 0 | — |
| `date_cloture` | DATETIME | YES | — | NULL | — |
| `utilisateur_cloture_id` | INT | YES | MUL | NULL | — |
| `notes_cloture` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `facture_articles` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `facture_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `quantite` | INT | NO | — | NULL | — |
| `prix_unitaire_ht` | DECIMAL(10,2) | NO | — | NULL | — |
| `montant_ht` | DECIMAL(12,2) | NO | — | NULL | — |
| `tva_taux` | DECIMAL(5,2) | YES | — | 0 | — |
| `montant_tva` | DECIMAL(12,2) | YES | — | 0 | — |
| `montant_ttc` | DECIMAL(12,2) | NO | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `factures` (19 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_facture` | VARCHAR(50) | NO | UNI | NULL | — |
| `vente_id` | INT | YES | MUL | NULL | — |
| `client_id` | INT | YES | MUL | NULL | — |
| `type_facture` | ENUM('VENTE', 'AVOIR', 'NOTE_CREDIT') | YES | — | 'VENTE' | — |
| `date_emission` | DATETIME | NO | — | NULL | — |
| `date_echeance` | DATE | YES | — | NULL | — |
| `montant_ht` | DECIMAL(12,2) | YES | — | 0 | — |
| `montant_tva` | DECIMAL(12,2) | YES | — | 0 | — |
| `montant_ttc` | DECIMAL(12,2) | NO | — | NULL | — |
| `montant_paye` | DECIMAL(12,2) | YES | — | 0 | — |
| `statut_paiement` | ENUM('IMPAYE', 'PARTIELLEMENT_PAYE', 'PAYE', 'ANNULE') | YES | — | 'IMPAYE' | — |
| `mode_paiement` | ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE_MONEY', 'VIREMENT') | YES | — | 'ESPECE' | — |
| `conditions_paiement` | VARCHAR(200) | YES | — | NULL | — |
| `notes` | TEXT | YES | — | NULL | — |
| `created_by` | INT | NO | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `fournisseur_reglements` (11 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `fournisseur_id` | INT | NO | MUL | NULL | — |
| `reception_id` | INT | YES | MUL | NULL | — |
| `type_mouvement` | ENUM('DEBIT','CREDIT','RISTOURNE','ESCOMPTE') | NO | — | NULL | — |
| `montant` | DECIMAL(12,2) | NO | — | NULL | — |
| `mode_paiement` | VARCHAR(40) | YES | — | NULL | — |
| `reference` | VARCHAR(120) | YES | — | NULL | — |
| `notes` | TEXT | YES | — | NULL | — |
| `utilisateur_id` | INT | YES | MUL | NULL | — |
| `date_mouvement` | DATETIME | NO | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `fournisseurs` (13 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `code` | VARCHAR(20) | NO | UNI | NULL | — |
| `nom` | VARCHAR(200) | NO | — | NULL | — |
| `adresse` | TEXT | YES | — | — | — |
| `telephone` | VARCHAR(20) | YES | — | — | — |
| `email` | VARCHAR(100) | YES | — | — | — |
| `registre_commerce` | VARCHAR(50) | YES | — | — | — |
| `compte_bancaire` | VARCHAR(50) | YES | — | — | — |
| `delai_livraison` | INT | YES | — | 7 | — |
| `is_actif` | BOOLEAN | YES | — | TRUE | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `inventaire_articles` (16 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `inventaire_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `lot_id` | INT | YES | MUL | NULL | — |
| `quantite_theorique` | INT | YES | — | 0 | — |
| `quantite_comptee` | INT | NO | — | NULL | — |
| `ecart` | INT | YES | — | 0 | — |
| `prix_unitaire` | DECIMAL(10,2) | YES | — | 0 | — |
| `valeur_totale` | DECIMAL(12,2) | YES | — | 0 | — |
| `statut_saisie` | ENUM('VALIDE', 'A_CORRIGER') | YES | — | 'VALIDE' | — |
| `notes` | TEXT | YES | — | NULL | — |
| `utilisateur_saisie_id` | INT | NO | MUL | NULL | — |
| `date_saisie` | DATETIME | NO | — | NULL | — |
| `date_modification` | DATETIME | YES | — | NULL | — |
| `utilisateur_modification_id` | INT | YES | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `inventaire_etat_stock` (7 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `inventaire_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `lot_id` | INT | YES | MUL | NULL | — |
| `quantite_stock` | INT | YES | — | 0 | — |
| `valeur_stock` | DECIMAL(12,2) | YES | — | 0 | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `inventaires` (19 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `reference` | VARCHAR(50) | NO | UNI | NULL | — |
| `type_inventaire` | ENUM('MANUEL', 'AUTOMATIQUE', 'PERIODIQUE') | YES | — | 'MANUEL' | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_debut` | DATETIME | NO | — | NULL | — |
| `date_fin` | DATETIME | YES | — | NULL | — |
| `date_fin_prevue` | DATETIME | YES | — | NULL | — |
| `statut` | ENUM('EN_COURS', 'CLOTURE', 'ANNULE') | YES | — | 'EN_COURS' | — |
| `notes` | TEXT | YES | — | NULL | — |
| `total_articles` | INT | YES | — | 0 | — |
| `total_valeur_theorique` | DECIMAL(15,2) | YES | — | 0 | — |
| `total_valeur_comptee` | DECIMAL(15,2) | YES | — | 0 | — |
| `total_ecart_valeur` | DECIMAL(15,2) | YES | — | 0 | — |
| `total_ecart_quantite` | INT | YES | — | 0 | — |
| `notes_cloture` | TEXT | YES | — | NULL | — |
| `utilisateur_cloture_id` | INT | YES | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `journal_comptable` (12 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_ecriture` | VARCHAR(50) | NO | UNI | NULL | — |
| `date_ecriture` | DATE | NO | — | NULL | — |
| `libelle` | VARCHAR(200) | NO | — | NULL | — |
| `reference_type` | ENUM('VENTE', 'ACHAT', 'CAISSE', 'BANQUE', 'OD') | NO | — | NULL | — |
| `reference_id` | INT | YES | MUL | — | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `is_validated` | BOOLEAN | YES | — | FALSE | — |
| `date_validation` | DATETIME | YES | — | — | — |
| `validated_by` | INT | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `lots` (13 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `produit_id` | INT | NO | MUL | NULL | — |
| `numero_lot` | VARCHAR(50) | NO | — | NULL | — |
| `date_fabrication` | DATE | YES | — | — | — |
| `date_peremption` | DATE | NO | — | NULL | — |
| `quantite_initiale` | INT | NO | — | NULL | — |
| `quantite_restante` | INT | NO | — | NULL | — |
| `prix_achat_unitaire` | DECIMAL(10,2) | NO | — | NULL | — |
| `fournisseur_id` | INT | YES | MUL | — | — |
| `commande_id` | INT | YES | MUL | — | — |
| `is_actif` | BOOLEAN | YES | — | TRUE | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `mouvements_caisse` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `caisse_session_id` | INT | NO | MUL | NULL | — |
| `type_mouvement` | ENUM('VENTE', 'REMBOURSEMENT', 'APPROVISIONNEMENT', 'RETRAIT', 'AJUSTEMENT') | NO | — | NULL | — |
| `montant` | DECIMAL(10,2) | NO | — | NULL | — |
| `moyen_paiement` | ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE_MONEY') | YES | — | 'ESPECE' | — |
| `reference` | VARCHAR(100) | YES | — | — | — |
| `description` | TEXT | YES | — | — | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_mouvement` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `mouvements_stock` (13 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `produit_id` | INT | NO | MUL | NULL | — |
| `lot_id` | INT | YES | MUL | — | — |
| `type_mouvement` | ENUM('ENTREE', 'SORTIE', 'TRANSFERT', 'AJUSTEMENT', 'PERTE') | NO | — | NULL | — |
| `quantite` | INT | NO | — | NULL | — |
| `quantite_avant` | INT | NO | — | NULL | — |
| `quantite_apres` | INT | NO | — | NULL | — |
| `motif` | VARCHAR(200) | YES | — | — | — |
| `reference_type` | ENUM('VENTE', 'COMMAND', 'INVENTAIRE', 'AJUSTEMENT', 'TRANSFERT') | NO | — | NULL | — |
| `reference_id` | INT | YES | MUL | — | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_mouvement` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `ordonnances` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_ordonnance` | VARCHAR(100) | NO | — | NULL | — |
| `date_ordonnance` | DATE | NO | — | NULL | — |
| `nom_medecin` | VARCHAR(255) | YES | — | NULL | — |
| `structure_sanitaire` | VARCHAR(255) | YES | — | NULL | — |
| `nom_patient` | VARCHAR(255) | YES | — | NULL | — |
| `telephone_patient` | VARCHAR(50) | YES | — | NULL | — |
| `observation` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `paiement_details` (19 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `vente_id` | INT | NO | MUL | NULL | — |
| `mode_paiement` | ENUM('ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON') | NO | — | NULL | — |
| `depot_nom_etablissement` | VARCHAR(200) | YES | — | NULL | — |
| `depot_adresse` | VARCHAR(300) | YES | — | NULL | — |
| `depot_telephone` | VARCHAR(30) | YES | — | NULL | — |
| `depot_numero_arrete` | VARCHAR(100) | YES | — | NULL | — |
| `mobile_operateur` | ENUM('ORANGE_MONEY', 'MOOV_MONEY', 'TELECEL_MONEY', 'AUTRE') | YES | — | NULL | — |
| `mobile_nom_titulaire` | VARCHAR(200) | YES | — | NULL | — |
| `mobile_telephone` | VARCHAR(30) | YES | — | NULL | — |
| `cheque_numero` | VARCHAR(50) | YES | — | NULL | — |
| `cheque_nom_banque` | VARCHAR(200) | YES | — | NULL | — |
| `bon_nom_beneficiaire` | VARCHAR(200) | YES | — | NULL | — |
| `bon_telephone` | VARCHAR(30) | YES | — | NULL | — |
| `bon_matricule` | VARCHAR(50) | YES | — | NULL | — |
| `bon_numero_bon` | VARCHAR(50) | YES | — | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `paiements_factures` (9 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `facture_id` | INT | NO | MUL | NULL | — |
| `montant_paiement` | DECIMAL(12,2) | NO | — | NULL | — |
| `date_paiement` | DATETIME | NO | — | NULL | — |
| `mode_paiement` | ENUM('ESPECE', 'CARTE', 'CHEQUE', 'MOBILE_MONEY', 'VIREMENT') | NO | — | NULL | — |
| `reference_paiement` | VARCHAR(100) | YES | — | NULL | — |
| `notes` | TEXT | YES | — | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `permissions` (5 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `nom` | VARCHAR(100) | NO | UNI | NULL | — |
| `description` | TEXT | YES | — | — | — |
| `module` | VARCHAR(50) | NO | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `plan_comptable` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_compte` | VARCHAR(20) | NO | UNI | NULL | — |
| `nom_compte` | VARCHAR(200) | NO | — | NULL | — |
| `classe` | INT | NO | — | NULL | — |
| `type_compte` | ENUM('ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT') | NO | — | NULL | — |
| `solde_initial` | DECIMAL(12,2) | YES | — | 0 | — |
| `solde_actuel` | DECIMAL(12,2) | YES | — | 0 | — |
| `is_actif` | BOOLEAN | YES | — | TRUE | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `preparation_commandes` (9 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `commande_id` | INT | NO | MUL | NULL | — |
| `preparateur_id` | INT | YES | MUL | NULL | — |
| `statut` | ENUM('A_PREPARER', 'EN_PREPARATION', 'CONTROLEE', 'PRETE', 'ANNULEE') | YES | — | 'A_PREPARER' | — |
| `commentaire` | TEXT | YES | — | NULL | — |
| `started_at` | DATETIME | YES | — | NULL | — |
| `completed_at` | DATETIME | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `product_price_history` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `produit_id` | INT | NO | MUL | NULL | — |
| `old_prix_achat` | DECIMAL(12,2) | NO | — | NULL | — |
| `new_prix_achat` | DECIMAL(12,2) | NO | — | NULL | — |
| `old_prix_vente` | DECIMAL(12,2) | NO | — | NULL | — |
| `new_prix_vente` | DECIMAL(12,2) | NO | — | NULL | — |
| `motif` | TEXT | NO | — | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `changed_at` | DATETIME | NO | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `produits` (19 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `code_cip` | VARCHAR(13) | YES | UNI | — | — |
| `code_barre` | VARCHAR(50) | YES | UNI | — | — |
| `nom` | VARCHAR(200) | NO | — | NULL | — |
| `description` | TEXT | YES | — | — | — |
| `categorie_id` | INT | YES | MUL | — | — |
| `fournisseur_id` | INT | YES | MUL | — | — |
| `prix_achat` | DECIMAL(10,2) | NO | — | NULL | — |
| `prix_vente` | DECIMAL(10,2) | NO | — | NULL | — |
| `prix_vente_assure` | DECIMAL(10,2) | YES | — | — | — |
| `unite_mesure` | VARCHAR(20) | YES | — | 'unité' | — |
| `stock_securite` | INT | YES | — | 0 | — |
| `stock_alerte` | INT | YES | — | 0 | — |
| `is_actif` | BOOLEAN | YES | — | TRUE | — |
| `requires_prescription` | BOOLEAN | YES | — | FALSE | — |
| `date_peremption_default` | DATE | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `rapports_comptables` (14 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `exercice` | INT | NO | — | NULL | — |
| `type_rapport` | ENUM('BILAN', 'COMPTE_RESULTAT', 'JOURNAL_GENERAL', 'GRAND_LIVRE', 'BALANCE', 'ETAT_FINANCIER') | NO | — | NULL | — |
| `titre` | VARCHAR(200) | NO | — | NULL | — |
| `contenu` | JSON | NO | — | NULL | — |
| `format_export` | ENUM('PDF', 'EXCEL', 'CSV') | YES | — | 'PDF' | — |
| `date_generation` | DATETIME | NO | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `utilisateur_generation_id` | INT | NO | MUL | NULL | — |
| `filename` | VARCHAR(255) | YES | — | NULL | — |
| `taille_fichier` | INT | YES | — | 0 | — |
| `statut_rapport` | ENUM('GENERATION', 'GENEREE', 'ERREUR') | YES | — | 'GENERATION' | — |
| `message_erreur` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `reception_items` (8 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `reception_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `quantite_attendue` | INT | NO | — | 0 | — |
| `quantite_recue` | INT | NO | — | NULL | — |
| `prix_achat` | DECIMAL(12,2) | NO | — | NULL | — |
| `ecart` | INT | NO | — | 0 | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `receptions` (15 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `supplier_order_id` | INT | YES | MUL | NULL | — |
| `fournisseur_id` | INT | NO | MUL | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `numero_reception` | VARCHAR(50) | NO | UNI | NULL | — |
| `date_reception` | DATE | NO | — | NULL | — |
| `numero_facture` | VARCHAR(100) | YES | — | NULL | — |
| `statut` | ENUM('EN_ATTENTE', 'PARTIELLEMENT_RECU', 'RECU_COMPLET') | NO | — | 'EN_ATTENTE' | — |
| `ecart_detecte` | BOOLEAN | NO | — | FALSE | — |
| `observations` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `date_facture` | DATE | YES | — | NULL | — |
| `reference_facture` | VARCHAR(120) | YES | — | NULL | — |
| `montant_facture` | DECIMAL(12,2) | NO | — | 0 | — |

## `reglements_clients` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `client_id` | INT | NO | MUL | NULL | — |
| `montant` | DECIMAL(10,2) | NO | — | NULL | — |
| `mode_paiement` | ENUM('ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON') | NO | — | 'ESPECE' | — |
| `reference` | VARCHAR(100) | YES | — | — | — |
| `notes` | TEXT | YES | — | — | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_reglement` | DATETIME | NO | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `regularisations_stock` (15 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `inventaire_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `lot_id` | INT | YES | MUL | NULL | — |
| `type_regularisation` | ENUM('MANQUANT', 'EXCEDENT', 'PERIME', 'DEGRADE') | YES | — | 'MANQUANT' | — |
| `quantite_theorique` | INT | NO | — | NULL | — |
| `quantite_comptee` | INT | NO | — | NULL | — |
| `ecart` | INT | NO | — | NULL | — |
| `valeur_ecart` | DECIMAL(12,2) | YES | — | 0 | — |
| `statut_regularisation` | ENUM('EN_ATTENTE', 'VALIDEE', 'ANNULEE') | YES | — | 'EN_ATTENTE' | — |
| `motif_regularisation` | TEXT | YES | — | NULL | — |
| `date_regularisation` | DATETIME | YES | — | NULL | — |
| `utilisateur_regularisation_id` | INT | YES | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `remises_commerciales` (12 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `tiers_type` | ENUM('CLIENT','FOURNISSEUR') | NO | — | NULL | — |
| `tiers_id` | INT | NO | MUL | NULL | — |
| `reference_type` | VARCHAR(60) | YES | — | NULL | — |
| `reference_id` | INT | YES | MUL | NULL | — |
| `type_avantage` | ENUM('RISTOURNE','ESCOMPTE') | NO | — | NULL | — |
| `montant` | DECIMAL(12,2) | NO | — | 0 | — |
| `pourcentage` | DECIMAL(5,2) | YES | — | NULL | — |
| `motif` | TEXT | YES | — | NULL | — |
| `utilisateur_id` | INT | YES | MUL | NULL | — |
| `date_operation` | DATETIME | NO | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `role_discount_limits` (5 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `role_id` | INT | NO | MUL | NULL | — |
| `max_discount_percent` | DECIMAL(5,2) | NO | — | 0 | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `role_permissions` (4 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `role_id` | INT | NO | MUL | NULL | — |
| `permission_id` | INT | NO | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `roles` (5 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `nom` | VARCHAR(50) | NO | UNI | NULL | — |
| `description` | TEXT | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `soldes_comptables` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `exercice` | INT | NO | — | NULL | — |
| `compte_id` | INT | NO | MUL | NULL | — |
| `solde_initial` | DECIMAL(15,2) | YES | — | 0 | — |
| `total_debit` | DECIMAL(15,2) | YES | — | 0 | — |
| `total_credit` | DECIMAL(15,2) | YES | — | 0 | — |
| `solde_final` | DECIMAL(15,2) | YES | — | 0 | — |
| `date_calcul` | DATETIME | NO | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `utilisateur_calcul_id` | INT | NO | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `stock` (9 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `produit_id` | INT | NO | UNI | NULL | — |
| `quantite_disponible` | INT | YES | — | 0 | — |
| `quantite_theorique` | INT | YES | — | 0 | — |
| `quantite_reservee` | INT | YES | — | 0 | — |
| `valeur_stock` | DECIMAL(12,2) | YES | — | 0 | — |
| `dernier_mouvement` | DATETIME | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `stock_entries` (13 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `produit_id` | INT | NO | MUL | NULL | — |
| `fournisseur_id` | INT | YES | MUL | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `reception_id` | INT | YES | MUL | NULL | — |
| `quantite` | INT | NO | — | NULL | — |
| `prix_achat` | DECIMAL(12,2) | NO | — | NULL | — |
| `date_reception` | DATE | NO | — | NULL | — |
| `numero_facture` | VARCHAR(100) | YES | — | NULL | — |
| `observations` | TEXT | YES | — | NULL | — |
| `stock_avant` | INT | NO | — | NULL | — |
| `stock_apres` | INT | NO | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `supplier_order_items` (9 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `supplier_order_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `quantite_commandee` | INT | NO | — | NULL | — |
| `quantite_recue` | INT | NO | — | 0 | — |
| `prix_achat` | DECIMAL(12,2) | NO | — | NULL | — |
| `montant_total` | DECIMAL(12,2) | NO | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `supplier_orders` (11 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_commande` | VARCHAR(50) | NO | UNI | NULL | — |
| `fournisseur_id` | INT | NO | MUL | NULL | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `date_commande` | DATE | NO | — | NULL | — |
| `date_livraison_prevue` | DATE | YES | — | NULL | — |
| `statut` | ENUM('BROUILLON', 'ENVOYEE', 'VALIDEE', 'RECEPTION_PARTIELLE', 'RECEPTION_COMPLETE', 'ANNULEE') | NO | — | 'BROUILLON' | — |
| `montant_total` | DECIMAL(12,2) | NO | — | 0 | — |
| `observations` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `user_permissions` (6 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `user_id` | INT | NO | MUL | NULL | — |
| `permission_id` | INT | NO | MUL | NULL | — |
| `statut` | ENUM('ACTIF', 'SUPPRIME') | YES | — | 'ACTIF' | — |
| `date_attribution` | DATETIME | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `date_suppression` | DATETIME | YES | — | NULL | — |

## `utilisateurs` (13 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `username` | VARCHAR(50) | NO | UNI | NULL | — |
| `email` | VARCHAR(100) | NO | UNI | NULL | — |
| `password_hash` | VARCHAR(255) | NO | — | NULL | — |
| `nom` | VARCHAR(100) | NO | — | NULL | — |
| `prenom` | VARCHAR(100) | NO | — | NULL | — |
| `telephone` | VARCHAR(20) | YES | — | — | — |
| `role_id` | INT | NO | MUL | NULL | — |
| `is_active` | BOOLEAN | YES | — | TRUE | — |
| `tentative_echec` | INT | YES | — | 0 | — |
| `date_blocage` | DATETIME | YES | — | NULL | — |
| `date_creation` | DATETIME | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `date_modification_role` | DATETIME | YES | — | NULL | — |

## `validations_manager` (10 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `demandeur_id` | INT | NO | MUL | NULL | — |
| `manager_id` | INT | YES | MUL | NULL | — |
| `module` | VARCHAR(50) | NO | — | NULL | — |
| `action` | VARCHAR(100) | NO | — | NULL | — |
| `payload` | JSON | YES | — | NULL | — |
| `statut` | ENUM('EN_ATTENTE', 'VALIDEE', 'REFUSEE', 'EXPIREE') | YES | — | 'EN_ATTENTE' | — |
| `motif` | TEXT | YES | — | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `vente_brouillons` (7 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `client_id` | INT | YES | MUL | NULL | — |
| `contenu` | JSON | NO | — | NULL | — |
| `statut` | ENUM('BROUILLON', 'REPRIS', 'ABANDONNE', 'TRANSFORME') | YES | — | 'BROUILLON' | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `vente_ordonnance_items` (5 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `ordonnance_id` | INT | NO | MUL | NULL | — |
| `vente_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |

## `vente_ordonnances` (12 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `vente_id` | INT | NO | MUL | NULL | — |
| `numero_ordonnance` | VARCHAR(100) | NO | — | NULL | — |
| `date_ordonnance` | DATE | NO | — | NULL | — |
| `nom_medecin` | VARCHAR(200) | NO | — | NULL | — |
| `structure_sanitaire` | VARCHAR(200) | NO | — | NULL | — |
| `nom_patient` | VARCHAR(200) | NO | — | NULL | — |
| `telephone_patient` | VARCHAR(30) | NO | — | NULL | — |
| `observation` | TEXT | YES | — | — | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |

## `ventes` (19 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `numero_facture` | VARCHAR(50) | NO | UNI | NULL | — |
| `client_id` | INT | YES | MUL | — | — |
| `utilisateur_id` | INT | NO | MUL | NULL | — |
| `caisse_session_id` | INT | YES | MUL | — | — |
| `date_vente` | DATETIME | NO | — | NULL | — |
| `montant_total` | DECIMAL(10,2) | NO | — | NULL | — |
| `montant_remise` | DECIMAL(10,2) | YES | — | 0 | — |
| `montant_net` | DECIMAL(10,2) | NO | — | NULL | — |
| `montant_paye` | DECIMAL(10,2) | YES | — | 0 | — |
| `montant_restant` | DECIMAL(10,2) | YES | — | 0 | — |
| `type_paiement` | ENUM('ESPECE', 'CARTE', 'CHEQUE', 'CREDIT', 'MOBILE_MONEY') | YES | — | 'ESPECE' | — |
| `statut_vente` | ENUM('EN_COURS', 'PAYEE', 'PARTIELLEMENT_PAYEE', 'ANNULEE') | YES | — | 'EN_COURS' | — |
| `is_credit` | BOOLEAN | YES | — | FALSE | — |
| `echeance_credit` | DATE | YES | — | — | — |
| `notes` | TEXT | YES | — | — | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| `updated_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `deleted_at` | TIMESTAMP | YES | — | NULL | — |

## `ventes_items` (9 champs)

| Champ | Type | Null | Clé | Défaut | Extra |
|-------|------|------|-----|--------|-------|
| `id` | INT | YES | PRI | — | auto_increment |
| `vente_id` | INT | NO | MUL | NULL | — |
| `produit_id` | INT | NO | MUL | NULL | — |
| `lot_id` | INT | YES | MUL | — | — |
| `quantite` | INT | NO | — | NULL | — |
| `prix_unitaire` | DECIMAL(10,2) | NO | — | NULL | — |
| `montant_total` | DECIMAL(10,2) | NO | — | NULL | — |
| `remise` | DECIMAL(5,2) | YES | — | 0 | — |
| `created_at` | TIMESTAMP | YES | — | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
