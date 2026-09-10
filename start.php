<?php

/**
 * Script de démarrage pour le serveur de développement
 */

// Démarrer le serveur PHP
$host = '127.0.0.1';
$port = 8000;
$docroot = __DIR__ . '/public';

echo "Démarrage du serveur PHP sur http://{$host}:{$port}\n";
echo "Répertoire racine: {$docroot}\n";
echo "Appuyez sur Ctrl+C pour arrêter\n\n";

// Démarrer le serveur intégré
$command = "php -S {$host}:{$port} -t {$docroot}";
passthru($command);
