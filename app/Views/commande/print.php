<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande Fournisseur <?= htmlspecialchars((string)($order['numero_commande'] ?? '')) ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px; }
        .info-grid div { display: flex; }
        .info-grid span:first-child { font-weight: bold; width: 150px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .totals { text-align: right; margin-top: 20px; }
        .totals div { margin: 5px 0; }
        .totals .total { font-weight: bold; font-size: 1.2em; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1>COMMANDE FOURNISSEUR</h1>
        <p><?= htmlspecialchars((string)($order['numero_commande'] ?? '')) ?></p>
    </div>

    <div class="info-grid">
        <div><span>Fournisseur:</span> <?= htmlspecialchars((string)($order['fournisseur_nom'] ?? '-')) ?></div>
        <div><span>Référence:</span> <?= htmlspecialchars((string)($order['reference_commande'] ?? '-')) ?></div>
        <div><span>Date commande:</span> <?= htmlspecialchars((string)($order['date_commande'] ?? '-')) ?></div>
        <div><span>Livraison prévue:</span> <?= htmlspecialchars((string)($order['date_livraison_prevue'] ?? '-')) ?></div>
        <div><span>Statut:</span> <?= htmlspecialchars(str_replace('_', ' ', (string)($order['statut'] ?? ''))) ?></div>
        <div><span>Créé par:</span> <?= htmlspecialchars((string)($order['utilisateur_nom'] ?? '-')) ?></div>
    </div>

    <?php if (!empty($order['observations'])): ?>
    <div style="margin-bottom: 20px;">
        <strong>Observations:</strong> <?= htmlspecialchars((string)$order['observations']) ?>
    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th style="text-align: center;">Quantité</th>
                <th style="text-align: right;">Prix achat</th>
                <th style="text-align: center;">Remise %</th>
                <th style="text-align: center;">TVA %</th>
                <th style="text-align: right;">Total HT</th>
                <th style="text-align: right;">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($items ?? []) as $item): ?>
                <?php 
                $lineHT = $item['quantite_commandee'] * $item['prix_achat'];
                $lineRemise = $lineHT * (($item['remise'] ?? 0) / 100);
                $lineApresRemise = $lineHT - $lineRemise;
                $lineTVA = $lineApresRemise * (($item['tva'] ?? 0) / 100);
                $lineTTC = $lineApresRemise + $lineTVA;
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)($item['produit_nom'] ?? '-')) ?></td>
                    <td style="text-align: center;"><?= (int)$item['quantite_commandee'] ?></td>
                    <td style="text-align: right;"><?= number_format((float)$item['prix_achat'], 2, ',', ' ') ?> FCFA</td>
                    <td style="text-align: center;"><?= number_format((float)($item['remise'] ?? 0), 2, ',', ' ') ?> %</td>
                    <td style="text-align: center;"><?= number_format((float)($item['tva'] ?? 0), 2, ',', ' ') ?> %</td>
                    <td style="text-align: right;"><?= number_format($lineHT, 2, ',', ' ') ?> FCFA</td>
                    <td style="text-align: right;"><?= number_format($lineTTC, 2, ',', ' ') ?> FCFA</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div>Total HT: <?= number_format((float)($order['montant_ht'] ?? 0), 2, ',', ' ') ?> FCFA</div>
        <div>Remise globale (<?= number_format((float)($order['remise_globale'] ?? 0), 2, ',', ' ') ?>%): <?= number_format((float)($order['montant_ht'] ?? 0) * ((float)($order['remise_globale'] ?? 0) / 100), 2, ',', ' ') ?> FCFA</div>
        <div>TVA (<?= number_format((float)($order['tva_globale'] ?? 0), 2, ',', ' ') ?>%): <?= number_format(((float)($order['montant_ht'] ?? 0) - ((float)($order['montant_ht'] ?? 0) * ((float)($order['remise_globale'] ?? 0) / 100))) * ((float)($order['tva_globale'] ?? 0) / 100), 2, ',', ' ') ?> FCFA</div>
        <div class="total">Total TTC: <?= number_format((float)($order['montant_ttc'] ?? 0), 2, ',', ' ') ?> FCFA</div>
    </div>

    <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #666;">
        <p>Document généré le <?= date('d/m/Y H:i') ?></p>
    </div>
</body>
</html>
