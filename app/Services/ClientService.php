<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Vente;
use App\Services\AuditService;
use PDO;
use PDOException;
use Exception;

class ClientService
{
    private PDO $db;
    private AuditService $auditService;

    public function __construct(PDO $db, AuditService $auditService)
    {
        $this->db = $db;
        $this->auditService = $auditService;
    }

    /**
     * Crée un nouveau client avec validation complète
     */
    public function creerClient(array $data): array
    {
        $this->db->beginTransaction();
        
        try {
            // Validation des champs obligatoires
            $this->validerDonneesClient($data);
            
            // Vérifier unicité du matricule
            if ($this->matriculeExiste($data['matricule'])) {
                throw new Exception("Ce matricule existe déjà");
            }
            
            // Générer un code client si non fourni
            $codeClient = $data['code'] ?? $this->genererCodeClient();
            
            // Calculer l'âge à partir de la date de naissance
            $age = $this->calculerAge($data['date_naissance']);
            
            // Insérer le client
            $sql = "INSERT INTO clients (
                        code, 
                        nom, 
                        prenom, 
                        matricule, 
                        date_naissance, 
                        age,
                        telephone, 
                        email, 
                        adresse, 
                        type_client, 
                        numero_assurance, 
                        compagnie_assurance,
                        plafond_credit,
                        solde_credit,
                        is_actif,
                        notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $codeClient,
                $data['nom'],
                $data['prenom'] ?? null,
                $data['matricule'],
                $data['date_naissance'],
                $age,
                $data['telephone'] ?? null,
                $data['email'] ?? null,
                $data['adresse'] ?? null,
                $data['type_client'],
                $data['numero_assurance'] ?? null,
                $data['compagnie_assurance'] ?? null,
                $data['plafond_credit'] ?? 0,
                $data['solde_credit'] ?? 0,
                $data['is_actif'] ?? true,
                $data['notes'] ?? null
            ]);
            
            $clientId = $this->db->lastInsertId();
            
            // Logger l'action
            $this->auditService->logAction(
                $data['utilisateur_id'],
                'CREATE_CLIENT',
                'clients',
                $clientId,
                null,
                array_merge($data, ['code' => $codeClient, 'age' => $age])
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'client_id' => $clientId,
                'code' => $codeClient,
                'message' => 'Client créé avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            $this->auditService->logAction(
                $data['utilisateur_id'] ?? null,
                'ERROR_CREATE_CLIENT',
                'clients',
                null,
                null,
                ['error' => $e->getMessage(), 'data' => $data]
            );
            
