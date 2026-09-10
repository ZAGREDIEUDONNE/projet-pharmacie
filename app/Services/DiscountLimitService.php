<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Plafonds de remise par rôle (pharmacie Burkina Faso).
 */
class DiscountLimitService
{
    private PDO $db;

    private const DEFAULT_LIMITS = [
        'administrateur' => 25.0,
        'admin' => 25.0,
        'assistant' => 15.0,
        'pharmacien' => 15.0,
        'vendeur' => 10.0,
        'charge_commande' => 0.0,
        'charge de commande' => 0.0,
    ];

    public const ABSOLUTE_MAX_PERCENT = 25.0;
    public const ABSOLUTE_MAX_MESSAGE = 'Remise supérieure au plafond autorisé (25%).';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getMaxDiscountForRole(int $roleId): float
    {
        if ($roleId <= 0) {
            return 0.0;
        }

        if ($this->tableExists('role_discount_limits')) {
            $stmt = $this->db->prepare(
                'SELECT max_discount_percent FROM role_discount_limits WHERE role_id = ? LIMIT 1'
            );
            $stmt->execute([$roleId]);
            $value = $stmt->fetchColumn();
            if ($value !== false) {
                return min(self::ABSOLUTE_MAX_PERCENT, max(0.0, (float)$value));
            }
        }

        $stmt = $this->db->prepare('SELECT LOWER(nom) FROM roles WHERE id = ? LIMIT 1');
        $stmt->execute([$roleId]);
        $roleName = strtolower((string)$stmt->fetchColumn());

        return min(self::ABSOLUTE_MAX_PERCENT, max(0.0, (float)(self::DEFAULT_LIMITS[$roleName] ?? 0.0)));
    }

    public function getMaxDiscountForUser(array $user): float
    {
        $roleId = (int)($user['role_id'] ?? 0);
        if ($roleId > 0) {
            return $this->getMaxDiscountForRole($roleId);
        }

        $roleCode = strtolower((string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? ''));
        return min(self::ABSOLUTE_MAX_PERCENT, max(0.0, (float)(self::DEFAULT_LIMITS[$roleCode] ?? 0.0)));
    }

    public function validateDiscount(float $discount, array $user): float
    {
        $discount = max(0.0, min(100.0, $discount));

        if ($discount > self::ABSOLUTE_MAX_PERCENT) {
            throw new Exception(self::ABSOLUTE_MAX_MESSAGE);
        }

        $limit = $this->getMaxDiscountForUser($user);

        if ($discount > $limit) {
            throw new Exception(
                sprintf('Remise supérieure au plafond autorisé (%.2f %%).', $limit)
            );
        }

        return $discount;
    }

    public function getAllLimits(): array
    {
        if (!$this->tableExists('role_discount_limits')) {
            return [];
        }

        $sql = 'SELECT rdl.*, r.nom AS role_nom
                FROM role_discount_limits rdl
                JOIN roles r ON r.id = rdl.role_id
                ORDER BY r.nom';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateLimit(int $roleId, float $percent): void
    {
        $percent = min(self::ABSOLUTE_MAX_PERCENT, max(0.0, $percent));

        if (!$this->tableExists('role_discount_limits')) {
            throw new Exception('Table role_discount_limits absente. Exécutez la migration 014.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO role_discount_limits (role_id, max_discount_percent)
             VALUES (:role_id, :percent)
             ON DUPLICATE KEY UPDATE max_discount_percent = VALUES(max_discount_percent)'
        );
        $stmt->execute(['role_id' => $roleId, 'percent' => $percent]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
