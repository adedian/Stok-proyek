<?php

/**
 * Helper terkait user yang sedang login
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentUserName(): string
{
    return $_SESSION['full_name'] ?? 'Guest';
}

function currentUserRole(): ?string
{
    return $_SESSION['role_slug'] ?? null;
}

function hasRole($roles): bool
{
    $roles = is_array($roles) ? $roles : [$roles];
    return in_array(currentUserRole(), $roles, true);
}
