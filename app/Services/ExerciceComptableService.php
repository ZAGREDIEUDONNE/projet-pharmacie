<?php

namespace App\Services;

use Exception;
use PDO;

/** Rules for the real `exercices_comptables` schema. */
final class ExerciceComptableService
{
    public function __construct(private PDO $db)
    {
    }

    /** Refuses entries outside an open accounting period. */
    public function assertEcritureAllowed(string $dateEcriture): void
    {
        $date = substr($dateEcriture, 0, 10);
        $stmt = $this->db->prepare(
            "SELECT exercice, statut FROM exercices_comptables
             WHERE date_debut <= ? AND date_fin >= ?
             ORDER BY date_debut DESC LIMIT 1"
        );
        $stmt->execute([$date, $date]);
        $exercice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exercice) {
            throw new Exception("Aucun exercice comptable ne couvre la date {$date}");
        }
        if ($exercice['statut'] !== 'ouvert') {
            throw new Exception("L'exercice {$exercice['exercice']} n'est pas ouvert");
        }
    }

    public function creerExercice(string $libelle, string $dateDebut, string $dateFin): int
    {
        if ($dateDebut > $dateFin) {
            throw new Exception('La date de début doit précéder la date de fin');
        }
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            // Lock the current periods so concurrent creations cannot leave two open periods.
            $this->db->query("SELECT id FROM exercices_comptables WHERE statut = 'ouvert' FOR UPDATE")->fetchAll();
            $check = $this->db->prepare(
                "SELECT COUNT(*) FROM exercices_comptables
                 WHERE statut = 'ouvert' OR (date_debut <= ? AND date_fin >= ?)"
            );
            $check->execute([$dateFin, $dateDebut]);
            if ((int)$check->fetchColumn() > 0) {
                throw new Exception('Un exercice ouvert ou chevauchant existe déjà');
            }
            $stmt = $this->db->prepare(
                "INSERT INTO exercices_comptables (exercice, date_debut, date_fin, statut)
                 VALUES (?, ?, ?, 'ouvert')"
            );
            $stmt->execute([$libelle, $dateDebut, $dateFin]);
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
