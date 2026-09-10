<?php

namespace App\Core;

final class DatabaseSql
{
    public const COLLATE = 'utf8mb4_unicode_ci';

    public static function collate(string $expression): string
    {
        return $expression . ' COLLATE ' . self::COLLATE;
    }

    public static function equals(string $left, string $right): string
    {
        return self::collate($left) . ' = ' . self::collate($right);
    }

    public static function joinPlanComptableOnCompteCode(string $compteExpr = 'le.compte_code'): string
    {
        return self::equals($compteExpr, 'pc.numero_compte');
    }

    public static function joinClassesComptesOnPlan(): string
    {
        return '(' . self::equals('CAST(pc.classe AS CHAR)', 'cc.code')
            . ' OR ' . self::equals('pc.classe_id', 'cc.code') . ')';
    }
}
