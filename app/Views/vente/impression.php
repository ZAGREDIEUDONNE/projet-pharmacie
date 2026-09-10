<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Impression Ticket') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $defaultReturnTo = match (true) {
            $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
            default => '/vente',
        };
        $returnTo = (string)($returnTo ?? $_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-cash-register text-green-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Impression Ticket</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Vendeur: <?= htmlspecialchars($user['name'] ?? 'Vendeur') ?></span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour Vente
                    </a>
                    <a href="/logout" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <?= htmlspecialchars((string)$_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Impression de Tickets</h1>
            <p class="text-gray-600">Recherchez et imprimez les tickets de vente</p>
        </div>

        <!-- Search Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Rechercher un Ticket</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Numéro de Ticket</label>
                    <input type="text" id="ticketNumber" placeholder="Ex: 001234"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" id="ticketDate" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                    <input type="text" id="clientName" placeholder="Nom du client"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="searchTickets()" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-search mr-2"></i>Rechercher
                </button>
            </div>
        </div>

        <!-- Results Section -->
        <div id="resultsSection" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Résultats</h2>
            <div id="ticketsList" class="space-y-4">
                <!-- Results will be loaded here -->
            </div>
        </div>

        <!-- Print Preview Section -->
        <div id="printSection" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Aperçu du Ticket</h2>
                <div class="space-x-2">
                    <button onclick="printTicket()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </button>
                </div>
            </div>
            
            <!-- Ticket Preview -->
            <div id="ticketPreview" class="border-2 border-dashed border-gray-300 p-4 max-w-md mx-auto">
                <!-- Ticket content will be loaded here -->
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <i class="fas fa-clock-rotate-left text-blue-600 text-2xl mb-3"></i>
                <h3 class="text-lg font-semibold text-blue-800">Derniers Tickets</h3>
                <p class="text-sm text-blue-600 mt-2">Voir les tickets récents</p>
                <button onclick="loadRecentTickets()" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Afficher
                </button>
            </div>
            
            <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                <i class="fas fa-cash-register text-green-600 text-2xl mb-3"></i>
                <h3 class="text-lg font-semibold text-green-800">Tickets du Jour</h3>
                <p class="text-sm text-green-600 mt-2">Ventes d'aujourd'hui</p>
                <button onclick="loadTodayTickets()" class="mt-3 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    Afficher
                </button>
            </div>
            
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
                <i class="fas fa-chart-line text-purple-600 text-2xl mb-3"></i>
                <h3 class="text-lg font-semibold text-purple-800">Statistiques</h3>
                <p class="text-sm text-purple-600 mt-2">Résumé des ventes</p>
                <button onclick="loadStatistics()" class="mt-3 bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                    Afficher
                </button>
            </div>
        </div>
    </main>

    <!-- Print Styles -->
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #ticketPreview, #ticketPreview * {
                visibility: visible;
            }
            #ticketPreview {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>

    <script>
        // Données de vente depuis le controller
        const venteData = <?= json_encode($vente ?? []) ?>;
        const ticketsData = <?= json_encode($tickets ?? []) ?>;
        
        let currentTicket = null;
        if (venteData && venteData.id) {
            currentTicket = venteData;
        }

        function toNumber(value) {
            const number = Number.parseFloat(value);
            return Number.isFinite(number) ? number : 0;
        }

        function formatMoney(value) {
            return toNumber(value).toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' FCFA';
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function getSelectedTickets() {
            if (Array.isArray(ticketsData) && ticketsData.length > 0) {
                return ticketsData;
            }

            return currentTicket ? [currentTicket] : [];
        }

        function searchTickets() {
            const ticketNumber = document.getElementById('ticketNumber').value;
            const ticketDate = document.getElementById('ticketDate').value;
            const clientName = document.getElementById('clientName').value;
            
            const filteredTickets = getSelectedTickets().filter(ticket => {
                const ticketId = String(ticket.id ?? '');
                const numeroFacture = String(ticket.numero_facture ?? '');
                const dateVente = String(ticket.date_vente ?? '').slice(0, 10);
                const client = `${ticket.client_nom ?? ''} ${ticket.client_prenom ?? ''}`.toLowerCase();

                return (!ticketNumber || ticketId.includes(ticketNumber) || numeroFacture.includes(ticketNumber)) &&
                       (!ticketDate || dateVente === ticketDate) &&
                       (!clientName || client.includes(clientName.toLowerCase()));
            });
            
            displayResults(filteredTickets);
        }

        function displayResults(tickets) {
            const resultsSection = document.getElementById('resultsSection');
            const ticketsList = document.getElementById('ticketsList');
            
            if (tickets.length === 0) {
                ticketsList.innerHTML = '<p class="text-gray-500">Aucun ticket trouvé</p>';
                resultsSection.classList.remove('hidden');
                return;
            }
            
            let html = '';
            tickets.forEach(ticket => {
                ticket.montant = toNumber(ticket.montant_net || ticket.montant_total);
                ticket.vendeur = ticket.vendeur_nom_complet || ticket.vendeur_nom || 'Non specifie';
                html += `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer" onclick="showTicketPreview('${ticket.id}')">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-semibold text-gray-800">Ticket #${escapeHtml(ticket.numero_facture || ticket.id)}</h3>
                    <p><strong>Client:</strong> ${escapeHtml(ticket.client_nom || 'Non specifie')}</p>
                                <p class="text-sm text-gray-600">Date: ${escapeHtml(String(ticket.date_vente || '').slice(0, 10))}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-green-600">${ticket.montant.toFixed(2)} FCFA</p>
                                <p class="text-sm text-gray-500">Vendeur: ${escapeHtml(ticket.vendeur_nom_complet || ticket.vendeur_nom || 'Non specifie')}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            ticketsList.innerHTML = html;
            resultsSection.classList.remove('hidden');
        }

        function showTicketPreview(ticketId) {
            const ticket = ticketId ? getSelectedTickets().find(item => String(item.id) === String(ticketId)) : null;
            if (!ticket) return;
            currentTicket = ticket;
            
            const printSection = document.getElementById('printSection');
            const ticketPreview = document.getElementById('ticketPreview');
            
            let produitsHtml = '';
            const articles = Array.isArray(ticket.articles) ? ticket.articles : [];
            if (articles.length > 0) {
                articles.forEach(produit => {
                    const prix = toNumber(produit.prix_unitaire || produit.prix);
                    const quantite = toNumber(produit.quantite);
                    const total = toNumber(produit.montant_total || produit.total_ligne || (prix * quantite));
                    produit.prix_vente = prix;
                    produit.total_ligne = total;
                    produitsHtml += `
                        <tr class="border-b">
                            <td class="py-2">${escapeHtml(produit.produit_nom || produit.nom || '')}</td>
                            <td class="py-2 text-center">${quantite}</td>
                            <td class="py-2 text-right">${formatMoney(prix)}</td>
                            <td class="py-2 text-right">${formatMoney(total)}</td>
                        </tr>
                    `;
                });
            } else {
                produitsHtml = '<tr><td colspan="4" class="py-3 text-center text-gray-500">Aucun article trouve pour cette vente</td></tr>';
            }

            let ordonnancesHtml = '';
            const ordonnances = Array.isArray(ticket.ordonnances) ? ticket.ordonnances : [];
            if (ordonnances.length > 0) {
                ordonnancesHtml = `
                    <div class="border-t pt-3 mt-3 text-sm">
                        <p class="font-bold mb-2">Ordonnance medicale</p>
                        ${ordonnances.map(ord => `
                            <div class="mb-2">
                                <p><strong>Numero:</strong> ${escapeHtml(ord.numero_ordonnance || '')}</p>
                                <p><strong>Date:</strong> ${escapeHtml(ord.date_ordonnance || '')}</p>
                                <p><strong>Medecin:</strong> ${escapeHtml(ord.nom_medecin || '')}</p>
                                <p><strong>Structure:</strong> ${escapeHtml(ord.structure_sanitaire || '')}</p>
                                <p><strong>Patient:</strong> ${escapeHtml(ord.nom_patient || '')} - ${escapeHtml(ord.telephone_patient || '')}</p>
                                ${ord.observation ? `<p><strong>Observation:</strong> ${escapeHtml(ord.observation)}</p>` : ''}
                                ${Array.isArray(ord.produits) && ord.produits.length ? `<p><strong>Produits couverts:</strong> ${ord.produits.map(p => escapeHtml(p.produit_nom || '')).join(', ')}</p>` : ''}
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            // Afficher les détails de paiement
            let paiementHtml = '';
            const paiementDetails = ticket.paiement_details;
            if (paiementDetails) {
                const modePaiement = paiementDetails.mode_paiement || ticket.type_paiement || 'ESPECE';
                let modeLabel = modePaiement;
                switch (modePaiement) {
                    case 'ESPECE': modeLabel = 'Espèces'; break;
                    case 'CARNET': modeLabel = 'Carnet'; break;
                    case 'DEPOT': modeLabel = 'Dépôt'; break;
                    case 'CARTE_VISA': modeLabel = 'Carte Visa'; break;
                    case 'MOBILE_MONEY': modeLabel = 'Mobile Money'; break;
                    case 'CHEQUE': modeLabel = 'Chèque'; break;
                    case 'BON': modeLabel = 'Bon'; break;
                }

                let detailsHtml = '';
                if (modePaiement === 'DEPOT') {
                    detailsHtml = `
                        <p><strong>Etablissement:</strong> ${escapeHtml(paiementDetails.depot_nom_etablissement || '')}</p>
                        <p><strong>Adresse:</strong> ${escapeHtml(paiementDetails.depot_adresse || '')}</p>
                        <p><strong>Téléphone:</strong> ${escapeHtml(paiementDetails.depot_telephone || '')}</p>
                        <p><strong>Arrêté ministériel:</strong> ${escapeHtml(paiementDetails.depot_numero_arrete || '')}</p>
                    `;
                } else if (modePaiement === 'MOBILE_MONEY') {
                    let operateurLabel = paiementDetails.mobile_operateur || '';
                    switch (operateurLabel) {
                        case 'ORANGE_MONEY': operateurLabel = 'Orange Money'; break;
                        case 'MOOV_MONEY': operateurLabel = 'Moov Money'; break;
                        case 'TELECEL_MONEY': operateurLabel = 'Telecel Money'; break;
                    }
                    detailsHtml = `
                        <p><strong>Opérateur:</strong> ${escapeHtml(operateurLabel)}</p>
                        <p><strong>Titulaire:</strong> ${escapeHtml(paiementDetails.mobile_nom_titulaire || '')}</p>
                        <p><strong>Téléphone:</strong> ${escapeHtml(paiementDetails.mobile_telephone || '')}</p>
                    `;
                } else if (modePaiement === 'CHEQUE') {
                    detailsHtml = `
                        <p><strong>Numéro chèque:</strong> ${escapeHtml(paiementDetails.cheque_numero || '')}</p>
                        <p><strong>Banque:</strong> ${escapeHtml(paiementDetails.cheque_nom_banque || '')}</p>
                    `;
                } else if (modePaiement === 'BON') {
                    detailsHtml = `
                        <p><strong>Bénéficiaire:</strong> ${escapeHtml(paiementDetails.bon_nom_beneficiaire || '')}</p>
                        <p><strong>Téléphone:</strong> ${escapeHtml(paiementDetails.bon_telephone || '')}</p>
                        <p><strong>Matricule:</strong> ${escapeHtml(paiementDetails.bon_matricule || '')}</p>
                        <p><strong>Numéro bon:</strong> ${escapeHtml(paiementDetails.bon_numero_bon || '')}</p>
                    `;
                }

                paiementHtml = `
                    <div class="border-t pt-3 mt-3 text-sm">
                        <p class="font-bold mb-2">Mode de paiement: ${escapeHtml(modeLabel)}</p>
                        ${detailsHtml}
                    </div>
                `;
            } else {
                // Afficher le mode de paiement simple si pas de détails
                const modePaiement = ticket.type_paiement || 'ESPECE';
                let modeLabel = modePaiement;
                switch (modePaiement) {
                    case 'ESPECE': modeLabel = 'Espèces'; break;
                    case 'CARNET': modeLabel = 'Carnet'; break;
                    case 'DEPOT': modeLabel = 'Dépôt'; break;
                    case 'CARTE_VISA': modeLabel = 'Carte Visa'; break;
                    case 'MOBILE_MONEY': modeLabel = 'Mobile Money'; break;
                    case 'CHEQUE': modeLabel = 'Chèque'; break;
                    case 'BON': modeLabel = 'Bon'; break;
                    case 'CREDIT': modeLabel = 'Crédit'; break;
                }
                paiementHtml = `
                    <div class="border-t pt-3 mt-3 text-sm">
                        <p><strong>Mode de paiement:</strong> ${escapeHtml(modeLabel)}</p>
                    </div>
                `;
            }
            
            ticketPreview.innerHTML = `
                <div class="text-center mb-4">
                    <h2 class="text-xl font-bold">PHARMACIE ERP JDS</h2>
                    <p class="text-sm text-gray-600">TICKET DE CAISSE</p>
                </div>
                <div class="mb-4">
                    <p><strong>Ticket:</strong> #${escapeHtml(ticket.numero_facture || ticket.id)}</p>
                    <p><strong>Vente ID:</strong> ${escapeHtml(ticket.id)}</p>
                    <p><strong>Date:</strong> ${new Date(ticket.date_vente).toLocaleDateString('fr-FR')}</p>
                    <p><strong>Client:</strong> ${escapeHtml(ticket.client_nom || 'Non specifie')}</p>
                    <p><strong>Vendeur:</strong> ${escapeHtml(ticket.vendeur_nom_complet || ticket.vendeur_nom || 'Non specifie')}</p>
                </div>
                <table class="w-full mb-4">
                    <thead>
                        <tr class="border-b-2">
                            <th class="text-left py-2">Produit</th>
                            <th class="text-center py-2">Qté</th>
                            <th class="text-right py-2">Prix</th>
                            <th class="text-right py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${produitsHtml}
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 font-bold">
                            <td colspan="3" class="py-2">TOTAL</td>
                            <td class="py-2 text-right">${formatMoney(ticket.montant_net || ticket.montant_total)}</td>
                        </tr>
                    </tfoot>
                </table>
                ${ordonnancesHtml}
                ${paiementHtml}
            `;
            
            printSection.classList.remove('hidden');
            ticketPreview.classList.remove('hidden');
        }

        function loadRecentTickets() {
            displayResults(getSelectedTickets());
        }

        function loadTodayTickets() {
            const today = new Date().toISOString().split('T')[0];
            const todayTickets = getSelectedTickets().filter(t => String(t.date_vente || '').slice(0, 10) === today);
            displayResults(todayTickets);
        }

        function loadStatistics() {
            const tickets = getSelectedTickets();
            const total = tickets.reduce((sum, ticket) => sum + toNumber(ticket.montant_net || ticket.montant_total), 0);
            const today = new Date().toISOString().split('T')[0];
            const todayTickets = tickets.filter(ticket => String(ticket.date_vente || '').slice(0, 10) === today);
            const todayTotal = todayTickets.reduce((sum, ticket) => sum + toNumber(ticket.montant_net || ticket.montant_total), 0);

            const resultsSection = document.getElementById('resultsSection');
            const ticketsList = document.getElementById('ticketsList');

            ticketsList.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <p class="text-sm text-purple-700">Tickets charges</p>
                        <p class="text-2xl font-bold text-purple-900">${tickets.length}</p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-sm text-green-700">Total charges</p>
                        <p class="text-2xl font-bold text-green-900">${formatMoney(total)}</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-sm text-blue-700">Total du jour</p>
                        <p class="text-2xl font-bold text-blue-900">${formatMoney(todayTotal)}</p>
                    </div>
                </div>
            `;
            resultsSection.classList.remove('hidden');
        }

        function printTicket() {
            window.print();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tickets = getSelectedTickets();
            if (tickets.length > 0) {
                displayResults(tickets);
            }

            if (currentTicket) {
                showTicketPreview(currentTicket.id);
            }
        });
    </script>
</body>
</html>
