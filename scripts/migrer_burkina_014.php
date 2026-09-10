<?php

/**
 * Applique la migration 014 — exigences métier pharmacie Burkina Faso.
 * Usage: php scripts/migrer_burkina_014.php
 */

require_once __DIR__ . '/../config/database.php';

$file = __DIR__ . '/../database/migrations/014_burkina_pharmacy_business_requirements.sql';
$result = Database::executeScript($file);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($result['success'] ? 0 : 1);
