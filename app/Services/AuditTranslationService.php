<?php

namespace App\Services;

use PDO;

class AuditTranslationService
{
    private array $translations = [
        // Actions Utilisateurs
        'LOGIN' => 'Connexion utilisateur',
        'LOGOUT' => 'Déconnexion utilisateur',
        'CREATE_USER' => 'Création d\'utilisateur',
        'UPDATE_USER' => 'Modification d\'utilisateur',
        'DELETE_USER' => 'Suppression d\'utilisateur',
        'CHANGE_PASSWORD' => 'Changement de mot de passe',
        
        // Actions Clients
        'CREATE_CLIENT' => 'Création de client',
        'UPDATE_CLIENT' => 'Modification de client',
        'DELETE_CLIENT' => 'Suppression de client',
        'ADD_CREDIT_CLIENT' => 'Ajout de crédit client',
        'DEDUCT_CREDIT_CLIENT' => 'Déduction de crédit client',
        
        // Actions Produits
        'CREATE_PRODUCT' => 'Création de produit',
        'UPDATE_PRODUCT' => 'Modification de produit',
        'DELETE_PRODUCT' => 'Suppression de produit',
        'ACTIVATE_PRODUCT' => 'Activation de produit',
        'DEACTIVATE_PRODUCT' => 'Désactivation de produit',
        
        // Actions Stock
        'ADD_STOCK' => 'Ajout de stock',
        'REMOVE_STOCK' => 'Retrait de stock',
        'ADJUST_STOCK' => 'Ajustement de stock',
        'CREATE_LOT' => 'Création de lot',
        'UPDATE_LOT' => 'Modification de lot',
        'DELETE_LOT' => 'Suppression de lot',
        'STOCK_MOVEMENT' => 'Mouvement de stock',
        'UPDATE_STOCK_TEMPS_REEL' => 'Mise à jour stock temps réel',
        
        // Actions Ventes
        'CREATE_VENTE' => 'Création de vente',
        'UPDATE_VENTE' => 'Modification de vente',
        'DELETE_VENTE' => 'Suppression de vente',
        'ANNULER_VENTE' => 'Annulation de vente',
        'CORRIGER_TICKET' => 'Correction de ticket',
        'AJOUT_ARTICLE_VENTE' => 'Ajout d\'article à la vente',
        'SUPPRIMER_ARTICLE_VENTE' => 'Suppression d\'article de la vente',
        
        // Actions Fournisseurs
        'CREATE_FOURNISSEUR' => 'Création de fournisseur',
        'UPDATE_FOURNISSEUR' => 'Modification de fournisseur',
        'DELETE_FOURNISSEUR' => 'Suppression de fournisseur',
        
        // Actions Comptabilité
        'CREATE_ECRITURE_COMPTABLE' => 'Création d\'écriture comptable',
        'AJUSTER_ECRITURE_COMPTABLE' => 'Ajustement d\'écriture comptable',
        'VALIDER_JOURNAL' => 'Validation du journal',
        'ANNULER_JOURNAL' => 'Annulation du journal',
        
        // Actions Caisse
        'OPEN_CAISSE' => 'Ouverture de caisse',
        'CLOSE_CAISSE' => 'Fermeture de caisse',
        'ENCAISSEMENT' => 'Encaissement',
        'RETOUR_CAISSE' => 'Retour caisse',
        'MOUVEMENT_CAISSE' => 'Mouvement de caisse',
        
        // Actions Système
        'BACKUP' => 'Sauvegarde système',
        'RESTORE' => 'Restauration système',
        'EXPORT_DATA' => 'Export de données',
        'IMPORT_DATA' => 'Import de données',
        'SYSTEM_CONFIG' => 'Configuration système',
        
        // Actions Sécurité
        'FAILED_LOGIN' => 'Tentative de connexion échouée',
        'SECURITY_ALERT' => 'Alerte de sécurité',
        'PERMISSION_DENIED' => 'Accès refusé',
        'DATA_ACCESS' => 'Accès aux données',
        
        // Actions Audit
        'VIEW_AUDIT' => 'Consultation du journal d\'audit',
        'EXPORT_AUDIT' => 'Export du journal d\'audit',
        'CLEAR_AUDIT' => 'Purge du journal d\'audit',

        // Ventes et tickets
        'CREATE_VENTE_ORDONNANCE' => 'Vente avec ordonnance',
        'ERROR_CREATE_VENTE' => 'Échec de création de vente',
        'CANCEL_TICKET_WITH_REASON' => 'Annulation de ticket avec motif',
        'CORRECTION_TICKET' => 'Correction de ticket de vente',

        // Stock et produits
        'CREATE_PRODUCT_WITH_INITIAL_STOCK' => 'Création de produit avec stock initial',
        'UPDATE_PRODUCT_PRICE' => 'Modification du prix produit',
        'MOUVEMENT_STOCK' => 'Mouvement de stock',
        'LOT_PERIME' => 'Lot périmé détecté',
        'TRAITER_LOT_PERIME' => 'Traitement de lot périmé',
        'UPDATE_PRIX_PROMOTION' => 'Mise à jour prix promotionnel',

        // Commandes fournisseurs
        'CREATE_SUPPLIER_ORDER' => 'Création de commande fournisseur',
        'UPDATE_SUPPLIER_ORDER_STATUS' => 'Mise à jour statut commande',
        'RECEIVE_PRODUCTS' => 'Réception de marchandises',
        'GENERATION_COMMANDES_AUTOMATIQUES' => 'Génération de commandes automatiques',
        'AJOUT_PRODUIT_COMMANDE_AUTO' => 'Ajout produit à commande automatique',

        // Caisse et sessions
        'OUVRIR_SESSION_CAISSE' => 'Ouverture de session de caisse',
        'FERMER_SESSION_CAISSE' => 'Fermeture de session de caisse',
        'CAISSE_ACCESS' => 'Accès module caisse',
        'AVANCE_ACCESS' => 'Accès avance de fonds',
        'DOUBLE_ACCESS_SUCCESS' => 'Double accès validé',
        'CAISSE_CODE_GENERATED' => 'Code caisse généré',
        'AVANCE_CODE_GENERATED' => 'Code avance généré',
        'CAISSE_CODE_DEACTIVATED' => 'Code caisse désactivé',
        'AVANCE_CODE_DEACTIVATED' => 'Code avance désactivé',

        // Comptabilité
        'ENREGISTREMENT_ECRITURE_SYSCOHADA' => 'Enregistrement écriture comptable',
        'DELETE_ECRITURE_COMPTABLE' => 'Suppression d\'écriture comptable',

        // Clients
        'UPDATE' => 'Modification d\'enregistrement',
        'UPDATE_CLIENT' => 'Modification de client',
        'UPDATE_SOLDE_CLIENT' => 'Mise à jour solde client',
        'MISE_A_JOUR_SOLDE_CLIENT' => 'Mise à jour solde client',
        'DESACTIVER_CLIENT' => 'Désactivation de client',
        'ERROR_CREATE_CLIENT' => 'Échec de création client',

        // Système et sécurité
        'CHANGE_DATE' => 'Modification de la date système',
        'UNAUTHORIZED_ACCESS' => 'Tentative d\'accès non autorisé',
        'RBAC_TEST' => 'Test des permissions',
        'RBAC_ATTACK_TEST' => 'Test de sécurité RBAC',
        'RBAC_TEST_SESSION' => 'Test de session RBAC',
    ];

