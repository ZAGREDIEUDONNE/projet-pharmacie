<?php

namespace App\Services;

class ChargeCommandePolicy
{
    public const ALLOWED_PERMISSIONS = [
        'view_stock',
        'add_stock',
        'receive_products',
        'create_supplier_orders',
        'view_stock_movements',
        'sortie_stock',
        'stock.create_product',
        'product.price.update',
        'product.price.history',
        'stock.update_product',
        'stock.adjust',
        'stock.view_expiry',
        'stock.inventory',
        'view_supplier_orders',
        'edit_supplier_orders',
        'send_supplier_orders',
        'stock.view',
        'stock.create',
        'commande.view',
        'commande.create',
    ];

    public const DENIED_PERMISSIONS = [
        'vente.view',
        'vente.create',
        'vente.cancel',
        'vente.print',
        'vente.history',
        'make_sale',
        'cancel_ticket',
        'ticket_annuler',
        'apply_discount',
        'remise.apply',
        'remise_apply',
        'finance.view',
        'benefice.view',
        'user.manage',
        'users_manage',
        'caisse.close',
        'caisse_arret',
        'close_cash_register',
        'client.view',
        'client.create',
        'client.update',
        'client.manage',
        'create_client',
        'edit_client',
        'system.config',
        'audit_view',
    ];

    public function can(string $permission): bool
    {
        if (in_array($permission, self::DENIED_PERMISSIONS, true)) {
            return false;
        }

        return in_array($permission, self::ALLOWED_PERMISSIONS, true);
    }

    public function deniedPermissions(): array
    {
        return self::DENIED_PERMISSIONS;
    }
}
