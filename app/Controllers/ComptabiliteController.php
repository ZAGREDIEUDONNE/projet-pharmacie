<?php

namespace App\Controllers;

use App\Services\PlanComptableService;
use App\Services\JournalComptableService;
use App\Services\GrandLivreService;
use App\Services\BalanceGeneraleService;
use App\Services\EtatsFinanciersService;
use App\Services\SuiviTiersService;
use App\Services\TVAService;
use App\Services\IntegrationComptableService;
use App\Services\CsrfService;
use App\Core\BaseController;
use Exception;

class ComptabiliteController extends BaseController
{
    private PlanComptableService $planComptableService;
    private JournalComptableService $journalService;
    private GrandLivreService $grandLivreService;
    private BalanceGeneraleService $balanceService;
    private EtatsFinanciersService $etatsFinanciersService;
    private SuiviTiersService $suiviTiersService;
    private TVAService $tvaService;
    private IntegrationComptableService $integrationService;

    public function __construct(
        ?PlanComptableService $planComptableService = null,
        ?JournalComptableService $journalService = null,
        ?GrandLivreService $grandLivreService = null,
        ?BalanceGeneraleService $balanceService = null,
        ?EtatsFinanciersService $etatsFinanciersService = null,
        ?SuiviTiersService $suiviTiersService = null,
        ?TVAService $tvaService = null,
        ?IntegrationComptableService $integrationService = null
    ) {
        parent::__construct();

        $auditService = new \App\Services\AuditService($this->db);
        $journalService = $journalService ?? new JournalComptableService($this->db, $auditService);

        $this->planComptableService = $planComptableService ?? new PlanComptableService($this->db);
        $this->journalService = $journalService;
        $this->grandLivreService = $grandLivreService ?? new GrandLivreService($this->db, $auditService);
        $this->balanceService = $balanceService ?? new BalanceGeneraleService($this->db, $auditService);
        $this->etatsFinanciersService = $etatsFinanciersService ?? new EtatsFinanciersService($this->db, $auditService);
        $this->suiviTiersService = $suiviTiersService ?? new SuiviTiersService($this->db, $auditService);
        $this->tvaService = $tvaService ?? new TVAService($this->db, $auditService);

        if ($integrationService) {
            $this->integrationService = $integrationService;
        } else {
            $ecritureService = new \App\Services\EcritureComptableService($this->db, $auditService, $journalService);
            $this->integrationService = new IntegrationComptableService($this->db, $ecritureService, $auditService);
        }
    }

    /**
     * Extrait la base de données d'un service
     */
    private function getDatabaseFromServices($service): \PDO
    {
        // Utiliser reflection pour obtenir la propriété db
        $reflection = new \ReflectionClass($service);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        return $dbProperty->getValue($service);
    }

    private function requireComptabiliteAccess(string $permission = 'comptabilite_view'): void
    {
        $this->requirePermission($permission);
    }

