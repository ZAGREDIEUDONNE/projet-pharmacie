<?php

namespace App\Services;

use PDO;
use Exception;
use ZipArchive;

/**
 * Service de sauvegarde automatique et restauration
 */
class BackupService
{
    private PDO $db;
    private string $backupPath;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->backupPath = __DIR__ . '/../../backups/';
        
        // Créer le répertoire de sauvegarde s'il n'existe pas
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Crée une sauvegarde complète de la base de données
     */
    public function createBackup(): array
    {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = $this->backupPath . 'backup_' . $timestamp . '.sql';
            
            // Récupérer toutes les tables
            $tables = $this->getAllTables();
            
            $backupContent = "-- Sauvegarde automatique - " . date('Y-m-d H:i:s') . "\n";
            $backupContent .= "-- Générée par le système de gestion de pharmacie\n\n";
            
            foreach ($tables as $table) {
                $backupContent .= $this->getTableStructure($table);
                $backupContent .= $this->getTableData($table);
                $backupContent .= "\n\n";
            }
            
            // Écrire le fichier de sauvegarde
            file_put_contents($backupFile, $backupContent);
            
            // Compresser le fichier
            $zipFile = $backupFile . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($backupFile, basename($backupFile));
                $zip->close();
                unlink($backupFile); // Supprimer le fichier SQL non compressé
            }
            
            // Nettoyer les anciennes sauvegardes (garder 7 jours)
            $this->cleanupOldBackups();
            
            return [
                'success' => true,
                'file' => basename($zipFile),
                'size' => filesize($zipFile),
                'tables' => count($tables)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restaure une sauvegarde
     */
    public function restoreBackup(string $backupFile): array
    {
        try {
            $fullPath = $this->backupPath . $backupFile;
            
            if (!file_exists($fullPath)) {
                return [
                    'success' => false,
                    'error' => 'Fichier de sauvegarde non trouvé'
                ];
            }
            
            // Décompresser si nécessaire
            if (pathinfo($backupFile, PATHINFO_EXTENSION) === 'zip') {
                $tempPath = $this->backupPath . 'temp_restore/';
                if (!is_dir($tempPath)) {
                    mkdir($tempPath, 0755, true);
                }
                
                $zip = new ZipArchive();
                if ($zip->open($fullPath) === TRUE) {
                    $zip->extractTo($tempPath);
                    $zip->close();
                }
                
                // Trouver le fichier SQL décompressé
                $sqlFiles = glob($tempPath . '*.sql');
                if (empty($sqlFiles)) {
                    return [
                        'success' => false,
                        'error' => 'Aucun fichier SQL trouvé dans l\'archive'
                    ];
                }
                
                $sqlFile = $sqlFiles[0];
            } else {
                $sqlFile = $fullPath;
            }
            
            // Lire et exécuter le fichier SQL
            $sqlContent = file_get_contents($sqlFile);
            $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
            
            $this->db->beginTransaction();
            
            try {
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $this->db->exec($statement);
                    }
                }
                
                $this->db->commit();
                
                // Nettoyer le répertoire temporaire
                if (isset($tempPath)) {
                    $this->removeDirectory($tempPath);
                }
                
                return [
                    'success' => true,
                    'statements_executed' => count($statements)
                ];
                
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Liste toutes les sauvegardes disponibles
     */
    public function listBackups(): array
    {
        $backups = [];
        $files = glob($this->backupPath . '*.zip');
        
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'timestamp' => filemtime($file)
            ];
        }
        
        // Trier par date (plus récent en premier)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }

    /**
     * Supprime une sauvegarde
     */
    public function deleteBackup(string $backupFile): bool
    {
        $fullPath = $this->backupPath . $backupFile;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }

    /**
     * Récupère toutes les tables de la base de données
     */
    private function getAllTables(): array
    {
        $stmt = $this->db->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Récupère la structure d'une table
     */
    private function getTableStructure(string $table): string
    {
        $stmt = $this->db->query("SHOW CREATE TABLE `{$table}`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql = "-- Structure de la table {$table}\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createTable['Create Table'] . ";\n\n";
        
        return $sql;
    }

    /**
     * Récupère les données d'une table
     */
    private function getTableData(string $table): string
    {
        $stmt = $this->db->query("SELECT * FROM `{$table}`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return "-- Pas de données dans {$table}\n\n";
        }
        
        $sql = "-- Données de la table {$table}\n";
        
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                $values[] = $this->db->quote($value);
            }
            $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
        }
        
        return $sql;
    }

    /**
     * Nettoie les anciennes sauvegardes
     */
    private function cleanupOldBackups(): void
    {
        $files = glob($this->backupPath . '*.zip');
        $maxAge = 7 * 24 * 60 * 60; // 7 jours en secondes
        
        foreach ($files as $file) {
            if (filemtime($file) < (time() - $maxAge)) {
                unlink($file);
            }
        }
    }

    /**
     * Supprime récursivement un répertoire
     */
    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }

    /**
     * Planifie les sauvegardes automatiques
     */
    public function scheduleBackup(): void
    {
        // Vérifier si une sauvegarde automatique est déjà planifiée
        $sql = "SELECT COUNT(*) as count FROM scheduled_backups 
                WHERE type = 'daily' AND statut = 'PLANIFIE'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            return; // Déjà planifié
        }
        
        // Planifier une sauvegarde quotidienne
        $sql = "INSERT INTO scheduled_backups (type, statut, date_planification, prochain_execution)
                VALUES ('daily', 'PLANIFIE', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))";
        $this->db->exec($sql);
    }
}
