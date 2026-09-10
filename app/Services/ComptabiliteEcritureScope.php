<?php

namespace App\Services;

/**
 * Canonical read scope for accounting reports.
 *
 * A reversal neutralises its source: neither the source nor its inverse may
 * contribute to VAT, balances, ledgers, or financial statements.
 */
final class ComptabiliteEcritureScope
{
    public static function valide(string $alias = 'ec'): string
    {
        return "{$alias}.is_equilibree = 1
            AND COALESCE({$alias}.reference_type, '') <> 'ANNULATION_VENTE'
            AND NOT EXISTS (
                SELECT 1 FROM ecritures_comptables ec_inverse
                WHERE ec_inverse.ecriture_origine_id = {$alias}.id
            )";
    }
}
