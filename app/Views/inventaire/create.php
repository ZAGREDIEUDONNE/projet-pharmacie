<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title><?= htmlspecialchars((string)($title ?? 'Nouvel inventaire')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"></head>
<body class="bg-gray-100 p-6">
<div class="max-w-xl mx-auto bg-white rounded-lg shadow p-6">
    <h1 class="text-xl font-bold mb-4">Lancer un inventaire</h1>
    <form method="POST" action="/inventaire/store">
        <label class="block text-sm mb-2">Notes</label>
        <textarea name="notes" rows="3" class="w-full border rounded px-3 py-2 mb-4"></textarea>
        <button class="bg-green-600 text-white px-6 py-2 rounded">Démarrer</button>
        <a href="/inventaire" class="ml-3 text-gray-600">Annuler</a>
    </form>
</div></body></html>
