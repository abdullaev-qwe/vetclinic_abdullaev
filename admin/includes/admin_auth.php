<?php
// =====================================================
// admin/includes/admin_auth.php
// Подключается в начале каждой страницы admin/
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAuth();
