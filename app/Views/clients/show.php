<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Fiche Client')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --surface: #f4f7fb;
            --panel: #ffffff;
            --line: #e5e7eb;
            --text: #172033;
            --muted: #667085;
            --blue: #2563eb;
            --green: #059669;
            --amber: #d97706;
            --red: #dc2626;
            --violet: #7c3aed;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f1f5f9; color: #475569; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
    </style>
</head>
<body class="bg-gray-100">
    <?php
        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $isAdmin = $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true);
        $defaultReturnTo = match (true) {
            $isAdmin => '/admin/dashboard',
            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
            $roleId === 2 || $roleCode === 'VENDEUR' => '/vente',
            default => '/clients',
        };
        $returnTo = (string)($_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
        
        $number = static fn($value): string => number_format((float)$value, 0, ',', ' ');
        $money = static fn($value): string => number_format((float)$value, 0, ',', ' ') . ' FCFA';
        $dateShort = static function ($value): string {
            if (empty($value)) return '-';
            $timestamp = strtotime((string)$value);
            return $timestamp ? date('d/m/Y H:i', $timestamp) : (string)$value;
        };
        
        $clientTypeLabels = [
            'ORDINAIRE' => 'Ordinaire',
            'COURANT' => 'Courant',
            'COURANT_DEPOT' => 'Courant - Dépôt',
            'COURANT_BON' => 'Courant - Bon',
            'COURANT_CARNET' => 'Courant - Carnet',
            'AUTRES_CLIENTS' => 'Autres clients',
            'ASSURE' => 'Assuré',
            'ENTREPRISE' => 'Entreprise'
        ];
        $clientTypeLabel = $clientTypeLabels[$client['type_client'] ?? ''] ?? $client['type_client'] ?? '-';
        $isActif = (int)($client['is_actif'] ?? 1) === 1;
    ?>

    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-user-circle text-3xl"></i>
                    <div>
                        <h1 class="text-xl font-bold">FICHE CLIENT</h1>
                        <p class="text-sm text-blue-200"><?= htmlspecialchars($client['code'] ?? '') ?> - <?= htmlspecialchars($client['nom'] ?? '') ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="badge <?= $isActif ? 'badge-green' : 'badge-red' ?>">
                        <?= $isActif ? 'Actif' : 'Inactif' ?>
                    </span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded text-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6">
                <?= htmlspecialchars((string)$_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Informations générales -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                Informations générales
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Code client</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['code'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Matricule</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['matricule'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Nom / Raison sociale</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['nom'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Prénom</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['prenom'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Type de client</p>
                    <p class="font-semibold"><?= htmlspecialchars($clientTypeLabel) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Statut</p>
                    <span class="badge <?= $isActif ? 'badge-green' : 'badge-red' ?>">
                        <?= $isActif ? 'Actif' : 'Inactif' ?>
                    </span>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Téléphone principal</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['telephone'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Téléphone secondaire</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['telephone_secondaire'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['email'] ?? '-') ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500">Adresse</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['adresse'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Ville</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['ville'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Date de naissance</p>
                    <p class="font-semibold"><?= $dateShort($client['date_naissance'] ?? null) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Âge</p>
                    <p class="font-semibold"><?= $number($client['age'] ?? 0) ?> ans</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Numéro IFU</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['numero_ifu'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Numéro RCCM</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['numero_rccm'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Numéro assurance</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['numero_assurance'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Compagnie assurance</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['compagnie_assurance'] ?? '-') ?></p>
                </div>
                <div class="md:col-span-3">
                    <p class="text-sm text-gray-500">Observations</p>
                    <p class="font-semibold"><?= htmlspecialchars($client['notes'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Date de création</p>
                    <p class="font-semibold"><?= $dateShort($client['created_at'] ?? null) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Dernière modification</p>
                    <p class="font-semibold"><?= $dateShort($client['updated_at'] ?? null) ?></p>
                </div>
            </div>
            
            <?php if ($isAdmin): ?>
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                <a href="/clients/modifier?id=<?= $client['id'] ?>&return_to=<?= urlencode($returnTo) ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-edit mr-2"></i>Modifier
                </a>
                <a href="/clients/desactiver?id=<?= $client['id'] ?>&return_to=<?= urlencode($returnTo) ?>" 
                   class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg"
                   onclick="return confirm('Êtes-vous sûr de vouloir désactiver ce client ?')">
                    <i class="fas fa-ban mr-2"></i><?= $isActif ? 'Désactiver' : 'Réactiver' ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Situation financière -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="card p-5">
                <p class="text-sm text-gray-500">Solde actuel</p>
                <p class="mt-1 text-2xl font-bold <?= ($client['solde_credit'] ?? 0) < 0 ? 'text-red-600' : 'text-green-600' ?>">
                    <?= htmlspecialchars($money($client['solde_credit'] ?? 0)) ?>
                </p>
            </div>
            <div class="card p-5">
                <p class="text-sm text-gray-500">Plafond de crédit</p>
                <p class="mt-1 text-2xl font-bold text-blue-600">
                    <?= htmlspecialchars($money($client['plafond_credit'] ?? 0)) ?>
                </p>
            </div>
            <div class="card p-5">
                <p class="text-sm text-gray-500">Crédit disponible</p>
                <p class="mt-1 text-2xl font-bold text-green-600">
                    <?= htmlspecialchars($money(($client['plafond_credit'] ?? 0) - ($client['solde_credit'] ?? 0))) ?>
                </p>
            </div>
        </div>

        <!-- Historique des ventes -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-shopping-cart mr-2 text-green-600"></i>
                Historique des ventes
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">N° Facture</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Montant</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($salesHistory) && is_array($salesHistory)): ?>
                            <?php foreach ($salesHistory as $sale): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?= htmlspecialchars($sale['numero_facture'] ?? '-') ?></td>
                                    <td class="py-3 px-4"><?= $dateShort($sale['date_vente'] ?? null) ?></td>
                                    <td class="py-3 px-4 text-right"><?= htmlspecialchars($money($sale['montant_net'] ?? 0)) ?></td>
                                    <td class="py-3 px-4">
                                        <span class="badge badge-green"><?= htmlspecialchars($sale['statut_vente'] ?? '-') ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-500">Aucune vente enregistrée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Historique des règlements -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-money-bill-wave mr-2 text-amber-600"></i>
                Historique des règlements
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Mode de paiement</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Montant</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Référence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($paymentHistory) && is_array($paymentHistory)): ?>
                            <?php foreach ($paymentHistory as $payment): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4"><?= $dateShort($payment['date_paiement'] ?? null) ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($payment['mode_paiement'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-right"><?= htmlspecialchars($money($payment['montant'] ?? 0)) ?></td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($payment['reference'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-500">Aucun règlement enregistré</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dernière opération -->
        <?php if (!empty($lastOperation)): ?>
        <div class="card p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-clock mr-2 text-violet-600"></i>
                Dernière opération
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Date</p>
                    <p class="font-semibold"><?= $dateShort($lastOperation['date'] ?? null) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Type</p>
                    <p class="font-semibold"><?= htmlspecialchars($lastOperation['type'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Montant</p>
                    <p class="font-semibold"><?= htmlspecialchars($money($lastOperation['montant'] ?? 0)) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
