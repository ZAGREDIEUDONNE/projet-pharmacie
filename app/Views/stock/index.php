<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Stock - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stock-row:hover { background-color: #ffffff; }
        .stock-critique { color: #dc2626; font-weight: bold; }
        .stock-alerte { color: #f59e0b; font-weight: bold; }
        .stock-normal { color: #10b981; }
        .stock-rupture { background-color: #fee2e2; }
        .badge-critique { background-color: #dc2626; }
        .badge-alerte { background-color: #f59e0b; }
        .badge-normal { background-color: #10b981; }
        .badge-rayon { background-color: #3b82f6; }
        .badge-frigo { background-color: #06b6d4; }
        .badge-armoire { background-color: #8b5cf6; }
        .badge-vitrine { background-color: #f59e0b; }
        .badge-comptoir { background-color: #10b981; }
        .badge-magasin { background-color: #6b7280; }
        .badge-autres { background-color: #ec4899; }
    </style>
</head>
<body class="bg-gray-100">
    <?php
        $canManageStock = in_array((int)($user['role_id'] ?? 0), [1, 3, 4], true);
        $roleLabel = (string)($user['role_code'] ?? $user['role'] ?? $user['role_name'] ?? $_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $roleId = (int)($user['role_id'] ?? $_SESSION['user']['role_id'] ?? 0);
        $canEditPrices = $roleId === 1
            || $roleId === 4
            || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR', 'CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true);
        $defaultReturnTo = match (true) {
            in_array($roleCode, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE'], true) => '/commande/dashboard',
            $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
            $roleId === 2 || $roleCode === 'VENDEUR' => '/vente',
            default => '/vente',
        };
        $returnTo = (string)($_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>
    <!-- Header -->
    <header class="bg-green-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-warehouse text-2xl"></i>
                    <h1 class="text-xl font-bold">GESTION STOCK</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm">
                        <span class="font-semibold">Utilisateur:</span> 
                        <?= htmlspecialchars($user['name'] ?? 'Invité') ?>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">Date:</span> 
                        <?= date('d/m/Y H:i') ?>
                    </div>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded text-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <!-- Statistiques principales -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Produits</p>
                        <p class="text-2xl font-bold text-gray-800" id="totalProduits">0</p>
                    </div>
                    <i class="fas fa-boxes text-3xl text-blue-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Stock Total</p>
                        <p class="text-2xl font-bold text-gray-800" id="stockTotal">0</p>
                    </div>
                    <i class="fas fa-cubes text-3xl text-green-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Valeur Stock</p>
                        <p class="text-2xl font-bold text-gray-800" id="valeurStock"> FCFA</p>
                    </div>
                    <i class="fas fa-money-bill-wave text-3xl text-purple-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Alertes</p>
                        <p class="text-2xl font-bold text-orange-600" id="nombreAlertes">0</p>
                    </div>
                    <i class="fas fa-exclamation-triangle text-3xl text-orange-600"></i>
                </div>
            </div>
        </div>

        <!-- Actions principales -->
        <?php if ($canManageStock): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-cogs mr-2 text-green-600"></i>
                    Actions Stock
                </h2>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="/stock/ajouter-produit?return_to=<?= urlencode($returnTo) ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-box-medical text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Nouveau Produit</div>
                </a>
                <a href="/stock/ajouter" class="bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-plus-circle text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Entrée Stock</div>
                </a>
                <a href="/stock/ajustement" class="bg-green-600 hover:bg-green-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-sliders-h text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Ajustement</div>
                </a>
                <a href="/produits/sortie-stock" class="bg-orange-600 hover:bg-orange-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-minus-circle text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Sortie Stock</div>
                </a>
                <a href="/stock/peremptions?return_to=<?= urlencode($returnTo) ?>" class="bg-red-600 hover:bg-red-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-clock text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Péremptions</div>
                </a>
                <a href="/stock/mouvements" class="bg-indigo-600 hover:bg-indigo-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-exchange-alt text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Mouvements</div>
                </a>
                <a href="/stock/commandes-automatiques" class="bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-robot text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Commandes Auto</div>
                </a>
                <?php if (in_array((int)($user['role_id'] ?? 0), [1, 3], true)): ?>
                <a href="/fournisseurs/ajouter?return_to=<?= urlencode($returnTo) ?>" class="bg-green-600 hover:bg-green-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-truck text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Ajouter Fournisseur</div>
                </a>
                <?php endif; ?>
                <a href="/stock/rapports" class="bg-gray-600 hover:bg-gray-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-chart-line text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Rapports</div>
                </a>
                <?php if ($canEditPrices): ?>
                <a href="/stock/historique-prix" class="bg-yellow-600 hover:bg-yellow-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-tags text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Historique Prix</div>
                </a>
                <?php endif; ?>
                <button onclick="synchroniserStocks()" class="bg-green-600 hover:bg-green-700 text-white p-4 rounded-lg text-center">
                    <i class="fas fa-sync text-2xl mb-2"></i>
                    <div class="text-sm font-semibold">Synchroniser</div>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tableau des stocks -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-list mr-2 text-green-600"></i>
                    État du Stock
                </h3>
                <div class="flex items-center space-x-4">
                    <input type="text" 
                           id="searchProduit" 
                           placeholder="Rechercher un produit..."
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <select id="filterNiveau" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">Tous les niveaux</option>
                        <option value="CRITIQUE">Critique</option>
                        <option value="ALERTE">Alerte</option>
                        <option value="NORMAL">Normal</option>
                    </select>
                    <select id="filterRayon" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">Tous les rayons</option>
                        <option value="Rayon A">Rayon A</option>
                        <option value="Rayon B">Rayon B</option>
                        <option value="Rayon C">Rayon C</option>
                        <option value="Rayon D">Rayon D</option>
                        <option value="Rayon E">Rayon E</option>
                        <option value="Rayon F">Rayon F</option>
                        <option value="Rayon G">Rayon G</option>
                        <option value="Rayon H">Rayon H</option>
                        <option value="Rayon I">Rayon I</option>
                        <option value="Rayon J">Rayon J</option>
                        <option value="Rayon K">Rayon K</option>
                        <option value="Rayon L">Rayon L</option>
                        <option value="Rayon M">Rayon M</option>
                        <option value="Rayon N">Rayon N</option>
                        <option value="Rayon O">Rayon O</option>
                        <option value="Frigo">Frigo</option>
                        <option value="Armoire à clé">Armoire à clé</option>
                        <option value="Vitrines">Vitrines</option>
                        <option value="Comptoir">Comptoir</option>
                        <option value="Magasin">Magasin</option>
                        <option value="Autres">Autres</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-left">Produit</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Rayon</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock Réel</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Stock Théo</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Réservé</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Alerte</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Sécurité</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Valeur</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Niveau</th>
                            <th class="border border-gray-300 px-4 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="stocksTable">
                        <?php foreach (($stocks ?? []) as $stock): ?>
                            <?php
                                $quantiteDisponible = (float)($stock['quantite_disponible'] ?? 0);
                                $quantiteTheorique = (float)($stock['quantite_theorique'] ?? 0);
                                $stockReserve = (float)($stock['stock_reserve'] ?? 0);
                                $stockAlerte = (float)($stock['stock_alerte'] ?? 0);
                                $stockSecurite = (float)($stock['stock_securite'] ?? 0);
                                $valeurStock = (float)($stock['valeur_stock'] ?? 0);
                                $niveauStock = (string)($stock['niveau_stock'] ?? 'NORMAL');
                            ?>
                        <tr class="stock-row <?= $niveauStock === 'CRITIQUE' ? 'stock-rupture' : '' ?>">
                            <td class="border border-gray-300 px-4 py-2">
                                <div>
                                    <div class="font-semibold"><?= htmlspecialchars($stock['nom']) ?></div>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($stock['code_cip']) ?></div>
                                    <?php if ($stock['categorie']): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($stock['categorie']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <?php
                                    $rayon = $stock['rayon'] ?? '';
                                    $badgeClass = 'badge-rayon';
                                    if (str_contains($rayon, 'Frigo')) $badgeClass = 'badge-frigo';
                                    elseif (str_contains($rayon, 'Armoire')) $badgeClass = 'badge-armoire';
                                    elseif (str_contains($rayon, 'Vitrine')) $badgeClass = 'badge-vitrine';
                                    elseif (str_contains($rayon, 'Comptoir')) $badgeClass = 'badge-comptoir';
                                    elseif (str_contains($rayon, 'Magasin')) $badgeClass = 'badge-magasin';
                                    elseif (str_contains($rayon, 'Autres')) $badgeClass = 'badge-autres';
                                ?>
                                <?php if ($rayon): ?>
                                    <span class="<?= $badgeClass ?> text-white text-xs px-2 py-1 rounded">
                                        <?= htmlspecialchars($rayon) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="font-semibold <?= $quantiteDisponible <= 0 ? 'stock-critique' : 'stock-normal' ?>">
                                    <?= number_format($quantiteDisponible) ?>
                                </span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="font-semibold"><?= number_format($quantiteTheorique) ?></span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="text-orange-600"><?= number_format($stockReserve) ?></span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="text-orange-600"><?= number_format($stockAlerte) ?></span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="text-red-600"><?= number_format($stockSecurite) ?></span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-right">
                                <?= number_format($valeurStock, 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <span class="badge-<?= strtolower($niveauStock) ?> text-white text-xs px-2 py-1 rounded">
                                    <?= htmlspecialchars($niveauStock) ?>
                                </span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="/stock/details?id=<?= $stock['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($canEditPrices): ?>
                                        <a href="/stock/modifier/<?= $stock['id'] ?>?return_to=<?= urlencode($returnTo) ?>"
                                           class="text-yellow-600 hover:text-yellow-800" title="Modifier prix">
                                            <i class="fas fa-tags"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($stock['quantite_disponible'] <= $stock['stock_alerte']): ?>
                                        <button onclick="commanderAuto(<?= $stock['id'] ?>)" 
                                                class="text-orange-600 hover:text-orange-800" title="Commander">
                                            <i class="fas fa-shopping-cart"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal de détails -->
    <div id="modalDetails" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-96 overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4">Détails du Produit</h3>
            <div id="detailsContent">
                <!-- Contenu dynamique -->
            </div>
            <div class="mt-6 flex space-x-3">
                <button onclick="closeModal('modalDetails')" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-lg">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // Variables globales
        let stocksData = <?= json_encode($stocks ?? []) ?>;
        const canEditPrices = <?= $canEditPrices ? 'true' : 'false' ?>;
        const returnTo = <?= json_encode($returnTo) ?>;
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            updateStatistiques();
            setupEventListeners();
        });

        // Met à jour les statistiques
        function updateStatistiques() {
            const totalProduits = stocksData.length;
            const stockTotal = stocksData.reduce((sum, stock) => sum + (stock.quantite_disponible || 0), 0);
            const valeurStock = stocksData.reduce((sum, stock) => sum + (stock.valeur_stock || 0), 0);
            const nombreAlertes = stocksData.filter(stock => 
                stock.niveau_stock === 'CRITIQUE' || stock.niveau_stock === 'ALERTE'
            ).length;

            document.getElementById('totalProduits').textContent = totalProduits;
            document.getElementById('stockTotal').textContent = formatNumber(stockTotal);
            document.getElementById('valeurStock').textContent = formatNumber(valeurStock) + ' FCFA';
            document.getElementById('nombreAlertes').textContent = nombreAlertes;
        }

        // Configure les écouteurs d'événements
        function setupEventListeners() {
            // Recherche de produits
            document.getElementById('searchProduit').addEventListener('input', function() {
                filterStocks();
            });

            // Filtre par niveau
            document.getElementById('filterNiveau').addEventListener('change', filterStocks);

            // Filtre par rayon
            document.getElementById('filterRayon').addEventListener('change', filterStocks);
        }

        // Filtre les stocks
        function filterStocks() {
            const searchTerm = document.getElementById('searchProduit').value.toLowerCase();
            const niveauFilter = document.getElementById('filterNiveau').value;
            const rayonFilter = document.getElementById('filterRayon').value;
            
            const filteredStocks = stocksData.filter(stock => {
                const matchesSearch = !searchTerm || 
                    stock.nom.toLowerCase().includes(searchTerm) ||
                    stock.code_cip.toLowerCase().includes(searchTerm);
                
                const matchesNiveau = !niveauFilter || stock.niveau_stock === niveauFilter;
                
                const matchesRayon = !rayonFilter || stock.rayon === rayonFilter;
                
                return matchesSearch && matchesNiveau && matchesRayon;
            });
            
            updateStocksTable(filteredStocks);
        }

        // Met à jour le tableau des stocks
        function updateStocksTable(stocks) {
            const tbody = document.getElementById('stocksTable');
            
            if (stocks.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-8 text-gray-500">
                            <i class="fas fa-search text-4xl mb-2"></i>
                            <p>Aucun produit trouvé</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = stocks.map(stock => {
                const rayon = stock.rayon || '';
                let badgeClass = 'badge-rayon';
                if (rayon.includes('Frigo')) badgeClass = 'badge-frigo';
                else if (rayon.includes('Armoire')) badgeClass = 'badge-armoire';
                else if (rayon.includes('Vitrine')) badgeClass = 'badge-vitrine';
                else if (rayon.includes('Comptoir')) badgeClass = 'badge-comptoir';
                else if (rayon.includes('Magasin')) badgeClass = 'badge-magasin';
                else if (rayon.includes('Autres')) badgeClass = 'badge-autres';
                
                return `
                <tr class="stock-row ${stock.niveau_stock === 'CRITIQUE' ? 'stock-rupture' : ''}">
                    <td class="border border-gray-300 px-4 py-2">
                        <div>
                            <div class="font-semibold">${stock.nom}</div>
                            <div class="text-sm text-gray-600">${stock.code_cip}</div>
                            ${stock.categorie ? `<div class="text-xs text-gray-500">${stock.categorie}</div>` : ''}
                        </div>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        ${rayon ? `<span class="${badgeClass} text-white text-xs px-2 py-1 rounded">${rayon}</span>` : '<span class="text-gray-400 text-xs">-</span>'}
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        <span class="font-semibold ${stock.quantite_disponible <= 0 ? 'stock-critique' : 'stock-normal'}">
                            ${formatNumber(stock.quantite_disponible)}
                        </span>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        <span class="font-semibold">${formatNumber(stock.quantite_theorique)}</span>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        <span class="text-orange-600">${formatNumber(stock.stock_reserve)}</span>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        <span class="text-orange-600">${formatNumber(stock.stock_alerte)}</span>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        <span class="text-red-600">${formatNumber(stock.stock_securite)}</span>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-right">
                        ${formatNumber(stock.valeur_stock)} FCFA
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        <span class="badge-${stock.niveau_stock.toLowerCase()} text-white text-xs px-2 py-1 rounded">
                            ${stock.niveau_stock}
                        </span>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-center">
                        <div class="flex justify-center space-x-2">
                            <a href="/stock/details?id=${stock.id}" 
                               class="text-blue-600 hover:text-blue-800" title="Voir détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            ${canEditPrices ? `
                                <a href="/stock/modifier/${stock.id}?return_to=${encodeURIComponent(returnTo)}"
                                   class="text-yellow-600 hover:text-yellow-800" title="Modifier prix">
                                    <i class="fas fa-tags"></i>
                                </a>
                            ` : ''}
                            ${stock.quantite_disponible <= stock.stock_alerte ? `
                                <button onclick="commanderAuto(${stock.id})" 
                                        class="text-orange-600 hover:text-orange-800" title="Commander">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
        }

        // Voir les détails d'un produit
        function voirDetails(produitId) {
            const stock = stocksData.find(s => s.id === produitId);
            if (!stock) return;
            
            const content = document.getElementById('detailsContent');
            content.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold mb-2">Informations Produit</h4>
                        <div class="space-y-2 text-sm">
                            <div><strong>Nom:</strong> ${stock.nom}</div>
                            <div><strong>Code CIP:</strong> ${stock.code_cip}</div>
                            <div><strong>Catégorie:</strong> ${stock.categorie || 'N/A'}</div>
                            <div><strong>Fournisseur:</strong> ${stock.fournisseur || 'N/A'}</div>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-2">État du Stock</h4>
                        <div class="space-y-2 text-sm">
                            <div><strong>Stock Réel:</strong> ${formatNumber(stock.quantite_disponible)}</div>
                            <div><strong>Stock Théorique:</strong> ${formatNumber(stock.quantite_theorique)}</div>
                            <div><strong>Stock Réservé:</strong> ${formatNumber(stock.stock_reserve)}</div>
                            <div><strong>Valeur:</strong> ${formatNumber(stock.valeur_stock)} FCFA</div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <h4 class="font-semibold mb-2">Seuils d'Alerte</h4>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <strong>Alerte:</strong> 
                            <span class="text-orange-600">${formatNumber(stock.stock_alerte)}</span>
                        </div>
                        <div>
                            <strong>Sécurité:</strong> 
                            <span class="text-red-600">${formatNumber(stock.stock_securite)}</span>
                        </div>
                        <div>
                            <strong>Niveau:</strong> 
                            <span class="badge-${stock.niveau_stock.toLowerCase()} text-white text-xs px-2 py-1 rounded">
                                ${stock.niveau_stock}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="/stock/details?id=${produitId}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        Voir détails complets
                    </a>
                </div>
            `;
            
            document.getElementById('modalDetails').classList.remove('hidden');
        }

        // Synchronise les stocks
        function synchroniserStocks() {
            axios.post('/stock/synchroniser-stocks')
            .then(response => {
                if (response.data.success) {
                    alert('Stocks synchronisés avec succès!');
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Erreur synchronisation:', error);
                alert('Erreur lors de la synchronisation');
            });
        }

        // Commande automatique
        function commanderAuto(produitId) {
            if (confirm('Générer une commande automatique pour ce produit?')) {
                window.location.href = '/stock/commandes-automatiques';
            }
        }

        // Ferme une modale
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Formate un nombre
        function formatNumber(number) {
            return new Intl.NumberFormat('fr-FR').format(number || 0);
        }
    </script>
</body>
</html>
