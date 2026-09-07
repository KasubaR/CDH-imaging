<?php

namespace App\Models;

use App\Services\TransferAuthorizationService;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'can_send', 'can_receive', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'can_send' => 'boolean',
            'can_receive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(Examination::class, 'referring_department_id');
    }

    public function sentTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_department_id');
    }

    public function transferRecipients(): HasMany
    {
        return $this->hasMany(TransferRecipient::class);
    }

    public function outgoingPermissions(): HasMany
    {
        return $this->hasMany(DepartmentPermission::class, 'from_department_id');
    }

    public function incomingPermissions(): HasMany
    {
        return $this->hasMany(DepartmentPermission::class, 'to_department_id');
    }

    public function canSendTo(Department $to): bool
    {
        return app(TransferAuthorizationService::class)->canTransfer($this, $to);
    }

    public function canReceiveFrom(Department $from): bool
    {
        return app(TransferAuthorizationService::class)->canTransfer($from, $this);
    }
}
