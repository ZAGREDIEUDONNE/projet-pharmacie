<?php

/**
 * Configuration globale de l'application ERP
 */

// Nom de l'application
define('APP_NAME', 'ERP Pharmacy');
define('APP_VERSION', '1.0.0');

// Configuration de base
define('APP_DEBUG', true);
define('APP_ENV', 'development');

// Configuration base de données
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_DATABASE', 'medecin');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');

// Configuration session
define('SESSION_LIFETIME', 3600); // 1 heure

// Configuration URLs
define('BASE_URL', 'http://localhost:8000');
define('ASSETS_URL', BASE_URL . '/assets');

// Configuration sécurité
define('HASH_ALGO', PASSWORD_DEFAULT);
define('MIN_PASSWORD_LENGTH', 8);

// Configuration pagination
define('ITEMS_PER_PAGE', 20);

// Configuration fichiers
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
