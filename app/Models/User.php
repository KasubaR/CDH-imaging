<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'department_id', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function createdExaminations(): HasMany
    {
        return $this->hasMany(Examination::class, 'created_by');
    }

    public function uploadedImages(): HasMany
    {
        return $this->hasMany(Image::class, 'uploaded_by');
    }

    public function sentTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'sent_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'account_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin->value;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff->value;
    }

    public function isMoic(): bool
    {
        return $this->role === UserRole::Moic->value;
    }

    /**
     * Admin and MOIC both see every department's images/examinations,
     * bypassing the usual referring-department/transfer-participation scope
     * in ImagePolicy/ExaminationPolicy — but MOIC still needs the ViewImages
     * (and Download) permission actually assigned, unlike Admin, which
     * bypasses hasPermission() entirely. See UserRole::Moic's docblock.
     */
    public function canViewAllDepartments(): bool
    {
        return $this->isAdmin() || $this->isMoic();
    }

    public function hasPermission(PermissionEnum|string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $slug = $permission instanceof PermissionEnum ? $permission->value : $permission;

        return $this->permissions->contains('slug', $slug);
    }

    public function canAccessDepartment(): bool
    {
        // Admin and MOIC are both intentionally department-independent (see
        // UserRole::Moic) — neither should be logged out by
        // EnsureAccountIsActive just for having no department.
        if ($this->canViewAllDepartments()) {
            return true;
        }

        return $this->department !== null && $this->department->is_active;
    }
}
