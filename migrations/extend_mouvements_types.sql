-- Migration: Extension des types de mouvements de caisse
-- Date: 19 juillet 2026
-- Description: Ajoute de nouveaux types de mouvements pour le journal de caisse professionnel

-- Modifier l'ENUM type_mouvement pour ajouter les nouveaux types
ALTER TABLE mouvements_caisse 
MODIFY COLUMN type_mouvement ENUM(
    'VENTE',
    'REMBOURSEMENT',
    'APPROVISIONNEMENT',
    'RETRAIT',
    'DECAISSEMENT',
    'OUVERTURE_CAISSE',
    'FERMETURE_CAISSE',
    'ENCAISSEMENT_CLIENT',
    'REGLEMENT_CREANCE',
    'ACOMPTE_CLIENT',
    'ANNULATION_VENTE',
    'CORRECTION_CAISSE',
    'AJUSTEMENT_CAISSE',
    'DEPOT_BANCAIRE',
    'RETRAIT_BANCAIRE'
);
