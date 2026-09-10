<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvements de stock</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $canManageStock = !in_array((int)($user['role_id'] ?? 0), [3], true);
        $roleLabel = (string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? $_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
        $defaultReturnTo = match (true) {
            in_array($roleCode, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true) => '/commande/dashboard',
            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
            $roleId === 2 || $roleCode === 'VENDEUR' => '/vente',
            default => '/stock',
        };
        $returnTo = (string)($_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exchange-alt text-indigo-600 text-2xl mr-3"></i>
                <h1 class="text-xl font-bold text-gray-800">Mouvements de stock</h1>
            </div>
            <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Retour
            </a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6">
                <?= htmlspecialchars((string)$_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Flux de stock</h2>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                    <button onclick="exportPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </button>
                    <button onclick="exportExcel()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-file-excel mr-2"></i>Excel
                    </button>
                </div>
            </div>

            <form method="GET" action="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/stock/mouvements', PHP_URL_PATH) ?: '/stock/mouvements') ?>" class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-6">
                <input type="text" name="recherche" id="recherche" placeholder="Recherche instantanée..." value="<?= htmlspecialchars((string)($_GET['recherche'] ?? '')) ?>" class="border border-gray-300 rounded px-3 py-2">
                <input type="date" name="date_debut" value="<?= htmlspecialchars((string)($_GET['date_debut'] ?? '')) ?>" class="border border-gray-300 rounded px-3 py-2">
                <input type="date" name="date_fin" value="<?= htmlspecialchars((string)($_GET['date_fin'] ?? '')) ?>" class="border border-gray-300 rounded px-3 py-2">
                <select name="produit_id" class="border border-gray-300 rounded px-3 py-2">
                    <option value="">Produit</option>
                    <?php foreach (($produits ?? []) as $produit): ?>
                        <option value="<?= (int)$produit['id'] ?>" <?= (string)($_GET['produit_id'] ?? '') === (string)$produit['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$produit['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="border border-gray-300 rounded px-3 py-2">
                    <option value="">Type</option>
                    <?php foreach (['ENTREE', 'SORTIE', 'AJUSTEMENT', 'TRANSFERT', 'PERTE'] as $typeFilter): ?>
                        <option value="<?= $typeFilter ?>" <?= (string)($_GET['type'] ?? '') === $typeFilter ? 'selected' : '' ?>><?= $typeFilter ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="flex gap-2">
                    <select name="utilisateur_id" class="border border-gray-300 rounded px-3 py-2 w-full">
                        <option value="">Utilisateur</option>
                        <?php foreach (($utilisateurs ?? []) as $utilisateur): ?>
                            <option value="<?= (int)$utilisateur['id'] ?>" <?= (string)($_GET['utilisateur_id'] ?? '') === (string)$utilisateur['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$utilisateur['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded"><i class="fas fa-filter"></i></button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse" id="table-mouvements">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Date</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Heure</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Type</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Entrée</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Sortie</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock avant</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock après</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Utilisateur</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Référence</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Observation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($mouvements ?? []) as $mouvement): ?>
                            <?php
                                $type = (string)($mouvement['type_mouvement'] ?? '');
                                $colors = [
                                    'ENTREE' => 'bg-green-100 text-green-700',
                                    'SORTIE' => 'bg-red-100 text-red-700',
                                    'AJUSTEMENT' => 'bg-orange-100 text-orange-700',
                                    'TRANSFERT' => 'bg-blue-100 text-blue-700',
                                    'PERTE' => 'bg-red-100 text-red-700',
                                ];
                                $dateMouvement = (string)($mouvement['date_mouvement'] ?? $mouvement['created_at'] ?? 'now');
                                $datePart = date('d/m/Y', strtotime($dateMouvement));
                                $heurePart = date('H:i', strtotime($dateMouvement));
                                $entree = ($type === 'ENTREE') ? (int)($mouvement['quantite'] ?? 0) : 0;
                                $sortie = ($type === 'SORTIE') ? (int)($mouvement['quantite'] ?? 0) : 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($datePart) ?></td>
                                <td class="border border-gray-300 px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($heurePart) ?></td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <div class="font-semibold"><?= htmlspecialchars((string)($mouvement['produit_nom'] ?? '')) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars((string)($mouvement['code_cip'] ?? '')) ?></div>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center">
                                    <span class="<?= $colors[$type] ?? 'bg-gray-100 text-gray-700' ?> text-xs font-semibold px-2 py-1 rounded">
                                        <?= htmlspecialchars($type) ?>
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-green-600"><?= $entree > 0 ? number_format($entree, 0, ',', ' ') : '-' ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center font-semibold text-red-600"><?= $sortie > 0 ? number_format($sortie, 0, ',', ' ') : '-' ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= number_format((float)($mouvement['quantite_avant'] ?? 0), 0, ',', ' ') ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center"><?= number_format((float)($mouvement['quantite_apres'] ?? 0), 0, ',', ' ') ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($mouvement['utilisateur_nom'] ?? '-')) ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($mouvement['reference_type'] ?? '-')) ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars((string)($mouvement['motif'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($mouvements)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-gray-500 py-8">Aucun mouvement de stock trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Recherche instantanée
            $('#recherche').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#table-mouvements tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
        });

        function exportPDF() {
            alert('Export PDF - Fonctionnalité à implémenter avec une librairie comme jsPDF ou TCPDF');
        }

        function exportExcel() {
            var table = document.getElementById('table-mouvements');
            var html = table.outerHTML;
            var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            var link = document.createElement('a');
            link.download = 'flux_stock_' + new Date().toISOString().slice(0,10) + '.xls';
            link.href = url;
            link.click();
        }
    </script>
</body>
</html>
