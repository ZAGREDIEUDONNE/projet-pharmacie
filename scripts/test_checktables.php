<?php
require_once __DIR__ . '/../config/database.php';

$result = Database::checkTables();
var_dump($result);
