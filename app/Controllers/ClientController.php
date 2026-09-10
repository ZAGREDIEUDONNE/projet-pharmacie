<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\RBACService;
use App\Services\AuditService;
use App\Services\ClientService;
use App\Models\Client;
use PDO;

/**
 * Contrôleur pour la gestion des clients
 */
class ClientController extends BaseController
{
    private RBACService $rbacService;
    private AuditService $auditService;
    private ClientService $clientService;

    public function __construct()
    {
        parent::__construct();
        $this->rbacService = new RBACService($this->db);
        $this->auditService = new AuditService($this->db);
        $this->clientService = new ClientService($this->db, $this->auditService);
    }

    /**
     * Affiche le dashboard de gestion des clients
     */
    public function dashboard(): void
    {
        $this->requireClientReadAccess();
        $this->render('clients/dashboard', [
            'title' => 'Dashboard Gestion des Clients'
        ]);
    }

    /**
     * Affiche le formulaire de création de client
     */
    public function create(): void
    {
        $this->requireClientCreateAccess();

        $this->render('clients/create', [
            'title' => 'Créer un Client'
        ]);
    }

    /**
     * Enregistre un nouveau client dans la base de données
     */
    public function store(): void
    {
        $this->requireClientCreateAccess();
        $returnTo = $this->getSafeReturnUrl('/clients');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/clients/creer?return_to=' . urlencode($returnTo));
            return;
        }

