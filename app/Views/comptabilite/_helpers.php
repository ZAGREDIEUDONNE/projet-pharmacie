<?php
if (!function_exists('c_h')) {
    function c_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('c_money')) {
    function c_money($value): string
    {
        return number_format((float)$value, 0, ',', ' ') . ' FCFA';
    }
}

if (!function_exists('c_date')) {
    function c_date($value): string
    {
        if (empty($value)) {
            return '-';
        }

        return date('d/m/Y', strtotime((string)$value));
    }
}

if (!function_exists('c_header')) {
    function c_header(string $title, string $subtitle, string $icon): void
    {
        $user = $_SESSION['user'] ?? [];
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= c_h($title) ?> - Gestion Pharmacie</title>
            <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        </head>
        <body class="bg-gray-100">
            <header class="bg-purple-600 text-white shadow-lg">
                <div class="container mx-auto px-4 py-4">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                        <div class="flex items-center space-x-4">
                            <i class="fas <?= c_h($icon) ?> text-2xl"></i>
                            <div>
                                <h1 class="text-xl font-bold"><?= c_h($title) ?></h1>
                                <p class="text-sm text-purple-100"><?= c_h($subtitle) ?></p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span><strong>Utilisateur:</strong> <?= c_h($user['name'] ?? $user['username'] ?? 'Invite') ?></span>
                            <span><strong>Date:</strong> <?= date('d/m/Y H:i') ?></span>
                            <a href="/comptabilite" class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded">
                                <i class="fas fa-arrow-left mr-1"></i> Retour
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            <main class="container mx-auto px-4 py-6">
        <?php
    }
}

if (!function_exists('c_footer')) {
    function c_footer(): void
    {
        echo '</main></body></html>';
    }
}

if (!function_exists('c_stat_card')) {
    function c_stat_card(string $label, string $value, string $icon, string $color = 'text-blue-600'): void
    {
        ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600"><?= c_h($label) ?></p>
                    <p class="text-2xl font-bold <?= c_h($color) ?>"><?= c_h($value) ?></p>
                </div>
                <i class="fas <?= c_h($icon) ?> text-3xl <?= c_h($color) ?>"></i>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('c_empty_row')) {
    function c_empty_row(int $colspan, string $message = 'Aucune donnee disponible.'): void
    {
        ?>
        <tr>
            <td colspan="<?= $colspan ?>" class="border border-gray-300 px-4 py-8 text-center text-gray-500">
                <?= c_h($message) ?>
            </td>
        </tr>
        <?php
    }
}
