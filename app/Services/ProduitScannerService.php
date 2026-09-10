<?php

namespace App\Services;

use PDO;

/** Recherche exacte d'un produit par le code-barres physique. */
class ProduitScannerService
{
    public function __construct(private PDO $db)
    {
    }

    public function rechercherParCodeBarres(string $codeBarres): ?array
    {
        $stmt = $this->db->prepare("SELECT p.id, p.nom, p.code_cip, p.code_barre, p.dci,
                                           COALESCE(NULLIF(p.forme_pharmaceutique, ''), p.forme) AS forme_pharmaceutique,
                                           p.prix_achat, p.prix_vente AS prix_vente_catalogue, p.is_actif, s.quantite_disponible, s.date_peremption,
                                           CASE WHEN s.date_peremption IS NOT NULL AND s.date_peremption < CURDATE() THEN 1 ELSE 0 END AS est_perime
                                    FROM produits p
                                    LEFT JOIN stock s ON s.produit_id = p.id
                                    WHERE p.code_barre = ? AND p.deleted_at IS NULL
                                    LIMIT 1");
        $stmt->execute([$codeBarres]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$produit) {
            return null;
        }

        $produit['quantite_disponible'] = (int)($produit['quantite_disponible'] ?? 0);
        // The configured sales price is the single source of truth for scanner and checkout.
        $produit['prix_vente'] = (float)$produit['prix_vente_catalogue'];
        $produit['is_actif'] = (bool)$produit['is_actif'];
        $produit['est_perime'] = (bool)$produit['est_perime'];
        $produit['vendable'] = $produit['is_actif'] && !$produit['est_perime'] && $produit['quantite_disponible'] > 0;
        $produit['statut'] = !$produit['is_actif'] ? 'INACTIF' : ($produit['est_perime'] ? 'PERIME' : ($produit['quantite_disponible'] > 0 ? 'DISPONIBLE' : 'STOCK_INSUFFISANT'));
        return $produit;
    }
}
