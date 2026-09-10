<?php

namespace App\Services;

/**
 * Référentiel métier produits pharmaceutiques (Burkina Faso).
 */
class PharmacyProductService
{
    public const TYPE_MEDICAMENT_CONSEIL = 'MEDICAMENT_CONSEIL';
    public const TYPE_HORS_LISTE = 'HORS_LISTE';
    public const TYPE_ORDONNANCIER = 'ORDONNANCIER';
    public const TYPE_PSYCHOTROPE = 'PSYCHOTROPE';
    public const TYPE_ANTICANCEREUX = 'ANTICANCEREUX';

    /** Plafond absolu de remise (tous rôles). */
    public const MAX_DISCOUNT_PERCENT = 25.0;

    /** Types nécessitant une ordonnance obligatoire avant vente. */
    public const PRESCRIPTION_REQUIRED_TYPES = [
        self::TYPE_ORDONNANCIER,
        self::TYPE_PSYCHOTROPE,
        self::TYPE_ANTICANCEREUX,
    ];

    /** Types à ordonnance facultative. */
    public const PRESCRIPTION_OPTIONAL_TYPES = [
        self::TYPE_MEDICAMENT_CONSEIL,
        self::TYPE_HORS_LISTE,
    ];

    /** Rayons autorisés en pharmacie. */
    public const RAYONS = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'Frigo', 'Armoire à clé', 'Vitrine', 'Comptoir', 'Magasin', 'Autres',
    ];

    public static function typeDelivranceLabels(): array
    {
        return [
            self::TYPE_MEDICAMENT_CONSEIL => 'Médicament de conseil',
            self::TYPE_HORS_LISTE => 'Hors liste',
            self::TYPE_ORDONNANCIER => 'Ordonnancier',
            self::TYPE_PSYCHOTROPE => 'Psychotrope',
            self::TYPE_ANTICANCEREUX => 'Anticancéreux',
        ];
    }

    public static function formesPharmaceutiques(): array
    {
        return [
            'Comprimé', 'Gélule', 'Sirop', 'Suspension', 'Granulés',
            'Goutte nasale', 'Goutte auriculaire', 'Collyre', 'Pommade cutanée',
            'Pommade ophtalmique', 'Gel', 'Lotion', 'Tisane', 'Poudre',
            'Consommable médical', 'Injectable', 'Vaccin', 'Sérum', 'Suppositoire',
            'Comprimé gynécologique', 'Bain de bouche', 'Parapharmacie',
            'Phytomédicament', 'Équipements médicaux', 'Autres',
        ];
    }

    public static function rayons(): array
    {
        return self::RAYONS;
    }

    public static function isRayonValide(?string $rayon): bool
    {
        return in_array(trim((string)$rayon), self::RAYONS, true);
    }

    public static function requiresPrescription(string $typeDelivrance): bool
    {
        return in_array(strtoupper($typeDelivrance), self::PRESCRIPTION_REQUIRED_TYPES, true);
    }

    public static function normalizeTypeDelivrance(string $value): string
    {
        $value = strtoupper(trim($value));
        $labels = array_keys(self::typeDelivranceLabels());
        return in_array($value, $labels, true) ? $value : self::TYPE_MEDICAMENT_CONSEIL;
    }
}
