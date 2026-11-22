<?php
// config/auth.php
session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function loginUser(int $id, string $username): void {
    $_SESSION['user_id']  = $id;
    $_SESSION['username'] = $username;
}

function logoutUser(): void {
    session_destroy();
    header('Location: /login.php');
    exit;
}