            throw new Exception("Erreur lors de la création du client: " . $e->getMessage());
        }
    }

    /**
     * Met à jour les informations d'un client
     */
    public function modifierClient(int $clientId, array $data, int $utilisateurId): array
    {
        $this->db->beginTransaction();
        
        try {
            // Récupérer les données actuelles
            $clientActuel = $this->getClientById($clientId);
            if (!$clientActuel) {
                throw new Exception("Client non trouvé");
            }
            
            // Validation des données
            $this->validerDonneesClient($data, $clientId);
            
            // Vérifier unicité du matricule (si modifié)
            if (isset($data['matricule']) && $data['matricule'] !== $clientActuel['matricule']) {
                if ($this->matriculeExiste($data['matricule'], $clientId)) {
                    throw new Exception("Ce matricule existe déjà");
                }
            }
            
            // Préparer les champs à mettre à jour
            $champs = [];
            $valeurs = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['id', 'created_at', 'updated_at'])) {
                    continue;
                }
                
                if ($key === 'date_naissance' && $value) {
                    $champs[] = 'age = ?';
                    $valeurs[] = $this->calculerAge($value);
                }
                
                $champs[] = "$key = ?";
                $valeurs[] = $value;
            }
            
            $champs[] = 'updated_at = NOW()';
            $valeurs[] = $clientId;
            
            // Mettre à jour le client
            $sql = "UPDATE clients SET " . implode(', ', $champs) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($valeurs);
            
            // Logger l'action
            $this->auditService->logAction(
                $utilisateurId,
                'UPDATE_CLIENT',
                'clients',
                $clientId,
                $clientActuel,
                $data
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Client modifié avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la modification du client: " . $e->getMessage());
        }
    }

    /**
     * Met à jour le solde d'un client (temps réel)
     */
    public function mettreAJourSolde(int $clientId, float $montant, string $typeOperation, int $utilisateurId): array
    {
        $this->db->beginTransaction();
        
        try {
            $client = $this->getClientById($clientId);
            if (!$client) {
                throw new Exception("Client non trouvé");
            }
            
            $ancienSolde = $client['solde_credit'];
            $nouveauSolde = $ancienSolde;
            
            switch ($typeOperation) {
                case 'AUGMENTER':
                    $nouveauSolde += $montant;
                    break;
                case 'DIMINUER':
                    $nouveauSolde -= $montant;
                    if ($nouveauSolde < 0) {
                        throw new Exception("Solde insuffisant");
                    }
                    break;
                case 'REGLEMENT':
                    $nouveauSolde -= $montant;
                    if ($nouveauSolde < 0) {
                        $nouveauSolde = 0; // Ne pas aller en négatif pour un règlement
                    }
                    break;
                default:
                    throw new Exception("Type d'opération invalide");
            }
            
            // Vérifier le plafond de crédit
            if ($nouveauSolde > $client['plafond_credit']) {
                throw new Exception("Solde dépasse le plafond de crédit autorisé");
            }
            
            // Mettre à jour le solde
            $sql = "UPDATE clients SET solde_credit = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nouveauSolde, $clientId]);
            
            // Logger l'action
            $this->auditService->logAction(
                $utilisateurId,
                'UPDATE_SOLDE_CLIENT',
                'clients',
                $clientId,
                ['solde_credit' => $ancienSolde],
                [
                    'solde_credit' => $nouveauSolde,
                    'operation' => $typeOperation,
                    'montant' => $montant
                ]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'ancien_solde' => $ancienSolde,
                'nouveau_solde' => $nouveauSolde,
                'message' => 'Solde mis à jour avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la mise à jour du solde: " . $e->getMessage());
        }
    }

    /**
     * Récupère l'historique d'achats d'un client
     */
    public function getHistoriqueAchats(int $clientId, string $dateDebut = null, string $dateFin = null, int $limit = 50): array
    {
        $sql = "SELECT v.*, 
                       COUNT(vi.id) as nombre_articles,
                       SUM(vi.quantite) as quantite_totale
                FROM ventes v
                LEFT JOIN ventes_items vi ON v.id = vi.vente_id
                WHERE v.client_id = ? 
                AND v.deleted_at IS NULL";
        
        $params = [$clientId];
        
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
    public function getStatistiquesClient(int $clientId): array
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
        $stmt->execute([$clientId]);
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ajouter les informations actuelles du client
        $client = $this->getClientById($clientId);
        if ($client) {
            $stats['solde_actuel'] = $client['solde_credit'];
            $stats['plafond_credit'] = $client['plafond_credit'];
            $stats['credit_disponible'] = $client['plafond_credit'] - $client['solde_credit'];
        }
        
        return $stats;
    }

    /**
     * Recherche des clients
     */
    public function rechercherClients(string $query, string $typeClient = null, int $limit = 50): array
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
     * Récupère les clients avec solde débiteur
     */
    public function getClientsDebiteurs(): array
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
     * Récupère les clients par type
     */
    public function getClientsParType(string $typeClient): array
    {
        $sql = "SELECT * FROM clients 
                WHERE type_client = ? 
                AND is_actif = 1 
                AND deleted_at IS NULL
                ORDER BY nom, prenom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$typeClient]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un client a dépassé son plafond de crédit
     */
    public function verifierPlafondCredit(int $clientId, float $montantAchat): bool
    {
        $client = $this->getClientById($clientId);
        if (!$client) {
            return false;
        }
        
        $nouveauSolde = $client['solde_credit'] + $montantAchat;
        return $nouveauSolde <= $client['plafond_credit'];
    }

    /**
     * Désactive un client (soft delete)
     */
    public function desactiverClient(int $clientId, int $utilisateurId): array
    {
        $this->db->beginTransaction();
        
        try {
            $client = $this->getClientById($clientId);
            if (!$client) {
                throw new Exception("Client non trouvé");
            }
            
            // Vérifier si le client a un solde non nul
            if ($client['solde_credit'] > 0) {
                throw new Exception("Impossible de désactiver un client avec un solde débiteur");
            }
            
            // Désactiver le client
            $sql = "UPDATE clients SET 
                        is_actif = 0, 
                        updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId]);
            
            // Logger l'action
            $this->auditService->logAction(
                $utilisateurId,
                'DESACTIVER_CLIENT',
                'clients',
                $clientId,
                $client,
                ['is_actif' => false]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Client désactivé avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Erreur lors de la désactivation du client: " . $e->getMessage());
        }
    }

    /**
     * Valide les données du client
     */
    private function validerDonneesClient(array $data, int $excludeId = null): void
    {
        $champsObligatoires = ['nom', 'matricule', 'date_naissance', 'type_client'];
        
        foreach ($champsObligatoires as $champ) {
            if (empty($data[$champ])) {
                throw new Exception("Le champ '$champ' est obligatoire");
            }
        }
        
        // Valider le format du matricule
        if (!preg_match('/^[A-Z0-9]{3,20}$/', $data['matricule'])) {
            throw new Exception("Le matricule doit contenir 3 à 20 caractères alphanumériques en majuscules");
        }
        
        // Valider la date de naissance
        if (!$this->validerDateNaissance($data['date_naissance'])) {
            throw new Exception("Date de naissance invalide");
        }
        
        // Valider le type de client
        $typesAutorises = ['ORDINAIRE', 'COURANT', 'COURANT_DEPOT', 'COURANT_BON', 'COURANT_CARNET', 'AUTRES_CLIENTS'];
        if (!in_array($data['type_client'], $typesAutorises)) {
            throw new Exception("Type de client non valide");
        }
        
        // Valider l'email si fourni
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email invalide");
        }
        
        // Valider le téléphone si fourni
        if (!empty($data['telephone']) && !preg_match('/^[0-9+]{8,20}$/', $data['telephone'])) {
            throw new Exception("Numéro de téléphone invalide");
        }
    }

    /**
     * Vérifie si un matricule existe déjà
     */
    private function matriculeExiste(string $matricule, int $excludeId = null): bool
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
     * Génère un code client unique
     */
    private function genererCodeClient(): string
    {
        $prefix = 'CLI';
        $sql = "SELECT COUNT(*) as count FROM clients WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $prefix . date('Ymd') . str_pad($result['count'] + 1, 3, '0', STR_PAD_LEFT);
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
     * Valide la date de naissance
     */
    private function validerDateNaissance(string $dateNaissance): bool
    {
        try {
            $date = new \DateTime($dateNaissance);
            $aujourdHui = new \DateTime();
            
            // Vérifier que la date n'est pas dans le futur
            if ($date > $aujourdHui) {
                return false;
            }
            
            // Vérifier que l'âge est raisonnable (entre 0 et 120 ans)
            $age = $aujourdHui->diff($date)->y;
            return $age >= 0 && $age <= 120;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère un client par son ID
     */
    private function getClientById(int $clientId): ?array
    {
        $sql = "SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère un client par son matricule
     */
    public function getClientByMatricule(string $matricule): ?array
    {
        $sql = "SELECT * FROM clients WHERE matricule = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$matricule]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Exporte la liste des clients
     */
    public function exporterClients(array $filtres = []): array
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
        
        if (!empty($filtres['actif'])) {
            $sql .= " AND c.is_actif = ?";
            $params[] = $filtres['actif'];
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.nom, c.prenom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
