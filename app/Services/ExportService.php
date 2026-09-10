<?php

namespace App\Services;

use PDO;

class ExportService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Exporte le stock en CSV
     */
    public function exportStockCSV(): string
    {
        $filename = 'export_stock_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $fp = fopen($filepath, 'w');
        if ($fp === false) {
            throw new \Exception('Impossible de créer le fichier CSV');
        }

        // En-têtes CSV
        fputcsv($fp, [
            'ID',
            'Code CIP',
            'Nom du produit',
            'Catégorie',
            'Fournisseur',
            'Stock disponible',
            'Stock théorique',
            'Stock réservé',
            'Valeur stock',
            'Prix vente',
            'Stock sécurité',
            'Stock alerte',
            'Rayon',
            'Dernier mouvement',
            'Niveau stock'
        ], ';');

        // Données
        $sql = "SELECT 
                    p.id, p.code_cip, p.nom, p.prix_vente, p.stock_securite, p.stock_alerte, p.rayon,
                    s.quantite_disponible, s.quantite_theorique, s.valeur_stock,
                    s.dernier_mouvement,
                    c.nom as categorie,
                    f.nom as fournisseur,
                    (s.quantite_disponible - s.quantite_theorique) as stock_reserve,
                    CASE 
                        WHEN s.quantite_disponible <= p.stock_securite THEN 'CRITIQUE'
                        WHEN s.quantite_disponible <= p.stock_alerte THEN 'ALERTE'
                        ELSE 'NORMAL'
                    END as niveau_stock
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                WHERE p.deleted_at IS NULL AND p.is_actif = 1
                ORDER BY p.nom";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stocks as $stock) {
            fputcsv($fp, [
                $stock['id'],
                $stock['code_cip'],
                $stock['nom'],
                $stock['categorie'] ?? '',
                $stock['fournisseur'] ?? '',
                $stock['quantite_disponible'] ?? 0,
                $stock['quantite_theorique'] ?? 0,
                $stock['stock_reserve'] ?? 0,
                $stock['valeur_stock'] ?? 0,
                $stock['prix_vente'] ?? 0,
                $stock['stock_securite'] ?? 0,
                $stock['stock_alerte'] ?? 0,
                $stock['rayon'] ?? '',
                $stock['dernier_mouvement'] ?? '',
                $stock['niveau_stock'] ?? 'NORMAL'
            ], ';');
        }

        fclose($fp);

        return $filepath;
    }

    /**
     * Exporte les mouvements de stock en CSV
     */
    public function exportMouvementsCSV(string $dateDebut = null, string $dateFin = null): string
    {
        $filename = 'export_mouvements_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $fp = fopen($filepath, 'w');
        if ($fp === false) {
            throw new \Exception('Impossible de créer le fichier CSV');
        }

        // En-têtes CSV
        fputcsv($fp, [
            'Date',
            'Produit',
            'Code CIP',
            'Lot',
            'Type',
            'Quantité',
            'Stock avant',
            'Stock après',
            'Motif',
            'Utilisateur'
        ], ';');

        // Données
        $sql = "SELECT ms.*, 
                       p.nom as produit_nom, p.code_cip,
                       l.numero_lot,
                       u.username as utilisateur_nom
                FROM mouvements_stock ms
                JOIN produits p ON ms.produit_id = p.id
                LEFT JOIN lots l ON ms.lot_id = l.id
                LEFT JOIN utilisateurs u ON ms.utilisateur_id = u.id";

        $params = [];
        if ($dateDebut && $dateFin) {
            $sql .= " WHERE ms.date_mouvement BETWEEN ? AND ?";
            $params = [$dateDebut, $dateFin];
        }

        $sql .= " ORDER BY ms.date_mouvement DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($mouvements as $mouvement) {
            fputcsv($fp, [
                $mouvement['date_mouvement'],
                $mouvement['produit_nom'],
                $mouvement['code_cip'],
                $mouvement['numero_lot'] ?? '',
                $mouvement['type_mouvement'],
                $mouvement['quantite'],
                $mouvement['quantite_avant'],
                $mouvement['quantite_apres'],
                $mouvement['motif'] ?? '',
                $mouvement['utilisateur_nom'] ?? ''
            ], ';');
        }

        fclose($fp);

        return $filepath;
    }

    /**
     * Exporte les péremptions en CSV
     */
    public function exportPeremptionsCSV(): string
    {
        $filename = 'export_peremptions_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $fp = fopen($filepath, 'w');
        if ($fp === false) {
            throw new \Exception('Impossible de créer le fichier CSV');
        }

        // En-têtes CSV
        fputcsv($fp, [
            'Produit',
            'Code CIP',
            'Lot',
            'Péremption',
            'Jours restants',
            'Quantité',
            'Prix achat unitaire',
            'Valeur',
            'Niveau péremption'
        ], ';');

        // Données
        $sql = "SELECT l.*, 
                       p.nom as produit_nom, p.code_cip,
                       DATEDIFF(l.date_peremption, CURDATE()) as jours_restants,
                       CASE 
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 0 THEN 'PERIME'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 30 THEN 'URGENT'
                           WHEN DATEDIFF(l.date_peremption, CURDATE()) <= 90 THEN 'ALERTE'
                           ELSE 'NORMAL'
                       END as niveau_peremption
                FROM lots l
                JOIN produits p ON l.produit_id = p.id
                WHERE l.is_actif = 1
                ORDER BY l.date_peremption ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lots as $lot) {
            $valeur = ($lot['quantite_restante'] ?? 0) * ($lot['prix_achat_unitaire'] ?? 0);
            fputcsv($fp, [
                $lot['produit_nom'],
                $lot['code_cip'],
                $lot['numero_lot'],
                $lot['date_peremption'],
                $lot['jours_restants'],
                $lot['quantite_restante'],
                $lot['prix_achat_unitaire'],
                $valeur,
                $lot['niveau_peremption']
            ], ';');
        }

        fclose($fp);

        return $filepath;
    }

    /**
     * Génère un HTML pour impression PDF du stock
     */
    public function generateStockHTML(): string
    {
        $sql = "SELECT 
                    p.id, p.code_cip, p.nom, p.prix_vente, p.stock_securite, p.stock_alerte, p.rayon,
                    s.quantite_disponible, s.quantite_theorique, s.valeur_stock,
                    s.dernier_mouvement,
                    c.nom as categorie,
                    f.nom as fournisseur,
                    (s.quantite_disponible - s.quantite_theorique) as stock_reserve,
                    CASE 
                        WHEN s.quantite_disponible <= p.stock_securite THEN 'CRITIQUE'
                        WHEN s.quantite_disponible <= p.stock_alerte THEN 'ALERTE'
                        ELSE 'NORMAL'
                    END as niveau_stock
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
                WHERE p.deleted_at IS NULL AND p.is_actif = 1
                ORDER BY p.nom";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Stock</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .critique { color: red; font-weight: bold; }
        .alerte { color: orange; font-weight: bold; }
        .normal { color: green; }
    </style>
</head>
<body>
    <h1>Rapport du Stock - ' . date('d/m/Y H:i') . '</h1>
    <table>
        <thead>
            <tr>
                <th>Code CIP</th>
                <th>Produit</th>
                <th>Catégorie</th>
                <th>Fournisseur</th>
                <th>Stock Dispo</th>
                <th>Stock Théo</th>
                <th>Valeur</th>
                <th>Niveau</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($stocks as $stock) {
            $class = 'normal';
            if ($stock['niveau_stock'] === 'CRITIQUE') $class = 'critique';
            elseif ($stock['niveau_stock'] === 'ALERTE') $class = 'alerte';

            $html .= '<tr>
                <td>' . htmlspecialchars($stock['code_cip']) . '</td>
                <td>' . htmlspecialchars($stock['nom']) . '</td>
                <td>' . htmlspecialchars($stock['categorie'] ?? '') . '</td>
                <td>' . htmlspecialchars($stock['fournisseur'] ?? '') . '</td>
                <td>' . number_format($stock['quantite_disponible'] ?? 0, 0, ',', ' ') . '</td>
                <td>' . number_format($stock['quantite_theorique'] ?? 0, 0, ',', ' ') . '</td>
                <td>' . number_format($stock['valeur_stock'] ?? 0, 0, ',', ' ') . ' FCFA</td>
                <td class="' . $class . '">' . htmlspecialchars($stock['niveau_stock']) . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>
</body>
</html>';

        return $html;
    }

    /**
     * Télécharge un fichier
     */
    public function downloadFile(string $filepath, string $filename): void
    {
        if (!file_exists($filepath)) {
            throw new \Exception('Fichier non trouvé');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        readfile($filepath);
        unlink($filepath);
        exit;
    }
}
