<?php

namespace App\Middleware;

use App\Services\RolePermissionService;
use App\Services\AssistantAuthService;
use Exception;

class RolePermissionMiddleware
{
    private RolePermissionService $rolePermissionService;
    private AssistantAuthService $assistantAuthService;

    public function __construct(
        RolePermissionService $rolePermissionService,
        AssistantAuthService $assistantAuthService
    ) {
        $this->rolePermissionService = $rolePermissionService;
        $this->assistantAuthService = $assistantAuthService;
    }

    /**
     * Vérifie les permissions pour une action spécifique
     */
    public function checkPermission(int $userId, string $action, ?string $assistantCode = null): array
    {
        try {
            // Vérifier si l'utilisateur existe et est actif
            if (!$this->isUserActive($userId)) {
                return [
                    'allowed' => false,
                    'message' => 'Utilisateur inexistant ou inactif',
                    'redirect' => '/login'
                ];
            }

            // Vérification des permissions de base
            $permissionCheck = $this->rolePermissionService->checkActionPermission($userId, $action);
            
            if (!$permissionCheck['allowed']) {
                return [
                    'allowed' => false,
                    'message' => 'Permission refusée: ' . $permissionCheck['message'],
                    'permission' => $permissionCheck['permission']
                ];
            }

            // Vérifications spécifiques pour l'assistant
            if ($this->rolePermissionService->isAssistant($userId)) {
                return $this->checkAssistantPermissions($userId, $action, $assistantCode);
            }

            // Vérifications spécifiques pour le vendeur
            if ($this->rolePermissionService->isVendeur($userId)) {
                return $this->checkVendeurPermissions($userId, $action);
            }

            // Vérifications spécifiques pour le chargé de commande
            if ($this->rolePermissionService->isChargeCommande($userId)) {
                return $this->checkChargeCommandePermissions($userId, $action);
            }

            // Administrateur : accès complet
            return [
                'allowed' => true,
                'message' => 'Accès autorisé',
                'user_role' => 'administrateur'
            ];

        } catch (Exception $e) {
            return [
                'allowed' => false,
                'message' => 'Erreur lors de la vérification des permissions: ' . $e->getMessage(),
                'error' => true
            ];
        }
    }

    /**
     * Vérifications spécifiques pour le rôle assistant
     */
    private function checkAssistantPermissions(int $userId, string $action, ?string $assistantCode): array
    {
        // Actions nécessitant le code caisse (type 1)
        $caisseActions = ['vente', 'session_change', 'date_change'];
        
        // Actions nécessitant le code avancé (type 2)
        $avanceActions = [
            'commande_manage', 'remise_apply', 'ticket_annuler', 'vente_corriger',
            'caisse_arret', 'facture_imprimer', 'commande_preparer', 
            'statistiques_view', 'stock_consulter'
        ];

        if (in_array($action, $caisseActions)) {
            if (!$assistantCode) {
                return [
                    'allowed' => false,
                    'message' => 'Code d\'accès caisse requis',
                    'require_code' => 'caisse'
                ];
            }

            $verification = $this->assistantAuthService->verifyAssistantCode($userId, $assistantCode, 'caisse');
            if (!$verification['success']) {
                return [
                    'allowed' => false,
                    'message' => 'Code d\'accès caisse invalide: ' . $verification['message'],
                    'require_code' => 'caisse'
                ];
            }

            return [
                'allowed' => true,
                'message' => 'Accès caisse autorisé',
                'access_level' => 'caisse',
                'user_role' => 'assistant'
            ];
        }

        if (in_array($action, $avanceActions)) {
            if (!$assistantCode) {
                return [
                    'allowed' => false,
                    'message' => 'Code d\'accès avancé requis',
                    'require_code' => 'avance'
                ];
            }

            $verification = $this->assistantAuthService->verifyAssistantCode($userId, $assistantCode, 'avance');
            if (!$verification['success']) {
                return [
                    'allowed' => false,
                    'message' => 'Code d\'accès avancé invalide: ' . $verification['message'],
                    'require_code' => 'avance'
                ];
            }

            return [
                'allowed' => true,
                'message' => 'Accès avancé autorisé',
                'access_level' => 'avance',
                'user_role' => 'assistant'
            ];
        }

        return [
            'allowed' => false,
            'message' => 'Action non autorisée pour le rôle assistant'
        ];
    }

