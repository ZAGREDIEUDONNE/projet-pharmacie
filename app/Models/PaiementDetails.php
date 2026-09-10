<?php

namespace App\Models;

use PDO;
use PDOException;

class PaiementDetails
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée les détails de paiement pour une vente
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO paiement_details (
                    vente_id,
                    mode_paiement,
                    depot_nom_etablissement,
                    depot_adresse,
                    depot_telephone,
                    depot_numero_arrete,
                    mobile_operateur,
                    mobile_nom_titulaire,
                    mobile_telephone,
                    cheque_numero,
                    cheque_nom_banque,
                    bon_nom_beneficiaire,
                    bon_telephone,
                    bon_matricule,
                    bon_numero_bon,
                    utilisateur_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['vente_id'],
            $data['mode_paiement'],
            $data['depot_nom_etablissement'] ?? null,
            $data['depot_adresse'] ?? null,
            $data['depot_telephone'] ?? null,
            $data['depot_numero_arrete'] ?? null,
            $data['mobile_operateur'] ?? null,
            $data['mobile_nom_titulaire'] ?? null,
            $data['mobile_telephone'] ?? null,
            $data['cheque_numero'] ?? null,
            $data['cheque_nom_banque'] ?? null,
            $data['bon_nom_beneficiaire'] ?? null,
            $data['bon_telephone'] ?? null,
            $data['bon_matricule'] ?? null,
            $data['bon_numero_bon'] ?? null,
            $data['utilisateur_id']
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Récupère les détails de paiement pour une vente
     */
    public function findByVenteId(int $venteId): ?array
    {
        $sql = "SELECT * FROM paiement_details WHERE vente_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$venteId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour les détails de paiement
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'vente_id', 'utilisateur_id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        
        $sql = "UPDATE paiement_details SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Supprime les détails de paiement pour une vente
     */
    public function deleteByVenteId(int $venteId): bool
    {
        $sql = "DELETE FROM paiement_details WHERE vente_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$venteId]);
    }

    /**
     * Valide les données de paiement selon le mode
     */
    public function validatePaymentData(string $modePaiement, array $data): array
    {
        $errors = [];
        
        switch ($modePaiement) {
            case 'DEPOT':
                if (empty($data['depot_nom_etablissement'])) {
                    $errors[] = 'Le nom de l\'établissement est obligatoire pour le paiement par dépôt';
                }
                if (empty($data['depot_adresse'])) {
                    $errors[] = 'L\'adresse est obligatoire pour le paiement par dépôt';
                }
                if (empty($data['depot_telephone'])) {
                    $errors[] = 'Le téléphone est obligatoire pour le paiement par dépôt';
                }
                if (empty($data['depot_numero_arrete'])) {
                    $errors[] = 'Le numéro de l\'arrêté ministériel est obligatoire pour le paiement par dépôt';
                }
                break;
                
            case 'MOBILE_MONEY':
                if (empty($data['mobile_operateur'])) {
                    $errors[] = 'L\'opérateur téléphonique est obligatoire pour le paiement Mobile Money';
                }
                if (empty($data['mobile_nom_titulaire'])) {
                    $errors[] = 'Le nom du titulaire est obligatoire pour le paiement Mobile Money';
                }
                if (empty($data['mobile_telephone'])) {
                    $errors[] = 'Le numéro de téléphone est obligatoire pour le paiement Mobile Money';
                }
                break;
                
            case 'CHEQUE':
                if (empty($data['cheque_numero'])) {
                    $errors[] = 'Le numéro du chèque est obligatoire';
                }
                if (empty($data['cheque_nom_banque'])) {
                    $errors[] = 'Le nom de la banque est obligatoire';
                }
                break;
                
            case 'BON':
                if (empty($data['bon_nom_beneficiaire'])) {
                    $errors[] = 'Le nom du bénéficiaire est obligatoire pour le paiement par bon';
                }
                if (empty($data['bon_telephone'])) {
                    $errors[] = 'Le téléphone est obligatoire pour le paiement par bon';
                }
                if (empty($data['bon_matricule'])) {
                    $errors[] = 'Le matricule est obligatoire pour le paiement par bon';
                }
                if (empty($data['bon_numero_bon'])) {
                    $errors[] = 'Le numéro du bon est obligatoire';
                }
                break;
                
            case 'ESPECE':
            case 'CARNET':
            case 'CARTE_VISA':
            case 'CREDIT':
                // Aucun champ supplémentaire requis
                break;
                
            default:
                $errors[] = 'Mode de paiement non reconnu';
        }
        
        return $errors;
    }

    /**
     * Normalise le mode de paiement
     */
    public function normalizePaymentMode(string $mode): string
    {
        return match (strtoupper(trim($mode))) {
            'ESPECES', 'ESPECE' => 'ESPECE',
            'CARNET' => 'CARNET',
            'DEPOT' => 'DEPOT',
            'CARTE', 'CARTE_VISA', 'VISA' => 'CARTE_VISA',
            'MOBILE', 'MOBILE_MONEY' => 'MOBILE_MONEY',
            'CHEQUE' => 'CHEQUE',
            'BON' => 'BON',
            'CREDIT' => 'CREDIT',
            default => 'ESPECE',
        };
    }

    /**
     * Récupère les statistiques de paiement par mode
     */
    public function getStatsByMode(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT 
                    pd.mode_paiement,
                    COUNT(*) as nombre_transactions,
                    SUM(v.montant_net) as montant_total
                FROM paiement_details pd
                JOIN ventes v ON pd.vente_id = v.id
                WHERE v.date_vente BETWEEN ? AND ?
                AND v.deleted_at IS NULL
                GROUP BY pd.mode_paiement
                ORDER BY montant_total DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