    private array $tableLabels = [
        'ventes' => 'Ventes',
        'produits' => 'Produits',
        'stock' => 'Stock',
        'stock_entries' => 'Entrées de stock',
        'lots' => 'Lots',
        'mouvements_stock' => 'Mouvements de stock',
        'clients' => 'Clients',
        'fournisseurs' => 'Fournisseurs',
        'supplier_orders' => 'Commandes fournisseurs',
        'receptions' => 'Réceptions',
        'commandes' => 'Commandes',
        'utilisateurs' => 'Utilisateurs',
        'caisse_sessions' => 'Sessions de caisse',
        'mouvements_caisse' => 'Mouvements de caisse',
        'ecritures_comptables' => 'Écritures comptables',
        'journaux_comptables' => 'Journaux comptables',
        'plan_comptable' => 'Plan comptable',
        'ordonnances' => 'Ordonnances',
        'audit_logs' => 'Journal d\'audit',
    ];

    /**
     * Traduit une action d'audit en français
     */
    public function translateAction(string $action): string
    {
        if (isset($this->translations[$action])) {
            return $this->translations[$action];
        }

        return $this->humanizeActionCode($action);
    }

    public function translateTableName(string $tableName): string
    {
        $key = strtolower(trim($tableName));
        if (isset($this->tableLabels[$key])) {
            return $this->tableLabels[$key];
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    public function getActionCategory(string $action): string
    {
        $code = strtoupper($action);

        if (preg_match('/(CREATE|ADD|OUVRIR|GENERATION|ENCAISSEMENT|RECEIVE|LOGIN)/', $code)) {
            return 'creation';
        }
        if (preg_match('/(UPDATE|MODIF|CORRECTION|AJUST|CHANGE|MISE_A_JOUR|ENREGISTREMENT)/', $code)) {
            return 'modification';
        }
        if (preg_match('/(DELETE|ANNUL|SUPPR|FERMER|TRAITER|PERIME|ERROR|UNAUTHORIZED|RBAC_ATTACK)/', $code)) {
            return 'suppression';
        }
        if (preg_match('/(ACCESS|LOGOUT|SECURITY|RBAC)/', $code)) {
            return 'securite';
        }

        return 'autre';
    }

    private function humanizeActionCode(string $action): string
    {
        $label = strtolower(str_replace('_', ' ', $action));
        return ucfirst($label);
    }

    /**
     * Obtient la description détaillée d'une action avec contexte
     */
    public function getActionDescription(string $action, ?array $context = null): string
    {
        $baseDescription = $this->translateAction($action);
        
        if (!$context) {
            return $baseDescription;
        }

        // Ajout de contexte spécifique selon l'action
        switch ($action) {
            case 'CREATE_PRODUCT':
                $productName = $context['nom'] ?? $context['product_name'] ?? 'produit';
                return "Création du produit : {$productName}";
                
            case 'CREATE_CLIENT':
                $clientName = $context['nom'] ?? $context['client_name'] ?? 'client';
                return "Création du client : {$clientName}";
                
            case 'CREATE_VENTE':
                $venteId = $context['vente_id'] ?? $context['id'] ?? $context['numero_facture'] ?? 'N/A';
                $montant = $context['montant_net'] ?? $context['montant'] ?? $context['montant_total'] ?? 0;
                return "Création de la vente #{$venteId}" . ($montant ? " ({$montant} FCFA)" : '');

            case 'CREATE_VENTE_ORDONNANCE':
                return 'Vente enregistrée avec ordonnance associée';

            case 'CANCEL_TICKET_WITH_REASON':
            case 'ANNULER_VENTE':
                $venteId = $context['vente_id'] ?? $context['id'] ?? 'N/A';
                $motif = $context['motif'] ?? $context['reason'] ?? null;
                return $motif
                    ? "Annulation de la vente #{$venteId} — motif : {$motif}"
                    : "Annulation de la vente #{$venteId}";

            case 'CORRECTION_TICKET':
                return 'Correction d\'un ticket de vente';

            case 'CREATE_PRODUCT_WITH_INITIAL_STOCK':
                $productName = $context['nom'] ?? $context['nom_produit'] ?? 'produit';
                return "Création du produit {$productName} avec stock initial";

            case 'UPDATE_PRODUCT_PRICE':
                return 'Modification du prix d\'achat et de vente';

            case 'GENERATION_COMMANDES_AUTOMATIQUES':
                $count = $context['nombre_commandes'] ?? $context['total'] ?? null;
                return $count
                    ? "Génération de {$count} commande(s) automatique(s)"
                    : 'Génération de commandes automatiques';

            case 'CREATE_SUPPLIER_ORDER':
                $numero = $context['numero_commande'] ?? $context['reference'] ?? 'N/A';
                return "Création de la commande fournisseur {$numero}";

            case 'RECEIVE_PRODUCTS':
                return 'Réception de marchandises fournisseur';

            case 'OUVRIR_SESSION_CAISSE':
                return 'Ouverture d\'une session de caisse';

            case 'FERMER_SESSION_CAISSE':
                return 'Fermeture de la session de caisse';

            case 'ENREGISTREMENT_ECRITURE_SYSCOHADA':
                return 'Écriture comptable SYSCOHADA enregistrée';

            case 'TRAITER_LOT_PERIME':
                return 'Traitement de lots périmés';

            case 'UNAUTHORIZED_ACCESS':
                return 'Tentative d\'accès refusée';
                
            case 'ADD_STOCK':
                $productName = $context['produit_nom'] ?? $context['product_name'] ?? 'produit';
                $quantity = $context['quantite'] ?? $context['quantity'] ?? 0;
                return "Ajout de {$quantity} unités au stock de : {$productName}";
                
            case 'CREATE_LOT':
                $numeroLot = $context['numero_lot'] ?? $context['lot_number'] ?? 'N/A';
                $productName = $context['produit_nom'] ?? $context['product_name'] ?? 'produit';
                return "Création du lot {$numeroLot} pour : {$productName}";
                
            case 'LOGIN':
                $username = $context['username'] ?? $context['user_name'] ?? 'utilisateur';
                return "Connexion de l'utilisateur : {$username}";
                
            case 'CREATE_USER':
                $username = $context['username'] ?? $context['name'] ?? 'utilisateur';
                $role = $context['role'] ?? $context['role_name'] ?? 'rôle';
                return "Création de l'utilisateur {$username} ({$role})";
                
            case 'CREATE_FOURNISSEUR':
                $fournisseurName = $context['nom'] ?? $context['name'] ?? 'fournisseur';
                return "Création du fournisseur : {$fournisseurName}";
                
            default:
                return $baseDescription;
        }
    }

    /**
     * Formate un message d'audit complet avec traduction
     */
    public function formatAuditMessage(array $auditLog): string
    {
        $action = $auditLog['action'] ?? '';
        $description = $this->getActionDescription($action, $auditLog['details'] ?? null);
        
        // Ajout d'informations temporelles
        $date = $auditLog['created_at'] ?? '';
        if ($date) {
            $formattedDate = date('d/m/Y H:i:s', strtotime($date));
            $description .= " - {$formattedDate}";
        }
        
        return $description;
    }

    /**
     * Obtient toutes les traductions disponibles
     */
    public function getAllTranslations(): array
    {
        return $this->translations;
    }

    /**
     * Ajoute une nouvelle traduction
     */
    public function addTranslation(string $action, string $frenchText): void
    {
        $this->translations[$action] = $frenchText;
    }

    /**
     * Vérifie si une traduction existe
     */
    public function hasTranslation(string $action): bool
    {
        return isset($this->translations[$action]);
    }
}
