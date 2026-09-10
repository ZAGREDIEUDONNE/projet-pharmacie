<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caisse - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-row:hover { background-color: #f3f4f6; }
        .stock-critique { color: #dc2626; font-weight: bold; }
        .stock-alerte { color: #f59e0b; font-weight: bold; }
        .stock-normal { color: #10b981; }
        .btn-primary { background-color: #3b82f6; }
        .btn-danger { background-color: #ef4444; }
        .btn-success { background-color: #10b981; }
        .btn-warning { background-color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-cash-register text-2xl"></i>
                    <h1 class="text-xl font-bold">CAISSE RAPIDE</h1>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-sm">
                        <span class="font-semibold">Session:</span> 
                        <span class="text-yellow-300"><?= $session['numero_session'] ?? '--' ?></span>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">Caissier:</span> 
                        <?= htmlspecialchars($user['name'] ?? 'Invité') ?>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">Date:</span> 
                        <?= date('d/m/Y H:i') ?>
                    </div>
                    <a href="/caisse/fermeture" class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i> Fermer
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Section Recherche Produits -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-search mr-2 text-blue-600"></i>
                        Recherche Produits
                    </h2>
                    
                    <!-- Barre de recherche -->
                    <div class="mb-4">
                        <div class="relative">
                            <input type="text" 
                                   id="searchProduit" 
                                   placeholder="Code CIP, Code barre ou nom du produit..."
                                   class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Résultats de recherche -->
                    <div id="searchResults" class="hidden max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
                        <!-- Résultats AJAX -->
                    </div>

                    <!-- Tableau des ventes -->
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-shopping-cart mr-2 text-green-600"></i>
                            Panier de Vente
                        </h3>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-4 py-2 text-left">Code</th>
                                        <th class="border border-gray-300 px-4 py-2 text-left">Désignation</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Prix Vente</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Stock Réel</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Stock Théo</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Qté</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Remise</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Bon</th>
                                        <th class="border border-gray-300 px-4 py-2 text-left">Lot</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">Montant</th>
                                        <th class="border border-gray-300 px-4 py-2 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="panierArticles">
                                    <!-- Articles ajoutés dynamiquement -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-center text-gray-500" id="panierVide">
                            <i class="fas fa-shopping-basket text-4xl mb-2"></i>
                            <p>Aucun article dans le panier</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Client et Résumé -->
            <div class="space-y-6">
                <!-- Informations Client -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-user mr-2 text-purple-600"></i>
                        Informations Client
                    </h3>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type Client</label>
                            <select id="typeClient" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="ORDINAIRE">Ordinaire</option>
                                <option value="ASSURE">Assuré</option>
                                <option value="BENEFICIAIRE">Bénéficiaire</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Matricule Client *</label>
                            <input type="text" id="matriculeClient" placeholder="Matricule ou ID client"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Âge / Date Naissance *</label>
                            <input type="date" id="dateNaissance" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prescripteur</label>
                            <input type="text" id="prescripteur" placeholder="Nom du prescripteur"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Solde Client</label>
                            <div class="bg-gray-100 px-3 py-2 rounded-lg">
                                <span id="soldeClient" class="font-semibold">0 FCFA</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Résumé de Vente -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-calculator mr-2 text-orange-600"></i>
                        Résumé de Vente
                    </h3>
                    
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span>Total:</span>
                            <span id="totalVente" class="font-semibold">0 FCFA</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Remise Globale:</span>
                            <span id="remiseGlobale" class="font-semibold">0 FCFA</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Part Bon:</span>
                            <span id="partBon" class="font-semibold">0 FCFA</span>
                        </div>
                        <hr class="my-2">
                        <div class="flex justify-between text-lg font-bold">
                            <span>Net à Payer:</span>
                            <span id="netAPayer" class="text-green-600">0 FCFA</span>
                        </div>
                    </div>

                    <!-- Mode de paiement -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mode de Paiement</label>
                        <select id="modePaiement" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="ESPECE">Espèce</option>
                            <option value="CARTE">Carte Bancaire</option>
                            <option value="CHEQUE">Chèque</option>
                            <option value="MOBILE_MONEY">Mobile Money</option>
                            <option value="CREDIT">Crédit</option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 space-y-3">
                        <button id="btnComptabiliser" 
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition duration-200 disabled:bg-gray-400">
                            <i class="fas fa-cash-register mr-2"></i>
                            Comptabiliser Vente
                        </button>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <button id="btnImprimerTicket" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition duration-200 disabled:bg-gray-400">
                                <i class="fas fa-receipt mr-1"></i>
                                Ticket
                            </button>
                            <button id="btnImprimerFacture" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg transition duration-200 disabled:bg-gray-400">
                                <i class="fas fa-file-invoice mr-1"></i>
                                Facture
                            </button>
                        </div>
                        
                        <button id="btnAnnulerVente" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times-circle mr-2"></i>
                            Annuler Vente
                        </button>
                        
                        <button id="btnCorrigerTicket" 
                                class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>
                            Corriger Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de correction -->
    <div id="modalCorrection" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Corriger le Ticket</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de Correction</label>
                    <select id="typeCorrection" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="MODIFIER_QUANTITE">Modifier Quantité</option>
                        <option value="MODIFIER_PRIX">Modifier Prix</option>
                        <option value="AJOUT_REMISE">Ajouter Remise</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nouvelle Valeur</label>
                    <input type="text" id="valeurCorrection" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div class="flex space-x-3">
                    <button id="btnValiderCorrection" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg">
                        Valider
                    </button>
                    <button id="btnAnnulerCorrection" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-lg">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // Variables globales
        let panier = [];
        let currentSession = <?= json_encode($session ?? []) ?>;
        
        // Éléments DOM
        const searchInput = document.getElementById('searchProduit');
        const searchResults = document.getElementById('searchResults');
        const panierArticles = document.getElementById('panierArticles');
        const panierVide = document.getElementById('panierVide');
        
        // Recherche de produits
        searchInput.addEventListener('input', debounce(function(e) {
            const query = e.target.value.trim();
            if (query.length >= 2) {
                rechercherProduits(query);
            } else {
                searchResults.classList.add('hidden');
            }
        }, 300));
        
        function rechercherProduits(query) {
            axios.get('/ventes/rechercher-produits', { params: { q: query } })
                .then(response => {
                    if (response.data.success) {
                        afficherResultats(response.data.produits);
                    }
                })
                .catch(error => {
                    console.error('Erreur recherche:', error);
                });
        }
        
        function afficherResultats(produits) {
            if (produits.length === 0) {
                searchResults.innerHTML = '<div class="p-4 text-gray-500">Aucun produit trouvé</div>';
            } else {
                searchResults.innerHTML = produits.map(produit => `
                    <div class="p-3 hover:bg-gray-50 cursor-pointer border-b product-row" 
                         onclick="ajouterAuPanier(${produit.id})">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold">${produit.nom}</div>
                                <div class="text-sm text-gray-600">CIP: ${produit.code_cip || 'N/A'}</div>
                                <div class="text-sm">Prix: ${formatMonnaie(produit.prix_vente)}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm ${produit.stock_disponible <= 0 ? 'stock-critique' : 
                                                     produit.stock_disponible <= produit.stock_alerte ? 'stock-alerte' : 'stock-normal'}">
                                    Stock: ${produit.stock_disponible}
                                </div>
                                <div class="text-xs text-gray-500">Théo: ${produit.stock_theorique}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            searchResults.classList.remove('hidden');
        }
        
        function ajouterAuPanier(produitId) {
            axios.post('/ventes/ajouter-article', {
                produit_id: produitId,
                quantite: 1,
                remise: 0
            })
            .then(response => {
                if (response.data.success) {
                    panier.push(response.data.article);
                    mettreAJourPanier();
                    searchResults.classList.add('hidden');
                    searchInput.value = '';
                } else {
                    alert(response.data.message);
                }
            })
            .catch(error => {
                console.error('Erreur ajout panier:', error);
                alert('Erreur lors de l\'ajout au panier');
            });
        }
        
        function mettreAJourPanier() {
            if (panier.length === 0) {
                panierArticles.innerHTML = '';
                panierVide.style.display = 'block';
            } else {
                panierVide.style.display = 'none';
                panierArticles.innerHTML = panier.map((article, index) => `
                    <tr class="product-row">
                        <td class="border border-gray-300 px-4 py-2">${article.code_cip}</td>
                        <td class="border border-gray-300 px-4 py-2">${article.designation}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">${formatMonnaie(article.prix_unitaire)}</td>
                        <td class="border border-gray-300 px-4 py-2 text-center ${article.stock_reel <= 0 ? 'stock-critique' : 'stock-normal'}">
                            ${article.stock_reel}
                        </td>
                        <td class="border border-gray-300 px-4 py-2 text-center">${article.stock_theorique}</td>
                        <td class="border border-gray-300 px-4 py-2 text-center">
                            <input type="number" value="${article.quantite}" min="1" 
                                   class="w-16 px-2 py-1 border rounded text-center"
                                   onchange="modifierQuantite(${index}, this.value)">
                        </td>
                        <td class="border border-gray-300 px-4 py-2 text-center">
                            <input type="number" value="${article.remise}" min="0" max="100" step="0.1"
                                   class="w-16 px-2 py-1 border rounded text-center"
                                   onchange="modifierRemise(${index}, this.value)">
                        </td>
                        <td class="border border-gray-300 px-4 py-2 text-center">
                            <select class="w-16 px-2 py-1 border rounded text-center">
                                <option value="0">0%</option>
                                <option value="25">25%</option>
                                <option value="50">50%</option>
                                <option value="75">75%</option>
                                <option value="100">100%</option>
                            </select>
                        </td>
                        <td class="border border-gray-300 px-4 py-2 text-sm">${article.lot_numero}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">${formatMonnaie(article.montant)}</td>
                        <td class="border border-gray-300 px-4 py-2 text-center">
                            <button onclick="supprimerDuPanier(${index})" 
                                    class="text-red-600 hover:text-red-800">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
            
            calculerTotaux();
        }
        
        function calculerTotaux() {
            const total = panier.reduce((sum, article) => sum + article.montant, 0);
            const remiseGlobale = panier.reduce((sum, article) => sum + (article.montant * article.remise / 100), 0);
            const netAPayer = total - remiseGlobale;
            
            document.getElementById('totalVente').textContent = formatMonnaie(total);
            document.getElementById('remiseGlobale').textContent = formatMonnaie(remiseGlobale);
            document.getElementById('netAPayer').textContent = formatMonnaie(netAPayer);
            
            // Activer/désactiver les boutons
            const btnComptabiliser = document.getElementById('btnComptabiliser');
            const btnImprimerTicket = document.getElementById('btnImprimerTicket');
            const btnImprimerFacture = document.getElementById('btnImprimerFacture');
            
            const hasArticles = panier.length > 0;
            btnComptabiliser.disabled = !hasArticles;
            btnImprimerTicket.disabled = !hasArticles;
            btnImprimerFacture.disabled = !hasArticles;
        }
        
        function modifierQuantite(index, nouvelleQuantite) {
            const quantite = parseInt(nouvelleQuantite);
            if (quantite > 0 && quantite <= panier[index].stock_reel) {
                panier[index].quantite = quantite;
                panier[index].montant = panier[index].prix_unitaire * quantite * (1 - panier[index].remise / 100);
                mettreAJourPanier();
            } else {
                alert('Quantité invalide ou stock insuffisant');
                mettreAJourPanier();
            }
        }
        
        function modifierRemise(index, nouvelleRemise) {
            const remise = parseFloat(nouvelleRemise);
            if (remise >= 0 && remise <= 100) {
                panier[index].remise = remise;
                panier[index].montant = panier[index].prix_unitaire * panier[index].quantite * (1 - remise / 100);
                mettreAJourPanier();
            }
        }
        
        function supprimerDuPanier(index) {
            panier.splice(index, 1);
            mettreAJourPanier();
        }
        
        // Validation de la vente
        document.getElementById('btnComptabiliser').addEventListener('click', function() {
            if (panier.length === 0) {
                alert('Le panier est vide');
                return;
            }
            
            const clientId = document.getElementById('matriculeClient').value || null;
            const dateNaissance = document.getElementById('dateNaissance').value;
            
            if (!dateNaissance) {
                alert('La date de naissance est obligatoire');
                return;
            }
            
            axios.post('/ventes/valider-vente', {
                client_id: clientId,
                type_paiement: document.getElementById('modePaiement').value,
                is_credit: document.getElementById('modePaiement').value === 'CREDIT',
                montant_paye: document.getElementById('netAPayer').textContent.replace(/[^0-9]/g, ''),
                notes: `Type: ${document.getElementById('typeClient').value}, Prescripteur: ${document.getElementById('prescripteur').value}`,
                articles: panier
            })
            .then(response => {
                if (response.data.success) {
                    alert('Vente comptabilisée avec succès!\nFacture: ' + response.data.numero_facture);
                    panier = [];
                    mettreAJourPanier();
                    if (response.data.ticket_url) {
                        window.location.href = response.data.ticket_url;
                    }
                } else {
                    alert(response.data.message);
                }
            })
            .catch(error => {
                console.error('Erreur validation:', error);
                alert('Erreur lors de la validation de la vente');
            });
        });
        
        // Annulation de vente
        document.getElementById('btnAnnulerVente').addEventListener('click', function() {
            if (panier.length === 0) return;
            
            if (confirm('Êtes-vous sûr de vouloir annuler cette vente?')) {
                panier = [];
                mettreAJourPanier();
            }
        });
        
        // Utilitaires
        function formatMonnaie(montant) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'XOF',
                minimumFractionDigits: 0
            }).format(montant);
        }
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Initialisation
        mettreAJourPanier();
    </script>
</body>
</html>
