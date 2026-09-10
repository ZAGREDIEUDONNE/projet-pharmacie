<?php
$root = dirname(__DIR__);
$dbRoot = $root . DIRECTORY_SEPARATOR . 'database';
$outPath = $dbRoot . DIRECTORY_SEPARATOR . 'INVENTAIRE_TABLES.md';

$sqlFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dbRoot));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'sql') {
        $sqlFiles[] = $file->getPathname();
    }
}
sort($sqlFiles);

function stripComments($text) {
    $lines = preg_split('/\R/', $text);
    $out = [];
    foreach ($lines as $line) {
        $out[] = preg_replace('/--.*$/', '', $line);
    }
    return implode("\n", $out);
}

function normalizeType($defn) {
    $s = trim($defn);
    $s = preg_replace('/\bNOT NULL\b/i', '', $s);
    $s = preg_replace('/\bNULL\b/i', '', $s);
    $s = preg_replace('/\bPRIMARY KEY\b/i', '', $s);
    $s = preg_replace('/\bUNIQUE\b/i', '', $s);
    $s = preg_replace('/\bAUTO_INCREMENT\b/i', '', $s);
    $s = preg_replace('/\bDEFAULT\s+[^,\s]+/i', '', $s);
    $s = preg_replace('/\bON UPDATE CURRENT_TIMESTAMP\b/i', '', $s);
    $s = preg_replace('/\bCHARACTER SET[^,]+/i', '', $s);
    $s = preg_replace('/\bCOLLATE[^,]+/i', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim(rtrim($s, ','));
}

function normalizeDefault($defn) {
    if (preg_match('/DEFAULT\s+([^\s,]+)/i', $defn, $m)) {
        $value = strtoupper($m[1]);
        if ($value === 'CURRENT_TIMESTAMP') return 'CURRENT_TIMESTAMP';
        if ($value === 'NULL') return 'NULL';
        return $m[1];
    }
    return (stripos($defn, 'NULL') !== false) ? 'NULL' : '—';
}

function normalizeNullable($defn) {
    return stripos($defn, 'NOT NULL') !== false ? 'NO' : 'YES';
}

function normalizeKey($defn, $colName, $fkCols) {
    $upper = strtoupper($defn);
    if (strpos($upper, 'PRIMARY KEY') !== false) return 'PRI';
    if (strpos($upper, 'UNIQUE') !== false) return 'UNI';
    if (in_array($colName, $fkCols, true) || substr($colName, -3) === '_id') return 'MUL';
    return '—';
}

function normalizeExtra($defn) {
    $upper = strtoupper($defn);
    $extra = strpos($upper, 'AUTO_INCREMENT') !== false ? 'auto_increment' : '—';
    if (strpos($upper, 'ON UPDATE CURRENT_TIMESTAMP') !== false) {
        return $extra === '—' ? 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP' : $extra . ' on update CURRENT_TIMESTAMP';
    }
    if (strpos($upper, 'DEFAULT') !== false && strpos($upper, 'CURRENT_TIMESTAMP') !== false && $extra === '—') {
        return 'DEFAULT_GENERATED';
    }
    return $extra;
}

function parseColumns($body) {
    $lines = preg_split('/\R/', $body);
    $columns = [];
    $fkCols = [];
    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === '') continue;
        if (preg_match('/^(CONSTRAINT|PRIMARY|UNIQUE|KEY|INDEX|CHECK)/i', $line)) {
            if (preg_match('/FOREIGN\s+KEY\s*\(([^)]+)\)/i', $line, $m)) {
                foreach (preg_split('/,/', $m[1]) as $col) {
                    $fkCols[] = trim(str_replace('`', '', $col));
                }
            }
            continue;
        }
        if (preg_match('/^FOREIGN/i', $line)) {
            if (preg_match('/FOREIGN\s+KEY\s*\(([^)]+)\)/i', $line, $m)) {
                foreach (preg_split('/,/', $m[1]) as $col) {
                    $fkCols[] = trim(str_replace('`', '', $col));
                }
            }
            continue;
        }
        if ($line === ')' || $line === ');' || $line === '},') continue;
        if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/', $line, $m)) {
            $colName = $m[1];
            $colDef = trim(rtrim($m[2], ','));
            $columns[] = [
                'name' => $colName,
                'definition' => $colDef,
                'type' => normalizeType($colDef),
                'null' => normalizeNullable($colDef),
                'key' => normalizeKey($colDef, $colName, $fkCols),
                'default' => normalizeDefault($colDef),
                'extra' => normalizeExtra($colDef),
            ];
        }
    }
    return $columns;
}

