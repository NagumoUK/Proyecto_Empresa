<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gestor',
            self::Viewer => 'Consulta',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Admin->value => self::Admin->label(),
            self::Manager->value => self::Manager->label(),
            self::Viewer->value => self::Viewer->label(),
        ];
    }
}
