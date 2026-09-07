<?php

namespace App\Enums;

enum RejectionReason: string
{
    case WrongPatient = 'wrong_patient';
    case WrongExamination = 'wrong_examination';
    case WrongDepartment = 'wrong_department';
    case PoorImage = 'poor_image';
    case Duplicate = 'duplicate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WrongPatient => 'Wrong patient',
            self::WrongExamination => 'Wrong examination',
            self::WrongDepartment => 'Wrong department',
            self::PoorImage => 'Poor image',
            self::Duplicate => 'Duplicate',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