$schemaTables = [];
foreach ($sqlFiles as $filePath) {
    $text = file_get_contents($filePath);
    $clean = stripComments($text);

    preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\);/is', $clean, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $tableName = strtolower($match[1]);
        if ($tableName === 'sqlite_sequence') continue;
        $body = $match[2];
        $schemaTables[$tableName] = [
            'name' => $tableName,
            'columns' => parseColumns($body),
        ];
    }

    preg_match_all('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.+?);/is', $clean, $alterMatches, PREG_SET_ORDER);
    foreach ($alterMatches as $alter) {
        $tableName = strtolower($alter[1]);
        if (!isset($schemaTables[$tableName])) {
            $schemaTables[$tableName] = ['name' => $tableName, 'columns' => []];
        }
        $body = $alter[2];

        if (stripos($body, 'ADD COLUMN') !== false) {
            preg_match_all('/ADD\s+COLUMN(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s+(.+?)(?=(?:,\s+ADD\s+COLUMN)|$)/is', $body, $addMatches, PREG_SET_ORDER);
            foreach ($addMatches as $add) {
                $colName = $add[1];
                $colDef = trim(rtrim($add[2], ','));
                $colDef = preg_replace('/\b(AFTER|FIRST)\s+`?[a-zA-Z0-9_]+`?$/i', '', $colDef);
                $colDef = trim($colDef);
                $columns = &$schemaTables[$tableName]['columns'];
                $existing = null;
                foreach ($columns as &$col) {
                    if ($col['name'] === $colName) {
                        $existing = &$col;
                        break;
                    }
                }
                if ($existing) {
                    $existing['definition'] = $colDef;
                    $existing['type'] = normalizeType($colDef);
                    $existing['null'] = normalizeNullable($colDef);
                    $existing['key'] = normalizeKey($colDef, $colName, []);
                    $existing['default'] = normalizeDefault($colDef);
                    $existing['extra'] = normalizeExtra($colDef);
                } else {
                    $columns[] = [
                        'name' => $colName,
                        'definition' => $colDef,
                        'type' => normalizeType($colDef),
                        'null' => normalizeNullable($colDef),
                        'key' => normalizeKey($colDef, $colName, []),
                        'default' => normalizeDefault($colDef),
                        'extra' => normalizeExtra($colDef),
                    ];
                }
                unset($col);
                unset($existing);
            }
        }

        if (stripos($body, 'MODIFY COLUMN') !== false) {
            preg_match_all('/MODIFY\s+COLUMN\s+`?([a-zA-Z0-9_]+)`?\s+(.+?)(?=(?:,\s+ADD\s+COLUMN)|$)/is', $body, $modMatches, PREG_SET_ORDER);
            foreach ($modMatches as $mod) {
                $colName = $mod[1];
                $colDef = trim(rtrim($mod[2], ','));
                $colDef = preg_replace('/\b(AFTER|FIRST)\s+`?[a-zA-Z0-9_]+`?$/i', '', $colDef);
                $colDef = trim($colDef);
                $columns = &$schemaTables[$tableName]['columns'];
                foreach ($columns as &$col) {
                    if ($col['name'] === $colName) {
                        $col['definition'] = $colDef;
                        $col['type'] = normalizeType($colDef);
                        $col['null'] = normalizeNullable($colDef);
                        $col['key'] = normalizeKey($colDef, $colName, []);
                        $col['default'] = normalizeDefault($colDef);
                        $col['extra'] = normalizeExtra($colDef);
                        break;
                    }
                }
                unset($col);
            }
        }
    }
}

$lines = [];
$lines[] = '# Inventaire de la base de données medecin';
$lines[] = '';
$lines[] = 'Généré automatiquement à partir des scripts SQL et migrations présents dans le dépôt.';
$lines[] = '';
$lines[] = 'Nombre de tables répertoriées : ' . count($schemaTables);
$lines[] = '';

ksort($schemaTables);
foreach ($schemaTables as $tableName => $item) {
    $columns = $item['columns'];
    $lines[] = '## `' . $tableName . '` (' . count($columns) . ' champs)';
    $lines[] = '';
    $lines[] = '| Champ | Type | Null | Clé | Défaut | Extra |';
    $lines[] = '|-------|------|------|-----|--------|-------|';
    foreach ($columns as $col) {
        $lines[] = '| `' . $col['name'] . '` | ' . $col['type'] . ' | ' . $col['null'] . ' | ' . $col['key'] . ' | ' . $col['default'] . ' | ' . $col['extra'] . ' |';
    }
    $lines[] = '';
}

file_put_contents($outPath, implode("\n", $lines));
echo "Wrote $outPath with " . count($schemaTables) . " tables\n";
?>
