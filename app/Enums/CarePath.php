<?php

namespace App\Enums;

enum CarePath: string
{
    case EmergencyCare = 'emergency_care';
    case SpecialistVisit = 'specialist_visit';
    case SecondOpinion = 'second_opinion';
    case PrimaryCare = 'primary_care';
    case Telemedicine = 'telemedicine';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
