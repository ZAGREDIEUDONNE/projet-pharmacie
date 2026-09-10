<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Décaissements') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-arrow-up text-red-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Décaissements</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? ($user['username'] ?? '')) ?></span>
                    <a href="/caisse" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Formulaire de décaissement -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-plus-circle mr-2"></i>Nouveau Décaissement
                </h2>

                <?php if (!$session): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                            <p class="text-yellow-800">Aucune session de caisse ouverte. Veuillez ouvrir une session avant d'effectuer un décaissement.</p>
                        </div>
                    </div>
                    <a href="/caisse/session" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 inline-block">
                        <i class="fas fa-play mr-2"></i>Ouvrir une session
                    </a>
                <?php else: ?>
                    <form method="POST" action="/caisse/decaissements" class="space-y-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-blue-800 mb-2">Session Active</h3>
                            <p class="text-sm text-gray-600">Numéro: <?= htmlspecialchars($session['numero_session']) ?></p>
                            <p class="text-sm text-gray-600">Ouverture: <?= date('d/m/Y H:i', strtotime($session['date_ouverture'])) ?></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Motif</label>
                            <select name="motif" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Sélectionner un motif</option>
                                <option value="Achat fournisseur">Achat fournisseur</option>
                                <option value="Remboursement client">Remboursement client</option>
                                <option value="Dépense diverses">Dépense diverses</option>
                                <option value="Retrait caisse">Retrait caisse</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Montant (FCFA)</label>
                            <input type="number" name="montant" required min="1" step="100"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="0">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Moyen de paiement</label>
                            <select name="moyen_paiement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="ESPECE">Espèces</option>
                                <option value="CARTE">Carte bancaire</option>
                                <option value="CHEQUE">Chèque</option>
                                <option value="MOBILE">Mobile money</option>
                                <option value="VIREMENT">Virement</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Observations</label>
                            <textarea name="observations" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Détails supplémentaires..."></textarea>
                        </div>

                        <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                            <i class="fas fa-check mr-2"></i>Enregistrer le décaissement
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Historique des décaissements récents -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-history mr-2"></i>Décaissements Récents
                </h2>

                <?php
                $recentDecaissements = [];
                if ($session) {
                    $sql = "SELECT * FROM mouvements_caisse 
                            WHERE caisse_session_id = ? AND type_mouvement = 'DECAISSEMENT'
                            ORDER BY date_mouvement DESC LIMIT 10";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$session['id']]);
                    $recentDecaissements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                ?>

                <?php if (!empty($recentDecaissements)): ?>
                    <div class="space-y-3">
                        <?php foreach ($recentDecaissements as $dec): ?>
                            <div class="border-l-4 border-red-500 bg-red-50 p-3 rounded">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($dec['description']) ?></p>
                                        <p class="text-sm text-gray-600"><?= date('d/m/Y H:i', strtotime($dec['date_mouvement'])) ?></p>
                                    </div>
                                    <p class="font-bold text-red-600"><?= number_format($dec['montant'], 0, ',', ' ') ?> FCFA</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>Aucun décaissement récent</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
