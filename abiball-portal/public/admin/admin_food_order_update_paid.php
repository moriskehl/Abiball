<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Security/AdminGuard.php';
require_once __DIR__ . '/../../src/Security/Csrf.php';
require_once __DIR__ . '/../../src/Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../../src/Repository/AdminAuditLogRepository.php';
require_once __DIR__ . '/../../src/Auth/AdminContext.php';
require_once __DIR__ . '/../../src/Http/Request.php';
require_once __DIR__ . '/../../src/Http/Response.php';

Bootstrap::init();
requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::redirect('/admin/admin_dashboard.php#food');
}

// CSRF-Validierung
if (!Csrf::validate(Request::postString('_csrf'))) {
    Response::redirect('/admin/admin_dashboard.php?err=csrf#food');
}

$orderId = trim(Request::postString('order_id'));
$paidAmount = trim(Request::postString('paid_amount'));

if ($orderId === '' || $paidAmount === '') {
    Response::redirect('/admin/admin_dashboard.php?err=food_missing_data#food');
}

$order = FoodOrderRepository::findByOrderId($orderId);

if (!$order) {
    Response::redirect('/admin/admin_dashboard.php?err=food_order_not_found#food');
}

if ($order['status'] !== 'open') {
    Response::redirect('/admin/admin_dashboard.php?err=food_invalid_status#food');
}

// Zahlungsbetrag aktualisieren und Status auf "paid" setzen
$success = FoodOrderRepository::updatePaidAmount($orderId, (float)$paidAmount);

if ($success) {
    $success = FoodOrderRepository::updateStatus($orderId, 'paid');
}

if ($success) {
    // Audit Log
    AdminAuditLogRepository::append(
        'food_order_mark_paid',
        [
            'order_id' => $orderId,
            'paid_amount' => $paidAmount
        ]
    );
    
    Response::redirect('/admin/admin_dashboard.php?ok=food_paid#food');
} else {
    Response::redirect('/admin/admin_dashboard.php?err=food_update_failed#food');
}
