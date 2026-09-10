<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Ajouter un Client')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $old = $_SESSION['old_input'] ?? [];
        $value = function (string $key, $default = '') use ($old) {
            return htmlspecialchars((string)($old[$key] ?? $default));
        };
        $typeClient = (string)($old['type_client'] ?? 'ORDINAIRE');
        $typesClient = [
            'ORDINAIRE' => 'Ordinaire',
            'COURANT' => 'Courant',
            'COURANT_DEPOT' => 'Courant - Dépôt',
            'COURANT_BON' => 'Courant - Bon',
            'COURANT_CARNET' => 'Courant - Carnet',
            'AUTRES_CLIENTS' => 'Autres clients'
        ];
        $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
        $roleLabel = (string)($_SESSION['user']['role_code'] ?? $_SESSION['user']['role_name'] ?? '');
        $roleAscii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $roleLabel) ?: $roleLabel) : $roleLabel;
        $roleCode = strtoupper(str_replace([' ', '-'], '_', $roleAscii));
        $defaultReturnTo = match (true) {
            $roleId === 1 || in_array($roleCode, ['ADMIN', 'ADMINISTRATEUR'], true) => '/admin/dashboard',
            $roleId === 3 || $roleCode === 'ASSISTANT' => '/assistant/dashboard',
            $roleId === 2 || $roleCode === 'VENDEUR' => '/vente',
            default => '/clients',
        };
        $returnTo = (string)($old['return_to'] ?? $_GET['return_to'] ?? $defaultReturnTo);
        $returnTo = str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') ? $returnTo : $defaultReturnTo;
    ?>

    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-users text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Nouveau Client</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Utilisateur: <?= htmlspecialchars((string)($_SESSION['username'] ?? '')) ?></span>
                    <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h4 class="text-red-800 font-semibold mb-2">Erreurs</h4>
                <ul class="text-red-700 list-disc ml-5">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?= htmlspecialchars((string)$error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <form method="POST" action="/clients/store" id="clientForm" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-user-plus mr-2"></i>Informations du Client
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Code client</label>
                    <input type="text" name="code" value="<?= $value('code') ?>" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none"
                           placeholder="Généré automatiquement">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Matricule</label>
                    <input type="text" name="matricule" value="<?= $value('matricule') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: CLI000001">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom / Raison sociale <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" value="<?= $value('nom') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                    <input type="text" name="prenom" value="<?= $value('prenom') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type de client <span class="text-red-500">*</span></label>
                    <select name="type_client" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($typesClient as $typeValue => $typeLabel): ?>
                            <option value="<?= htmlspecialchars($typeValue) ?>" <?= $typeClient === $typeValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($typeLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Statut <span class="text-red-500">*</span></label>
                    <select name="is_actif" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="1" selected>Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone principal <span class="text-red-500">*</span></label>
                    <input type="tel" name="telephone" value="<?= $value('telephone') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone secondaire</label>
                    <input type="tel" name="telephone_secondaire" value="<?= $value('telephone_secondaire') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" value="<?= $value('email') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de naissance</label>
                    <input type="date" name="date_naissance" value="<?= $value('date_naissance') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                    <textarea name="adresse" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= $value('adresse') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ville</label>
                    <input type="text" name="ville" value="<?= $value('ville') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Numéro IFU</label>
                    <input type="text" name="numero_ifu" value="<?= $value('numero_ifu') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Identifiant Fiscal Unique">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Numéro RCCM</label>
                    <input type="text" name="numero_rccm" value="<?= $value('numero_rccm') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Registre du Commerce">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Plafond de crédit</label>
                    <input type="number" name="plafond_credit" value="<?= $value('plafond_credit', 0) ?>" step="0.01" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Solde initial</label>
                    <input type="number" name="solde_initial" value="<?= $value('solde_initial', 0) ?>" step="0.01" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Numéro assurance</label>
                    <input type="text" name="numero_assurance" value="<?= $value('numero_assurance') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Compagnie assurance</label>
                    <input type="text" name="compagnie_assurance" value="<?= $value('compagnie_assurance') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observations</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= $value('notes') ?></textarea>
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Annuler
                </a>
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-save mr-2"></i>Enregistrer le Client
                </button>
            </div>
        </form>
        <?php unset($_SESSION['old_input']); ?>
    </main>

    <script>
        const allowedClientTypes = ['ORDINAIRE', 'COURANT', 'COURANT_DEPOT', 'COURANT_BON', 'COURANT_CARNET', 'AUTRES_CLIENTS'];

        document.getElementById('clientForm').addEventListener('submit', function(e) {
            const form = e.target;
            const telephone = form.telephone.value.trim();
            const email = form.email.value.trim();
            const typeClient = form.type_client.value.trim().toUpperCase();

            if (!telephone) {
                e.preventDefault();
                alert('Le telephone est obligatoire');
                return false;
            }

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Format email invalide');
                return false;
            }

            // Valider le type de client
            if (!allowedClientTypes.includes(typeClient)) {
                e.preventDefault();
                alert('Type de client non valide');
                return false;
            }
        });
    </script>
</body>
</html>
