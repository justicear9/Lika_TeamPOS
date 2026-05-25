<?php

use Illuminate\Support\Facades\Route;
use Modules\ApprovalWorkflow\Http\Controllers\ApprovalActionController;
use Modules\ApprovalWorkflow\Http\Controllers\InstallController;
use Modules\ApprovalWorkflow\Http\Controllers\PendingApprovalController;
use Modules\ApprovalWorkflow\Http\Controllers\SettingsController;

Route::middleware(['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'SetSessionData'])
    ->prefix('approval-workflow')
    ->name('approvalworkflow.')
    ->group(function () {
        Route::get('/install', [InstallController::class, 'index'])->name('install.index');
        Route::post('/install', [InstallController::class, 'install'])->name('install.run');
        Route::get('/install/uninstall', [InstallController::class, 'uninstall'])->name('install.uninstall');

        Route::get('/pending', [PendingApprovalController::class, 'index'])->name('pending.index');
        Route::post('/{id}/approve', [ApprovalActionController::class, 'approve'])->name('actions.approve')->whereNumber('id');
        Route::post('/{id}/reject', [ApprovalActionController::class, 'reject'])->name('actions.reject')->whereNumber('id');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
