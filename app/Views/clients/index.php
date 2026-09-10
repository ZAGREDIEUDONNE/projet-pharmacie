<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Clients - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .client-row:hover { background-color: #f3f4f6; }
        .badge-ordinaire { background-color: #6b7280; }
        .badge-courant { background-color: #3b82f6; }
        .badge-courant-depot { background-color: #8b5cf6; }
        .badge-courant-bon { background-color: #f59e0b; }
        .badge-courant-carnet { background-color: #10b981; }
        .badge-autres-clients { background-color: #f97316; }
        .solde-debiteur { color: #dc2626; font-weight: bold; }
        .solde-ok { color: #10b981; }
    </style>
</head>
<body class="bg-gray-100">
    <?php
        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $isAssistant = $roleId === 3 || $roleCode === 'ASSISTANT';
        $canDeleteClient = $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true);
        $defaultReturnTo = match (true) {
            $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
            $isAssistant => '/assistant/dashboard',
            in_array($roleCode, ['CHARGE_COMMANDE', 'CHARGE_DE_COMMANDE', 'COMMANDE'], true) => '/commande/dashboard',
            $roleId === 2 || $roleCode === 'VENDEUR' => '/vente',
            default => '/vente',
        };
        $returnTo = (string)($_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-users text-2xl"></i>
                    <h1 class="text-xl font-bold">GESTION CLIENTS</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm">
                        <span class="font-semibold">Utilisateur:</span>
                        <?= htmlspecialchars((string)($user['name'] ?? $_SESSION['username'] ?? 'Non connecte')) ?>
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
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-search mr-2 text-blue-600"></i>
                    Recherche Clients
                </h2>
                <div class="flex space-x-3">
                    <a href="/clients/creer?return_to=<?= urlencode($returnTo) ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Nouveau Client
                    </a>
                    <a href="/clients/debiteurs?return_to=<?= urlencode($returnTo) ?>" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Debiteurs
                    </a>
                    <a href="/clients/statistiques?return_to=<?= urlencode($returnTo) ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-chart-bar mr-2"></i>Statistiques
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="relative">
                    <input type="text"
                           id="searchClient"
                           placeholder="Code, nom, prenom, telephone, email..."
                           class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                </div>
                <select id="filterType" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Tous les types</option>
                    <option value="ORDINAIRE">Ordinaire</option>
                    <option value="COURANT">Courant</option>
                    <option value="COURANT_DEPOT">Courant - Dépôt</option>
                    <option value="COURANT_BON">Courant - Bon</option>
                    <option value="COURANT_CARNET">Courant - Carnet</option>
                    <option value="AUTRES_CLIENTS">Autres clients</option>
                </select>
                <button onclick="searchClients()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-search mr-2"></i>Rechercher
                </button>
            </div>
            <div id="searchFeedback" class="hidden mt-3 rounded-lg p-3 text-sm"></div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold"><?= !empty($isDebiteursList) ? 'Clients débiteurs' : 'Liste des Clients' ?></h3>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        <span id="nombreClients"><?= count($clients ?? []) ?></span> client(s)
                    </span>
                    <a href="/clients/exporter" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-download mr-1"></i>Exporter
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto" style="min-width: 2800px;">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Code</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Matricule</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Nom / Raison sociale</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Prénom</th>
                            <th class="border border-gray-300 px-3 py-2 text-center whitespace-nowrap">Type</th>
                            <th class="border border-gray-300 px-3 py-2 text-center whitespace-nowrap">Statut</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Tél. principal</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Tél. secondaire</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Email</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Date naissance</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Adresse</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Ville</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">N° IFU</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">N° RCCM</th>
                            <th class="border border-gray-300 px-3 py-2 text-right whitespace-nowrap">Plafond crédit</th>
                            <th class="border border-gray-300 px-3 py-2 text-right whitespace-nowrap">Solde initial</th>
                            <th class="border border-gray-300 px-3 py-2 text-right whitespace-nowrap">Solde actuel</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">N° Assurance</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Compagnie assurance</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Observations</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Date création</th>
                            <th class="border border-gray-300 px-3 py-2 text-left whitespace-nowrap">Dernière modif.</th>
                            <th class="border border-gray-300 px-3 py-2 text-center whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="clientsTable">
                        <?php foreach (($clients ?? []) as $client): ?>
                            <?php
                                $id = (int)($client['id'] ?? 0);
                                $code = (string)($client['code'] ?? '');
                                $codeClient = (string)($client['code_client'] ?? '');
                                $matricule = (string)($client['matricule'] ?? '');
                                $nom = (string)($client['nom'] ?? '');
                                $prenom = (string)($client['prenom'] ?? '');
                                $telephone = (string)($client['telephone'] ?? '');
                                $telephoneSecondaire = (string)($client['telephone_secondaire'] ?? '');
                                $email = (string)($client['email'] ?? '');
                                $dateNaissance = (string)($client['date_naissance'] ?? '');
                                $adresse = (string)($client['adresse'] ?? '');
                                $ville = (string)($client['ville'] ?? '');
                                $numeroIfu = (string)($client['numero_ifu'] ?? '');
                                $numeroRccm = (string)($client['numero_rccm'] ?? '');
                                $numeroAssurance = (string)($client['numero_assurance'] ?? '');
                                $compagnieAssurance = (string)($client['compagnie_assurance'] ?? '');
                                $typeClient = trim((string)($client['type_client'] ?? ''));
                                $typeClient = $typeClient !== '' ? $typeClient : 'ORDINAIRE';
                                $typeLabel = \App\Models\Client::getClientTypeLabel($typeClient);
                                $badgeClass = strtolower(str_replace('_', '-', $typeClient));
                                $plafond = (float)($client['plafond'] ?? 0);
                                $soldeInitial = (float)($client['solde_initial'] ?? 0);
                                $solde = (float)($client['solde'] ?? 0);
                                $observations = (string)($client['observations'] ?? '');
                                $notes = (string)($client['notes'] ?? '');
                                $createdAt = (string)($client['created_at'] ?? '');
                                $updatedAt = (string)($client['updated_at'] ?? '');
                                $isActif = (int)($client['is_actif'] ?? 0) === 1;
                            ?>
                            <tr class="client-row">
                                <td class="border border-gray-300 px-3 py-2">
                                    <span class="font-mono text-xs"><?= htmlspecialchars($codeClient ?: $code) ?></span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <span class="font-mono text-xs"><?= htmlspecialchars($matricule ?: '-') ?></span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <div class="font-semibold"><?= htmlspecialchars($nom) ?></div>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($prenom ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    <span class="badge-<?= htmlspecialchars($badgeClass) ?> text-white text-xs px-2 py-1 rounded">
                                        <?= htmlspecialchars($typeLabel) ?>
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    <span class="<?= $isActif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?> text-xs px-2 py-1 rounded">
                                        <?= $isActif ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($telephone ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($telephoneSecondaire ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($email ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($dateNaissance ? date('d/m/Y', strtotime($dateNaissance)) : '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 max-w-xs truncate" title="<?= htmlspecialchars($adresse) ?>">
                                    <?= htmlspecialchars($adresse ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($ville ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($numeroIfu ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($numeroRccm ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-right">
                                    <?= number_format($plafond, 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-right">
                                    <?= number_format($soldeInitial, 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-right">
                                    <span class="<?= $solde > 0 ? 'solde-debiteur' : 'solde-ok' ?>">
                                        <?= number_format($solde, 0, ',', ' ') ?> FCFA
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($numeroAssurance ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($compagnieAssurance ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 max-w-xs truncate" title="<?= htmlspecialchars($observations ?: $notes) ?>">
                                    <?= htmlspecialchars($observations ?: $notes ?: '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($createdAt ? date('d/m/Y H:i', strtotime($createdAt)) : '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <?= htmlspecialchars($updatedAt ? date('d/m/Y H:i', strtotime($updatedAt)) : '-') ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    <div class="flex justify-center space-x-1">
                                        <a href="/clients/fiche?id=<?= $id ?>" class="text-blue-600 hover:text-blue-800" title="Voir fiche">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/clients/modifier?id=<?= $id ?>&return_to=<?= urlencode($returnTo) ?>" class="text-green-600 hover:text-green-800" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/suivi-client/solde-courant?client_id=<?= $id ?>" class="text-purple-600 hover:text-purple-800" title="Compte client">
                                            <i class="fas fa-money-bill"></i>
                                        </a>
                                        <a href="/suivi-client/releve-courant?client_id=<?= $id ?>" class="text-indigo-600 hover:text-indigo-800" title="Règlements">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <a href="/ventes?client_id=<?= $id ?>" class="text-orange-600 hover:text-orange-800" title="Ventes">
                                            <i class="fas fa-shopping-cart"></i>
                                        </a>
                                        <button onclick="window.print()" class="text-gray-600 hover:text-gray-800" title="Imprimer">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <?php if ($canDeleteClient && $solde == 0): ?>
                                            <form method="POST" action="/clients/desactiver" class="inline" onsubmit="return confirm('Supprimer ce client ?');">
                                                <input type="hidden" name="client_id" value="<?= $id ?>">
                                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($clients ?? [])): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-users text-4xl mb-2"></i>
                    <p>Aucun client trouve</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="modalPlafond" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Verification Plafond de Credit</h3>
            <div id="plafondContent" class="space-y-3"></div>
            <div class="mt-6 flex space-x-3">
                <button onclick="closeModal('modalPlafond')" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-lg">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        function formatNumber(number) {
            return new Intl.NumberFormat('fr-FR').format(Number(number ?? 0));
        }

        function badgeClass(typeClient) {
            return String(typeClient || 'ORDINAIRE').toLowerCase().replaceAll('_', '-');
        }

        function showSearchFeedback(message, isError = false) {
            const box = document.getElementById('searchFeedback');
            if (!box) return;
            box.textContent = message;
            box.className = isError
                ? 'mt-3 rounded-lg p-3 text-sm bg-red-50 border border-red-200 text-red-700'
                : 'mt-3 rounded-lg p-3 text-sm bg-blue-50 border border-blue-200 text-blue-700';
            box.classList.remove('hidden');
        }

        function searchClients() {
            const query = document.getElementById('searchClient').value.trim();
            const type = document.getElementById('filterType').value;

            axios.get('/clients/rechercher', {
                params: { q: query, type: type }
            })
            .then(response => {
                if (response.data && response.data.success) {
                    const clients = response.data.clients || [];
                    updateClientsTable(clients);
                    document.getElementById('nombreClients').textContent = clients.length;
                    showSearchFeedback(clients.length + ' client(s) trouve(s).');
                } else {
                    showSearchFeedback((response.data && response.data.message) || 'Recherche impossible.', true);
                }
            })
            .catch(error => {
                console.error('Erreur recherche:', error);
                const message = error.response?.data?.message || 'Erreur lors de la recherche des clients.';
                showSearchFeedback(message, true);
            });
        }

        function updateClientsTable(clients) {
            const tbody = document.getElementById('clientsTable');

            if (!clients.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="22" class="text-center py-8 text-gray-500">
                            <i class="fas fa-users text-4xl mb-2"></i>
                            <p>Aucun client trouve</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = clients.map(client => {
                const id = Number(client.id ?? 0);
                const code = escapeHtml(client.code);
                const codeClient = escapeHtml(client.code_client || code);
                const matricule = escapeHtml(client.matricule || '-');
                const nom = escapeHtml(client.nom);
                const prenom = escapeHtml(client.prenom || '-');
                const telephone = escapeHtml(client.telephone || '-');
                const telephoneSecondaire = escapeHtml(client.telephone_secondaire || '-');
                const email = escapeHtml(client.email || '-');
                const dateNaissance = client.date_naissance ? new Date(client.date_naissance).toLocaleDateString('fr-FR') : '-';
                const adresse = escapeHtml(client.adresse || '-');
                const ville = escapeHtml(client.ville || '-');
                const numeroIfu = escapeHtml(client.numero_ifu || '-');
                const numeroRccm = escapeHtml(client.numero_rccm || '-');
                const numeroAssurance = escapeHtml(client.numero_assurance || '-');
                const compagnieAssurance = escapeHtml(client.compagnie_assurance || '-');
                const rawTypeClient = String(client.type_client || '').trim() || 'ORDINAIRE';
                const typeLabel = escapeHtml(rawTypeClient);
                const plafond = Number(client.plafond || 0);
                const soldeInitial = Number(client.solde_initial || 0);
                const solde = Number(client.solde || 0);
                const observations = escapeHtml(client.observations || client.notes || '-');
                const createdAt = client.created_at ? new Date(client.created_at).toLocaleString('fr-FR') : '-';
                const updatedAt = client.updated_at ? new Date(client.updated_at).toLocaleString('fr-FR') : '-';
                const isActif = Number(client.is_actif ?? 0) === 1;

                return `
                    <tr class="client-row">
                        <td class="border border-gray-300 px-3 py-2"><span class="font-mono text-xs">${codeClient}</span></td>
                        <td class="border border-gray-300 px-3 py-2"><span class="font-mono text-xs">${matricule}</span></td>
                        <td class="border border-gray-300 px-3 py-2"><div class="font-semibold">${nom}</div></td>
                        <td class="border border-gray-300 px-3 py-2">${prenom}</td>
                        <td class="border border-gray-300 px-3 py-2 text-center"><span class="badge-${badgeClass(rawTypeClient)} text-white text-xs px-2 py-1 rounded">${typeLabel}</span></td>
                        <td class="border border-gray-300 px-3 py-2 text-center"><span class="${isActif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'} text-xs px-2 py-1 rounded">${isActif ? 'Actif' : 'Inactif'}</span></td>
                        <td class="border border-gray-300 px-3 py-2">${telephone}</td>
                        <td class="border border-gray-300 px-3 py-2">${telephoneSecondaire}</td>
                        <td class="border border-gray-300 px-3 py-2">${email}</td>
                        <td class="border border-gray-300 px-3 py-2">${dateNaissance}</td>
                        <td class="border border-gray-300 px-3 py-2 max-w-xs truncate" title="${adresse}">${adresse}</td>
                        <td class="border border-gray-300 px-3 py-2">${ville}</td>
                        <td class="border border-gray-300 px-3 py-2">${numeroIfu}</td>
                        <td class="border border-gray-300 px-3 py-2">${numeroRccm}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">${formatNumber(plafond)} FCFA</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">${formatNumber(soldeInitial)} FCFA</td>
                        <td class="border border-gray-300 px-3 py-2 text-right"><span class="${solde > 0 ? 'solde-debiteur' : 'solde-ok'}">${formatNumber(solde)} FCFA</span></td>
                        <td class="border border-gray-300 px-3 py-2">${numeroAssurance}</td>
                        <td class="border border-gray-300 px-3 py-2">${compagnieAssurance}</td>
                        <td class="border border-gray-300 px-3 py-2 max-w-xs truncate" title="${observations}">${observations}</td>
                        <td class="border border-gray-300 px-3 py-2">${createdAt}</td>
                        <td class="border border-gray-300 px-3 py-2">${updatedAt}</td>
                        <td class="border border-gray-300 px-3 py-2 text-center">
                            <div class="flex justify-center space-x-1">
                                <a href="/clients/fiche?id=${id}" class="text-blue-600 hover:text-blue-800" title="Voir fiche"><i class="fas fa-eye"></i></a>
                                <a href="/clients/modifier?id=${id}&return_to=<?= urlencode($returnTo) ?>" class="text-green-600 hover:text-green-800" title="Modifier"><i class="fas fa-edit"></i></a>
                                <a href="/suivi-client/solde-courant?client_id=${id}" class="text-purple-600 hover:text-purple-800" title="Compte client"><i class="fas fa-money-bill"></i></a>
                                <a href="/suivi-client/releve-courant?client_id=${id}" class="text-indigo-600 hover:text-indigo-800" title="Règlements"><i class="fas fa-file-invoice"></i></a>
                                <a href="/ventes?client_id=${id}" class="text-orange-600 hover:text-orange-800" title="Ventes"><i class="fas fa-shopping-cart"></i></a>
                                <button onclick="window.print()" class="text-gray-600 hover:text-gray-800" title="Imprimer"><i class="fas fa-print"></i></button>
                                ${<?= $canDeleteClient ? 'true' : 'false' ?> && solde == 0 ? `
                                    <form method="POST" action="/clients/desactiver" class="inline" onsubmit="return confirm('Supprimer ce client ?');">
                                        <input type="hidden" name="client_id" value="${id}">
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Supprimer"><i class="fas fa-trash"></i></button>
                                    </form>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function verifierPlafond(clientId) {
            const montantAchat = prompt('Montant de l\'achat souhaite (FCFA):');
            if (!montantAchat || isNaN(montantAchat)) {
                return;
            }

            axios.get('/clients/verifier-plafond', {
                params: { client_id: clientId, montant: parseFloat(montantAchat) }
            })
            .then(response => {
                if (response.data.success) {
                    const data = response.data;
                    const content = document.getElementById('plafondContent');

                    content.innerHTML = `
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span>Solde actuel:</span>
                                <span class="font-semibold">${formatNumber(data.solde_actuel)} FCFA</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Plafond credit:</span>
                                <span class="font-semibold">${formatNumber(data.plafond_credit)} FCFA</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Credit disponible:</span>
                                <span class="font-semibold ${Number(data.credit_disponible ?? 0) > 0 ? 'text-green-600' : 'text-red-600'}">
                                    ${formatNumber(data.credit_disponible)} FCFA
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Montant achat:</span>
                                <span class="font-semibold">${formatNumber(montantAchat)} FCFA</span>
                            </div>
                        </div>
                        <div class="mt-4 p-3 rounded-lg ${data.peut_acheter ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            <i class="fas fa-${data.peut_acheter ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>
                            ${escapeHtml(data.message)}
                        </div>
                    `;

                    document.getElementById('modalPlafond').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Erreur verification plafond:', error);
                alert('Erreur lors de la verification du plafond');
            });
        }

        function desactiverClient(clientId) {
            if (!confirm('Etes-vous sur de vouloir desactiver ce client?')) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/clients/desactiver';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'client_id';
            input.value = clientId;

            const returnInput = document.createElement('input');
            returnInput.type = 'hidden';
            returnInput.name = 'return_to';
            returnInput.value = <?= json_encode($returnTo) ?>;

            form.appendChild(input);
            form.appendChild(returnInput);
            document.body.appendChild(form);
            form.submit();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        document.getElementById('searchClient').addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchClients();
            }
        });

        document.getElementById('filterType').addEventListener('change', searchClients);
    </script>
</body>
</html>
