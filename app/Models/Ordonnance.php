<?php

namespace App\Models;

use PDO;

class Ordonnance
{
    public function __construct(private PDO $db)
    {
    }

    public function rechercher(array $filtres): array
    {
        $where = [];
        $params = [];

        foreach (['numero' => 'o.numero_ordonnance', 'patient' => 'o.nom_patient', 'prescripteur' => 'o.nom_medecin'] as $cle => $colonne) {
            if (($filtres[$cle] ?? '') !== '') {
                $where[] = "$colonne LIKE ?";
                $params[] = '%' . trim((string)$filtres[$cle]) . '%';
            }
        }
        if (($filtres['date'] ?? '') !== '') {
            $where[] = 'o.date_ordonnance = ?';
            $params[] = $filtres['date'];
        }

        $sql = "SELECT o.*, COUNT(DISTINCT voi.produit_id) AS nombre_produits,
                       COUNT(DISTINCT vo.vente_id) AS nombre_ventes,
                       COUNT(DISTINCT CASE WHEN v.deleted_at IS NULL AND v.statut_vente <> 'ANNULEE' THEN vo.vente_id END) AS nombre_ventes_actives,
                       MAX(CASE WHEN v.deleted_at IS NULL AND v.statut_vente <> 'ANNULEE' THEN v.id END) AS derniere_vente_id
                FROM ordonnances o
                LEFT JOIN vente_ordonnances vo ON vo.ordonnance_id = o.id
                LEFT JOIN ventes v ON v.id = vo.vente_id
                LEFT JOIN vente_ordonnance_items voi ON voi.ordonnance_id = o.id
                " . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
                GROUP BY o.id
                ORDER BY o.date_ordonnance DESC, o.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function trouver(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT o.*, COUNT(DISTINCT voi.produit_id) AS nombre_produits,
                                           COUNT(DISTINCT vo.vente_id) AS nombre_ventes,
                                           COUNT(DISTINCT CASE WHEN v.deleted_at IS NULL AND v.statut_vente <> 'ANNULEE' THEN vo.vente_id END) AS nombre_ventes_actives
                                    FROM ordonnances o
                                    LEFT JOIN vente_ordonnances vo ON vo.ordonnance_id = o.id
                                    LEFT JOIN ventes v ON v.id = vo.vente_id
                                    LEFT JOIN vente_ordonnance_items voi ON voi.ordonnance_id = o.id
                                    WHERE o.id = ? GROUP BY o.id");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function produitsEtVentes(int $id): array
    {
        $stmt = $this->db->prepare("SELECT voi.produit_id, p.nom, p.code_cip,
                                           SUM(vi.quantite) AS quantite_delivree,
                                           MAX(vi.prix_unitaire) AS prix_unitaire,
                                           SUM(vi.montant_total) AS montant_total,
                                           MAX(v.date_vente) AS date_vente
                                    FROM vente_ordonnance_items voi
                                    JOIN produits p ON p.id = voi.produit_id
                                    LEFT JOIN ventes_items vi ON vi.vente_id = voi.vente_id AND vi.produit_id = voi.produit_id
                                    LEFT JOIN ventes v ON v.id = voi.vente_id AND v.deleted_at IS NULL AND v.statut_vente <> 'ANNULEE'
                                    WHERE voi.ordonnance_id = ?
                                    GROUP BY voi.produit_id, p.nom, p.code_cip
                                    ORDER BY p.nom");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
