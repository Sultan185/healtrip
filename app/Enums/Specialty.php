<?php

namespace App\Enums;

/**
 * The specialty taxonomy is owned by code, not by a table: it is part of the
 * agent's tool schema (as an enum), and that schema must stay byte-stable
 * for the lifetime of a conversation.
 */
enum Specialty: string
{
    case Cardiology = 'cardiology';
    case CardiacSurgery = 'cardiac_surgery';
    case EmergencyMedicine = 'emergency_medicine';
    case InternalMedicine = 'internal_medicine';
    case Orthopedics = 'orthopedics';
    case Neurology = 'neurology';
    case Oncology = 'oncology';
    case Gastroenterology = 'gastroenterology';

    public function labelEn(): string
    {
        return match ($this) {
            self::Cardiology => 'Cardiology',
            self::CardiacSurgery => 'Cardiac Surgery',
            self::EmergencyMedicine => 'Emergency Medicine',
            self::InternalMedicine => 'Internal Medicine',
            self::Orthopedics => 'Orthopedics',
            self::Neurology => 'Neurology',
            self::Oncology => 'Oncology',
            self::Gastroenterology => 'Gastroenterology',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Cardiology => 'أمراض القلب',
            self::CardiacSurgery => 'جراحة القلب',
            self::EmergencyMedicine => 'طب الطوارئ',
            self::InternalMedicine => 'الباطنة',
            self::Orthopedics => 'العظام',
            self::Neurology => 'المخ والأعصاب',
            self::Oncology => 'الأورام',
            self::Gastroenterology => 'الجهاز الهضمي',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
