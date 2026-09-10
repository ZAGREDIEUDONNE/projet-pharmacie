<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fermeture Session Caisse - Gestion Pharmacie</title>
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
    <!-- Header -->
    <header class="bg-red-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-cash-register text-2xl"></i>
                    <h1 class="text-xl font-bold">FERMETURE SESSION CAISSE</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm">
                        <span class="font-semibold">Session:</span> 
                        <span class="text-yellow-300"><?= isset($session['numero_session']) ? $session['numero_session'] : '--' ?></span>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">Caissier:</span> 
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

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Carte de fermeture -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-6">
                    <i class="fas fa-cash-register text-6xl text-red-600 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800">Fermer Session Caisse</h2>
                    <p class="text-gray-600 mt-2">Finalisez votre journée de travail</p>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Résumé de la session -->
                <form method="POST" action="/caisse/traiter-fermeture" id="fermetureForm">
                    <input type="hidden" name="session_id" value="<?= (int)($session['id'] ?? 0) ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">

                <div class="bg-blue-50 p-6 rounded-lg mb-6">
                    <h3 class="font-semibold text-blue-800 mb-4">
                        <i class="fas fa-info-circle mr-2"></i>
                        Résumé Session
                    </h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Numéro Session:</span>
                            <span class="font-semibold ml-2"><?= isset($session['numero_session']) ? $session['numero_session'] : '--' ?></span>
                        </div>
                        <div>
                            <span class="text-gray-600">Ouverture:</span>
                            <span class="font-semibold ml-2"><?= isset($session['date_ouverture']) ? date('d/m/Y H:i', strtotime($session['date_ouverture'])) : '--/-- --:--' ?></span>
                        </div>
                        <div>
                            <span class="text-gray-600">Durée:</span>
                            <span class="font-semibold ml-2" id="dureeSession">Calcul...</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Caissier:</span>
                            <span class="font-semibold ml-2"><?= htmlspecialchars($user['name'] ?? 'Invité') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Informations financières -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h4 class="font-semibold text-green-800 mb-4">
                            <i class="fas fa-arrow-down mr-2"></i>
                            Entrées
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Montant Ouverture:</span>
                                <span class="font-semibold"><?= isset($session['montant_ouverture']) ? number_format($session['montant_ouverture'], 0, ',', ' ') : '0' ?> FCFA</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Ventes:</span>
                                <span class="font-semibold"><?= isset($session['montant_ventes']) ? number_format($session['montant_ventes'], 0, ',', ' ') : '0' ?> FCFA</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold text-green-600">
                                <span>Total Entrées:</span>
                                <span id="totalEntrees"><?= number_format((isset($session['montant_ouverture']) ? $session['montant_ouverture'] : 0) + (isset($session['montant_ventes']) ? $session['montant_ventes'] : 0), 0, ',', ' ') ?> FCFA</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-red-50 p-6 rounded-lg">
                        <h4 class="font-semibold text-red-800 mb-4">
                            <i class="fas fa-arrow-up mr-2"></i>
                            Sorties
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Retraits:</span>
                                <span class="font-semibold">0 FCFA</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Remboursements:</span>
                                <span class="font-semibold">0 FCFA</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold text-red-600">
                                <span>Total Sorties:</span>
                                <span>0 FCFA</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Montant théorique et réel -->
                <div class="bg-yellow-50 p-6 rounded-lg mb-6">
                    <h3 class="font-semibold text-yellow-800 mb-4">
                        <i class="fas fa-calculator mr-2"></i>
                        Contrôle Caisse
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700 font-medium">Montant Théorique:</span>
                            <span class="text-xl font-bold text-blue-600" id="montantTheorique">
                                <?= number_format($montant_theorique ?? 0, 0, ',', ' ') ?> FCFA
                            </span>
                        </div>
                        
                        <div class="border-t pt-4">
                            <label for="montant_fermeture" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave mr-2"></i>
                                Montant Réel en Caisse (FCFA)
                            </label>
                            <div class="relative">
                                <input type="text"
                                       id="montant_fermeture"
                                       name="montant_fermeture"
                                       value="<?= number_format($montant_theorique ?? 0, 0, '', '') ?>"
                                       required
                                       class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent text-lg font-semibold"
                                       placeholder="Saisir le montant">
                                <span class="absolute right-3 top-3 text-gray-500">
                                    <i class="fas fa-coins"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="border-t pt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700 font-medium">Écart:</span>
                                <span class="text-xl font-bold" id="ecart">
                                    0 FCFA
                                </span>
                            </div>
                            <div class="mt-2" id="ecartMessage"></div>
                        </div>
                    </div>
                </div>

                <!-- Notes de contrôle -->
                <div class="mb-6">
                    <label for="notes_controle" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sticky-note mr-2"></i>
                        Notes de Contrôle (optionnel)
                    </label>
                    <textarea id="notes_controle" 
                              name="notes_controle"
                              rows="3"
                              placeholder="Commentaires sur l'écart, incidents, etc."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>

                <!-- Boutons d'action -->
                <div class="space-y-3">
                    <button type="button" 
                            id="btnFermerSession"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                        <i class="fas fa-stop-circle mr-2"></i>
                        Fermer la Session
                    </button>
                    
                    <button type="button"
                            onclick="window.location.href='<?= htmlspecialchars($returnTo, ENT_QUOTES) ?>'"
                            class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Retour à la Caisse
                    </button>
                </div>

                <!-- Instructions -->
                </form>

                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <h4 class="font-semibold text-yellow-800 mb-2">
                        <i class="fas fa-lightbulb mr-2"></i>
                        Instructions de Fermeture
                    </h4>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• Comptez soigneusement tout l'argent disponible dans la caisse</li>
                        <li>• Vérifiez les tickets, chèques et autres moyens de paiement</li>
                        <li>• Saisissez le montant exact compté en caisse</li>
                        <li>• Notez toute justification en cas d'écart</li>
                        <li>• La fermeture est définitive et ne peut être modifiée</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de confirmation -->
    <div id="modalConfirmation" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Confirmation de Fermeture</h3>
            <div class="space-y-3 mb-6">
                <div class="flex justify-between">
                    <span>Montant Théorique:</span>
                    <span class="font-semibold" id="confirmTheorique">0 FCFA</span>
                </div>
                <div class="flex justify-between">
                    <span>Montant Réel:</span>
                    <span class="font-semibold" id="confirmReel">0 FCFA</span>
                </div>
                <div class="flex justify-between font-bold">
                    <span>Écart:</span>
                    <span id="confirmEcart" class="font-bold">0 FCFA</span>
                </div>
            </div>
            <div class="flex space-x-3">
                <button id="btnConfirmerFermeture" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg">
                    Confirmer Fermeture
                </button>
                <button id="btnAnnulerFermeture" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-lg">
                    Annuler
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables
        const montantTheorique = <?= $montant_theorique ?? 0 ?>;
        const montantOuverture = <?= $session['montant_ouverture'] ?? 0 ?>;
        const montantVentes = <?= $session['montant_ventes'] ?? 0 ?>;
        
        // Calculer la durée de la session
        function calculerDureeSession() {
            const dateOuverture = new Date('<?= $session['date_ouverture'] ?? '' ?>');
            const maintenant = new Date();
            const duree = maintenant - dateOuverture;
            
            const heures = Math.floor(duree / (1000 * 60 * 60));
            const minutes = Math.floor((duree % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('dureeSession').textContent = `${heures}h ${minutes}min`;
        }
        
        // Calculer l'écart
        function calculerEcart() {
            const montantReel = parseFloat(document.getElementById('montant_fermeture').value) || 0;
            const ecart = montantReel - montantTheorique;
            
            const ecartElement = document.getElementById('ecart');
            const ecartMessage = document.getElementById('ecartMessage');
            
            ecartElement.textContent = (ecart >= 0 ? '+' : '') + number_format(ecart, 0, ',', ' ') + ' FCFA';
            
            // Couleur et message selon l'écart
            if (ecart === 0) {
                ecartElement.className = 'text-xl font-bold text-green-600';
                ecartMessage.innerHTML = '<i class="fas fa-check-circle text-green-600"></i> <span class="text-green-600">Caisse équilibrée</span>';
            } else if (ecart > 0) {
                ecartElement.className = 'text-xl font-bold text-orange-600';
                ecartMessage.innerHTML = '<i class="fas fa-exclamation-triangle text-orange-600"></i> <span class="text-orange-600">Excédent de ' + number_format(ecart, 0, ',', ' ') + ' FCFA</span>';
            } else {
                ecartElement.className = 'text-xl font-bold text-red-600';
                ecartMessage.innerHTML = '<i class="fas fa-exclamation-triangle text-red-600"></i> <span class="text-red-600">Manquant de ' + number_format(Math.abs(ecart), 0, ',', ' ') + ' FCFA</span>';
            }
        }
        
        // Validation du montant
        document.getElementById('montant_fermeture').addEventListener('input', function(e) {
            // Supprimer les caractères non numériques sauf le point décimal
            let value = e.target.value.replace(/[^0-9.]/g, '');
            
            // S'assurer qu'il n'y a qu'un seul point décimal
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            e.target.value = value;
            calculerEcart();
        });
        
        // Modal de confirmation
        document.getElementById('btnFermerSession').addEventListener('click', function() {
            const montantReel = parseFloat(document.getElementById('montant_fermeture').value) || 0;
            const ecart = montantReel - montantTheorique;
            
            document.getElementById('confirmTheorique').textContent = number_format(montantTheorique, 0, ',', ' ') + ' FCFA';
            document.getElementById('confirmReel').textContent = number_format(montantReel, 0, ',', ' ') + ' FCFA';
            document.getElementById('confirmEcart').textContent = (ecart >= 0 ? '+' : '') + number_format(ecart, 0, ',', ' ') + ' FCFA';
            
            document.getElementById('modalConfirmation').classList.remove('hidden');
        });
        
        document.getElementById('btnConfirmerFermeture').addEventListener('click', function() {
            // Soumettre le formulaire
            const form = document.getElementById('fermetureForm');
            if (form) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Fermeture en cours...';
                form.submit();
                return;
            }

            const formFallback = document.createElement('form');
            formFallback.method = 'POST';
            formFallback.action = '/caisse/traiter-fermeture';
            
            // Champs cachés
            const champSessionId = document.createElement('input');
            champSessionId.type = 'hidden';
            champSessionId.name = 'session_id';
            champSessionId.value = <?= $session['id'] ?? 0 ?>;
            formFallback.appendChild(champSessionId);
            
            const champMontant = document.createElement('input');
            champMontant.type = 'hidden';
            champMontant.name = 'montant_fermeture';
            champMontant.value = document.getElementById('montant_fermeture').value;
            formFallback.appendChild(champMontant);
            
            const champNotes = document.createElement('input');
            champNotes.type = 'hidden';
            champNotes.name = 'notes_controle';
            champNotes.value = document.getElementById('notes_controle').value;
            formFallback.appendChild(champNotes);

            const champRetour = document.createElement('input');
            champRetour.type = 'hidden';
            champRetour.name = 'return_to';
            champRetour.value = <?= json_encode($returnTo) ?>;
            formFallback.appendChild(champRetour);
            
            // Afficher le chargement
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Fermeture en cours...';
            
            document.body.appendChild(formFallback);
            formFallback.submit();
        });
        
        document.getElementById('btnAnnulerFermeture').addEventListener('click', function() {
            document.getElementById('modalConfirmation').classList.add('hidden');
        });
        
        // Utilitaire de formatage
        function number_format(number, decimals, dec_point, thousands_sep) {
            number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
            var n = !isFinite(+number) ? 0 : +number,
                prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
                dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
                s = '',
                toFixedFix = function (n, prec) {
                    var k = Math.pow(10, prec);
                    return '' + Math.round(n * k) / k;
                };
            s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
            if (sep) {
                var re = /(-?\d+)(\d{3})/;
                while (re.test(s[0])) {
                    s[0] = s[0].replace(re, '$1' + sep + '$2');
                }
            }
            if ((dec || '') && s.length > 1) {
                s[1] = s[1] || '';
                s[1] = s[1] + new Array(prec - s[1].length + 1).join('0');
            } else {
                s[1] = new Array(prec + 1).join('0');
            }
            return s.join(dec);
        }
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            calculerDureeSession();
            calculerEcart();
            document.getElementById('montant_fermeture').focus();
        });
    </script>
</body>
</html>
