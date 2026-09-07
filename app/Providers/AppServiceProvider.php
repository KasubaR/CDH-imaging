<?php

namespace App\Providers;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Image;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Transfer;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use App\Policies\ExaminationPolicy;
use App\Policies\ExaminationTypePolicy;
use App\Policies\ImagePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PatientPolicy;
use App\Policies\TransferPolicy;
use App\Policies\UserPolicy;
use App\Services\TransferAuthorizationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TransferAuthorizationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Transfer::class, TransferPolicy::class);
        Gate::policy(Image::class, ImagePolicy::class);
        Gate::policy(Examination::class, ExaminationPolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(ExaminationType::class, ExaminationTypePolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);

        $service = fn (): TransferAuthorizationService => app(TransferAuthorizationService::class);

        Gate::define('manage-departments', fn (User $user) => $user->isAdmin());
        Gate::define('manage-accounts', fn (User $user) => $user->isAdmin());
        Gate::define('manage-examination-types', fn (User $user) => $user->isAdmin());
        Gate::define('manage-permissions', fn (User $user) => $user->isAdmin());
        Gate::define('view-audit-logs', fn (User $user) => $user->isAdmin());
        Gate::define('manage-settings', fn (User $user) => $user->isAdmin());
        Gate::define('delete-images', fn (User $user) => $user->isAdmin());
        Gate::define('view-system-statistics', fn (User $user) => $user->isAdmin());

        Gate::define('view-images', fn (User $user) => $user->hasPermission(PermissionEnum::ViewImages));
        Gate::define('upload-images', fn (User $user) => $user->hasPermission(PermissionEnum::Upload));
        Gate::define('download-images', fn (User $user) => $user->hasPermission(PermissionEnum::Download));

        Gate::define('send-transfers', fn (User $user) => $service()->canUserSendAtAll($user));
        Gate::define('receive-transfers', fn (User $user) => $service()->canUserReceiveAtAll($user));
        Gate::define('forward-transfers', fn (User $user) => $user->hasPermission(PermissionEnum::Forward) && $service()->canUserSendAtAll($user));

        Gate::define('send-transfer-to', fn (User $user, Department $to) => $service()->canUserSendTo($user, $to));
        Gate::define('receive-transfer-from', fn (User $user, Department $from) => $service()->canUserReceiveFrom($user, $from));
        Gate::define('forward-transfer-to', fn (User $user, Department $to) => $service()->canUserForward($user, $to));
    }
}
