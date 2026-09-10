<?php

namespace App\Helpers;

/**
 * Helper pour les vues avec fonctions sécurisées
 */
trait ViewHelper
{
    /**
     * Affiche en toute sécurité une valeur utilisateur
     */
    protected function safeUserField(string $field, string $default = 'Invité'): string
    {
        global $user;
        return htmlspecialchars($user[$field] ?? $default);
    }

    /**
     * Affiche en toute sécurité une valeur de tableau
     */
    protected function safeArrayValue(array $array, string $key, $default = ''): string
    {
        return htmlspecialchars($array[$key] ?? $default);
    }

    /**
     * Affiche en toute sécurité un nombre formaté
     */
    protected function safeNumberFormat($value, int $decimals = 0, string $default = '0'): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return number_format((float)$value, $decimals, ',', ' ');
    }

    /**
     * Affiche en toute sécurité une date
     */
    protected function safeDate(string $date, string $format = 'd/m/Y H:i', string $default = '--/-- --:--'): string
    {
        if (empty($date)) {
            return $default;
        }
        return date($format, strtotime($date));
    }

    /**
     * Vérifie si une variable existe et n'est pas vide
     */
    protected function safeExists($variable): bool
    {
        return isset($variable) && !empty($variable);
    }

    /**
     * Affiche en toute sécurité le nom d'utilisateur
     */
    protected function safeUserName(): string
    {
        global $user;
        return htmlspecialchars($user['name'] ?? $user['username'] ?? 'Invité');
    }

    /**
     * Affiche en toute sécurité le montant en FCFA
     */
    protected function safeMontant($montant): string
    {
        return $this->safeNumberFormat($montant, 0, '0') . ' FCFA';
    }

    /**
     * Itère en toute sécurité sur un tableau
     */
    protected function safeForEach($array, callable $callback): void
    {
        if (empty($array) || !is_array($array)) {
            return;
        }
        foreach ($array as $item) {
            $callback($item);
        }
    }

    /**
     * Compte en toute sécurité les éléments d'un tableau
     */
    protected function safeCount($array): int
    {
        return is_array($array) ? count($array) : 0;
    }

    /**
     * Affiche en toute sécurité une valeur de session
     */
    protected function safeSession(string $key, $default = '--'): string
    {
        global $session;
        return htmlspecialchars($session[$key] ?? $default);
    }
}

/**
 * Fonctions globales pour les vues
 */
if (!function_exists('safe_user')) {
    function safe_user(string $field, string $default = 'Invité'): string
    {
        global $user;
        return htmlspecialchars($user[$field] ?? $default);
    }
}

if (!function_exists('safe_array')) {
    function safe_array(array $array, string $key, $default = ''): string
    {
        return htmlspecialchars($array[$key] ?? $default);
    }
}

if (!function_exists('safe_number')) {
    function safe_number($value, int $decimals = 0, string $default = '0'): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return number_format((float)$value, $decimals, ',', ' ');
    }
}

if (!function_exists('safe_date')) {
    function safe_date(string $date, string $format = 'd/m/Y H:i', string $default = '--/-- --:--'): string
    {
        if (empty($date)) {
            return $default;
        }
        return date($format, strtotime($date));
    }
}

if (!function_exists('safe_montant')) {
    function safe_montant($montant): string
    {
        return safe_number($montant, 0, '0') . ' FCFA';
    }
}

if (!function_exists('safe_session')) {
    function safe_session(string $key, $default = '--'): string
    {
        global $session;
        return htmlspecialchars($session[$key] ?? $default);
    }
}

if (!function_exists('safe_count')) {
    function safe_count($array): int
    {
        return is_array($array) ? count($array) : 0;
    }
}
