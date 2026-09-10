<?php

namespace App\Services;

use PDO;
use Exception;

class PriceService
{
    private PDO $db;
    private AuditService $auditService;

    public function __construct(PDO $db, ?AuditService $auditService = null)
    {
        $this->db = $db;
        $this->auditService = $auditService ?? new AuditService($db);
    }

    /**
     * Modifie le prix d'un produit et enregistre l'historique
     */
    public function modifyPrice(array $data, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Récupérer le produit actuel
            $stmt = $this->db->prepare(
                "SELECT id, nom, code_cip, prix_achat, prix_vente, prix_vente_assure 
                 FROM produits 
                 WHERE id = :id AND deleted_at IS NULL"
            );
            $stmt->execute(['id' => $data['produit_id']]);
            $produit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produit) {
                throw new Exception('Produit introuvable.');
            }

            // Valider les nouveaux prix
            $this->validatePrices($data, $produit);

            // Enregistrer l'historique
            $this->recordPriceHistory($produit, $data, $userId);

            // Mettre à jour le produit
            $updateSql = "UPDATE produits SET ";
            $updateParams = [];
            $updateFields = [];

            if (isset($data['nouveau_prix_achat'])) {
                $updateFields[] = "prix_achat = :nouveau_prix_achat";
                $updateParams['nouveau_prix_achat'] = $data['nouveau_prix_achat'];
            }

            if (isset($data['nouveau_prix_vente'])) {
                $updateFields[] = "prix_vente = :nouveau_prix_vente";
                $updateParams['nouveau_prix_vente'] = $data['nouveau_prix_vente'];
            }

            if (isset($data['nouveau_prix_vente_assure'])) {
                $updateFields[] = "prix_vente_assure = :nouveau_prix_vente_assure";
                $updateParams['nouveau_prix_vente_assure'] = $data['nouveau_prix_vente_assure'];
            }

            $updateFields[] = "updated_at = NOW()";
            $updateSql .= implode(', ', $updateFields) . " WHERE id = :id";
            $updateParams['id'] = $data['produit_id'];

            $stmt = $this->db->prepare($updateSql);
            $stmt->execute($updateParams);

            $this->db->commit();

            // Logger l'action
            $this->auditService->log(
                $userId,
                'PRICE_MODIFIED',
                'produits',
                $data['produit_id'],
                [
                    'produit_nom' => $produit['nom'],
                    'ancien_prix_achat' => $produit['prix_achat'],
                    'nouveau_prix_achat' => $data['nouveau_prix_achat'] ?? $produit['prix_achat'],
                    'ancien_prix_vente' => $produit['prix_vente'],
                    'nouveau_prix_vente' => $data['nouveau_prix_vente'],
                    'motif' => $data['motif'],
                ]
            );

