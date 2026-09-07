<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\DepartmentTransferMatrixController;
use App\Http\Controllers\Admin\ExaminationTypeController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\Examination\ExaminationController;
use App\Http\Controllers\Image\ImageController;
use App\Http\Controllers\Image\UploadChunkController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Transfer\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::post('/', [LoginController::class, 'store']);

    Route::redirect('/login', '/');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/', [AdminLoginController::class, 'store']);
    });
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/examination-types', [ExaminationTypeController::class, 'index'])->name('examination-types.index');
        Route::get('/examination-types/create', [ExaminationTypeController::class, 'create'])->name('examination-types.create');
        Route::post('/examination-types', [ExaminationTypeController::class, 'store'])->name('examination-types.store');
        Route::get('/examination-types/{examination_type}/edit', [ExaminationTypeController::class, 'edit'])->name('examination-types.edit');
        Route::put('/examination-types/{examination_type}', [ExaminationTypeController::class, 'update'])->name('examination-types.update');
        Route::patch('/examination-types/{examination_type}/deactivate', [ExaminationTypeController::class, 'deactivate'])->name('examination-types.deactivate');
        Route::patch('/examination-types/{examination_type}/activate', [ExaminationTypeController::class, 'activate'])->name('examination-types.activate');

        Route::get('/accounts', [UserController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/create', [UserController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [UserController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}/edit', [UserController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{account}', [UserController::class, 'update'])->name('accounts.update');
        Route::patch('/accounts/{account}/deactivate', [UserController::class, 'deactivate'])->name('accounts.deactivate');
        Route::patch('/accounts/{account}/activate', [UserController::class, 'activate'])->name('accounts.activate');
        Route::patch('/accounts/{account}/reset-password', [UserController::class, 'resetPassword'])->name('accounts.reset-password');

        Route::get('/departments', [AdminDepartmentController::class, 'index'])->name('departments.index');
        Route::get('/departments/create', [AdminDepartmentController::class, 'create'])->name('departments.create');
        Route::post('/departments', [AdminDepartmentController::class, 'store'])->name('departments.store');
        Route::get('/departments/matrix', [DepartmentTransferMatrixController::class, 'edit'])->name('departments.matrix');
        Route::put('/departments/matrix', [DepartmentTransferMatrixController::class, 'update'])->name('departments.matrix.update');
        Route::get('/departments/{department}/edit', [AdminDepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('/departments/{department}', [AdminDepartmentController::class, 'update'])->name('departments.update');
        Route::patch('/departments/{department}/deactivate', [AdminDepartmentController::class, 'deactivate'])->name('departments.deactivate');
        Route::patch('/departments/{department}/activate', [AdminDepartmentController::class, 'activate'])->name('departments.activate');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::prefix('department')->name('department.')->middleware('permission:view_images')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
    });

    Route::prefix('patients')->name('patients.')->middleware('permission:view_images')->group(function () {
        Route::get('/', [PatientController::class, 'index'])->name('index');
        Route::get('/create', [PatientController::class, 'create'])->name('create')->middleware('can:create,App\Models\Patient');
        Route::post('/', [PatientController::class, 'store'])->name('store')->middleware('can:create,App\Models\Patient');
        Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    });

    Route::prefix('patients/{patient}/examinations')->name('patients.examinations.')->middleware('can:create,App\Models\Examination')->group(function () {
        Route::get('/create', [ExaminationController::class, 'create'])->name('create');
        Route::post('/', [ExaminationController::class, 'store'])->name('store');
    });

    Route::get('/examinations/{examination}', [ExaminationController::class, 'show'])
        ->name('examinations.show')
        ->middleware('permission:view_images');

    Route::get('/images/{image}', [ImageController::class, 'show'])
        ->name('images.show')
        ->middleware('permission:view_images');

    Route::get('/images/{image}/thumbnail', [ImageController::class, 'thumbnail'])
        ->name('images.thumbnail')
        ->middleware('permission:view_images');

    Route::get('/images/{image}/download', [ImageController::class, 'download'])
        ->name('images.download')
        ->middleware('permission:download');

    Route::get('/images/{image}/delete', [ImageController::class, 'confirmDelete'])
        ->name('images.delete');

    Route::delete('/images/{image}', [ImageController::class, 'destroy'])
        ->name('images.destroy');

    Route::prefix('examinations/{examination}/images')->name('examinations.images.')
        ->middleware('permission:upload')->group(function () {
            Route::get('/create', [ImageController::class, 'create'])->name('create');
            Route::post('/', [ImageController::class, 'store'])->name('store');
            Route::post('/chunks', [UploadChunkController::class, 'store'])->name('chunks.store');
            Route::get('/chunks/{uploadId}/status', [UploadChunkController::class, 'status'])
                ->name('chunks.status')
                ->where('uploadId', '[0-9a-fA-F-]{36}');
        });

    Route::post('/examinations/{examination}/transfers', [TransferController::class, 'store'])
        ->name('examinations.transfers.store')
        ->middleware('permission:send');

    Route::post('/transfers/{transfer}/forward', [TransferController::class, 'forward'])
        ->name('transfers.forward')
        ->middleware('permission:forward');

    Route::post('/transfers/{transfer}/recall', [TransferController::class, 'recall'])
        ->name('transfers.recall');

    Route::prefix('transfers/{transfer}')->name('transfers.')->middleware('permission:receive')->group(function () {
        Route::post('/acknowledge', [TransferController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/complete', [TransferController::class, 'complete'])->name('complete');
        Route::post('/reject', [TransferController::class, 'reject'])->name('reject');
    });

    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])
        ->name('notifications.show');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
});