        // Récupérer et valider les données
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $telephone_secondaire = trim($_POST['telephone_secondaire'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $type_client = $this->normalizeClientType($_POST['type_client'] ?? null);
        $numero_assurance = trim($_POST['numero_assurance'] ?? '');
        $compagnie_assurance = trim($_POST['compagnie_assurance'] ?? '');
        $numero_ifu = trim($_POST['numero_ifu'] ?? '');
        $numero_rccm = trim($_POST['numero_rccm'] ?? '');
        $plafond_credit = floatval($_POST['plafond_credit'] ?? 0);
        $solde_initial = floatval($_POST['solde_initial'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        // Validation complète
        $errors = [];
        if (empty($nom)) {
            $errors[] = 'Le nom est obligatoire';
        }
        if (empty($prenom)) {
            $errors[] = 'Le prénom est obligatoire';
        }
        if (empty($telephone)) {
            $errors[] = 'Le téléphone est obligatoire';
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'email n\'est pas valide';
        }
        if ($plafond_credit < 0) {
            $errors[] = 'Le plafond credit ne peut pas etre negatif';
        }

            if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/clients/creer?return_to=' . urlencode($returnTo));
            return;
        }

        try {
            // Générer le code client unique
            $code = $this->generateClientCode();

            // Générer le matricule si non fourni
            $matricule = trim($_POST['matricule'] ?? '');
            if (empty($matricule)) {
                $matricule = 'CLI' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            }

            // Calculer l'âge si date de naissance fournie
            $date_naissance = trim($_POST['date_naissance'] ?? '');
            $age = null;
            if (!empty($date_naissance)) {
                $birthDate = new \DateTime($date_naissance);
                $today = new \DateTime();
                $age = $today->diff($birthDate)->y;
            }

            // Insérer le client dans la base de données avec tous les champs
            $sql = "INSERT INTO clients (
                        code, nom, prenom, matricule, date_naissance, age,
                        telephone, telephone_secondaire, email, adresse, ville,
                        type_client, numero_assurance, compagnie_assurance,
                        numero_ifu, numero_rccm, plafond_credit, solde_initial,
                        solde_credit, is_actif, notes, utilisateur_creation_id,
                        created_at, updated_at
                    ) VALUES (
                        :code, :nom, :prenom, :matricule, :date_naissance, :age,
                        :telephone, :telephone_secondaire, :email, :adresse, :ville,
                        :type_client, :numero_assurance, :compagnie_assurance,
                        :numero_ifu, :numero_rccm, :plafond_credit, :solde_initial,
                        :solde_credit, :is_actif, :notes, :utilisateur_creation_id,
                        NOW(), NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                'code' => $code,
                'nom' => $nom,
                'prenom' => $prenom,
                'matricule' => $matricule,
                'date_naissance' => $date_naissance ?: null,
                'age' => $age,
                'telephone' => $telephone,
                'telephone_secondaire' => $telephone_secondaire ?: null,
                'email' => $email ?: null,
                'adresse' => $adresse ?: null,
                'ville' => $ville ?: null,
                'type_client' => $type_client,
                'numero_assurance' => $numero_assurance ?: null,
                'compagnie_assurance' => $compagnie_assurance ?: null,
                'numero_ifu' => $numero_ifu ?: null,
                'numero_rccm' => $numero_rccm ?: null,
                'plafond_credit' => $plafond_credit,
                'solde_initial' => $solde_initial,
                'solde_credit' => $solde_initial, // Initialiser le solde avec le solde initial
                'is_actif' => 1, // Actif par défaut
                'notes' => $notes ?: null,
                'utilisateur_creation_id' => $_SESSION['user_id'] ?? null
            ]);

            if (!$result) {
                throw new \Exception("Erreur lors de l'insertion du client");
            }

            $clientId = $this->db->lastInsertId();

            // Journaliser l'action dans l'audit
            $this->auditService->logAction(
                $_SESSION['user_id'] ?? null,
                'CREATE_CLIENT',
                'clients',
                $clientId,
                null,
                [
                    'code' => $code,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'telephone' => $telephone,
                    'email' => $email,
                    'adresse' => $adresse,
                    'type_client' => $type_client,
                    'numero_assurance' => $numero_assurance,
                    'compagnie_assurance' => $compagnie_assurance,
                    'plafond_credit' => $plafond_credit
                ],
                null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            );

            $_SESSION['success'] = 'Client créé avec succès';
            $this->redirect($returnTo);

        } catch (\Exception $e) {
            error_log("ClientController::store - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors de la création du client'];
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/clients/creer?return_to=' . urlencode($returnTo));
        }
    }

    /**
     * Affiche la liste des clients
     */
    public function index(): void
    {
        $this->requireClientReadAccess();

        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
            $offset = ($page - 1) * $perPage;

            // Compter le total
            $sqlCount = "SELECT COUNT(*) as total FROM clients WHERE deleted_at IS NULL";
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute();
            $total = $stmtCount->fetch(\PDO::FETCH_ASSOC)['total'];

            // Aliases preserve the legacy view contract while using the real MySQL columns.
            $sql = "SELECT id, code, code AS code_client, matricule, nom, prenom, date_naissance, age, telephone, telephone_secondaire, email, adresse, ville, numero_ifu, numero_rccm, numero_assurance, compagnie_assurance, type_client, is_actif, plafond_credit AS plafond, solde_initial, COALESCE(solde_credit, solde, 0) AS solde, notes, notes AS observations, created_at, updated_at FROM clients WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$perPage, $offset]);
            $clients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalPages = (int)ceil($total / $perPage);

            $this->render('clients/index', [
                'title' => 'Liste des Clients',
                'clients' => $clients,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_prev' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'prev_page' => $page - 1,
                    'next_page' => $page + 1
                ]
            ]);

        } catch (\Exception $e) {
            error_log("ClientController::index - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement des clients'];
            $this->render('clients/index', [
                'title' => 'Liste des Clients',
                'clients' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 25,
                    'total' => 0,
                    'total_pages' => 0,
                    'has_prev' => false,
                    'has_next' => false,
                    'prev_page' => 1,
                    'next_page' => 1
                ]
            ]);
        }
    }

    /**
     * Affiche les clients actifs dont le solde de crédit est débiteur.
     */
    public function debiteurs(): void
    {
        $this->requireClientReadAccess();

        try {
            $clients = $this->clientService->getClientsDebiteurs();

            $this->render('clients/index', [
                'title' => 'Clients débiteurs',
                'clients' => $clients,
                'user' => $this->getCurrentUser(),
                'isDebiteursList' => true,
            ]);
        } catch (\Exception $e) {
            error_log('ClientController::debiteurs - ' . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement des clients débiteurs'];
            $this->redirect($this->getSafeReturnUrl('/clients'));
        }
    }

    /**
     * Export CSV de la liste des clients.
     */
    public function export(): void
    {
        $this->requireClientReadAccess();

        $filtres = [
            'type_client' => trim((string)($_GET['type'] ?? '')),
            'actif' => isset($_GET['actif']) ? 1 : 0,
        ];

        try {
            $clients = $this->clientService->exporterClients($filtres);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Impossible de preparer le fichier CSV');
            }

            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, [
                'Code',
                'Nom',
                'Prenom',
                'Matricule',
                'Type',
                'Telephone',
                'Email',
                'Plafond credit',
                'Solde credit',
                'Date naissance',
                'Nombre achats',
                'Total achats',
            ], ';');

            foreach ($clients as $client) {
                fputcsv($output, [
                    $client['code'] ?? '',
                    $client['nom'] ?? '',
                    $client['prenom'] ?? '',
                    $client['matricule'] ?? '',
                    $client['type_client'] ?? '',
                    $client['telephone'] ?? '',
                    $client['email'] ?? '',
                    $client['plafond_credit'] ?? 0,
                    $client['solde_credit'] ?? 0,
                    $client['date_naissance'] ?? '',
                    $client['nombre_achats'] ?? 0,
                    $client['total_achats'] ?? 0,
                ], ';');
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            error_log('ClientController::export - ' . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors de l\'export des clients';
            $this->redirect('/clients');
        }
    }

    /**
     * Affiche les détails d'un client
     */
    public function show($id = null): void
    {
        $this->requireClientReadAccess();
        $id = $id ? (int)$id : (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['errors'] = ['ID client non fourni'];
            $this->redirect('/clients');
            return;
        }

        try {
            $sql = "SELECT * FROM clients WHERE id = :id AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $client = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$client) {
                $_SESSION['errors'] = ['Client non trouvé'];
                $this->redirect('/clients');
                return;
            }

            // Récupérer l'historique des ventes
            $sqlSales = "SELECT v.numero_facture, v.date_vente, v.montant_net, v.statut_vente 
                        FROM ventes v 
                        WHERE v.client_id = :id 
                        ORDER BY v.date_vente DESC 
                        LIMIT 20";
            $stmtSales = $this->db->prepare($sqlSales);
            $stmtSales->execute(['id' => $id]);
            $salesHistory = $stmtSales->fetchAll(\PDO::FETCH_ASSOC);

            // Récupérer l'historique des règlements
            $sqlPayments = "SELECT p.date_paiement, p.mode_paiement, p.montant, p.reference 
                            FROM paiements p 
                            WHERE p.client_id = :id 
                            ORDER BY p.date_paiement DESC 
                            LIMIT 20";
            $stmtPayments = $this->db->prepare($sqlPayments);
            $stmtPayments->execute(['id' => $id]);
            $paymentHistory = $stmtPayments->fetchAll(\PDO::FETCH_ASSOC);

            // Récupérer la dernière opération
            $lastOperation = null;
            if (!empty($salesHistory)) {
                $lastOperation = [
                    'date' => $salesHistory[0]['date_vente'],
                    'type' => 'Vente',
                    'montant' => $salesHistory[0]['montant_net']
                ];
            }
            if (!empty($paymentHistory)) {
                if (!$lastOperation || strtotime($paymentHistory[0]['date_paiement']) > strtotime($lastOperation['date'])) {
                    $lastOperation = [
                        'date' => $paymentHistory[0]['date_paiement'],
                        'type' => 'Paiement',
                        'montant' => $paymentHistory[0]['montant']
                    ];
                }
            }

            $this->render('clients/show', [
                'title' => 'Fiche Client',
                'client' => $client,
                'salesHistory' => $salesHistory,
                'paymentHistory' => $paymentHistory,
                'lastOperation' => $lastOperation
            ]);

        } catch (\Exception $e) {
            error_log("ClientController::show - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement du client'];
            $this->redirect('/clients');
        }
    }

    /**
     * Affiche le formulaire d'édition d'un client
     */
    public function edit(?int $id = null): void
    {
        $this->requireClientWriteAccess('client.update');
        $id = $id ?? (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['errors'] = ['ID client non fourni'];
            $this->redirect('/clients');
            return;
        }

        try {
            $sql = "SELECT * FROM clients WHERE id = :id AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $client = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$client) {
                $_SESSION['errors'] = ['Client non trouvé'];
                $this->redirect('/clients');
                return;
            }

            $this->render('clients/edit', [
                'title' => 'Modifier un Client',
                'client' => $client
            ]);

        } catch (\Exception $e) {
            error_log("ClientController::edit - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement du client'];
            $this->redirect('/clients');
        }
    }

    /**
     * Met à jour un client
     */
    public function update(?int $id = null): void
    {
        $this->requireClientWriteAccess('client.update');
        $returnTo = $this->getSafeReturnUrl('/clients');
        $id = $id ?? (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['errors'] = ['ID client non fourni'];
            $this->redirect('/clients');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/clients/modifier?id={$id}&return_to=" . urlencode($returnTo));
            return;
        }

        // Récupérer les données
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $matricule = trim($_POST['matricule'] ?? '');
        $date_naissance = trim($_POST['date_naissance'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $telephone_secondaire = trim($_POST['telephone_secondaire'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $type_client = $this->normalizeClientType($_POST['type_client'] ?? null);
        $numero_assurance = trim($_POST['numero_assurance'] ?? '');
        $compagnie_assurance = trim($_POST['compagnie_assurance'] ?? '');
        $numero_ifu = trim($_POST['numero_ifu'] ?? '');
        $numero_rccm = trim($_POST['numero_rccm'] ?? '');
        $plafond_credit = floatval($_POST['plafond_credit'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $is_actif = isset($_POST['is_actif']) ? 1 : 0;

        // Calculer l'âge si date de naissance fournie
        $age = null;
        if (!empty($date_naissance)) {
            $birthDate = new \DateTime($date_naissance);
            $today = new \DateTime();
            $age = $today->diff($birthDate)->y;
        }

        // Validation
        $errors = [];
        if (empty($nom)) {
            $errors[] = 'Le nom est obligatoire';
        }
        if (empty($prenom)) {
            $errors[] = 'Le prénom est obligatoire';
        }
        if (empty($telephone)) {
            $errors[] = 'Le téléphone est obligatoire';
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old_input'] = $_POST;
            $this->redirect("/clients/modifier?id={$id}&return_to=" . urlencode($returnTo));
            return;
        }

        try {
            // Récupérer les anciennes valeurs pour l'audit
            $sql = "SELECT * FROM clients WHERE id = :id AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $oldClient = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$oldClient) {
                $_SESSION['errors'] = ['Client non trouvé'];
                $this->redirect('/clients');
                return;
            }

            // Mettre à jour le client avec tous les champs
            $sql = "UPDATE clients
                    SET nom = :nom,
                        prenom = :prenom,
                        matricule = :matricule,
                        date_naissance = :date_naissance,
                        age = :age,
                        telephone = :telephone,
                        telephone_secondaire = :telephone_secondaire,
                        email = :email,
                        adresse = :adresse,
                        ville = :ville,
                        type_client = :type_client,
                        numero_assurance = :numero_assurance,
                        compagnie_assurance = :compagnie_assurance,
                        numero_ifu = :numero_ifu,
                        numero_rccm = :numero_rccm,
                        plafond_credit = :plafond_credit,
                        notes = :notes,
                        is_actif = :is_actif,
                        utilisateur_modification_id = :utilisateur_modification_id,
                        updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'matricule' => $matricule ?: null,
                'date_naissance' => $date_naissance ?: null,
                'age' => $age,
                'telephone' => $telephone,
                'telephone_secondaire' => $telephone_secondaire ?: null,
                'email' => $email ?: null,
                'adresse' => $adresse ?: null,
                'ville' => $ville ?: null,
                'type_client' => $type_client,
                'numero_assurance' => $numero_assurance ?: null,
                'compagnie_assurance' => $compagnie_assurance ?: null,
                'numero_ifu' => $numero_ifu ?: null,
                'numero_rccm' => $numero_rccm ?: null,
                'plafond_credit' => $plafond_credit,
                'notes' => $notes ?: null,
                'is_actif' => $is_actif,
                'utilisateur_modification_id' => $_SESSION['user_id'] ?? null,
                'id' => $id
            ]);

            // Journaliser l'action
            $this->auditService->logAction(
                $_SESSION['user_id'] ?? null,
                'UPDATE',
                'clients',
                $id,
                $oldClient,
                [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'telephone' => $telephone,
                    'email' => $email,
                    'adresse' => $adresse,
                    'type_client' => $type_client,
                    'numero_assurance' => $numero_assurance,
                    'compagnie_assurance' => $compagnie_assurance,
                    'plafond_credit' => $plafond_credit,
                    'is_actif' => $is_actif
                ],
                null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            );

            $_SESSION['success'] = 'Client mis à jour avec succès';
            $this->redirect($returnTo);

        } catch (\Exception $e) {
            error_log("ClientController::update - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors de la mise à jour du client'];
            $_SESSION['old_input'] = $_POST;
            $this->redirect("/clients/modifier?id={$id}&return_to=" . urlencode($returnTo));
        }
    }

    /**
     * Génère un code client unique automatiquement
     */
    /**
     * Affiche les statistiques globales des clients.
     */
    public function statistiques(): void
    {
        $this->requireClientReadAccess();

        try {
            $sql = "SELECT
                        COUNT(*) as total_clients,
                        COUNT(CASE WHEN type_client = 'ORDINAIRE' THEN 1 END) as ordinaires,
                        COUNT(CASE WHEN type_client = 'COURANT' THEN 1 END) as courants,
                        COUNT(CASE WHEN type_client = 'COURANT_DEPOT' THEN 1 END) as courants_depot,
                        COUNT(CASE WHEN type_client = 'COURANT_BON' THEN 1 END) as courants_bon,
                        COUNT(CASE WHEN type_client = 'COURANT_CARNET' THEN 1 END) as courants_carnet,
                        COUNT(CASE WHEN type_client = 'AUTRES_CLIENTS' THEN 1 END) as autres_clients,
                        COUNT(CASE WHEN solde_credit > 0 THEN 1 END) as clients_debiteurs,
                        COALESCE(SUM(solde_credit), 0) as total_debiteur,
                        COALESCE(SUM(plafond_credit), 0) as total_plafond
                    FROM clients
                    WHERE deleted_at IS NULL";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $sql = "SELECT
                        c.id,
                        c.code,
                        c.nom,
                        c.prenom,
                        c.telephone,
                        c.type_client,
                        c.solde_credit,
                        COUNT(v.id) as nombre_achats,
                        COALESCE(SUM(v.montant_net), 0) as total_achats
                    FROM clients c
                    LEFT JOIN ventes v ON c.id = v.client_id AND v.deleted_at IS NULL
                    WHERE c.deleted_at IS NULL
                    GROUP BY c.id, c.code, c.nom, c.prenom, c.telephone, c.type_client, c.solde_credit
                    ORDER BY nombre_achats DESC, total_achats DESC
                    LIMIT 10";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $clientsActifs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->render('clients/statistiques', [
                'title' => 'Statistiques Clients',
                'stats' => $stats,
                'clients_actifs' => $clientsActifs,
                'user' => $this->getCurrentUser()
            ]);

        } catch (\Exception $e) {
            error_log("ClientController::statistiques - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors du chargement des statistiques clients'];
            $this->redirect('/clients');
        }
    }

    public function rechercher(): void
    {
        $this->requireClientReadAccess();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $query = trim((string)($_GET['q'] ?? ''));
            $typeFilter = trim((string)($_GET['type'] ?? ''));
            $params = [];

            // The database uses code, plafond_credit and notes (not legacy aliases).
            $sql = 'SELECT id, code, code AS code_client, matricule, nom, prenom, date_naissance, age, telephone, telephone_secondaire, email, adresse, ville, numero_ifu, numero_rccm, numero_assurance, compagnie_assurance, type_client, is_actif, plafond_credit AS plafond, solde_initial, COALESCE(solde_credit, solde, 0) AS solde, notes, notes AS observations, created_at, updated_at FROM clients WHERE deleted_at IS NULL';

            if ($query !== '') {
                $sql .= ' AND (
                    code LIKE ? OR nom LIKE ? OR prenom LIKE ?
                    OR telephone LIKE ? OR email LIKE ? OR matricule LIKE ?
                )';
                $like = '%' . $query . '%';
                array_push($params, $like, $like, $like, $like, $like, $like);
            }

            if ($typeFilter !== '') {
                $sql .= ' AND type_client = ?';
                $params[] = $this->normalizeClientType($typeFilter);
            }

            $sql .= ' ORDER BY created_at DESC LIMIT 100';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            echo json_encode([
                'success' => true,
                'clients' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('ClientController::rechercher - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function verifierPlafond(): void
    {
        $this->requireClientReadAccess();
        header('Content-Type: application/json');

        try {
            $clientId = (int)($_GET['client_id'] ?? 0);
            $montant = (float)($_GET['montant'] ?? 0);

            $stmt = $this->db->prepare("SELECT id, plafond_credit, solde_credit FROM clients WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$client) {
                echo json_encode(['success' => false, 'message' => 'Client introuvable']);
                return;
            }

            $solde = (float)($client['solde_credit'] ?? 0);
            $plafond = (float)($client['plafond_credit'] ?? 0);
            $disponible = max(0, $plafond - $solde);
            $peutAcheter = $montant <= $disponible;

            echo json_encode([
                'success' => true,
                'solde_actuel' => $solde,
                'plafond_credit' => $plafond,
                'credit_disponible' => $disponible,
                'peut_acheter' => $peutAcheter,
                'message' => $peutAcheter ? 'Plafond suffisant' : 'Plafond insuffisant',
            ]);
        } catch (\Exception $e) {
            error_log("ClientController::verifierPlafond - " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la verification']);
        }
    }

    public function desactiver(): void
    {
        $this->requireAdminClientDeleteAccess();
        $returnTo = $this->getSafeReturnUrl('/clients');
        $clientId = (int)($_POST['client_id'] ?? 0);

        if ($clientId <= 0) {
            $_SESSION['errors'] = ['ID client non fourni'];
            $this->redirect($returnTo);
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$clientId]);
            $oldClient = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$oldClient) {
                $_SESSION['errors'] = ['Client introuvable'];
                $this->redirect($returnTo);
                return;
            }

            $stmt = $this->db->prepare("UPDATE clients SET is_actif = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$clientId]);

            $this->auditService->logAction(
                $_SESSION['user_id'] ?? null,
                'DELETE_CLIENT',
                'clients',
                $clientId,
                $oldClient,
                ['deleted_at' => date('Y-m-d H:i:s')],
                null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            );

            $_SESSION['success'] = 'Client supprime avec succes';
        } catch (\Exception $e) {
            error_log("ClientController::desactiver - " . $e->getMessage());
            $_SESSION['errors'] = ['Erreur lors de la suppression du client'];
        }

        $this->redirect($returnTo);
    }

    private function generateClientCode(): string
    {
        do {
            $code = 'CLT' . date('YmdHis') . rand(100, 999);
            
            // Vérifier si le code existe déjà
            $sql = "SELECT COUNT(*) as count FROM clients WHERE code = :code";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['code' => $code]);
            $exists = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] > 0;
            
        } while ($exists);

        return $code;
    }

    /**
     * Autorise explicitement Admin, Vendeur et Assistant a creer des clients.
     */
    private function requireClientCreateAccess(): void
    {
        $this->requireClientWriteAccess('client.create');
    }

    /**
     * Autorise explicitement Admin, Vendeur et Assistant a consulter les clients.
     */
    private function requireClientReadAccess(): void
    {
        $this->denyChargeCommandeRestrictedModules();
        $this->requirePermission('client.view');
    }

    /**
     * Autorise explicitement Admin, Vendeur et Assistant a gerer les clients.
     */
    private function requireClientWriteAccess(string $fallbackPermission): void
    {
        $this->denyChargeCommandeRestrictedModules();
        $this->requirePermission($fallbackPermission);
    }

    /**
     * Autorise explicitement Admin, Vendeur et Assistant a gerer le crédit client.
     */
    private function requireClientManageAccess(): void
    {
        $this->denyChargeCommandeRestrictedModules();
        $this->requirePermission('client.manage');
    }

    private function requireAdminClientDeleteAccess(): void
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();
        $role = $user['role'] ?? '';
        $roleId = (int)($user['role_id'] ?? 0);

        if ($roleId === 1 || in_array($role, ['ADMIN', 'ADMINISTRATEUR'], true)) {
            return;
        }

        http_response_code(403);
        echo "Acces refuse";
        exit;
    }

    private function normalizeClientType($type): string
    {
        return Client::normalizeClientType($type);
    }

    /**
     * Page de gestion du crédit client
     */
    public function credit(): void
    {
        $this->requireClientManageAccess();
        
        try {
            $sql = "SELECT c.*,
                           COALESCE(SUM(CASE WHEN v.is_credit = 1 AND v.statut_vente != 'ANNULEE' THEN v.montant_restant ELSE 0 END), 0) AS reste_a_payer,
                           COALESCE(SUM(CASE WHEN v.statut_vente != 'ANNULEE' THEN v.montant_paye ELSE 0 END), 0) AS montant_paye,
                           COALESCE(SUM(CASE WHEN v.is_credit = 1 AND v.statut_vente != 'ANNULEE' THEN v.montant_net ELSE 0 END), 0) AS credit_total
                    FROM clients c
                    LEFT JOIN ventes v ON c.id = v.client_id
                    WHERE c.deleted_at IS NULL
                    GROUP BY c.id
                    HAVING reste_a_payer > 0 OR c.solde_credit > 0
                    ORDER BY c.nom";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('clients/credit', [
                'title' => 'Gestion Crédit Client',
                'clients' => $clients,
                'user' => $this->currentUser
            ]);
            
        } catch (\Exception $e) {
            error_log("ClientController::credit - " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors du chargement de la gestion de crédit';
            $this->redirect('/clients');
        }
    }
}
