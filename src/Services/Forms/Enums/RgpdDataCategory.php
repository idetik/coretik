<?php

namespace Coretik\Services\Forms\Enums;

/**
 * Categories of personal data (GDPR, "RGPD" in French), declared in the rules of a field:
 *   'rgpd' => ['category' => RgpdDataCategory::CONTACT_DATA, 'private' => true]
 *
 * TunnelForm::getRgpdDataCategories() lists the collected categories, e.g. to build the information notice.
 * A project may use its own categories: any string or backed enum is accepted as category.
 */
enum RgpdDataCategory: string
{
    case CONTACT_DATA = 'contact_data';
    case IDENTIFICATION_DATA = 'identification_data';
    case PROJECT_DATA = 'project_data';
    case FINANCIAL_DATA = 'financial_data';
    case HEALTH_DATA = 'health_data';
    case TECHNICAL_DATA = 'technical_data';
    case COMMUNICATION_DATA = 'communication_data';
    case LOCATION_DATA = 'location_data';
    case PROFILE_DATA = 'profile_data';

    public function label(): string
    {
        return match ($this) {
            self::CONTACT_DATA => \__('Données de contact', 'coretik'),
            self::IDENTIFICATION_DATA => \__('Données d\'identification', 'coretik'),
            self::PROJECT_DATA => \__('Données relatives à la demande', 'coretik'),
            self::FINANCIAL_DATA => \__('Données financières', 'coretik'),
            self::HEALTH_DATA => \__('Données de santé', 'coretik'),
            self::TECHNICAL_DATA => \__('Données techniques', 'coretik'),
            self::COMMUNICATION_DATA => \__('Données de communication', 'coretik'),
            self::LOCATION_DATA => \__('Données de localisation', 'coretik'),
            self::PROFILE_DATA => \__('Données de profil', 'coretik'),
        };
    }

    /**
     * @return array<string, string> value => label
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
