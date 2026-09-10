<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Plafonds remise')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-2">Plafonds de remise par rôle</h1>
    <p class="text-sm text-gray-600 mb-6">Maximum autorisé pour l'administrateur : 25 %</p>
    <?php if (!empty($_SESSION['success'])): ?><div class="bg-green-50 p-3 rounded mb-4"><?= htmlspecialchars((string)$_SESSION['success']) ?></div><?php unset($_SESSION['success']); endif; ?>
    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <?php foreach (($limits ?? []) as $limit): ?>
        <form method="POST" action="/finance/remises-limites" class="flex items-end gap-4 border-b pb-4">
            <input type="hidden" name="role_id" value="<?= (int)$limit['role_id'] ?>">
            <div class="flex-1"><label class="text-sm text-gray-500">Rôle</label><p class="font-semibold"><?= htmlspecialchars((string)($limit['role_nom'] ?? '')) ?></p></div>
            <div><label class="text-sm">Max %</label><input type="number" name="max_discount_percent" value="<?= htmlspecialchars((string)$limit['max_discount_percent']) ?>" min="0" max="25" step="0.01" class="border rounded px-3 py-2 w-28"></div>
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Mettre à jour</button>
        </form>
        <?php endforeach; ?>
    </div>
</div></body></html>
