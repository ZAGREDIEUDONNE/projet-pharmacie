<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Détail Opération') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php if (isset($_GET['print']) && $_GET['print']): ?>
        <style>
            body { background: white; }
            .no-print { display: none !important; }
            @media print {
                body { background: white; }
                .no-print { display: none !important; }
            }
        </style>
    <?php endif; ?>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg no-print">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-eye text-indigo-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Détail de l'Opération</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars($_SESSION['username'] ?? ($user['username'] ?? '')) ?></span>
                    <a href="/caisse/journal" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto p-6">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <!-- En-tête -->
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?= getLibelleType($operation['type_mouvement']) ?>
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Référence: <?= htmlspecialchars($operation['reference'] ?? ($operation['vente_reference'] ?? '-')) ?>
                    </p>
                </div>
                <div class="text-right no-print">
                    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                </div>
            </div>

            <!-- Informations principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-3">
                        <i class="fas fa-info-circle mr-2"></i>Informations
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-medium"><?= date('d/m/Y', strtotime($operation['date_mouvement'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Heure:</span>
                            <span class="font-medium"><?= date('H:i:s', strtotime($operation['date_mouvement'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Utilisateur:</span>
                            <span class="font-medium"><?= htmlspecialchars($operation['utilisateur_nom'] ?? '-') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Session:</span>
                            <span class="font-medium"><?= htmlspecialchars($operation['numero_session'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-green-800 mb-3">
                        <i class="fas fa-coins mr-2"></i>Montants
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Montant:</span>
                            <span class="font-bold"><?= number_format($operation['montant'], 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Solde avant:</span>
                            <span class="font-medium"><?= number_format($operation['solde_avant'] ?? 0, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Solde après:</span>
                            <span class="font-bold text-blue-600"><?= number_format($operation['solde_apres'] ?? 0, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Moyen paiement:</span>
                            <span class="font-medium"><?= htmlspecialchars($operation['moyen_paiement'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entrée/Sortie -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <?php
                $isEntree = in_array($operation['type_mouvement'], ['VENTE', 'APPROVISIONNEMENT', 'ENCAISSEMENT_CLIENT', 'REGLEMENT_CREANCE', 'ACOMPTE_CLIENT', 'DEPOT_BANCAIRE']);
                $isSortie = in_array($operation['type_mouvement'], ['REMBOURSEMENT', 'DECAISSEMENT', 'RETRAIT', 'ANNULATION_VENTE', 'RETRAIT_BANCAIRE']);
                ?>
                <div class="<?= $isEntree ? 'bg-green-100 border-green-300' : 'bg-gray-50 border-gray-200' ?> border rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">Entrée</p>
                    <p class="text-2xl font-bold <?= $isEntree ? 'text-green-600' : 'text-gray-400' ?>">
                        <?= $isEntree ? number_format($operation['montant'], 0, ',', ' ') . ' FCFA' : '-' ?>
                    </p>
                </div>
                <div class="<?= $isSortie ? 'bg-red-100 border-red-300' : 'bg-gray-50 border-gray-200' ?> border rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">Sortie</p>
                    <p class="text-2xl font-bold <?= $isSortie ? 'text-red-600' : 'text-gray-400' ?>">
                        <?= $isSortie ? number_format($operation['montant'], 0, ',', ' ') . ' FCFA' : '-' ?>
                    </p>
                </div>
            </div>

            <!-- Observation -->
            <div class="bg-yellow-50 p-4 rounded-lg mb-6">
                <h3 class="font-semibold text-yellow-800 mb-2">
                    <i class="fas fa-sticky-note mr-2"></i>Observation
                </h3>
                <p class="text-gray-700"><?= htmlspecialchars($operation['description'] ?? 'Aucune observation') ?></p>
            </div>

            <!-- Informations complémentaires -->
            <?php if (!empty($operation['vente_reference'])): ?>
                <div class="bg-purple-50 p-4 rounded-lg mb-6">
                    <h3 class="font-semibold text-purple-800 mb-2">
                        <i class="fas fa-receipt mr-2"></i>Informations Vente
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">N° Facture:</span>
                            <span class="font-medium"><?= htmlspecialchars($operation['vente_reference']) ?></span>
                        </div>
                        <?php if (!empty($operation['client_nom'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Client:</span>
                            <span class="font-medium"><?= htmlspecialchars($operation['client_nom']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Traçabilité -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-800 mb-2">
                    <i class="fas fa-history mr-2"></i>Informations de traçabilité
                </h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">ID opération:</span>
                        <span class="font-medium ml-2"><?= $operation['id'] ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Créé le:</span>
                        <span class="font-medium ml-2"><?= date('d/m/Y H:i:s', strtotime($operation['created_at'])) ?></span>
                    </div>
                    <?php if (!empty($operation['supprime']) && $operation['supprime']): ?>
                        <div>
                            <span class="text-gray-600">Supprimé le:</span>
                            <span class="font-medium ml-2 text-red-600"><?= date('d/m/Y H:i:s', strtotime($operation['date_suppression'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        <?php
        function getLibelleType($type) {
            $libelles = [
                'VENTE' => 'Vente comptant',
                'REMBOURSEMENT' => 'Remboursement',
                'APPROVISIONNEMENT' => 'Approvisionnement',
                'RETRAIT' => 'Retrait',
                'DECAISSEMENT' => 'Décaissement',
                'OUVERTURE_CAISSE' => 'Ouverture caisse',
                'FERMETURE_CAISSE' => 'Fermeture caisse',
                'ENCAISSEMENT_CLIENT' => 'Encaissement client',
                'REGLEMENT_CREANCE' => 'Règlement créance',
                'ACOMPTE_CLIENT' => 'Acompte client',
                'ANNULATION_VENTE' => 'Annulation vente',
                'CORRECTION_CAISSE' => 'Correction caisse',
                'AJUSTEMENT_CAISSE' => 'Ajustement caisse',
                'DEPOT_BANCAIRE' => 'Dépôt bancaire',
                'RETRAIT_BANCAIRE' => 'Retrait bancaire'
            ];
            return $libelles[$type] ?? $type;
        }
        ?>
    </script>
</body>
</html>
