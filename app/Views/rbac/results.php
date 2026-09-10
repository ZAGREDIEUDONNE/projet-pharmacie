<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Résultats des Tests RBAC' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pass { background-color: #10b981; }
        .fail { background-color: #ef4444; }
        .blocked { background-color: #3b82f6; }
        .vulnerable { background-color: #f59e0b; }
        .secure { background-color: #10b981; }
        .needs-attention { background-color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-chart-line text-purple-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-800">Résultats des Tests RBAC</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Testé le: <?= $testResults['timestamp'] ?? 'N/A' ?></span>
                    <a href="/rbac/test" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        <i class="fas fa-redo mr-2"></i>Nouveau Test
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto p-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Scénarios Réussis</p>
                        <p class="text-2xl font-bold text-blue-600">
                            <?= $testResults['summary']['passed_scenarios'] ?? 0 ?>/<?= $testResults['summary']['total_scenarios'] ?? 0 ?>
                        </p>
                    </div>
                    <i class="fas fa-check-circle text-blue-600 text-3xl"></i>
                </div>
                <div class="mt-2">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $testResults['summary']['scenario_success_rate'] ?? 0 ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1"><?= $testResults['summary']['scenario_success_rate'] ?? 0 ?>% de réussite</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Attaques Bloquées</p>
                        <p class="text-2xl font-bold text-green-600">
                            <?= $testResults['summary']['blocked_attacks'] ?? 0 ?>/<?= $testResults['summary']['total_attacks'] ?? 0 ?>
                        </p>
                    </div>
                    <i class="fas fa-shield-alt text-green-600 text-3xl"></i>
                </div>
                <div class="mt-2">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: <?= $testResults['summary']['security_score'] ?? 0 ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1"><?= $testResults['summary']['security_score'] ?? 0 ?>% sécurisé</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Tests Échoués</p>
                        <p class="text-2xl font-bold text-red-600"><?= $testResults['summary']['failed_scenarios'] ?? 0 ?></p>
                    </div>
                    <i class="fas fa-times-circle text-red-600 text-3xl"></i>
                </div>
                <div class="mt-2">
                    <p class="text-xs text-gray-500">Scénarios non conformes</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Statut Global</p>
                        <p class="text-2xl font-bold <?= ($testResults['summary']['overall_status'] ?? 'UNKNOWN') === 'SECURE' ? 'text-green-600' : 'text-orange-600' ?>">
                            <?= $testResults['summary']['overall_status'] ?? 'UNKNOWN' ?>
                        </p>
                    </div>
                    <i class="fas fa-globe <?= ($testResults['summary']['overall_status'] ?? 'UNKNOWN') === 'SECURE' ? 'text-green-600' : 'text-orange-600' ?> text-3xl"></i>
                </div>
                <div class="mt-2">
                    <p class="text-xs text-gray-500">
                        <?= ($testResults['summary']['overall_status'] ?? 'UNKNOWN') === 'SECURE' ? 'Système sécurisé' : 'Attention requise' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Scenario Test Results -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-vial text-blue-600 mr-2"></i>
                    Résultats des Scénarios de Test
                </h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rôle</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permission</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attendu</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Résultat</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Temps</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($testResults['scenarios'] ?? [] as $scenario): ?>
                            <tr class="<?= $scenario['result'] === 'FAIL' ? 'bg-red-50' : '' ?>">
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($scenario['role']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900">
                                    <?= htmlspecialchars($scenario['permission']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= htmlspecialchars($scenario['description']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs rounded <?= $scenario['expected'] === 'GRANTED' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($scenario['expected']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs rounded <?= $scenario['actual'] === 'GRANTED' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($scenario['actual']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $scenario['result'] === 'PASS' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($scenario['result']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    <?= $scenario['execution_time'] ?>ms
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Attack Test Results -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-shield-virus text-red-600 mr-2"></i>
                    Résultats des Tests d'Attaque
                </h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach ($testResults['attacks'] ?? [] as $attack): ?>
                    <div class="border rounded-lg p-4 <?= $attack['result'] === 'BLOCKED' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas <?= $attack['result'] === 'BLOCKED' ? 'fa-shield-alt text-green-600' : 'fa-exclamation-triangle text-red-600' ?> text-xl mr-3"></i>
                                <div>
                                    <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($attack['attack']) ?></h4>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($attack['description']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($attack['details']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-3 py-1 text-sm rounded-full <?= $attack['result'] === 'BLOCKED' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= htmlspecialchars($attack['result']) ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-1"><?= $attack['timestamp'] ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-download mr-2"></i>
                Exporter les Résultats
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="exportJSON()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    <i class="fas fa-file-code mr-2"></i>Exporter JSON
                </button>
                <button onclick="exportCSV()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <i class="fas fa-file-csv mr-2"></i>Exporter CSV
                </button>
                <button onclick="printReport()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    <i class="fas fa-print mr-2"></i>Imprimer
                </button>
            </div>
        </div>
    </main>

    <script>
        // Données de test pour l'export
        const testResults = <?= json_encode($testResults) ?>;

        // Export JSON
        function exportJSON() {
            const dataStr = JSON.stringify(testResults, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = 'rbac_test_results_' + new Date().toISOString().slice(0,10) + '.json';
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        }

        // Export CSV
        function exportCSV() {
            let csv = 'Role,Permission,Description,Expected,Actual,Result,Execution Time, Timestamp\n';
            
            (testResults.scenarios || []).forEach(scenario => {
                csv += `"${scenario.role}","${scenario.permission}","${scenario.description}","${scenario.expected}","${scenario.actual}","${scenario.result}","${scenario.execution_time}","${scenario.timestamp}"\n`;
            });
            
            const dataUri = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            const exportFileDefaultName = 'rbac_test_results_' + new Date().toISOString().slice(0,10) + '.csv';
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        }

        // Imprimer le rapport
        function printReport() {
            window.print();
        }

        // Animation des résultats
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>

    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .bg-gray-100 { background: white; }
        }
    </style>
</body>
</html>