    /**
     * Page d'accueil de la comptabilité
     */
    public function index(): void
    {
        $this->requireComptabiliteAccess();

        try {
            $dateFin = date('Y-m-d');
            $planStats = $this->planComptableService->getStatistiquesPlanComptable();
            $balanceCheck = $this->balanceService->verifierEquilibre($dateFin);
            $etatsStats = $this->getStatsEtatsFinanciers();
            $tiersStats = $this->getStatsTiers();

            $stats = [
                'plan_comptable' => [
                    'total_comptes' => (int)($planStats['comptes_actifs'] ?? $planStats['total_comptes'] ?? 0),
                ],
                'balance' => [
                    'equilibre' => (bool)($balanceCheck['equilibre'] ?? false),
                    'ecart' => (float)($balanceCheck['ecart'] ?? 0),
                ],
                'etats_financiers' => [
                    'resultat_exploitation' => (float)($etatsStats['resultat_exploitation'] ?? 0),
                ],
                'tiers' => [
                    'total_clients' => (int)($tiersStats['total_clients'] ?? 0),
                    'total_fournisseurs' => (int)($tiersStats['total_fournisseurs'] ?? 0),
                ],
                'exercices' => $this->getStatsExercices(),
                'kpis' => $this->getDashboardKpis($dateFin),
                'equilibre' => (bool)($balanceCheck['equilibre'] ?? false),
                'ecart' => (float)($balanceCheck['ecart'] ?? 0),
                'total_actif' => (float)($etatsStats['total_actif'] ?? 0),
                'total_passif' => (float)($etatsStats['total_passif'] ?? 0),
            ];

            $activitesRecentes = $this->getActivitesRecentes();

            $this->render('comptabilite/index', [
                'stats' => $stats,
                'activitesRecentes' => $activitesRecentes,
            ]);
        } catch (Exception $e) {
            $this->render('comptabilite/index', [
                'stats' => ['plan_comptable' => ['total_comptes' => 0], 'balance' => ['equilibre' => false, 'ecart' => 0]],
                'activitesRecentes' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Plan comptable
     */
    public function planComptable(): void
    {
        $this->requireComptabiliteAccess('plan_comptable_view');

        try {
            $allComptes = $this->planComptableService->getPlanComptable();
            $classes = $this->getClassesComptables();
            $classeFilter = $_GET['classe'] ?? '';
            $search = trim($_GET['q'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(10, min(100, (int)($_GET['per_page'] ?? 20)));

            $filtered = array_values(array_filter($allComptes, function (array $compte) use ($classeFilter, $search): bool {
                $numeroCompte = (string)($compte['numero_compte'] ?? '');
                $libelle = (string)($compte['libelle'] ?? $compte['nom_compte'] ?? '');
                $classe = (string)($compte['classe_id'] ?? $compte['classe'] ?? '');

                if ($classeFilter !== '' && $classe !== (string)$classeFilter) {
                    return false;
                }
                if ($search !== '') {
                    $haystack = strtolower($numeroCompte . ' ' . $libelle);
                    if (strpos($haystack, strtolower($search)) === false) {
                        return false;
                    }
                }

                return true;
            }));

            $totalComptes = count($filtered);
            $offset = ($page - 1) * $perPage;
            $planComptable = array_slice($filtered, $offset, $perPage);

            $this->render('comptabilite/plan_comptable', [
                'planComptable' => $planComptable,
                'classes' => $classes,
                'classeFilter' => $classeFilter,
                'search' => $search,
                'page' => $page,
                'perPage' => $perPage,
                'totalComptes' => $totalComptes,
            ]);
        } catch (Exception $e) {
            $this->render('comptabilite/plan_comptable', [
                'planComptable' => [],
                'classes' => $this->getClassesComptables(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Journaux comptables
     */
    public function journaux(): void
    {
        $this->requireComptabiliteAccess('journaux_view');

        $journaux = $this->journalService->getJournaux();
        $ecritures = [];
        
        if (isset($_GET['journal']) && isset($_GET['date_debut']) && isset($_GET['date_fin'])) {
            $ecritures = $this->journalService->getEcrituresJournal(
                $_GET['journal'],
                $_GET['date_debut'],
                $_GET['date_fin']
            );
        }

        require_once __DIR__ . '/../Views/comptabilite/journaux.php';
    }

    /**
     * Journal des ventes
     */
    public function journalVentes(): void
    {
        $this->requireComptabiliteAccess('journaux_view');

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $search = $_GET['search'] ?? '';

        $ecritures = $this->journalService->getEcrituresJournal('VT', $dateDebut, $dateFin);
        
        // Récupérer les lignes d'écritures pour chaque écriture
        foreach ($ecritures as &$ecriture) {
            $ecriture['lignes'] = $this->journalService->getLignesEcriture((int)$ecriture['id']);
        }

        // Filtrer par recherche si nécessaire
        if ($search) {
            $ecritures = array_filter($ecritures, function($ecriture) use ($search) {
                return stripos($ecriture['numero_piece'], $search) !== false ||
                       stripos($ecriture['libelle'], $search) !== false ||
                       stripos($ecriture['reference_type'] . ' #' . $ecriture['reference_id'], $search) !== false;
            });
        }

        require_once __DIR__ . '/../Views/comptabilite/journal_ventes.php';
    }

    /**
     * Journal des achats
     */
    public function journalAchats(): void
    {
        $this->requireComptabiliteAccess('journaux_view');

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $search = $_GET['search'] ?? '';

        $ecritures = $this->journalService->getEcrituresJournal('AC', $dateDebut, $dateFin);
        
        // Récupérer les lignes d'écritures pour chaque écriture
        foreach ($ecritures as &$ecriture) {
            $ecriture['lignes'] = $this->journalService->getLignesEcriture((int)$ecriture['id']);
        }

        // Filtrer par recherche si nécessaire
        if ($search) {
            $ecritures = array_filter($ecritures, function($ecriture) use ($search) {
                return stripos($ecriture['numero_piece'], $search) !== false ||
                       stripos($ecriture['libelle'], $search) !== false ||
                       stripos($ecriture['reference_type'] . ' #' . $ecriture['reference_id'], $search) !== false;
            });
        }

        require_once __DIR__ . '/../Views/comptabilite/journal_achats.php';
    }

    /**
     * Journal de caisse
     */
    public function journalCaisse(): void
    {
        $this->requireComptabiliteAccess('journaux_view');

        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $search = $_GET['search'] ?? '';

        $ecritures = $this->journalService->getEcrituresJournal('CA', $dateDebut, $dateFin);
        
        // Récupérer les lignes d'écritures pour chaque écriture
        foreach ($ecritures as &$ecriture) {
            $ecriture['lignes'] = $this->journalService->getLignesEcriture((int)$ecriture['id']);
        }

        // Filtrer par recherche si nécessaire
        if ($search) {
            $ecritures = array_filter($ecritures, function($ecriture) use ($search) {
                return stripos($ecriture['numero_piece'], $search) !== false ||
                       stripos($ecriture['libelle'], $search) !== false ||
                       stripos($ecriture['reference_type'] . ' #' . $ecriture['reference_id'], $search) !== false;
            });
        }

        // Calculer le solde progressif
        $solde = 0;
        foreach ($ecritures as &$ecriture) {
            $totalDebit = (float)($ecriture['total_debit'] ?? 0);
            $totalCredit = (float)($ecriture['total_credit'] ?? 0);
            $solde += ($totalDebit - $totalCredit);
            $ecriture['solde'] = $solde;
        }

        require_once __DIR__ . '/../Views/comptabilite/journal_caisses.php';
    }

    /**
     * Grand livre
     */
    public function grandLivre(): void
    {
        $this->requireComptabiliteAccess('grand_livre_view');
        $grandLivre = null;
        $compte = $_GET['compte'] ?? null;
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin = $_GET['date_fin'] ?? null;

        if ($compte) {
            $grandLivre = $this->grandLivreService->getGrandLivreCompte($compte, $dateDebut, $dateFin);
        }

        $planComptable = $this->planComptableService->getPlanComptable();

        require_once __DIR__ . '/../Views/comptabilite/grand_livre.php';
    }

    /**
     * Balance générale
     */
    public function balance(): void
    {
        $this->requireComptabiliteAccess('balance_view');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
        $balance = $this->balanceService->genererBalance($dateFin);
        $equilibre = $this->balanceService->verifierEquilibre($dateFin);

        require_once __DIR__ . '/../Views/comptabilite/balance.php';
    }

    /**
     * États financiers
     */
    public function etatsFinanciers(): void
    {
        $this->requireComptabiliteAccess('etats_financiers_view');
        $type = $_GET['type'] ?? 'compte_resultat';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $etat = null;
        switch ($type) {
            case 'compte_resultat':
                $etat = $this->etatsFinanciersService->genererCompteResultat($dateDebut, $dateFin);
                break;
            case 'bilan':
                $etat = $this->etatsFinanciersService->genererBilan($dateFin);
                break;
            case 'tresorerie':
                $etat = $this->etatsFinanciersService->genererTableauTresorerie($dateDebut, $dateFin);
                break;
            case 'tva':
                $etat = $this->tvaService->genererRapportTVA($dateDebut, $dateFin);
                break;
            case 'analyse_financiere':
                $etat = $this->etatsFinanciersService->genererAnalyseFinanciere($dateDebut, $dateFin);
                break;
        }

        require_once __DIR__ . '/../Views/comptabilite/etats_financiers.php';
    }

    /**
     * Suivi des tiers
     */
    public function suiviTiers(): void
    {
        $this->requireComptabiliteAccess('suivi_tiers_view');
        $type = $_GET['type'] ?? 'clients';
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $suivi = null;
        switch ($type) {
            case 'clients':
                $suivi = $this->suiviTiersService->getSuiviClients($dateFin);
                break;
            case 'fournisseurs':
                $suivi = $this->suiviTiersService->getSuiviFournisseurs($dateFin);
                break;
            case 'age_creances':
                $suivi = $this->suiviTiersService->getAgeCreancesClients();
                break;
            case 'age_dettes':
                $suivi = $this->suiviTiersService->getAgeDettesFournisseurs();
                break;
            case 'rapport':
                $suivi = $this->suiviTiersService->genererRapportTiers(
                    $_GET['date_debut'] ?? date('Y-m-01'),
                    $dateFin
                );
                break;
        }

        require_once __DIR__ . '/../Views/comptabilite/suivi_tiers.php';
    }

    /**
     * Gestion TVA
     */
    public function tva(): void
    {
        $this->requireComptabiliteAccess('tva_view');
        $action = $_GET['action'] ?? 'declaration';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $donnees = null;
        switch ($action) {
            case 'declaration':
                $donnees = $this->tvaService->genererDeclarationTVA($dateDebut, $dateFin);
                break;
            case 'rapport':
                $donnees = $this->tvaService->genererRapportTVA($dateDebut, $dateFin);
                break;
            case 'taux':
                $donnees = $this->tvaService->getTauxTVAActifs();
                break;
        }

        require_once __DIR__ . '/../Views/comptabilite/tva.php';
    }

    /**
     * Intégration comptable
     */
    public function integration(): void
    {
        $this->requireComptabiliteAccess('integration_view');
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $input = $this->requestInput($isPost);
        $action = $isPost ? ($input['action'] ?? '') : 'etat';
        $dateDebut = $isPost ? ($input['date_debut'] ?? date('Y-m-01')) : ($_GET['date_debut'] ?? date('Y-m-01'));
        $dateFin = $isPost ? ($input['date_fin'] ?? date('Y-m-d')) : ($_GET['date_fin'] ?? date('Y-m-d'));

        $resultat = null;
        if ($isPost && !in_array($action, ['etat', 'ventes', 'commandes', 'caisse', 'stock', 'complet', 'forcer'], true)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Action d’intégration invalide']);
            return;
        }
        if ($isPost && !CsrfService::isValid($input['csrf_token'] ?? null)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Token CSRF invalide ou manquant', 'code' => 'CSRF_INVALID']);
            return;
        }
        switch ($action) {
            case 'etat':
                $resultat = $this->integrationService->verifierEtatIntegration($dateFin);
                break;
            case 'ventes':
                $resultat = $this->integrationService->integrerVentes($dateDebut, $dateFin);
                break;
            case 'commandes':
                $resultat = $this->integrationService->integrerCommandes($dateDebut, $dateFin);
                break;
            case 'caisse':
                $resultat = $this->integrationService->integrerMouvementsCaisse($dateDebut, $dateFin);
                break;
            case 'stock':
                $resultat = $this->integrationService->integrerStock($dateDebut, $dateFin);
                break;
            case 'complet':
                $resultat = $this->integrationService->executerToutesIntegrations($dateDebut, $dateFin);
                break;
            case 'forcer':
                $resultat = $this->integrationService->forcerSynchronisation();
                break;
        }

        if ($isPost) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resultat ?? ['success' => false, 'message' => 'Action invalide']);
            return;
        }

        require_once __DIR__ . '/../Views/comptabilite/integration.php';
    }

    /**
     * Export de données
     */
    public function exporter(): void
    {
        $this->requireComptabiliteAccess('comptabilite_view');
        $type = $_GET['type'] ?? 'balance';
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        switch ($type) {
            case 'balance':
                $export = $this->balanceService->exporterBalance($dateDebut, $dateFin);
                break;
            case 'grand_livre':
                $compte = $_GET['compte'] ?? '';
                $export = $this->grandLivreService->exporterGrandLivre($compte, $dateDebut, $dateFin);
                break;
            case 'compte_resultat':
                $export = $this->etatsFinanciersService->exporterEtatsFinanciers($dateDebut, $dateFin, 'compte_resultat');
                break;
            case 'bilan':
                $export = $this->etatsFinanciersService->exporterEtatsFinanciers($dateDebut, $dateFin, 'bilan');
                break;
            case 'tresorerie':
                $export = $this->etatsFinanciersService->exporterEtatsFinanciers($dateDebut, $dateFin, 'tresorerie');
                break;
            case 'tva':
                $export = $this->tvaService->exporterDeclarationTVA($dateDebut, $dateFin);
                break;
            case 'clients':
                $export = $this->suiviTiersService->exporterSuiviClients($dateFin);
                break;
            case 'fournisseurs':
                $export = $this->suiviTiersService->exporterSuiviFournisseurs($dateFin);
                break;
            case 'journal':
                $journal = $_GET['journal'] ?? 'VT';
                $export = [
                    'donnees' => $this->journalService->exporterJournal($journal, $dateDebut, $dateFin),
                ];
                break;
            default:
                throw new Exception("Type d'export non valide: $type");
        }

        // Générer le fichier CSV
        $filename = $type . '_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // En-tête CSV
        if (!empty($export['donnees'])) {
            fputcsv($output, array_keys($export['donnees'][0]));
            
            // Données
            foreach ($export['donnees'] as $ligne) {
                fputcsv($output, $ligne);
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * API pour les requêtes AJAX
     */
    public function api(): void
    {
        header('Content-Type: application/json');

        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $input = $this->requestInput($isPost);
        $action = $isPost ? ($input['action'] ?? '') : ($_GET['action'] ?? 'verifier_equilibre');
        $permissions = [
            'initialiser_plan_comptable' => 'plan_comptable_view',
            'creer_journaux' => 'journaux_view',
            'initialiser_tva' => 'tva_view',
            'integrer_ventes' => 'integration_view',
            'integrer_commandes' => 'integration_view',
            'integrer_caisse' => 'integration_view',
            'verifier_equilibre' => 'balance_view',
            'calculer_tva' => 'tva_view',
        ];
        if (!isset($permissions[$action])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action comptable invalide']);
            return;
        }
        $mutations = ['initialiser_plan_comptable', 'creer_journaux', 'initialiser_tva', 'integrer_ventes', 'integrer_commandes', 'integrer_caisse'];
        if (!$isPost && in_array($action, $mutations, true)) {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Cette action exige POST']);
            return;
        }
        if ($isPost && !CsrfService::isValid($input['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF invalide ou manquant', 'code' => 'CSRF_INVALID']);
            return;
        }
        $this->requireComptabiliteAccess($permissions[$action]);
        $reponse = ['success' => false, 'message' => 'Action non reconnue'];

        try {
            switch ($action) {
                case 'initialiser_plan_comptable':
                    $reponse = $this->planComptableService->initialiserPlanComptable();
                    break;
                
                case 'creer_journaux':
                    $reponse = $this->journalService->creerJournauxPrincipaux();
                    break;
                
                case 'initialiser_tva':
                    $reponse = $this->tvaService->initialiserTVA();
                    break;
                
                case 'verifier_equilibre':
                    $dateFin = ($isPost ? ($input['date_fin'] ?? null) : ($_GET['date_fin'] ?? null)) ?? date('Y-m-d');
                    $reponse = $this->balanceService->verifierEquilibre($dateFin);
                    break;
                
                case 'calculer_tva':
                    $montant = floatval(($isPost ? ($input['montant'] ?? null) : ($_GET['montant'] ?? null)) ?? 0);
                    $codeTVA = ($isPost ? ($input['code_tva'] ?? null) : ($_GET['code_tva'] ?? null)) ?? 'TVA_18';
                    $reponse = $this->tvaService->calculerTVA($montant, $codeTVA);
                    break;
                
                case 'integrer_ventes':
                    $dateDebut = $input['date_debut'] ?? null;
                    $dateFin = $input['date_fin'] ?? null;
                    $reponse = $this->integrationService->integrerVentes($dateDebut, $dateFin);
                    break;
                
                case 'integrer_commandes':
                    $dateDebut = $input['date_debut'] ?? null;
                    $dateFin = $input['date_fin'] ?? null;
                    $reponse = $this->integrationService->integrerCommandes($dateDebut, $dateFin);
                    break;
                
                case 'integrer_caisse':
                    $dateDebut = $input['date_debut'] ?? null;
                    $dateFin = $input['date_fin'] ?? null;
                    $reponse = $this->integrationService->integrerMouvementsCaisse($dateDebut, $dateFin);
                    break;
            }
        } catch (Exception $e) {
            $reponse = ['success' => false, 'error' => $e->getMessage()];
        }

        echo json_encode($reponse);
        exit;
    }

    /** @return array<string,mixed> */
    private function requestInput(bool $isPost): array
    {
        if (!$isPost) {
            return $_GET;
        }
        $input = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($input === [] && stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
        return $input;
    }

    /**
     * Récupère les classes de comptes
     */
    private function getClassesComptables(): array
    {
        return [
            '1' => 'Comptes de ressources durables',
            '2' => 'Comptes de valeurs immobilisées',
            '3' => 'Comptes de stocks',
            '4' => 'Comptes de tiers',
            '5' => 'Comptes de trésorerie',
            '6' => 'Comptes des charges',
            '7' => 'Comptes des produits',
            '8' => 'Comptes des autres charges et produits'
        ];
    }

    /**
     * Récupère les statistiques des états financiers
     */
    private function getStatsEtatsFinanciers(): array
    {
        $dateDebut = date('Y-m-01');
        $dateFin = date('Y-m-d');
        
        try {
            $compteResultat = $this->etatsFinanciersService->genererCompteResultat($dateDebut, $dateFin);
            $bilan = $this->etatsFinanciersService->genererBilan($dateFin);
            $tresorerie = $this->etatsFinanciersService->genererTableauTresorerie($dateDebut, $dateFin);
            
            return [
                'resultat_exploitation' => $compteResultat['totaux_generaux']['resultat_exploitation'],
                'total_actif' => $bilan['totaux_generaux']['total_actif'],
                'total_passif' => $bilan['totaux_generaux']['total_passif'],
                'solde_tresorerie' => $tresorerie['totaux_generaux']['solde_final'],
                'equilibre_bilan' => $bilan['totaux_generaux']['equilibre']
            ];
        } catch (Exception $e) {
            return [
                'resultat_exploitation' => 0,
                'total_actif' => 0,
                'total_passif' => 0,
                'solde_tresorerie' => 0,
                'equilibre_bilan' => false
            ];
        }
    }

    /**
     * Récupère les statistiques des tiers
     */
    private function getStatsTiers(): array
    {
        try {
            $clients = $this->suiviTiersService->getSuiviClientsComptable();
            $fournisseurs = $this->suiviTiersService->getSuiviFournisseursComptable();
            
            return [
                'total_clients' => $clients['statistiques']['total_clients'],
                'total_debiteurs' => $clients['statistiques']['total_debiteurs'],
                'total_creances' => $clients['statistiques']['total_creances'],
                'total_fournisseurs' => $fournisseurs['statistiques']['total_fournisseurs'],
                'total_dettes' => $fournisseurs['statistiques']['total_dettes']
            ];
        } catch (Exception $e) {
            return [
                'total_clients' => 0,
                'total_debiteurs' => 0,
                'total_creances' => 0,
                'total_fournisseurs' => 0,
                'total_dettes' => 0
            ];
        }
    }

    /** KPIs sourced from accounting entries and business tables, never literals. */
    private function getDashboardKpis(string $dateFin): array
    {
        $dateDebut = date('Y-m-01');
        $sql = "SELECT
                    (SELECT COALESCE(SUM(total_credit), 0) FROM ecritures_comptables ec_vente
                     WHERE " . \App\Services\ComptabiliteEcritureScope::valide('ec_vente') . "
                     AND reference_type = 'VENTE' AND date_ecriture BETWEEN ? AND ?) AS chiffre_affaires_ttc,
                    (SELECT COUNT(*) FROM ecritures_comptables ec_compte
                     WHERE " . \App\Services\ComptabiliteEcritureScope::valide('ec_compte') . "
                     AND date_ecriture BETWEEN ? AND ?) AS nombre_ecritures,
                    COALESCE(SUM(CASE WHEN le.compte_code = '44571' THEN le.credit ELSE 0 END), 0) AS tva_collectee,
                    COALESCE(SUM(CASE WHEN le.compte_code = '44561' THEN le.debit ELSE 0 END), 0) AS tva_deductible
                FROM lignes_ecritures le
                JOIN ecritures_comptables ec ON ec.id = le.ecriture_id
                WHERE " . \App\Services\ComptabiliteEcritureScope::valide('ec') . "
                AND ec.date_ecriture BETWEEN ? AND ?";
        $stmt = $this->db->prepare($sql);
        $borneFin = $dateFin . ' 23:59:59';
        $stmt->execute([$dateDebut, $borneFin, $dateDebut, $borneFin, $dateDebut, $borneFin]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getStatsExercices(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) AS total_exercices,
            SUM(statut = 'ouvert') AS ouverts,
            MAX(CASE WHEN statut = 'ouvert' THEN exercice END) AS exercice_ouvert
            FROM exercices_comptables");
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return ['total_exercices' => (int)($stats['total_exercices'] ?? 0), 'ouverts' => (int)($stats['ouverts'] ?? 0), 'exercice_ouvert' => $stats['exercice_ouvert'] ?? null];
    }

    private function getActivitesRecentes(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT ec.date_ecriture, ec.reference_type, ec.libelle,
                    ec.total_debit, ec.total_credit, ec.is_equilibree, j.code AS journal_code
             FROM ecritures_comptables ec
             JOIN journaux_comptables j ON ec.journal_id = j.id
             WHERE " . \App\Services\ComptabiliteEcritureScope::valide('ec') . "
             ORDER BY ec.date_ecriture DESC, ec.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