            return [
                'success' => true,
                'message' => 'Prix modifié avec succès'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Valide les nouveaux prix
     */
    private function validatePrices(array $data, array $produit): void
    {
        // Prix de vente obligatoire
        if (!isset($data['nouveau_prix_vente']) || empty($data['nouveau_prix_vente'])) {
            throw new Exception('Le nouveau prix de vente est obligatoire.');
        }

        // Motif obligatoire
        if (!isset($data['motif']) || empty($data['motif'])) {
            throw new Exception('Le motif est obligatoire.');
        }

        // Date d'application obligatoire
        if (!isset($data['date_application']) || empty($data['date_application'])) {
            throw new Exception('La date d\'application est obligatoire.');
        }

        // Valider que les prix ne sont pas négatifs
        if (isset($data['nouveau_prix_achat']) && $data['nouveau_prix_achat'] < 0) {
            throw new Exception('Le prix d\'achat ne peut pas être négatif.');
        }

        if (isset($data['nouveau_prix_vente']) && $data['nouveau_prix_vente'] < 0) {
            throw new Exception('Le prix de vente ne peut pas être négatif.');
        }

        if (isset($data['nouveau_prix_vente_assure']) && $data['nouveau_prix_vente_assure'] < 0) {
            throw new Exception('Le prix de vente assuré ne peut pas être négatif.');
        }

        // Valider que les prix ne sont pas zéro
        if (isset($data['nouveau_prix_achat']) && $data['nouveau_prix_achat'] == 0) {
            throw new Exception('Le prix d\'achat ne peut pas être zéro.');
        }

        if (isset($data['nouveau_prix_vente']) && $data['nouveau_prix_vente'] == 0) {
            throw new Exception('Le prix de vente ne peut pas être zéro.');
        }

        // Valider que le prix de vente n'est pas inférieur au prix d'achat
        $nouveauPrixAchat = $data['nouveau_prix_achat'] ?? $produit['prix_achat'];
        if ($data['nouveau_prix_vente'] < $nouveauPrixAchat) {
            throw new Exception('Le prix de vente ne peut pas être inférieur au prix d\'achat.');
        }
    }

    /**
     * Enregistre l'historique des prix
     */
    private function recordPriceHistory(array $produit, array $data, int $userId): void
    {
        // Récupérer les informations de l'utilisateur
        $stmt = $this->db->prepare("SELECT username FROM utilisateurs WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "INSERT INTO historique_prix 
                (produit_id, produit_nom, code_cip, ancien_prix_achat, nouveau_prix_achat,
                 ancien_prix_vente, nouveau_prix_vente, ancien_prix_vente_assure, nouveau_prix_vente_assure,
                 date_application, motif, observation, utilisateur_id, utilisateur_nom, adresse_ip, created_at)
             VALUES 
                (:produit_id, :produit_nom, :code_cip, :ancien_prix_achat, :nouveau_prix_achat,
                 :ancien_prix_vente, :nouveau_prix_vente, :ancien_prix_vente_assure, :nouveau_prix_vente_assure,
                 :date_application, :motif, :observation, :utilisateur_id, :utilisateur_nom, :adresse_ip, NOW())"
        );

        $stmt->execute([
            'produit_id' => $produit['id'],
            'produit_nom' => $produit['nom'],
            'code_cip' => $produit['code_cip'],
            'ancien_prix_achat' => $produit['prix_achat'],
            'nouveau_prix_achat' => $data['nouveau_prix_achat'] ?? null,
            'ancien_prix_vente' => $produit['prix_vente'],
            'nouveau_prix_vente' => $data['nouveau_prix_vente'],
            'ancien_prix_vente_assure' => $produit['prix_vente_assure'] ?? null,
            'nouveau_prix_vente_assure' => $data['nouveau_prix_vente_assure'] ?? null,
            'date_application' => $data['date_application'],
            'motif' => $data['motif'],
            'observation' => $data['observation'] ?? null,
            'utilisateur_id' => $userId,
            'utilisateur_nom' => $user['username'] ?? 'Inconnu',
            'adresse_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * Récupère l'historique des prix
     */
    public function getPriceHistory(int $limit = 100, array $filters = []): array
    {
        $sql = "SELECT hp.*, 
                       p.code_cip,
                       DATEDIFF(NOW(), hp.created_at) as jours_ecoules
                FROM historique_prix hp
                LEFT JOIN produits p ON hp.produit_id = p.id
                WHERE 1=1";

        $params = [];

        // Filtre par produit
        if (!empty($filters['produit_id'])) {
            $sql .= " AND hp.produit_id = :produit_id";
            $params['produit_id'] = $filters['produit_id'];
        }

        // Filtre par recherche (produit ou code)
        if (!empty($filters['search'])) {
            $sql .= " AND (hp.produit_nom LIKE :search OR hp.code_cip LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // Filtre par utilisateur
        if (!empty($filters['utilisateur_id'])) {
            $sql .= " AND hp.utilisateur_id = :utilisateur_id";
            $params['utilisateur_id'] = $filters['utilisateur_id'];
        }

        // Filtre par motif
        if (!empty($filters['motif'])) {
            $sql .= " AND hp.motif = :motif";
            $params['motif'] = $filters['motif'];
        }

        // Filtre par date début
        if (!empty($filters['date_debut'])) {
            $sql .= " AND hp.date_application >= :date_debut";
            $params['date_debut'] = $filters['date_debut'];
        }

        // Filtre par date fin
        if (!empty($filters['date_fin'])) {
            $sql .= " AND hp.date_application <= :date_fin";
            $params['date_fin'] = $filters['date_fin'];
        }

        // Filtre par période
        if (!empty($filters['periode'])) {
            switch ($filters['periode']) {
                case 'today':
                    $sql .= " AND DATE(hp.created_at) = CURDATE()";
                    break;
                case 'week':
                    $sql .= " AND hp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $sql .= " AND hp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
        }

        $sql .= " ORDER BY hp.created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un produit pour la modification de prix
     */
    public function getProductForPriceModification(int $productId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nom, code_cip, prix_achat, prix_vente, prix_vente_assure 
             FROM produits 
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $productId]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produit) {
            throw new Exception('Produit introuvable.');
        }

        return $produit;
    }

    /**
     * Récupère les motifs de modification de prix
     */
    public function getPriceModificationMotifs(): array
    {
        return [
            'Changement fournisseur' => 'Changement fournisseur',
            'Augmentation du coût d\'achat' => 'Augmentation du coût d\'achat',
            'Promotion' => 'Promotion',
            'Révision tarifaire' => 'Révision tarifaire',
            'Erreur de saisie' => 'Erreur de saisie',
            'Autre' => 'Autre',
        ];
    }

    /**
     * Exporte l'historique des prix en CSV
     */
    public function exportPriceHistoryCSV(array $filters = []): string
    {
        $history = $this->getPriceHistory(10000, $filters);

        $filename = 'historique_prix_' . date('YmdHis') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $output = fopen($filepath, 'w');

        // En-têtes CSV
        fputcsv($output, [
            'ID',
            'Produit',
            'Code CIP',
            'Ancien prix achat',
            'Nouveau prix achat',
            'Ancien prix vente',
            'Nouveau prix vente',
            'Ancien prix vente assure',
            'Nouveau prix vente assure',
            'Date application',
            'Motif',
            'Observation',
            'Utilisateur',
            'Date modification',
            'Adresse IP'
        ], ';');

        // Données
        foreach ($history as $record) {
            fputcsv($output, [
                $record['id'],
                $record['produit_nom'],
                $record['code_cip'],
                $record['ancien_prix_achat'],
                $record['nouveau_prix_achat'] ?? 'N/A',
                $record['ancien_prix_vente'],
                $record['nouveau_prix_vente'],
                $record['ancien_prix_vente_assure'] ?? 'N/A',
                $record['nouveau_prix_vente_assure'] ?? 'N/A',
                $record['date_application'],
                $record['motif'],
                $record['observation'] ?? 'N/A',
                $record['utilisateur_nom'],
                $record['created_at'],
                $record['adresse_ip'] ?? 'N/A'
            ], ';');
        }

        fclose($output);

        return $filepath;
    }

    /**
     * Récupère les statistiques des modifications de prix
     */
    public function getPriceModificationStats(array $filters = []): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_modifications,
                    COUNT(DISTINCT produit_id) as produits_modifies,
                    COUNT(DISTINCT utilisateur_id) as utilisateurs_modificateurs,
                    AVG(nouveau_prix_vente - ancien_prix_vente) as augmentation_moyenne
                FROM historique_prix
                WHERE 1=1";

        $params = [];

        // Appliquer les mêmes filtres que getPriceHistory
        if (!empty($filters['produit_id'])) {
            $sql .= " AND produit_id = :produit_id";
            $params['produit_id'] = $filters['produit_id'];
        }

        if (!empty($filters['date_debut'])) {
            $sql .= " AND date_application >= :date_debut";
            $params['date_debut'] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $sql .= " AND date_application <= :date_fin";
            $params['date_fin'] = $filters['date_fin'];
        }

        if (!empty($filters['periode'])) {
            switch ($filters['periode']) {
                case 'today':
                    $sql .= " AND DATE(created_at) = CURDATE()";
                    break;
                case 'week':
                    $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
