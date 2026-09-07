<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    /**
     * Cross-department image/examination viewer — sees every department's
     * images like an admin, but none of the admin management screens
     * (accounts/departments/audit log/permissions/settings stay gated to
     * isAdmin() only) and none of admin's implicit upload/send/receive/
     * forward/delete abilities. Needs the ViewImages + Download permissions
     * assigned explicitly (see UserController::syncsPermissions()) — unlike
     * Admin, it does not bypass hasPermission().
     */
    case Moic = 'moic';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'System Administrator',
            self::Staff => 'Department account',
            self::Moic => 'Medical Officer in Charge',
        };
    }
}
