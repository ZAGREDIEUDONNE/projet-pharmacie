<?php

namespace App\Models;

use PDO;
use PDOException;

class Client
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée un nouveau client
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO clients (
                    code, 
                    nom, 
                    prenom, 
                    matricule, 
                    date_naissance, 
                    age,
                    telephone, 
                    telephone_secondaire,
                    email, 
                    adresse, 
                    ville,
                    type_client, 
                    numero_assurance, 
                    compagnie_assurance,
                    numero_ifu,
                    numero_rccm,
                    plafond_credit,
                    solde_initial,
                    solde_credit,
                    is_actif,
                    notes,
                    utilisateur_creation_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['code'],
            $data['nom'],
            $data['prenom'] ?? null,
            $data['matricule'],
            $data['date_naissance'] ?? null,
            $data['age'] ?? null,
            $data['telephone'] ?? null,
            $data['telephone_secondaire'] ?? null,
            $data['email'] ?? null,
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['type_client'],
            $data['numero_assurance'] ?? null,
            $data['compagnie_assurance'] ?? null,
            $data['numero_ifu'] ?? null,
            $data['numero_rccm'] ?? null,
            $data['plafond_credit'] ?? 0,
            $data['solde_initial'] ?? 0,
            $data['solde_credit'] ?? 0,
            $data['is_actif'] ?? true,
            $data['notes'] ?? null,
            $data['utilisateur_creation_id'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Récupère un client par son ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère un client par son matricule
     */
    public function findByMatricule(string $matricule): ?array
    {
        $sql = "SELECT * FROM clients WHERE matricule = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$matricule]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour un client
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            if ($key === 'date_naissance' && $value) {
                $fields[] = 'age = ?';
                $values[] = $this->calculerAge($value);
            }
            
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = 'updated_at = NOW()';
        $values[] = $id;
        
        $sql = "UPDATE clients SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Désactive un client (soft delete)
     */
    public function desactiver(int $id): bool
    {
        $sql = "UPDATE clients SET 
                    is_actif = 0, 
                    updated_at = NOW() 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Supprime un client (soft delete)
     */
    public function delete(int $id): bool
    {
        $sql = "UPDATE clients SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Recherche des clients
     */
    public function search(string $query, string $typeClient = null, int $limit = 50): array
    {
        $sql = "SELECT * FROM clients 
                WHERE (nom LIKE ? OR prenom LIKE ? OR matricule LIKE ? OR telephone LIKE ?)
                AND deleted_at IS NULL";
        
        $params = ["%$query%", "%$query%", "%$query%", "%$query%"];
        
        if ($typeClient) {
            $sql .= " AND type_client = ?";
            $params[] = $typeClient;
        }
        
        $sql .= " ORDER BY nom, prenom LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les clients par type
     */
    public function getByType(string $typeClient, bool $actifOnly = true): array
    {
        $sql = "SELECT * FROM clients WHERE type_client = ?";
        $params = [$typeClient];
        
        if ($actifOnly) {
            $sql .= " AND is_actif = 1";
        }
        
        $sql .= " AND deleted_at IS NULL ORDER BY nom, prenom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les clients avec solde débiteur
     */
    public function getDebiteurs(): array
    {
        $sql = "SELECT * FROM clients 
                WHERE solde_credit > 0 
                AND is_actif = 1 
                AND deleted_at IS NULL
                ORDER BY solde_credit DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour le solde d'un client
     */
    public function updateSolde(int $id, float $nouveauSolde): bool
    {
        $sql = "UPDATE clients SET 
                    solde_credit = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$nouveauSolde, $id]);
    }

    /**
     * Vérifie le plafond de crédit
     */
    public function verifierPlafond(int $id, float $montantAchat): bool
    {
        $sql = "SELECT solde_credit, plafond_credit FROM clients WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            return false;
        }
        
        $nouveauSolde = $client['solde_credit'] + $montantAchat;
        return $nouveauSolde <= $client['plafond_credit'];
    }

    /**
     * Récupère l'historique d'achats d'un client
     */
    public function getHistoriqueAchats(int $id, string $dateDebut = null, string $dateFin = null, int $limit = 50): array
    {
        $sql = "SELECT v.*, 
                       COUNT(vi.id) as nombre_articles,
                       SUM(vi.quantite) as quantite_totale
                FROM ventes v
                LEFT JOIN ventes_items vi ON v.id = vi.vente_id
                WHERE v.client_id = ? 
                AND v.deleted_at IS NULL";
        
        $params = [$id];
        
        if ($dateDebut) {
            $sql .= " AND v.date_vente >= ?";
            $params[] = $dateDebut;
        }
        
        if ($dateFin) {
            $sql .= " AND v.date_vente <= ?";
            $params[] = $dateFin;
        }
        
        $sql .= " GROUP BY v.id ORDER BY v.date_vente DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques d'un client
     */
    public function getStatistiques(int $id): array
    {
        $sql = "SELECT 
                    COUNT(*) as nombre_achats,
                    SUM(v.montant_net) as total_achats,
                    AVG(v.montant_net) as panier_moyen,
                    MAX(v.date_vente) as dernier_achat,
                    COUNT(CASE WHEN v.is_credit = 1 THEN 1 END) as achats_credit,
                    SUM(CASE WHEN v.is_credit = 1 THEN v.montant_net ELSE 0 END) as total_credit
                FROM ventes v
                WHERE v.client_id = ? 
                AND v.deleted_at IS NULL
                AND v.statut_vente != 'ANNULEE'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ajouter les informations actuelles du client
        $client = $this->findById($id);
        if ($client) {
            $stats['solde_actuel'] = $client['solde_credit'];
            $stats['plafond_credit'] = $client['plafond_credit'];
            $stats['credit_disponible'] = $client['plafond_credit'] - $client['solde_credit'];
        }
        
        return $stats;
    }

    /**
     * Exporte les clients
     */
    public function exporter(array $filtres = []): array
    {
        $sql = "SELECT c.*, 
                       COUNT(v.id) as nombre_achats,
                       COALESCE(SUM(v.montant_net), 0) as total_achats
                FROM clients c
                LEFT JOIN ventes v ON c.id = v.client_id AND v.deleted_at IS NULL
                WHERE c.deleted_at IS NULL";
        
        $params = [];
        
        if (!empty($filtres['type_client'])) {
            $sql .= " AND c.type_client = ?";
            $params[] = $filtres['type_client'];
        }
        
        if (isset($filtres['actif'])) {
            $sql .= " AND c.is_actif = ?";
            $params[] = $filtres['actif'];
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.nom, c.prenom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère un code client unique
     */
    public function genererCode(): string
    {
        $prefix = 'CLI';
        $sql = "SELECT COUNT(*) as count FROM clients WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $prefix . date('Ymd') . str_pad($result['count'] + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie si un matricule existe déjà
     */
    public function matriculeExiste(string $matricule, int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM clients WHERE matricule = ? AND deleted_at IS NULL";
        $params = [$matricule];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Calcule l'âge à partir de la date de naissance
     */
    private function calculerAge(string $dateNaissance): int
    {
        $dateNaissanceObj = new \DateTime($dateNaissance);
        $aujourdHui = new \DateTime();
        $age = $aujourdHui->diff($dateNaissanceObj)->y;
        
        return $age;
    }

    /**
     * Récupère les statistiques globales
     */
    public function getStatistiquesGlobales(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_clients,
                    COUNT(CASE WHEN type_client = 'ORDINAIRE' THEN 1 END) as ordinaires,
                    COUNT(CASE WHEN type_client = 'COURANT' THEN 1 END) as courants,
                    COUNT(CASE WHEN type_client = 'COURANT_DEPOT' THEN 1 END) as courants_depot,
                    COUNT(CASE WHEN type_client = 'COURANT_BON' THEN 1 END) as courants_bon,
                    COUNT(CASE WHEN type_client = 'COURANT_CARNET' THEN 1 END) as courants_carnet,
                    COUNT(CASE WHEN type_client = 'AUTRES_CLIENTS' THEN 1 END) as autres_clients,
                    COUNT(CASE WHEN solde_credit > 0 THEN 1 END) as clients_debiteurs,
                    SUM(solde_credit) as total_debiteur,
                    SUM(plafond_credit) as total_plafond
                FROM clients 
                WHERE deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les clients les plus actifs
     */
    public function getPlusActifs(int $limit = 10): array
    {
        $sql = "SELECT c.*, COUNT(v.id) as nombre_achats, SUM(v.montant_net) as total_achats
                FROM clients c
                LEFT JOIN ventes v ON c.id = v.client_id AND v.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                GROUP BY c.id
                ORDER BY nombre_achats DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les modes de paiement autorisés selon le type de client
     */
    public static function getPaymentModesByClientType(string $typeClient): array
    {
        return match ($typeClient) {
            'ORDINAIRE' => ['ESPECE', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE'],
            'COURANT' => ['ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON'],
            'COURANT_DEPOT' => ['DEPOT'],
            'COURANT_BON' => ['BON'],
            'COURANT_CARNET' => ['CARNET'],
            'AUTRES_CLIENTS' => ['ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON'],
            default => ['ESPECE', 'CARNET', 'DEPOT', 'CARTE_VISA', 'MOBILE_MONEY', 'CHEQUE', 'BON'],
        };
    }

    /**
     * Récupère le mode de paiement par défaut selon le type de client
     */
    public static function getDefaultPaymentMode(string $typeClient): string
    {
        return match ($typeClient) {
            'COURANT_DEPOT' => 'DEPOT',
            'COURANT_BON' => 'BON',
            'COURANT_CARNET' => 'CARNET',
            default => 'ESPECE',
        };
    }

    /**
     * Normalise le type de client
     */
    public static function normalizeClientType(string $type): string
    {
        $type = strtoupper(trim($type));
        
        // Mapping des anciens types vers les nouveaux
        $aliases = [
            'PARTICULIER' => 'ORDINAIRE',
            'SOUS_CLIENT' => 'ORDINAIRE',
            'BENEFICIAIRE' => 'ORDINAIRE',
            'PRESCRIPTEUR' => 'ORDINAIRE',
            'ASSURE' => 'COURANT',
            'ENTREPRISE' => 'AUTRES_CLIENTS',
            'COURANT-DEPOT' => 'COURANT_DEPOT',
            'COURANT-DEPÔT' => 'COURANT_DEPOT',
            'COURANT-BON' => 'COURANT_BON',
            'COURANT-CARNET' => 'COURANT_CARNET',
            'COURANT DEPOT' => 'COURANT_DEPOT',
            'COURANT DÉPÔT' => 'COURANT_DEPOT',
            'COURANT BON' => 'COURANT_BON',
            'COURANT CARNET' => 'COURANT_CARNET',
            'AUTRES CLIENTS' => 'AUTRES_CLIENTS',
            'AUTRES-CLIENTS' => 'AUTRES_CLIENTS',
        ];
        
        $type = $aliases[$type] ?? $type;
        
        $allowedTypes = [
            'ORDINAIRE',
            'COURANT',
            'COURANT_DEPOT',
            'COURANT_BON',
            'COURANT_CARNET',
            'AUTRES_CLIENTS'
        ];
        
        return in_array($type, $allowedTypes, true) ? $type : 'ORDINAIRE';
    }

    /**
     * Récupère le label affichable du type de client
     */
    public static function getClientTypeLabel(string $type): string
    {
        return match ($type) {
            'ORDINAIRE' => 'Ordinaire',
            'COURANT' => 'Courant',
            'COURANT_DEPOT' => 'Courant - Dépôt',
            'COURANT_BON' => 'Courant - Bon',
            'COURANT_CARNET' => 'Courant - Carnet',
            'AUTRES_CLIENTS' => 'Autres clients',
            default => 'Ordinaire',
        };
    }

    /**
     * Récupère la classe CSS pour le badge du type de client
     */
    public static function getClientTypeBadgeClass(string $type): string
    {
        return match ($type) {
            'ORDINAIRE' => 'bg-gray-100 text-gray-800',
            'COURANT' => 'bg-blue-100 text-blue-800',
            'COURANT_DEPOT' => 'bg-purple-100 text-purple-800',
            'COURANT_BON' => 'bg-yellow-100 text-yellow-800',
            'COURANT_CARNET' => 'bg-green-100 text-green-800',
            'AUTRES_CLIENTS' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
