<?php
    $canViewCaisse = $canViewCaisse ?? false;
    $activeCaisseSessions = is_array($activeCaisseSessions ?? null) ? $activeCaisseSessions : [];
    $activeCaisseSession = $activeCaisseSession ?? ($activeCaisseSessions[0] ?? null);
?>

<?php if ($canViewCaisse): ?>
    <section class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-start">
                <div class="<?= $activeCaisseSession ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?> w-12 h-12 rounded flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <?= $activeCaisseSession ? 'Caisse active' : 'Aucune caisse active' ?>
                    </h2>
                    <?php if ($activeCaisseSession): ?>
                        <p class="text-sm text-gray-600 mt-1">
                            Session <?= htmlspecialchars((string)($activeCaisseSession['numero_session'] ?? $activeCaisseSession['id'] ?? '')) ?>
                            ouverte par <?= htmlspecialchars((string)($activeCaisseSession['caissier_nom'] ?? 'Utilisateur')) ?>
                            depuis <?= (int)($activeCaisseSession['duree_minutes'] ?? 0) ?> min
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 mt-1">Ouvrez une session pour activer la caisse.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <?php if ($activeCaisseSession): ?>
                    <div class="text-left sm:text-right">
                        <p class="text-xs uppercase text-gray-500">Solde theorique</p>
                        <p class="text-xl font-bold text-gray-800">
                            <?= number_format((float)($activeCaisseSession['montant_theorique_actuel'] ?? $activeCaisseSession['montant_ouverture'] ?? 0), 0, ',', ' ') ?> FCFA
                        </p>
                    </div>
                <?php endif; ?>
                <a href="/caisse/etat" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-center">
                    <i class="fas fa-chart-line mr-2"></i>Voir caisse
                </a>
                <?php if ($activeCaisseSession): ?>
                    <a href="/caisse/fermeture" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-center">
                        <i class="fas fa-lock mr-2"></i>Fermer session
                    </a>
                <?php else: ?>
                    <a href="/caisse/session" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-center">
                        <i class="fas fa-play mr-2"></i>Ouvrir session
                    </a>
                <?php endif; ?>
                <a href="/caisse/session" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-center">
                    <i class="fas fa-clock mr-2"></i>Voir sessions
                </a>
            </div>
        </div>

        <?php if (count($activeCaisseSessions) > 1): ?>
            <p class="text-sm text-gray-500 mt-4">
                <?= count($activeCaisseSessions) ?> sessions de caisse sont ouvertes.
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>
