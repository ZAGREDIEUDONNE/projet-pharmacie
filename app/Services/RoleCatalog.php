<?php

namespace App\Services;

/**
 * Référentiel unique des 5 rôles métier de la pharmacie.
 */
final class RoleCatalog
{
    public const ADMIN_ID = 1;
    public const VENDEUR_ID = 2;
    public const ASSISTANT_ID = 3;
    public const COMMANDE_ID = 4;
    public const COMPTABLE_ID = 6;

    public const ALLOWED_IDS = [
        self::ADMIN_ID,
        self::VENDEUR_ID,
        self::ASSISTANT_ID,
        self::COMMANDE_ID,
        self::COMPTABLE_ID,
    ];

    private const ROLES = [
        self::ADMIN_ID => [
            'nom' => 'administrateur',
            'code' => 'ADMIN',
            'label' => 'Administrateur',
            'dashboard' => '/admin/dashboard',
        ],
        self::VENDEUR_ID => [
            'nom' => 'vendeur',
            'code' => 'VENDEUR',
            'label' => 'Vendeur',
            'dashboard' => '/vente',
        ],
        self::ASSISTANT_ID => [
            'nom' => 'assistant',
            'code' => 'ASSISTANT',
            'label' => 'Assistant',
            'dashboard' => '/assistant/dashboard',
        ],
        self::COMMANDE_ID => [
            'nom' => 'charge_commande',
            'code' => 'CHARGE_COMMANDE',
            'label' => 'Chargé de commande',
            'dashboard' => '/commande/dashboard',
        ],
        self::COMPTABLE_ID => [
            'nom' => 'comptable',
            'code' => 'COMPTABLE',
            'label' => 'Comptable',
            'dashboard' => '/comptabilite',
        ],
    ];

    public static function all(): array
    {
        return self::ROLES;
    }

    public static function get(int $roleId): ?array
    {
        return self::ROLES[$roleId] ?? null;
    }

    public static function normalizeCode(string $role): string
    {
        $role = trim($role);
        if ($role === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $role);
            if ($ascii !== false) {
                $role = $ascii;
            }
        }

        return strtoupper(str_replace([' ', '-'], '_', $role));
    }

    public static function resolveId(int $roleId, string $roleCode = '', string $roleName = ''): int
    {
        if (in_array($roleId, self::ALLOWED_IDS, true) && self::matchesRole($roleId, $roleCode, $roleName)) {
            return $roleId;
        }

        $mapped = self::mapCodeOrNameToId($roleCode !== '' ? $roleCode : $roleName);
        if ($mapped > 0) {
            return $mapped;
        }

        return in_array($roleId, self::ALLOWED_IDS, true) ? $roleId : 0;
    }

    public static function mapCodeOrNameToId(string $value): int
    {
        $code = self::normalizeCode($value);
        $name = strtolower(trim($value));

        foreach (self::ROLES as $id => $role) {
            if ($code === $role['code'] || $name === $role['nom']) {
                return $id;
            }
        }

        if (in_array($code, ['ADMINISTRATEUR', 'ADMIN'], true) || $name === 'admin') {
            return self::ADMIN_ID;
        }
        if (str_contains($code, 'COMMANDE') || in_array($name, ['commande', 'charge de commande'], true)) {
            return self::COMMANDE_ID;
        }
        if ($code === 'PHARMACIEN' || $name === 'pharmacien') {
            return self::COMMANDE_ID;
        }
        if ($code === 'COMPTABLE' || $name === 'comptable') {
            return self::COMPTABLE_ID;
        }

        return 0;
    }

    public static function getDashboard(int $roleId, string $roleCode = '', string $roleName = ''): string
    {
        $resolvedId = self::resolveId($roleId, $roleCode, $roleName);
        if ($resolvedId > 0) {
            return self::ROLES[$resolvedId]['dashboard'];
        }

        $mapped = self::mapCodeOrNameToId($roleCode !== '' ? $roleCode : $roleName);
        return $mapped > 0 ? self::ROLES[$mapped]['dashboard'] : '/login';
    }

    public static function isAdmin(int $roleId, string $roleCode = ''): bool
    {
        return self::resolveId($roleId, $roleCode) === self::ADMIN_ID
            || in_array(self::normalizeCode($roleCode), ['ADMIN', 'ADMINISTRATEUR'], true);
    }

    public static function isVendeur(int $roleId, string $roleCode = ''): bool
    {
        return self::resolveId($roleId, $roleCode) === self::VENDEUR_ID
            || self::normalizeCode($roleCode) === 'VENDEUR';
    }

    public static function isAssistant(int $roleId, string $roleCode = ''): bool
    {
        return self::resolveId($roleId, $roleCode) === self::ASSISTANT_ID
            || self::normalizeCode($roleCode) === 'ASSISTANT';
    }

    public static function isCommande(int $roleId, string $roleCode = ''): bool
    {
        $code = self::normalizeCode($roleCode);
        return self::resolveId($roleId, $roleCode) === self::COMMANDE_ID
            || in_array($code, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE', 'COMMANDE'], true)
            || str_contains($code, 'COMMANDE');
    }

    private static function matchesRole(int $roleId, string $roleCode, string $roleName): bool
    {
        if (!isset(self::ROLES[$roleId])) {
            return false;
        }

        $expected = self::ROLES[$roleId];
        $code = self::normalizeCode($roleCode);
        $name = strtolower(trim($roleName));

        if ($code !== '' && $code !== $expected['code']) {
            return false;
        }

        if ($name !== '' && $name !== $expected['nom']) {
            return false;
        }

        return true;
    }
}
