<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)($title ?? 'Modifier un Client')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php
        $old = $_SESSION['old_input'] ?? [];
        $value = function (string $key, $default = '') use ($old, $client) {
            return htmlspecialchars((string)($old[$key] ?? $client[$key] ?? $default));
        };
        $clientId = (int)($client['id'] ?? 0);
        $typeClient = (string)($old['type_client'] ?? $client['type_client'] ?? 'ORDINAIRE');
        $isActif = array_key_exists('is_actif', $old)
            ? isset($old['is_actif'])
            : ((int)($client['is_actif'] ?? 1) === 1);
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

    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-user-edit text-2xl"></i>
                    <h1 class="text-xl font-bold">MODIFIER CLIENT</h1>
                </div>
                <a href="<?= htmlspecialchars($returnTo) ?>" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
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

        <form method="POST" action="/clients/modifier" class="bg-white rounded-lg shadow-md p-6">
            <input type="hidden" name="id" value="<?= $clientId ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code client</label>
                    <input type="text" value="<?= $value('code') ?>" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Matricule</label>
                    <input type="text" name="matricule" value="<?= $value('matricule') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" value="<?= $value('nom') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" name="prenom" value="<?= $value('prenom') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
                    <input type="date" name="date_naissance" value="<?= $value('date_naissance') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Âge</label>
                    <input type="number" name="age" value="<?= $value('age') ?>" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone principal <span class="text-red-500">*</span></label>
                    <input type="tel" name="telephone" value="<?= $value('telephone') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone secondaire</label>
                    <input type="tel" name="telephone_secondaire" value="<?= $value('telephone_secondaire') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= $value('email') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                    <input type="text" name="ville" value="<?= $value('ville') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <textarea name="adresse" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?= $value('adresse') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type client <span class="text-red-500">*</span></label>
                    <select name="type_client" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($typesClient as $typeValue => $typeLabel): ?>
                            <option value="<?= htmlspecialchars($typeValue) ?>" <?= $typeClient === $typeValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($typeLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="is_actif" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="1" <?= $isActif ? 'selected' : '' ?>>Actif</option>
                        <option value="0" <?= !$isActif ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numéro IFU</label>
                    <input type="text" name="numero_ifu" value="<?= $value('numero_ifu') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Identifiant Fiscal Unique">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numéro RCCM</label>
                    <input type="text" name="numero_rccm" value="<?= $value('numero_rccm') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Registre du Commerce">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plafond de crédit</label>
                    <input type="number" name="plafond_credit" min="0" step="0.01" value="<?= $value('plafond_credit', 0) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Solde initial</label>
                    <input type="number" name="solde_initial" min="0" step="0.01" value="<?= $value('solde_initial', 0) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numéro assurance</label>
                    <input type="text" name="numero_assurance" value="<?= $value('numero_assurance') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compagnie assurance</label>
                    <input type="text" name="compagnie_assurance" value="<?= $value('compagnie_assurance') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observations</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?= $value('notes') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <a href="<?= htmlspecialchars($returnTo) ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Annuler
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
        </form>
        <?php unset($_SESSION['old_input']); ?>
    </main>

    <script>
        const allowedClientTypes = ['ORDINAIRE', 'COURANT', 'COURANT_DEPOT', 'COURANT_BON', 'COURANT_CARNET', 'AUTRES_CLIENTS'];

        document.querySelector('form').addEventListener('submit', function(e) {
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
