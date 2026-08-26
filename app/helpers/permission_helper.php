<?php

/**
 * Helper baca config/permissions.php -- dipakai controller (lewat Middleware)
 * dan view (untuk sembunyikan tombol yang usernya tidak berhak pakai).
 */

/**
 * Daftar role_slug yang diizinkan untuk satu module+action.
 * Module/action yang tidak terdaftar dianggap TIDAK ADA yang boleh akses
 * (fail closed) -- lebih aman daripada diam-diam meloloskan semua orang.
 */
function permissionRoles(string $module, string $action): array
{
    static $matrix = null;
    if ($matrix === null) {
        $matrix = require ROOT_PATH . '/config/permissions.php';
    }

    return $matrix[$module][$action] ?? [];
}

/**
 * Apakah user yang sedang login boleh melakukan $action di $module.
 */
function can(string $module, string $action): bool
{
    $role = currentUserRole();
    if (!$role) {
        return false;
    }

    return in_array($role, permissionRoles($module, $action), true);
}

function canView(string $module): bool
{
    return can($module, 'view');
}

function canCreate(string $module): bool
{
    return can($module, 'create');
}

function canEdit(string $module): bool
{
    return can($module, 'edit');
}

function canDelete(string $module): bool
{
    return can($module, 'delete');
}

function canApprove(string $module): bool
{
    return can($module, 'approve');
}

function canValidate(string $module): bool
{
    return can($module, 'validate');
}

function canQuickAdd(string $module): bool
{
    return can($module, 'quick_add');
}