    /**
     * Vérifications spécifiques pour le rôle vendeur
     */
    private function checkVendeurPermissions(int $userId, string $action): array
    {
        $allowedActions = ['vente', 'session_change', 'date_change'];

        if (!in_array($action, $allowedActions)) {
            return [
                'allowed' => false,
                'message' => 'Action non autorisée pour le rôle vendeur',
                'allowed_actions' => $allowedActions
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Accès autorisé',
            'user_role' => 'vendeur'
        ];
    }

    /**
     * Vérifications spécifiques pour le rôle chargé de commande
     */
    private function checkChargeCommandePermissions(int $userId, string $action): array
    {
        $allowedActions = [
            'view_stock',
            'add_stock',
            'receive_products',
            'create_supplier_orders',
            'view_stock_movements',
            'stock.create_product',
            'product.price.update',
            'product.price.history',
            'stock.update_product',
            'stock.adjust',
            'stock.view_expiry',
            'view_supplier_orders',
            'edit_supplier_orders',
            'send_supplier_orders',
            'stock.view',
            'stock.create',
            'commande.view',
            'commande.create',
        ];

        if (!in_array($action, $allowedActions)) {
            return [
                'allowed' => false,
                'message' => 'Action non autorisée pour le rôle chargé de commande',
                'allowed_actions' => $allowedActions
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Accès autorisé',
            'user_role' => 'charge_commande'
        ];
    }

    /**
     * Vérifie si un utilisateur est actif
     */
    private function isUserActive(int $userId): bool
    {
        try {
            $db = $this->rolePermissionService->getDb();
            $sql = "SELECT is_active FROM utilisateurs WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return $result && $result['is_active'] == 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Middleware principal pour les routes
     */
    public function handle(string $action, ?string $assistantCode = null): callable
    {
        return function() use ($action, $assistantCode) {
            // Récupérer l'utilisateur connecté
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Utilisateur non connecté',
                    'redirect' => '/login'
                ]);
                exit;
            }

            // Vérifier les permissions
            $check = $this->checkPermission($userId, $action, $assistantCode);
            
            if (!$check['allowed']) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => $check['message'],
                    'require_code' => $check['require_code'] ?? null,
                    'permission' => $check['permission'] ?? null
                ]);
                exit;
            }

            // Ajouter les infos de permission à la session
            $_SESSION['user_permissions'] = $check;
            
            return true;
        };
    }

    /**
     * Vérifie les permissions pour les requêtes AJAX
     */
    public function checkAjaxPermission(string $action, ?string $assistantCode = null): array
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return [
                'allowed' => false,
                'message' => 'Utilisateur non connecté',
                'redirect' => '/login'
            ];
        }

        return $this->checkPermission($userId, $action, $assistantCode);
    }

    /**
     * Vérifie les permissions multiples
     */
    public function checkMultiplePermissions(array $actions, ?string $assistantCode = null): array
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return [
                'allowed' => false,
                'message' => 'Utilisateur non connecté'
            ];
        }

        $results = [];
        foreach ($actions as $action) {
            $results[$action] = $this->checkPermission($userId, $action, $assistantCode);
        }

        return $results;
    }

    /**
     * Génère un formulaire de demande de code d'accès
     */
    public function generateCodeRequestForm(string $requiredCode): string
    {
        $formType = ($requiredCode === 'caisse') ? 'caisse' : 'avance';
        $title = ($requiredCode === 'caisse') ? 'Code d\'accès caisse' : 'Code d\'accès avancé';
        
        return '
        <div id="codeModal" class="modal" style="display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
            <div class="modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 300px;">
                <h3>' . $title . '</h3>
                <form id="codeForm">
                    <div class="form-group">
                        <label for="authCode">Code d\'accès:</label>
                        <input type="password" id="authCode" name="authCode" required maxlength="6" pattern="[0-9]{6}" placeholder="000000">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Valider</button>
                        <button type="button" class="btn btn-secondary" onclick="closeCodeModal()">Annuler</button>
                    </div>
                    <div id="codeError" class="error-message" style="color: red; margin-top: 10px; display: none;"></div>
                </form>
            </div>
        </div>
        
        <script>
        function closeCodeModal() {
            document.getElementById("codeModal").style.display = "none";
        }
        
        document.getElementById("codeForm").addEventListener("submit", function(e) {
            e.preventDefault();
            const code = document.getElementById("authCode").value;
            
            fetch("/api/verify-code", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    code: code,
                    type: "' . $formType . '"
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeCodeModal();
                    // Recharger la page ou continuer l\'action
                    window.location.reload();
                } else {
                    document.getElementById("codeError").textContent = data.message;
                    document.getElementById("codeError").style.display = "block";
                }
            })
            .catch(error => {
                document.getElementById("codeError").textContent = "Erreur de connexion";
                document.getElementById("codeError").style.display = "block";
            });
        });
        </script>';
    }

    /**
     * Enregistre une tentative d'accès non autorisée
     */
    public function logUnauthorizedAccess(int $userId, string $action, string $reason): void
    {
        try {
            $db = $this->rolePermissionService->getDb();
            $sql = "INSERT INTO audit_logs 
                    (utilisateur_id, action, table_name, new_values, ip_address, user_agent) 
                    VALUES (?, 'UNAUTHORIZED_ACCESS', 'system', ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $userId,
                json_encode(['action' => $action, 'reason' => $reason]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Erreur log unauthorized access: " . $e->getMessage());
        }
    }

    /**
     * Vérifie si une session de caisse est requise pour l'action
     */
    public function requiresCaisseSession(string $action): bool
    {
        $caisseRequiredActions = [
            'vente', 'ticket_annuler', 'vente_corriger', 
            'facture_imprimer', 'caisse_arret'
        ];
        
        return in_array($action, $caisseRequiredActions);
    }

    /**
     * Vérifie la session de caisse active
     */
    public function checkCaisseSession(int $userId): array
    {
        try {
            $db = $this->rolePermissionService->getDb();
            $sql = "SELECT session_number, statut FROM caisse_sessions 
                    WHERE utilisateur_id = ? AND statut = 'OUVERTE'";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$session) {
                return [
                    'has_session' => false,
                    'message' => 'Aucune session de caisse active'
                ];
            }
            
            return [
                'has_session' => true,
                'session_number' => $session['session_number'],
                'message' => 'Session ' . $session['session_number'] . ' active'
            ];
            
        } catch (Exception $e) {
            return [
                'has_session' => false,
                'message' => 'Erreur lors de la vérification de session: ' . $e->getMessage()
            ];
        }
    }
}
