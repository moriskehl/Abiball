<?php

declare(strict_types=1);

/**
 * AdminController - Administrationsoberfläche für das Organisationsteam
 * 
 * Verwaltet Teilnehmer, Preisüberschreibungen, Essensbestellungen und Audit-Logs.
 * Zugriff nur für authentifizierte Admins.
 */

require_once __DIR__ . '/../Bootstrap.php';
require_once __DIR__ . '/../Security/Csrf.php';
require_once __DIR__ . '/../Security/RateLimiter.php';
require_once __DIR__ . '/../Http/Request.php';
require_once __DIR__ . '/../Http/Response.php';
require_once __DIR__ . '/../Repository/ParticipantsRepository.php';
require_once __DIR__ . '/../Repository/PricingOverridesRepository.php';
require_once __DIR__ . '/../Repository/AdminAuditLogRepository.php';
require_once __DIR__ . '/../Repository/FoodOrderRepository.php';
require_once __DIR__ . '/../Service/PricingService.php';
require_once __DIR__ . '/../Service/SeatingService.php';
require_once __DIR__ . '/../Service/AdminPasswordService.php';
require_once __DIR__ . '/../Service/ParticipantAdminService.php';
require_once __DIR__ . '/../View/Layout.php';
require_once __DIR__ . '/../View/Helpers.php';
require_once __DIR__ . '/../Auth/AdminContext.php';

// dompdf (Composer)
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

final class AdminController
{
    /* ================================================
     * AUTHENTIFIZIERUNG
     * ================================================ */

  /**
   * Zeigt das Admin-Login-Formular an.
   */
  public static function showLoginForm(string $error = '', string $identifier = ''): void
  {
    Bootstrap::init();

    if (AdminContext::isAdmin()) {
      Response::redirect('/admin/admin_dashboard.php');
    }

    Layout::header('Admin – Login');
?>
    <main class="bg-starfield">
      <!-- Star layers -->
      <div class="stars-layer-1"></div>
      <div class="stars-layer-2"></div>
      <div class="stars-layer-3"></div>

      <div class="container py-5" style="max-width: 1100px;">

        <div class="glass-hero-header sm mb-5 animate-fade-up text-center mx-auto" style="max-width: 560px;">
          <h1 class="h-serif mb-3 reveal-text" style="font-size: clamp(36px, 4.5vw, 58px); font-weight: 300; line-height: 1.05;">
            Admin Login
          </h1>

          <p class="text-muted mb-0" style="font-size: 1.05rem; line-height: 1.7;">
            Anmeldung für das Organisations-Team. Bitte melde dich mit deinem Admin-Namen und Admin-Code an.
          </p>
        </div>

        <div class="card mx-auto" style="max-width: 560px;">
          <div class="card-body p-4 p-md-5">

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger mb-4"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin/admin_login.php" autocomplete="on">
              <?= Csrf::inputField() ?>

              <div class="mb-3">
                <label class="form-label">Admin-Name oder Admin-ID</label>
                <input class="form-control" name="identifier" value="<?= e($identifier) ?>" required autocomplete="username" inputmode="text">
              </div>

              <div class="mb-4">
                <label class="form-label">Admin-Code</label>
                <input class="form-control" type="password" name="password" required autocomplete="current-password">
              </div>

              <div class="d-grid gap-3">
                <button class="btn btn-cta btn-cta-lg" type="submit">Einloggen</button>
                <a class="btn btn-ghost text-muted" href="/">Zurück zur Startseite</a>
              </div>
            </form>

          </div>
        </div>

      </div>
    </main>
  <?php
    Layout::footer();
  }

  /**
   * Verarbeitet den Admin-Login mit Rate-Limiting.
   */
  public static function login(): void
  {
    Bootstrap::init();

    if (AdminContext::isAdmin()) {
      Response::redirect('/admin/admin_dashboard.php');
    }

    $ipKey = 'admin_login_' . Request::ip();
    if (!RateLimiter::allow($ipKey, 8, 60)) {
      self::showLoginForm('Zu viele Versuche. Bitte 1 Minute warten.');
      return;
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      self::showLoginForm('Ungültige Anfrage (CSRF). Seite neu laden und erneut versuchen.');
      return;
    }

    $identifier = trim(Request::postString('identifier'));
    $password   = trim(Request::postString('password'));

    if ($identifier === '' || $password === '') {
      self::showLoginForm('Bitte Daten eingeben.', $identifier);
      return;
    }

    $admin = ParticipantsRepository::findAdminByIdOrName($identifier);
    if (!$admin) {
      self::showLoginForm('Admin nicht gefunden.', $identifier);
      return;
    }

    $stored = (string)($admin['login_code'] ?? '');
    if ($stored === '' || $stored === '0') {
      self::showLoginForm('Admin-Code fehlt in CSV.', $identifier);
      return;
    }

    $input = $password;

    // Sowohl gehashte als auch Klartext-Passwörter akzeptieren (Migration)
    $isHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
    $ok = $isHashed ? password_verify($input, $stored) : hash_equals($stored, $input);

    if (!$ok) {
      self::showLoginForm('Admin-Code ist falsch.', $identifier);
      return;
    }

    // Automatische Migration: Klartext-Passwörter beim nächsten Login hashen
    if (!$isHashed) {
      $newHash = password_hash($input, PASSWORD_DEFAULT);
      if ($newHash !== false) {
        try {
          $adminId = (string)($admin['id'] ?? '');
          ParticipantsRepository::updateLoginCodeForMainId($adminId, $newHash);
        } catch (Throwable $e) {
          // Login war erfolgreich, Hash-Migration ist optional
        }
      }
    }

    AdminContext::loginAsAdmin($admin);
    Response::redirect('/admin/admin_dashboard.php');
  }

  /**
   * Meldet den Admin ab und leitet zur Login-Seite weiter.
   */
  public static function logout(): void
  {
    AdminContext::logout('/admin_login.php');
  }

    /* ================================================
     * TEILNEHMER- UND ZAHLUNGSVERWALTUNG
     * ================================================ */

  /**
   * Aktualisiert den bezahlten Betrag für einen Hauptgast.
   */
  public static function updatePaid(): void
  {
    AdminContext::requireAdmin();

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#edit');
    }

    $mainId  = trim(Request::postString('main_id'));
    $paidRaw = trim(Request::postString('amount_paid'));

    if ($mainId === '') {
      Response::redirect('/admin/admin_dashboard.php?err=main#edit');
    }

    if ($paidRaw === '' || !preg_match('/^\d+$/', $paidRaw)) {
      Response::redirect('/admin/admin_dashboard.php?err=paid#edit');
    }

    $paid = (int)$paidRaw;
    if ($paid < 0) $paid = 0;

    $oldPaid = (int)ParticipantsRepository::amountPaidForMainId($mainId);

    try {
      ParticipantsRepository::updateAmountPaidForMainId($mainId, $paid);

      AdminAuditLogRepository::append('update_paid', [
        'main_id' => $mainId,
        'old' => $oldPaid,
        'new' => $paid,
      ]);

      Response::redirect('/admin/admin_dashboard.php?ok=1#edit');
    } catch (Throwable $e) {
      Response::redirect('/admin/admin_dashboard.php?err=save#edit');
    }
  }

  /**
   * Speichert oder aktualisiert eine Preisüberschreibung.
   */
  public static function saveOverride(): void
  {
    AdminContext::requireAdmin();

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#ovr');
    }

    $id = trim(Request::postString('override_id'));
    $priceRaw = trim(Request::postString('ticket_price'));
    $reason = trim(Request::postString('reason'));

    if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
      Response::redirect('/admin/admin_dashboard.php?err=override_id#ovr');
    }

    if ($priceRaw === '' || !preg_match('/^\d+$/', $priceRaw)) {
      Response::redirect('/admin/admin_dashboard.php?err=override_price#ovr');
    }

    $price = (int)$priceRaw;
    if ($price < 0) $price = 0;

    $before = PricingOverridesRepository::mapById();
    $old = $before[$id] ?? null;

    try {
      PricingOverridesRepository::upsertOverrideForId($id, $price, $reason);

      AdminAuditLogRepository::append('override_save', [
        'id' => $id,
        'old' => $old,
        'new' => ['ticket_price' => $price, 'reason' => $reason],
      ]);

      Response::redirect('/admin/admin_dashboard.php?ok_override=1#ovr');
    } catch (Throwable $e) {
      Response::redirect('/admin/admin_dashboard.php?err=override_save#ovr');
    }
  }

  /**
   * Löscht eine Preisüberschreibung.
   */
  public static function deleteOverride(): void
  {
    AdminContext::requireAdmin();

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#ovr');
    }

    $id = trim(Request::postString('override_id'));
    if ($id === '') {
      Response::redirect('/admin/admin_dashboard.php?err=override_id#ovr');
    }

    $before = PricingOverridesRepository::mapById();
    $old = $before[$id] ?? null;

    try {
      PricingOverridesRepository::deleteOverrideForId($id);

      AdminAuditLogRepository::append('override_delete', [
        'id' => $id,
        'old' => $old,
      ]);

      Response::redirect('/admin/admin_dashboard.php?ok_override=1#ovr');
    } catch (Throwable $e) {
      Response::redirect('/admin/admin_dashboard.php?err=override_delete#ovr');
    }
  }

  /**
   * Legt einen neuen Hauptgast an.
   */
  public static function createMainGuest(): void
  {
    AdminContext::requireAdmin();

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#create');
    }

    $id = trim(Request::postString('id'));
    $name = trim(Request::postString('name'));
    $login = trim(Request::postString('login_code'));

    try {
      ParticipantsRepository::createMainGuest($id, $name, $login, 'USER');

      AdminAuditLogRepository::append('create_main_guest', [
        'id' => $id,
        'name' => $name,
        'login_code_set' => ($login !== ''),
      ]);

      Response::redirect('/admin/admin_dashboard.php?ok_create=1#create');
    } catch (Throwable $e) {
      Response::redirect('/admin/admin_dashboard.php?err=create_main#create');
    }
  }

  /**
   * Legt eine neue Begleitperson zu einem Hauptgast an.
   */
  public static function createCompanion(): void
  {
    AdminContext::requireAdmin();

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#create');
    }

    $mainId = trim(Request::postString('main_id'));
    $name   = trim(Request::postString('name'));
    $login  = trim(Request::postString('login_code')); // optional

    try {
      $newId = ParticipantsRepository::createCompanion($mainId, $name, $login);

      AdminAuditLogRepository::append('create_companion', [
        'main_id' => $mainId,
        'new_id' => $newId,
        'name' => $name,
        'login_code_set' => ($login !== ''),
      ]);

      Response::redirect('/admin/admin_dashboard.php?ok_create=1#create');
    } catch (Throwable $e) {
      Response::redirect('/admin/admin_dashboard.php?err=create_companion#create');
    }
  }



    /* ================================================
     * ADMIN-VERWALTUNG
     * ================================================ */

  /**
   * Ändert das Passwort des eingeloggten Admins.
   */
  public static function changeAdminPassword(): void
  {
    AdminContext::requireAdmin();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      Response::redirect('/admin/admin_dashboard.php');
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf');
    }

    $adminId = AdminContext::adminId();
    $current = trim(Request::postString('current_password'));
    $new1 = trim(Request::postString('new_password'));
    $new2 = trim(Request::postString('new_password2'));

    // Validierung und Passwortänderung an AdminPasswordService delegieren
    $result = AdminPasswordService::changePassword($adminId, $current, $new1, $new2);

    if ($result['success']) {
      AdminAuditLogRepository::append('admin_change_password', [
        'admin_id' => $adminId,
      ]);
      Response::redirect('/admin/admin_dashboard.php?ok=admin_pw_changed');
    } else {
      $error = $result['error'] ?? 'unknown';
      Response::redirect('/admin/admin_dashboard.php?err=' . urlencode($error));
    }
  }

  /**
   * Löscht einen Teilnehmer (Hauptgast oder Begleiter).
   */
  public static function deleteParticipant(): void
  {
    AdminContext::requireAdmin();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      Response::redirect('/admin/admin_dashboard.php');
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#edit');
    }

    $participantId = trim(Request::postString('participant_id'));

    $result = ParticipantAdminService::deleteParticipant($participantId);

    if ($result['success']) {
      AdminAuditLogRepository::append('delete_participant', [
        'participant_id' => $participantId,
      ]);
      Response::redirect('/admin/admin_dashboard.php?ok=delete_participant#edit');
    } else {
      $error = $result['error'] ?? 'unknown';
      Response::redirect('/admin/admin_dashboard.php?err=' . urlencode($error) . '#edit');
    }
  }

  /**
   * Ändert den Namen eines Teilnehmers.
   */
  public static function editParticipantName(): void
  {
    AdminContext::requireAdmin();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      Response::redirect('/admin/admin_dashboard.php');
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#edit');
    }

    $participantId = trim(Request::postString('participant_id'));
    $newName = trim(Request::postString('new_name'));

    $result = ParticipantAdminService::editParticipantName($participantId, $newName);

    if ($result['success']) {
      AdminAuditLogRepository::append('edit_participant_name', [
        'participant_id' => $participantId,
        'new_name' => $newName,
      ]);
      Response::redirect('/admin/admin_dashboard.php?ok=edit_participant_name#edit');
    } else {
      $error = $result['error'] ?? 'unknown';
      Response::redirect('/admin/admin_dashboard.php?err=' . urlencode($error) . '#edit');
    }
  }

  /**
   * Setzt das Passwort eines Teilnehmers neu.
   */
  public static function changeParticipantPassword(): void
  {
    AdminContext::requireAdmin();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      Response::redirect('/admin/admin_dashboard.php');
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#edit');
    }

    $participantId = trim(Request::postString('participant_id'));
    $newPassword = trim(Request::postString('new_password'));

    $result = ParticipantAdminService::changeParticipantPassword($participantId, $newPassword);

    if ($result['success']) {
      AdminAuditLogRepository::append('change_participant_password', [
        'participant_id' => $participantId,
      ]);
      Response::redirect('/admin/admin_dashboard.php?ok=change_participant_password#edit');
    } else {
      $error = $result['error'] ?? 'unknown';
      Response::redirect('/admin/admin_dashboard.php?err=' . urlencode($error) . '#edit');
    }
  }

  /**
   * Erstellt einen neuen Staff-Account (Food Helper oder Door).
   */
  public static function createStaff(): void
  {
    AdminContext::requireAdmin();

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#staff');
    }

    $name = trim(Request::postString('name'));
    $loginCode = trim(Request::postString('login_code'));
    $role = trim(Request::postString('role'));

    if ($name === '' || $loginCode === '' || !in_array($role, ['FOOD_HELPER', 'DOOR'], true)) {
      Response::redirect('/admin/admin_dashboard.php?err=staff_missing_data#staff');
    }

    try {
      ParticipantsRepository::createStaffMember($name, $loginCode, $role);

      AdminAuditLogRepository::append('create_staff', [
        'name' => $name,
        'role' => $role,
      ]);

      Response::redirect('/admin/admin_dashboard.php?ok=staff_created#staff');
    } catch (Throwable $e) {
      Response::redirect('/admin/admin_dashboard.php?err=staff_create_failed#staff');
    }
  }

  /**
   * Löscht einen Staff-Account.
   */
  public static function deleteStaff(): void
  {
    AdminContext::requireAdmin();

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#staff');
    }

    $id = trim(Request::postString('id'));

    if ($id === '') {
      Response::redirect('/admin/admin_dashboard.php?err=staff_id_missing#staff');
    }

    // Verifizieren, dass dies tatsächlich ein Staff-Mitglied ist (FOOD_HELPER oder DOOR)
    $participant = ParticipantsRepository::findById($id);
    if (!$participant || !in_array($participant['role'] ?? '', ['FOOD_HELPER', 'DOOR'], true)) {
      Response::redirect('/admin/admin_dashboard.php?err=staff_not_found#staff');
    }

    try {
      $name = $participant['name'] ?? '';
      $role = $participant['role'] ?? '';

      ParticipantsRepository::deleteParticipantById($id);

      AdminAuditLogRepository::append('delete_staff', [
        'id' => $id,
        'name' => $name,
        'role' => $role,
      ]);

      Response::redirect('/admin/admin_dashboard.php?ok=staff_deleted#staff');
    } catch (Throwable $e) {
      Response::redirect('/admin/admin_dashboard.php?err=staff_delete_failed#staff');
    }
  }

  /**
   * Setzt alle Tickets ohne vollständige Bezahlung auf 20€ (Preisüberschreibung).
   * Teilzahlungen werden gemeldet, damit der Admin sie manuell handhabt.
   */
  public static function bulkPriceOverride(): void
  {
    AdminContext::requireAdmin();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      Response::redirect('/admin/admin_dashboard.php');
    }

    if (!Csrf::validate(Request::postString('_csrf'))) {
      Response::redirect('/admin/admin_dashboard.php?err=csrf#ovr');
    }

    $newPrice = 20;
    $defaultPrice = PricingService::DEFAULT_TICKET_PRICE; // 17

    // Erst ab 15.02.2026 erlaubt
    if (date('Y-m-d') < '2026-02-15') {
      Response::redirect('/admin/admin_dashboard.php?err=bulk_too_early#ovr');
    }

    $allParticipants = ParticipantsRepository::all();
    $overrides = PricingOverridesRepository::mapById();

    // Group by main_id to determine payment status per group
    $groups = ParticipantsRepository::groupAllByMainIdRobust();

    // Track which main_ids have fully paid
    $fullyPaidMainIds = [];
    foreach ($groups as $mainId => $members) {
      $dueArr    = PricingService::amountDueForMainId($mainId);
      $amountDue = (int)($dueArr['amount_due'] ?? 0);
      $amountPaid = (int)ParticipantsRepository::amountPaidForMainId($mainId);

      if ($amountPaid >= $amountDue && $amountDue > 0) {
        $fullyPaidMainIds[$mainId] = true;
      }
    }

    $updated = 0;
    $skippedPaid = 0;
    $skippedOverride = 0;
    $partialPayments = []; // IDs with partial payments that need manual review

    foreach ($allParticipants as $p) {
      $id = trim((string)($p['id'] ?? ''));
      if ($id === '') continue;

      // Skip non-user roles (admins, food helpers, door staff)
      $role = strtoupper(trim((string)($p['role'] ?? 'USER')));
      if ($role !== 'USER') continue;

      // Resolve main_id for this participant
      $mainId = ParticipantsRepository::resolveMainIdFromRow($p);

      // Skip if the group has fully paid at the current price
      if (isset($fullyPaidMainIds[$mainId])) {
        $skippedPaid++;
        continue;
      }

      // Skip if already has a custom override (e.g. 0€ for teachers, or already 20€)
      if (isset($overrides[$id])) {
        $existingPrice = (int)$overrides[$id]['ticket_price'];
        if ($existingPrice !== $defaultPrice) {
          // Custom override (e.g. 0€ for exemption) — don't touch
          $skippedOverride++;
          continue;
        }
        // If override is already at default price (17), we'll update it to 20
      }

      // Check for partial payment in the group
      if (!empty($p['is_main_bool'])) {
        $amountPaid = (int)ParticipantsRepository::amountPaidForMainId($mainId);
        if ($amountPaid > 0) {
          // Partial payment — flag for manual review
          $dueArr = PricingService::amountDueForMainId($mainId);
          $amountDue = (int)($dueArr['amount_due'] ?? 0);
          $name = trim((string)($p['name'] ?? ''));
          $partialPayments[] = [
            'main_id' => $mainId,
            'name' => $name,
            'paid' => $amountPaid,
            'due' => $amountDue,
          ];
        }
      }

      // Set override to new price
      PricingOverridesRepository::upsertOverrideForId($id, $newPrice, 'Preiserhöhung ab 15.02.');
      $updated++;
    }

    AdminAuditLogRepository::append('bulk_price_override', [
      'new_price' => $newPrice,
      'updated_count' => $updated,
      'skipped_paid' => $skippedPaid,
      'skipped_override' => $skippedOverride,
      'partial_payments' => count($partialPayments),
    ]);

    // Store results in session for display
    $_SESSION['bulk_override_result'] = [
      'updated' => $updated,
      'skipped_paid' => $skippedPaid,
      'skipped_override' => $skippedOverride,
      'partial_payments' => $partialPayments,
    ];

    Response::redirect('/admin/admin_dashboard.php?ok=bulk_override#ovr');
  }

    /* ================================================
     * ADMIN-DASHBOARD
     * ================================================ */

  /**
   * Zeigt das Admin-Dashboard mit Statistiken, Teilnehmerliste und Verwaltungsfunktionen.
   */
  public static function dashboard(): void
  {
    AdminContext::requireAdmin();

    // Statistiken für die Charts berechnen
    $allForStats = ParticipantsRepository::all();

    $kindCounts = [];
    $classTotals = [];

    foreach ($allForStats as $p) {
      $id = trim((string)($p['id'] ?? ''));
      if ($id === '') continue;

      $kind = 'UNKNOWN';
      if (preg_match('/^ADMIN/i', $id)) $kind = 'ADMIN';
      elseif (preg_match('/B\d+$/i', $id)) $kind = 'COMPANION';
      elseif (preg_match('/L$/i', $id)) $kind = 'TEACHER';
      elseif (preg_match('/^E.*S$/i', $id)) $kind = 'ALUMNI';
      elseif (preg_match('/S$/i', $id)) $kind = 'MAIN';

      $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;

      // Klassen-Labels:
      // - SGGS/SSGS + ERSTE Zahl => separat (SGGS1, SSGS2)
      // - TEACHER => LEHRER
      // - sonst: Prefix ohne Zahlen
      $classLabel = '';

      if ($kind === 'TEACHER') {
        $classLabel = 'LEHRER';
      } elseif (preg_match('/^(SGGS|SSGS)(\d)/i', $id, $mm)) {
        // Nur erste Zahl zählt: SGGS104S -> SGGS1
        $classLabel = strtoupper($mm[1]) . $mm[2];
      } else {
        $classId = $id;
        $classId = preg_replace('/B\d+$/i', '', $classId);
        $classId = preg_replace('/S$/i', '', $classId);
        $classId = preg_replace('/L$/i', '', $classId);
        $classId = preg_replace('/\d+/', '', $classId);
        $classLabel = trim((string)$classId);
      }

      if ($classLabel !== '' && $kind !== 'ADMIN') {
        $classTotals[$classLabel] = ($classTotals[$classLabel] ?? 0) + 1;
      }
    }

    ksort($kindCounts);
    arsort($classTotals);
    $classTotals = array_slice($classTotals, 0, 12, true);

    // Suche und Gruppierung der Teilnehmer
    $q   = Request::getString('q');
    $all = ParticipantsRepository::all();

    $filtered = $all;
    if ($q !== '') {
      $qLower = mb_strtolower($q, 'UTF-8');
      // Erster Durchlauf: Alle passenden Teilnehmer finden
      $matchingMainIds = [];
      foreach ($all as $p) {
        $id   = mb_strtolower((string)($p['id'] ?? ''), 'UTF-8');
        $name = mb_strtolower((string)($p['name'] ?? ''), 'UTF-8');
        $main = mb_strtolower((string)($p['main_id'] ?? ''), 'UTF-8');
        if (str_contains($id, $qLower) || str_contains($name, $qLower) || str_contains($main, $qLower)) {
          $mainId = ParticipantsRepository::resolveMainIdFromRow($p);
          if ($mainId !== '') {
            $matchingMainIds[$mainId] = true;
          }
        }
      }
      // Zweiter Durchlauf: Alle Teilnehmer der passenden main_ids einschließen
      $filtered = array_values(array_filter($all, static function ($p) use ($matchingMainIds) {
        $mainId = ParticipantsRepository::resolveMainIdFromRow($p);
        return isset($matchingMainIds[$mainId]);
      }));
    }

    $groups = [];
    foreach ($filtered as $p) {
      $mainId = ParticipantsRepository::resolveMainIdFromRow($p);
      if ($mainId === '') continue;
      $groups[$mainId][] = $p;
    }
    ksort($groups);

    // Totals
    $totalDue = 0;
    $totalPaid = 0;
    foreach (array_keys($groups) as $mid) {
      $dueArr = PricingService::amountDueForMainId($mid);
      $totalDue += (int)($dueArr['amount_due'] ?? 0);
      $totalPaid += (int)ParticipantsRepository::amountPaidForMainId($mid);
    }
    $totalOpen = max(0, $totalDue - $totalPaid);

    // Food-Statistiken frühzeitig für die Übersicht laden
    $foodStatsOverview = FoodOrderRepository::getStatistics();
    $totalPaidWithFood = $totalPaid + (float)$foodStatsOverview['total_paid'] + (float)$foodStatsOverview['total_redeemed'];
    $totalOpenWithFood = $totalOpen + (float)$foodStatsOverview['total_open'];

    $overrides = PricingOverridesRepository::mapById();
    $audit = AdminAuditLogRepository::latest(200);

    // UI-Flags
    $ok  = Request::getString('ok');
    $err = Request::getString('err');
    $okOverride = Request::getString('ok_override');
    $okCreate = Request::getString('ok_create');

    // Bulk-Override-Ergebnis aus der Session
    $bulkResult = $_SESSION['bulk_override_result'] ?? null;
    unset($_SESSION['bulk_override_result']);

    Layout::header('Admin – Dashboard', '', '', true);
  ?>
    <main class="bg-starfield admin-dashboard">
      <!-- Star layers -->
      <div class="stars-layer-1"></div>
      <div class="stars-layer-2"></div>
      <div class="stars-layer-3"></div>

      <div class="container py-4">

        <div class="admin-head d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <h1 class="h-serif mb-1" style="font-size: 1.6rem; font-weight: 300;">Admin Dashboard</h1>
            <div class="text-muted" style="font-size:.95rem;">Eingeloggt als <?= e(AdminContext::adminName()) ?></div>
          </div>

          <div class="d-flex gap-2 flex-wrap">

            <a class="btn btn-outline-danger btn-sm" href="/admin/admin_logout.php">Logout</a>
          </div>
        </div>

        <?php if ($ok !== ''): ?>
          <div class="alert alert-success">
            <?php
            echo match ($ok) {
              'food_paid' => 'Essensbestellung als bezahlt markiert.',
              'staff_created' => 'Staff-Account angelegt.',
              'staff_deleted' => 'Staff-Account gelöscht.',
              'bulk_override' => 'Preiserhöhung durchgeführt.',
              default => 'Gespeichert.'
            };
            ?>
          </div>

          <?php if ($ok === 'bulk_override' && $bulkResult !== null): ?>
            <div class="alert alert-info">
              <strong>Ergebnis:</strong>
              <?= (int)($bulkResult['updated'] ?? 0) ?> Tickets auf 20&nbsp;€ gesetzt,
              <?= (int)($bulkResult['skipped_paid'] ?? 0) ?> bereits bezahlt (übersprungen),
              <?= (int)($bulkResult['skipped_override'] ?? 0) ?> mit Sonderpreis (übersprungen).
            </div>

            <?php $partials = $bulkResult['partial_payments'] ?? []; ?>
            <?php if (!empty($partials)): ?>
              <div class="alert alert-warning">
                <strong>⚠ Teilzahlungen – manuelle Prüfung erforderlich:</strong>
                <div class="table-responsive mt-2">
                  <table class="table table-sm table-striped mb-0">
                    <thead>
                      <tr>
                        <th>main_id</th>
                        <th>Name</th>
                        <th>Bezahlt</th>
                        <th>Soll (alt)</th>
                        <th>Differenz</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($partials as $pp): ?>
                        <tr>
                          <td><code><?= e($pp['main_id']) ?></code></td>
                          <td><?= e($pp['name']) ?></td>
                          <td><?= (int)$pp['paid'] ?> €</td>
                          <td><?= (int)$pp['due'] ?> €</td>
                          <td class="text-danger fw-semibold"><?= (int)$pp['paid'] - (int)$pp['due'] ?> €</td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="text-muted small mt-2">Diese Personen haben teilweise bezahlt. Die Tickets wurden trotzdem auf 20&nbsp;€ gesetzt. Bitte den offenen Differenzbetrag manuell klären.</div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($okOverride !== ''): ?>
          <div class="alert alert-success">Override gespeichert.</div>
        <?php endif; ?>

        <?php if ($okCreate !== ''): ?>
          <div class="alert alert-success">Teilnehmer angelegt.</div>
        <?php endif; ?>

        <?php if ($err !== ''): ?>
          <div class="alert alert-danger">
            <?php
            echo match ($err) {
              'csrf' => 'Ungültige Anfrage (CSRF).',
              'main' => 'main_id fehlt.',
              'paid' => 'Ungültiger Betrag (nur ganze Zahl).',
              'save' => 'Speichern fehlgeschlagen.',
              'override_id' => 'Override-ID ist ungültig.',
              'override_price' => 'Override-Preis ist ungültig (nur ganze Zahl).',
              'override_save' => 'Override speichern fehlgeschlagen.',
              'override_delete' => 'Override löschen fehlgeschlagen.',
              'create_main' => 'Hauptgast anlegen fehlgeschlagen (ID ungültig oder existiert).',
              'create_companion' => 'Begleiter anlegen fehlgeschlagen (main_id nicht gefunden/ungültig).',
              'food_missing_data' => 'Bestellnummer oder Betrag fehlt.',
              'food_order_not_found' => 'Essensbestellung nicht gefunden.',
              'food_invalid_status' => 'Bestellung kann nicht mehr geändert werden (Status ungültig).',
              'food_update_failed' => 'Essensbestellung aktualisieren fehlgeschlagen.',
              'staff_missing_data' => 'Name und Login-Code sind erforderlich.',
              'staff_create_failed' => 'Staff-Account anlegen fehlgeschlagen.',
              'staff_id_missing' => 'Staff-ID fehlt.',
              'staff_not_found' => 'Staff-Account nicht gefunden.',
              'staff_delete_failed' => 'Staff-Account löschen fehlgeschlagen.',
              'bulk_too_early' => 'Priserhöhung erst ab dem 15.02. möglich.',
              default => 'Fehler.'
            };
            ?>
          </div>
        <?php endif; ?>

        <!-- Immer sichtbar: Übersicht + Diagramme -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-lg-5">
            <div class="card admin-card h-100">
              <div class="card-body p-4">
                <div class="text-muted small admin-kicker">Übersicht</div>

                <div class="d-flex justify-content-between align-items-center mt-2">
                  <div class="text-muted">Gesamt Soll</div>
                  <div class="fw-semibold"><?= (int)$totalDue ?> €</div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2">
                  <div class="text-muted">Gesamt Bezahlt</div>
                  <div class="fw-semibold"><?= (int)$totalPaid ?> €</div>
                </div>

                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center">
                  <div class="text-muted">Gesamt Offen</div>
                  <?php if ($totalOpen === 0): ?>
                    <span class="badge text-bg-success">0 €</span>
                  <?php else: ?>
                    <span class="badge text-bg-danger"><?= (int)$totalOpen ?> €</span>
                  <?php endif; ?>
                </div>

                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center mt-2">
                  <div class="text-muted">Gesamt Bezahlt (mit Essen)</div>
                  <div class="fw-semibold"><?= number_format((float)$totalPaidWithFood, 2, ',', '.') ?> €</div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2">
                  <div class="text-muted">Gesamt Offen (mit Essen)</div>
                  <?php if ($totalOpenWithFood < 0.01): ?>
                    <span class="badge text-bg-success">0,00 €</span>
                  <?php else: ?>
                    <span class="badge text-bg-danger"><?= number_format((float)$totalOpenWithFood, 2, ',', '.') ?> €</span>
                  <?php endif; ?>
                </div>

                <div class="mt-3">
                  <label class="form-label mb-1">Bereich</label>
                  <select id="adminSectionSelect" class="form-select">
                    <option value="create">Teilnehmer hinzufügen</option>
                    <option value="ovr">Pricing Overrides</option>
                    <option value="edit" selected>Teilnehmer & Paid bearbeiten</option>
                    <option value="seating">Sitzgruppen & Notizen</option>
                    <option value="food">Essensbestellungen</option>
                    <option value="staff">Staff Zugangsdaten</option>
                    <option value="logs">Änderungsprotokoll</option>
                  </select>
                  <div class="text-muted small mt-2">
                    Auswertung bleibt immer sichtbar.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-7">
            <div class="card admin-card h-100">
              <div class="card-body p-4">
                <div class="text-muted small admin-kicker">Statistiken</div>

                <div class="row g-3 mt-1">
                  <div class="col-12 col-md-6">
                    <div class="admin-panel p-3">
                      <div class="text-muted" style="font-size:.9rem;">Teilnehmerarten</div>
                      <canvas id="chartKinds" height="150"></canvas>
                    </div>
                  </div>
                  <div class="col-12 col-md-6">
                    <div class="admin-panel p-3">
                      <div class="text-muted" style="font-size:.9rem;">Top Klassen (SGGS1/SSGS2 separat, Lehrer = LEHRER)</div>
                      <canvas id="chartClasses" height="150"></canvas>
                    </div>
                  </div>
                </div>

                <script>
                  (function() {
                    if (!window.Chart) return;

                    const kindLabels = <?= json_encode(array_keys($kindCounts), JSON_UNESCAPED_UNICODE) ?>;
                    const kindData = <?= json_encode(array_values($kindCounts), JSON_UNESCAPED_UNICODE) ?>;

                    const classLabels = <?= json_encode(array_keys($classTotals), JSON_UNESCAPED_UNICODE) ?>;
                    const classData = <?= json_encode(array_values($classTotals), JSON_UNESCAPED_UNICODE) ?>;

                    const c1 = document.getElementById('chartKinds');
                    const c2 = document.getElementById('chartClasses');

                    if (c1) {
                      new Chart(c1, {
                        type: 'doughnut',
                        data: {
                          labels: kindLabels,
                          datasets: [{
                            data: kindData
                          }]
                        },
                        options: {
                          responsive: true,
                          plugins: {
                            legend: {
                              position: 'bottom'
                            }
                          }
                        }
                      });
                    }

                    if (c2) {
                      new Chart(c2, {
                        type: 'bar',
                        data: {
                          labels: classLabels,
                          datasets: [{
                            data: classData
                          }]
                        },
                        options: {
                          responsive: true,
                          plugins: {
                            legend: {
                              display: false
                            }
                          },
                          scales: {
                            y: {
                              beginAtZero: true,
                              ticks: {
                                precision: 0
                              }
                            }
                          }
                        }
                      });
                    }
                  })();
                </script>
              </div>
            </div>
          </div>
        </div>

        <!-- Sections -->
        <section class="admin-section" data-section="create" id="create">
          <div class="card admin-card mb-3">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                  <div class="text-muted small admin-kicker">Teilnehmer</div>
                  <div class="h6 mb-0">Teilnehmer hinzufügen</div>
                  <div class="text-muted" style="font-size:.9rem;">
                    Hauptgast endet auf <code>S</code> <b>oder</b> <code>L</code> (Lehrer). Begleiter-ID wird automatisch (<code>B1/B2/…</code>) vergeben.
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-12 col-lg-6">
                  <div class="admin-panel p-3">
                    <div class="fw-semibold mb-2">Neuer Hauptgast</div>
                    <form method="post" action="/admin/admin_create_main_guest.php" class="row g-2">
                      <?= Csrf::inputField() ?>
                      <div class="col-12">
                        <input class="form-control" name="id" placeholder="ID z.B. WGW00S oder LEHRER01L" required>
                      </div>
                      <div class="col-12">
                        <input class="form-control" name="name" placeholder="Name" required>
                      </div>
                      <div class="col-12">
                        <input class="form-control" name="login_code" placeholder="Login-Code" required>
                      </div>
                      <div class="col-12">
                        <button class="btn btn-save" type="submit">Hauptgast anlegen</button>
                      </div>
                    </form>
                  </div>
                </div>

                <div class="col-12 col-lg-6">
                  <div class="admin-panel p-3">
                    <div class="fw-semibold mb-2">Neue Begleitperson</div>
                    <form method="post" action="/admin/admin_create_companion.php" class="row g-2">
                      <?= Csrf::inputField() ?>
                      <div class="col-12">
                        <input class="form-control" name="main_id" placeholder="main_id z.B. WGW00S" required>
                      </div>
                      <div class="col-12">
                        <input class="form-control" name="name" placeholder="Name" required>
                      </div>
                      <div class="col-12">
                        <input class="form-control" name="login_code" placeholder="Login-Code (leer = vom Hauptgast übernehmen)">
                      </div>
                      <div class="col-12">
                        <button class="btn btn-save" type="submit">Begleiter anlegen</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </section>

        <section class="admin-section" data-section="ovr" id="ovr">
          <div class="card admin-card mb-3">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                  <div class="text-muted small admin-kicker">Preise</div>
                  <div class="h6 mb-0">Pricing Overrides</div>
                  <div class="text-muted" style="font-size:.9rem;">Ticketpreis pro ID überschreiben (z.B. Lehrer = 0€).</div>
                </div>
                <?php $bulkAllowed = (date('Y-m-d') >= '2026-02-15'); ?>
                <form method="post" action="/admin/admin_bulk_override.php" onsubmit="return confirm('Alle nicht-bezahlten Tickets auf 20€ setzen?\n\nBereits bezahlte Tickets und Sonderpreise (z.B. 0€) werden nicht verändert.\n\nTeilzahlungen werden gemeldet.');" class="d-flex align-items-center gap-2">
                  <?= Csrf::inputField() ?>
                  <button class="btn btn-sm btn-warning" type="submit" <?= $bulkAllowed ? '' : 'disabled' ?>>Alle unbezahlten auf 20 € setzen</button>
                  <?php if (!$bulkAllowed): ?>
                    <span class="text-muted small">(Erst ab 15.02. verfügbar)</span>
                  <?php endif; ?>
                </form>
              </div>

              <div class="table-responsive mt-3">
                <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                  <thead>
                    <tr>
                      <th style="width:160px;">ID</th>
                      <th style="width:140px;">Ticketpreis (€)</th>
                      <th>Reason</th>
                      <th style="width:220px;">Aktion</th>
                    </tr>
                  </thead>
                  <tbody>

                    <tr>
                      <form method="post" action="/admin/admin_override_save.php">
                        <?= Csrf::inputField() ?>
                        <td><input class="form-control form-control-sm" name="override_id" placeholder="z.B. ABI00S"></td>
                        <td><input class="form-control form-control-sm" name="ticket_price" inputmode="numeric" pattern="\d+" placeholder="z.B. 0"></td>
                        <td><input class="form-control form-control-sm" name="reason" placeholder="z.B. Lehrer"></td>
                        <td><button class="btn btn-sm btn-save" type="submit">Hinzufügen</button></td>
                      </form>
                    </tr>

                    <?php foreach ($overrides as $id => $ov): ?>
                      <tr>
                        <form method="post" action="/admin/admin_override_save.php">
                          <?= Csrf::inputField() ?>
                          <td>
                            <input type="hidden" name="override_id" value="<?= e($id) ?>">
                            <span class="text-nowrap"><?= e($id) ?></span>
                          </td>
                          <td>
                            <input class="form-control form-control-sm" name="ticket_price" inputmode="numeric" pattern="\d+"
                              value="<?= e((string)((int)($ov['ticket_price'] ?? 0))) ?>">
                          </td>
                          <td>
                            <input class="form-control form-control-sm" name="reason" value="<?= e((string)($ov['reason'] ?? '')) ?>">
                          </td>
                          <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-save" type="submit">Speichern</button>
                        </form>

                        <form method="post" action="/admin/admin_override_delete.php" onsubmit="return confirm('Override wirklich löschen?');">
                          <?= Csrf::inputField() ?>
                          <input type="hidden" name="override_id" value="<?= e($id) ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                        </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>

                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </section>

        <section class="admin-section" data-section="edit" id="edit">
          <form class="card admin-card mb-3" method="get" action="/admin/admin_dashboard.php">
            <div class="card-body p-4 d-flex gap-2 flex-wrap">
              <input class="form-control" name="q" placeholder="Suche nach Name, ID, main_id ..." value="<?= e($q) ?>">
              <button class="btn btn-primary" type="submit">Suchen</button>
              <a class="btn btn-outline-secondary" href="/admin/admin_dashboard.php#edit">Zurücksetzen</a>
            </div>
          </form>

          <?php if (count($groups) === 0): ?>
            <div class="alert alert-warning">Keine Treffer.</div>
          <?php else: ?>
            <?php foreach ($groups as $mainId => $members): ?>
              <?php
              $main = null;
              $companions = [];
              foreach ($members as $m) {
                if (!empty($m['is_main_bool'])) $main = $m;
                else $companions[] = $m;
              }

              $ticketCount = 0;
              if (!empty($main)) $ticketCount++;
              $ticketCount += count($companions);

              $dueArr     = PricingService::amountDueForMainId($mainId);
              $amountDue  = (int)($dueArr['amount_due'] ?? 0);
              $amountPaid = (int)ParticipantsRepository::amountPaidForMainId($mainId);
              $open       = max(0, $amountDue - $amountPaid);
              ?>
              <div class="card admin-card mb-3">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                      <div class="text-muted small">main_id</div>
                      <div class="fw-semibold"><?= e($mainId) ?></div>
                    </div>
                    <div>
                      <div class="text-muted small">Hauptgast</div>
                      <div class="fw-semibold">
                        <?= e((string)($main['name'] ?? '—')) ?> <span class="text-muted">·</span> <?= e((string)($main['id'] ?? '—')) ?>
                      </div>
                    </div>
                    <div class="text-muted small">Begleiter: <?= count($companions) ?></div>
                  </div>

                  <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                      <thead>
                        <tr>
                          <th>Hauptgast Name</th>
                          <th>Login Code</th>
                          <th>Tickets</th>
                          <th>Soll</th>
                          <th>Bezahlt</th>
                          <th>Offen</th>
                          <th style="width: 280px;">paid ändern</th>
                          <th style="width: 250px;">Aktionen</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>
                            <form method="post" action="/admin/admin_edit_participant_name.php" class="d-flex gap-2">
                              <?= Csrf::inputField() ?>
                              <input type="hidden" name="participant_id" value="<?= e((string)($main['id'] ?? '')) ?>">
                              <input type="text" class="form-control form-control-sm" name="new_name" value="<?= e((string)($main['name'] ?? '')) ?>" required style="max-width: 200px;">
                              <button class="btn btn-sm btn-save" type="submit">✓</button>
                            </form>
                          </td>
                          <td>
                            <div class="password-cell" id="password-cell-<?= e($main['id']) ?>">
                              <div class="password-display">
                                <?php
                                $loginCode = (string)($main['login_code'] ?? '');
                                $isHashed = str_starts_with($loginCode, '$2y$') || str_starts_with($loginCode, '$argon2');
                                ?>
                                <?php if ($isHashed): ?>
                                  <span class="text-muted" style="font-size: 0.85rem;" title="Passwort wurde bereits vom Benutzer geändert">[gehasht]</span>
                                <?php else: ?>
                                  <code class="text-nowrap" style="font-size: 0.9rem;"><?= e($loginCode) ?></code>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-link p-0 ms-2 edit-password-btn" type="button" data-participant-id="<?= e($main['id']) ?>" title="Passwort ändern">
                                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 13.5V14h.5l7.707-7.707-1-1L2 13.5z" />
                                    <path d="M10.293 2.293a1 1 0 0 1 1.414 0l2 2a1 1 0 0 1 0 1.414l-8 8" />
                                  </svg>
                                </button>
                              </div>
                              <form method="post" action="/admin/admin_change_participant_password.php" class="password-edit-form d-none d-flex gap-2 mt-2" id="password-form-<?= e($main['id']) ?>">
                                <?= Csrf::inputField() ?>
                                <input type="hidden" name="participant_id" value="<?= e((string)($main['id'] ?? '')) ?>">
                                <input type="text" class="form-control form-control-sm" name="new_password" placeholder="Neues Passwort" required style="max-width: 200px;">
                                <button class="btn btn-sm btn-save" type="submit">✓</button>
                                <button class="btn btn-sm btn-outline-secondary cancel-password-btn" type="button" data-participant-id="<?= e($main['id']) ?>">✕</button>
                              </form>
                            </div>
                          </td>
                          <td><?= (int)$ticketCount ?></td>
                          <td><?= (int)$amountDue ?> €</td>
                          <td><?= (int)$amountPaid ?> €</td>
                          <td>
                            <?php if ($open === 0): ?>
                              <span class="badge text-bg-success">OK</span>
                            <?php else: ?>
                              <span class="badge text-bg-danger"><?= (int)$open ?> € offen</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                              <form class="d-flex gap-2" method="post" action="/admin/admin_update_paid.php">
                                <?= Csrf::inputField() ?>
                                <input type="hidden" name="main_id" value="<?= e($mainId) ?>">
                                <input class="form-control form-control-sm" name="amount_paid" inputmode="numeric" pattern="\d+"
                                  value="<?= e((string)$amountPaid) ?>" style="width: 80px;">
                                <button class="btn btn-sm btn-save" type="submit">Speichern</button>
                              </form>
                              <?php if ($open > 0): ?>
                                <form method="post" action="/admin/admin_update_paid.php" class="d-inline">
                                  <?= Csrf::inputField() ?>
                                  <input type="hidden" name="main_id" value="<?= e($mainId) ?>">
                                  <input type="hidden" name="amount_paid" value="<?= e((string)$amountDue) ?>">
                                  <button class="btn btn-sm btn-outline-success" type="submit" title="Soll-Betrag als bezahlt markieren">Soll bezahlen</button>
                                </form>
                              <?php endif; ?>
                            </div>
                          </td>
                          <td>
                            <form method="post" action="/admin/admin_delete_participant.php" style="display:inline;" onsubmit="return confirm('Wirklich löschen? (inkl. alle Begleiter)');">
                              <?= Csrf::inputField() ?>
                              <input type="hidden" name="participant_id" value="<?= e((string)($main['id'] ?? '')) ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                            </form>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <?php if (count($companions) > 0): ?>
                    <div class="table-responsive mt-3">
                      <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                        <thead>
                          <tr>
                            <th>Begleiter Name</th>
                            <th>Login Code</th>
                            <th class="text-nowrap">Begleiter ID</th>
                            <th style="width: 250px;">Aktionen</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($companions as $c): ?>
                            <tr>
                              <td>
                                <div class="companion-name-cell" id="name-cell-<?= e($c['id']) ?>">
                                  <span class="companion-name-display"><?= e((string)($c['name'] ?? '')) ?></span>
                                  <button class="btn btn-sm btn-link p-0 ms-2 edit-companion-btn" type="button" data-companion-id="<?= e($c['id']) ?>" title="Bearbeiten">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                      <path d="M2 13.5V14h.5l7.707-7.707-1-1L2 13.5z" />
                                      <path d="M10.293 2.293a1 1 0 0 1 1.414 0l2 2a1 1 0 0 1 0 1.414l-8 8" />
                                    </svg>
                                  </button>
                                  <form method="post" action="/admin/admin_edit_participant_name.php" class="companion-edit-form d-none d-flex gap-2 mt-2" id="edit-form-<?= e($c['id']) ?>">
                                    <?= Csrf::inputField() ?>
                                    <input type="hidden" name="participant_id" value="<?= e((string)($c['id'])) ?>">
                                    <input type="text" class="form-control form-control-sm" name="new_name" placeholder="<?= e((string)($c['name'] ?? '')) ?>" required style="max-width: 200px;">
                                    <button class="btn btn-sm btn-save" type="submit">✓</button>
                                    <button class="btn btn-sm btn-outline-secondary cancel-edit-btn" type="button" data-companion-id="<?= e($c['id']) ?>">✕</button>
                                  </form>
                                </div>
                              </td>
                              <td>
                                <div class="password-cell" id="password-cell-<?= e($c['id']) ?>">
                                  <div class="password-display">
                                    <?php
                                    $companionLoginCode = (string)($c['login_code'] ?? '');
                                    $isCompanionHashed = str_starts_with($companionLoginCode, '$2y$') || str_starts_with($companionLoginCode, '$argon2');
                                    ?>
                                    <?php if ($isCompanionHashed): ?>
                                      <span class="text-muted" style="font-size: 0.85rem;" title="Passwort wurde bereits vom Benutzer geändert">[gehasht]</span>
                                    <?php else: ?>
                                      <code class="text-nowrap" style="font-size: 0.9rem;"><?= e($companionLoginCode) ?></code>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-link p-0 ms-2 edit-password-btn" type="button" data-participant-id="<?= e($c['id']) ?>" title="Passwort ändern">
                                      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M2 13.5V14h.5l7.707-7.707-1-1L2 13.5z" />
                                        <path d="M10.293 2.293a1 1 0 0 1 1.414 0l2 2a1 1 0 0 1 0 1.414l-8 8" />
                                      </svg>
                                    </button>
                                  </div>
                                  <form method="post" action="/admin/admin_change_participant_password.php" class="password-edit-form d-none d-flex gap-2 mt-2" id="password-form-<?= e($c['id']) ?>">
                                    <?= Csrf::inputField() ?>
                                    <input type="hidden" name="participant_id" value="<?= e((string)($c['id'])) ?>">
                                    <input type="text" class="form-control form-control-sm" name="new_password" placeholder="Neues Passwort" required style="max-width: 200px;">
                                    <button class="btn btn-sm btn-save" type="submit">✓</button>
                                    <button class="btn btn-sm btn-outline-secondary cancel-password-btn" type="button" data-participant-id="<?= e($c['id']) ?>">✕</button>
                                  </form>
                                </div>
                              </td>
                              <td class="text-nowrap"><?= e((string)($c['id'] ?? '')) ?></td>
                              <td>
                                <form method="post" action="/admin/admin_delete_participant.php" style="display:inline;" onsubmit="return confirm('Wirklich löschen?');">
                                  <?= Csrf::inputField() ?>
                                  <input type="hidden" name="participant_id" value="<?= e((string)($c['id'] ?? '')) ?>">
                                  <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
                                </form>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>

                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>

        <section class="admin-section" data-section="seating" id="seating">
          <div class="card admin-card mb-3">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                  <div class="text-muted small admin-kicker">Sitzordnung</div>
                  <div class="h6 mb-0">Sitzgruppen & Notizen (pro main_id)</div>
                  <div class="text-muted" style="font-size:.9rem;">
                    Anzeige der gespeicherten Sitzgruppen aus <code>/storage/seating/{main_id}.json</code> inkl. Group-Notes und Person-Notes.
                  </div>
                </div>
              </div>

              <div class="mt-3 d-flex flex-column gap-3">
                <?php foreach (array_keys($groups) as $mainId): ?>
                  <?php
                  $grp = ParticipantsRepository::getGroupByMainId($mainId);
                  $main = $grp['main'] ?? null;
                  $companions = $grp['companions'] ?? [];

                  $idToName = [];
                  if (is_array($main) && !empty($main['id'])) $idToName[(string)$main['id']] = (string)($main['name'] ?? '');
                  if (is_array($companions)) {
                    foreach ($companions as $c) {
                      if (!is_array($c) || empty($c['id'])) continue;
                      $idToName[(string)$c['id']] = (string)($c['name'] ?? '');
                    }
                  }

                  $seat = SeatingService::load($mainId);
                  $seatGroups = $seat['groups'] ?? [];
                  $groupNotes = $seat['group_notes'] ?? [];
                  $personNotes = $seat['person_notes'] ?? [];

                  if (!is_array($seatGroups)) $seatGroups = [];
                  if (!is_array($groupNotes)) $groupNotes = [];
                  if (!is_array($personNotes)) $personNotes = [];
                  ?>

                  <div class="admin-panel p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                      <div class="fw-semibold">
                        <?= e($mainId) ?>
                        <?php if (is_array($main)): ?>
                          <span class="text-muted">·</span> <?= e((string)($main['name'] ?? '')) ?>
                        <?php endif; ?>
                      </div>
                      <a class="btn btn-sm btn-outline-secondary" href="/seating/seating.php?mid=<?= e($mainId) ?>" target="_blank" rel="noopener" style="border-radius:12px;">
                        Öffnen
                      </a>
                    </div>

                    <div class="row g-3 mt-1">
                      <?php foreach ($seatGroups as $gid => $g): ?>
                        <?php
                        if (!is_array($g)) continue;
                        $gName = trim((string)($g['name'] ?? $gid));
                        if ($gName === '') $gName = $gid;
                        $members = $g['members'] ?? [];
                        if (!is_array($members)) $members = [];
                        $gNote = trim((string)($groupNotes[$gid] ?? ''));
                        ?>
                        <div class="col-12 col-lg-4">
                          <div class="admin-seatgroup p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                              <div class="fw-semibold"><?= e($gName) ?></div>
                              <span class="badge text-bg-secondary"><?= count($members) ?></span>
                            </div>

                            <?php if ($gNote !== ''): ?>
                              <div class="mt-2 text-muted small">
                                <span class="fw-semibold">Gruppen-Notiz:</span>
                                <?= e($gNote) ?>
                              </div>
                            <?php endif; ?>

                            <div class="mt-3 d-flex flex-column gap-2">
                              <?php if (count($members) === 0): ?>
                                <div class="text-muted small">Keine Mitglieder.</div>
                              <?php else: ?>
                                <?php foreach ($members as $pid): ?>
                                  <?php
                                  $pid = trim((string)$pid);
                                  if ($pid === '') continue;
                                  $pName = $idToName[$pid] ?? $pid;
                                  $pNote = trim((string)($personNotes[$pid] ?? ''));
                                  ?>
                                  <div class="admin-seatperson">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                      <div class="fw-semibold"><?= e($pName) ?></div>
                                      <span class="badge text-bg-secondary"><?= e($pid) ?></span>
                                    </div>
                                    <?php if ($pNote !== ''): ?>
                                      <div class="text-muted small mt-1">
                                        <span class="fw-semibold">Notiz:</span> <?= e($pNote) ?>
                                      </div>
                                    <?php endif; ?>
                                  </div>
                                <?php endforeach; ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                <?php endforeach; ?>
              </div>

            </div>
          </div>
        </section>

        <section class="admin-section" data-section="logs" id="logs">
          <div class="card admin-card mb-3">
            <div class="card-body p-4">
              <div class="text-muted small admin-kicker">Sicherheit</div>
              <div class="h6 mb-0">Änderungsprotokoll (letzte 200)</div>

              <div class="table-responsive mt-3">
                <table class="table table-sm table-striped table-hover align-middle mb-0 admin-table">
                  <thead>
                    <tr>
                      <th style="width: 190px;">Zeit</th>
                      <th style="width: 220px;">Admin</th>
                      <th style="width: 160px;">Aktion</th>
                      <th>Details</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($audit) === 0): ?>
                      <tr>
                        <td colspan="4" class="text-muted">Noch keine Einträge.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($audit as $row): ?>
                        <tr>
                          <td class="text-nowrap"><?= e((string)($row['ts'] ?? '')) ?></td>
                          <td class="text-nowrap">
                            <?= e((string)(($row['admin']['name'] ?? '') ?: '')) ?>
                            <?php if (!empty($row['admin']['id'])): ?>
                              <span class="text-muted">·</span> <?= e((string)$row['admin']['id']) ?>
                            <?php endif; ?>
                          </td>
                          <td class="text-nowrap"><?= e((string)($row['action'] ?? '')) ?></td>
                          <td style="white-space: normal;">
                            <code style="font-size: .82rem;"><?= e(json_encode($row['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></code>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </section>

        <!-- Food Orders Section -->
        <?php
        // Lade Essensbestellungen für die Food-Section
        $foodOrders = FoodOrderRepository::getAllOrders();
        $foodStats = FoodOrderRepository::getStatistics();
        $foodQuery = Request::getString('fq');

        // Filter anwenden
        if ($foodQuery !== '') {
          $fqLower = mb_strtolower($foodQuery, 'UTF-8');
          $foodOrders = array_values(array_filter($foodOrders, function ($order) use ($fqLower) {
            $orderId = mb_strtolower($order['order_id'] ?? '', 'UTF-8');
            $mainId = mb_strtolower($order['main_id'] ?? '', 'UTF-8');
            return str_contains($orderId, $fqLower) || str_contains($mainId, $fqLower);
          }));
        }

        // Nach Erstellungsdatum sortieren (neueste zuerst)
        usort($foodOrders, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));
        ?>
        <section class="admin-section" data-section="food" id="food">
          <!-- Statistiken oben -->
          <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
              <div class="card admin-card h-100">
                <div class="card-body p-3 text-center">
                  <div class="text-muted small">Offen</div>
                  <div class="fw-semibold fs-4"><?= (int)$foodStats['open'] ?></div>
                  <div class="text-muted small"><?= number_format($foodStats['total_open'], 2) ?> €</div>
                </div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card admin-card h-100">
                <div class="card-body p-3 text-center">
                  <div class="text-muted small">Bezahlt</div>
                  <div class="fw-semibold fs-4 text-info"><?= (int)$foodStats['paid'] ?></div>
                  <div class="text-muted small"><?= number_format($foodStats['total_paid'], 2) ?> €</div>
                </div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card admin-card h-100">
                <div class="card-body p-3 text-center">
                  <div class="text-muted small">Eingelöst</div>
                  <div class="fw-semibold fs-4 text-success"><?= (int)$foodStats['redeemed'] ?></div>
                  <div class="text-muted small"><?= number_format($foodStats['total_redeemed'], 2) ?> €</div>
                </div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card admin-card h-100">
                <div class="card-body p-3 text-center">
                  <div class="text-muted small">Storniert</div>
                  <div class="fw-semibold fs-4 text-danger"><?= (int)$foodStats['cancelled'] ?></div>
                  <div class="text-muted small"><?= number_format($foodStats['total_cancelled'], 2) ?> €</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Suchformular -->
          <form class="card admin-card mb-3" method="get" action="/admin/admin_dashboard.php#food" onsubmit="this.action='/admin/admin_dashboard.php#food';">
            <div class="card-body p-4 d-flex gap-2 flex-wrap">
              <input class="form-control" name="fq" placeholder="Suche nach Bestellnr., main_id ..." value="<?= e($foodQuery) ?>">
              <button class="btn btn-primary" type="submit">Suchen</button>
              <a class="btn btn-outline-secondary" href="/admin/admin_dashboard.php#food">Zurücksetzen</a>
            </div>
          </form>

          <?php if (count($foodOrders) === 0): ?>
            <div class="alert alert-warning">Keine Essensbestellungen gefunden.</div>
          <?php else: ?>
            <?php foreach ($foodOrders as $order): ?>
              <?php
              $orderId = $order['order_id'] ?? '';
              $orderMainId = $order['main_id'] ?? '';
              $orderItems = $order['items'] ?? [];
              $orderTotal = (float)($order['total_price'] ?? 0);
              $orderPaid = (float)($order['paid_amount'] ?? 0);
              $orderStatus = $order['status'] ?? 'open';
              $orderCreated = $order['created_at'] ?? '';
              $orderRedeemed = $order['redeemed_at'] ?? '';
              $orderRedeemedBy = $order['redeemed_by'] ?? '';

              // Participant Name finden
              $orderParticipant = null;
              foreach ($all as $p) {
                if (($p['main_id'] ?? $p['id'] ?? '') === $orderMainId || ($p['id'] ?? '') === $orderMainId) {
                  $orderParticipant = $p;
                  break;
                }
              }
              $orderParticipantName = $orderParticipant['name'] ?? $orderMainId;

              $statusLabels = [
                'open' => ['Offen', 'bg-warning text-dark'],
                'paid' => ['Bezahlt', 'bg-info text-dark'],
                'redeemed' => ['Eingelöst', 'bg-success'],
                'cancelled' => ['Storniert', 'bg-danger']
              ];
              $statusLabel = $statusLabels[$orderStatus][0] ?? $orderStatus;
              $statusClass = $statusLabels[$orderStatus][1] ?? 'bg-secondary';
              ?>
              <div class="card admin-card mb-3">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                      <div class="text-muted small">Bestellnr.</div>
                      <div class="fw-semibold"><?= e($orderId) ?></div>
                    </div>
                    <div>
                      <div class="text-muted small">Hauptgast</div>
                      <div class="fw-semibold"><?= e($orderParticipantName) ?> <span class="text-muted">·</span> <?= e($orderMainId) ?></div>
                    </div>
                    <div>
                      <div class="text-muted small">Status</div>
                      <span class="badge <?= $statusClass ?>"><?= e($statusLabel) ?></span>
                    </div>
                    <div>
                      <div class="text-muted small">Erstellt</div>
                      <div><?= e($orderCreated ? date('d.m.Y H:i', strtotime($orderCreated)) : '-') ?></div>
                    </div>
                  </div>

                  <!-- Items -->
                  <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0 admin-table">
                      <thead>
                        <tr>
                          <th>Artikel</th>
                          <th style="width:80px;">Menge</th>
                          <th style="width:100px;">Preis</th>
                          <th style="width:100px;">Summe</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($orderItems as $item): ?>
                          <tr>
                            <td><?= e($item['name'] ?? '') ?></td>
                            <td><?= (int)($item['quantity'] ?? 0) ?></td>
                            <td><?= number_format((float)($item['price'] ?? 0), 2) ?> €</td>
                            <td><?= number_format((float)($item['subtotal'] ?? 0), 2) ?> €</td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                      <tfoot>
                        <tr class="fw-semibold">
                          <td colspan="3">Gesamt</td>
                          <td><?= number_format($orderTotal, 2) ?> €</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>

                  <?php if ($orderStatus === 'redeemed' && $orderRedeemed): ?>
                    <div class="text-muted small mt-2">
                      Eingelöst am <?= e(date('d.m.Y H:i', strtotime($orderRedeemed))) ?> von <?= e($orderRedeemedBy) ?>
                    </div>
                  <?php endif; ?>

                  <!-- Aktionen -->
                  <div class="d-flex gap-2 flex-wrap mt-3 align-items-center">
                    <?php if ($orderStatus === 'open'): ?>
                      <form class="d-flex gap-2 align-items-center" method="post" action="/admin/admin_food_order_update_paid.php">
                        <?= Csrf::inputField() ?>
                        <input type="hidden" name="order_id" value="<?= e($orderId) ?>">
                        <label class="text-muted small text-nowrap mb-0">Bezahlt:</label>
                        <input class="form-control form-control-sm" style="width:100px;" name="paid_amount" inputmode="numeric" step="0.01" value="<?= number_format($orderTotal, 2, '.', '') ?>">
                        <span class="text-muted">€</span>
                        <button class="btn btn-sm btn-success" type="submit">Als bezahlt markieren</button>
                      </form>
                    <?php elseif ($orderStatus === 'paid' || $orderStatus === 'redeemed'): ?>
                      <a href="/food_bon/pdf.php?order_id=<?= urlencode($orderId) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Bon anzeigen</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>

        <!-- Staff Credentials Section -->
        <section class="admin-section" data-section="staff" id="staff" style="display:none;">
          <div class="card admin-card mb-3">
            <div class="card-body p-4">
              <div class="text-muted small admin-kicker">Personal</div>
              <div class="h6 mb-3">Staff Zugangsdaten</div>
              <p class="text-muted" style="font-size:.9rem;">Login-Codes für Essensausgabe- und Türpersonal.</p>

              <div class="row g-4">
                <!-- Food Helpers -->
                <div class="col-12 col-lg-6">
                  <div class="admin-panel p-3">
                    <div class="fw-semibold mb-3">🍽️ Essensausgabe (Food Helper)</div>
                    <?php
                    $foodHelpers = array_filter(ParticipantsRepository::all(), static function ($p) {
                      return strtoupper(trim((string)($p['role'] ?? ''))) === 'FOOD_HELPER';
                    });
                    if (empty($foodHelpers)): ?>
                      <div class="text-muted mb-3">Keine Food Helper angelegt.</div>
                    <?php else: ?>
                      <table class="table table-sm mb-3">
                        <thead>
                          <tr>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Login-Code</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($foodHelpers as $fh): ?>
                            <tr>
                              <td><?= e((string)($fh['name'] ?? '')) ?></td>
                              <td><code><?= e((string)($fh['id'] ?? '')) ?></code></td>
                              <td>
                                <?php
                                $code = (string)($fh['login_code'] ?? '');
                                $isHashed = str_starts_with($code, '$2y$');
                                ?>
                                <?php if ($isHashed): ?>
                                  <span class="text-muted">(bcrypt-Hash)</span>
                                <?php else: ?>
                                  <code class="text-success"><?= e($code) ?></code>
                                <?php endif; ?>
                              </td>
                              <td>
                                <form method="post" action="/admin/admin_delete_staff.php" class="d-inline" onsubmit="return confirm('Wirklich löschen?');">
                                  <?= Csrf::inputField() ?>
                                  <input type="hidden" name="id" value="<?= e((string)($fh['id'] ?? '')) ?>">
                                  <button type="submit" class="btn btn-sm btn-outline-danger">×</button>
                                </form>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php endif; ?>

                    <!-- Create Food Helper Form -->
                    <div class="border-top pt-3 mt-2">
                      <div class="text-muted small mb-2">Neuer Food Helper</div>
                      <form method="post" action="/admin/admin_create_staff.php" class="row g-2">
                        <?= Csrf::inputField() ?>
                        <input type="hidden" name="role" value="FOOD_HELPER">
                        <div class="col-12">
                          <input class="form-control form-control-sm" name="name" placeholder="Name" required>
                        </div>
                        <div class="col-12">
                          <input class="form-control form-control-sm" name="login_code" placeholder="Login-Code" required>
                        </div>
                        <div class="col-12">
                          <button class="btn btn-sm btn-save" type="submit">Anlegen</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Door Staff -->
                <div class="col-12 col-lg-6">
                  <div class="admin-panel p-3">
                    <div class="fw-semibold mb-3">🚪 Türkontrolle (Door Staff)</div>
                    <?php
                    $doorStaff = array_filter(ParticipantsRepository::all(), static function ($p) {
                      return strtoupper(trim((string)($p['role'] ?? ''))) === 'DOOR';
                    });
                    if (empty($doorStaff)): ?>
                      <div class="text-muted mb-3">Kein Türpersonal angelegt.</div>
                    <?php else: ?>
                      <table class="table table-sm mb-3">
                        <thead>
                          <tr>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Login-Code</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($doorStaff as $ds): ?>
                            <tr>
                              <td><?= e((string)($ds['name'] ?? '')) ?></td>
                              <td><code><?= e((string)($ds['id'] ?? '')) ?></code></td>
                              <td>
                                <?php
                                $code = (string)($ds['login_code'] ?? '');
                                $isHashed = str_starts_with($code, '$2y$');
                                ?>
                                <?php if ($isHashed): ?>
                                  <span class="text-muted">(bcrypt-Hash)</span>
                                <?php else: ?>
                                  <code class="text-success"><?= e($code) ?></code>
                                <?php endif; ?>
                              </td>
                              <td>
                                <form method="post" action="/admin/admin_delete_staff.php" class="d-inline" onsubmit="return confirm('Wirklich löschen?');">
                                  <?= Csrf::inputField() ?>
                                  <input type="hidden" name="id" value="<?= e((string)($ds['id'] ?? '')) ?>">
                                  <button type="submit" class="btn btn-sm btn-outline-danger">×</button>
                                </form>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php endif; ?>

                    <!-- Create Door Staff Form -->
                    <div class="border-top pt-3 mt-2">
                      <div class="text-muted small mb-2">Neues Türpersonal</div>
                      <form method="post" action="/admin/admin_create_staff.php" class="row g-2">
                        <?= Csrf::inputField() ?>
                        <input type="hidden" name="role" value="DOOR">
                        <div class="col-12">
                          <input class="form-control form-control-sm" name="name" placeholder="Name" required>
                        </div>
                        <div class="col-12">
                          <input class="form-control form-control-sm" name="login_code" placeholder="Login-Code" required>
                        </div>
                        <div class="col-12">
                          <button class="btn btn-sm btn-save" type="submit">Anlegen</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>

              <div class="text-muted small mt-3">
                Hinweis: Wenn der Login-Code als "(bcrypt-Hash)" angezeigt wird, ist das Passwort verschlüsselt gespeichert und kann nicht mehr angezeigt werden.
              </div>
            </div>
          </div>
        </section>

      </div>
    </main>

    <script>
      (function() {
        // Auto-redirect after success message (1.5 second delay)
        const hasSuccess = !!document.querySelector('.alert-success');
        if (hasSuccess) {
          setTimeout(() => {
            location.replace('/admin/admin_dashboard.php');
          }, 1500);
        }
      })();

      // Section navigation
      (function() {
        const sel = document.getElementById('adminSectionSelect');
        const sections = Array.from(document.querySelectorAll('.admin-section'));

        function show(key) {
          sections.forEach(s => {
            s.style.display = (s.dataset.section === key) ? '' : 'none';
          });
          if (key) history.replaceState(null, '', '#' + key);
        }

        function init() {
          const hash = (location.hash || '').replace('#', '').trim();
          const valid = ['create', 'ovr', 'edit', 'seating', 'food', 'staff', 'logs'];
          const start = valid.includes(hash) ? hash : (sel ? sel.value : 'edit');
          if (sel) sel.value = start;
          show(start);
        }

        if (sel) sel.addEventListener('change', () => show(sel.value));
        window.addEventListener('hashchange', init);
        init();
      })();

      // Inline companion name editing
      (function() {
        document.addEventListener('click', function(e) {
          // Edit button clicked
          if (e.target.closest('.edit-companion-btn')) {
            const btn = e.target.closest('.edit-companion-btn');
            const companionId = btn.dataset.companionId;
            const nameCell = document.getElementById('name-cell-' + companionId);
            const display = nameCell.querySelector('.companion-name-display');
            const form = document.getElementById('edit-form-' + companionId);

            display.classList.add('d-none');
            btn.classList.add('d-none');
            form.classList.remove('d-none');
            form.classList.add('d-flex');

            // Focus the input field
            const input = form.querySelector('input[name="new_name"]');
            input.focus();
          }

          // Cancel button clicked
          if (e.target.closest('.cancel-edit-btn')) {
            const btn = e.target.closest('.cancel-edit-btn');
            const companionId = btn.dataset.companionId;
            const nameCell = document.getElementById('name-cell-' + companionId);
            const display = nameCell.querySelector('.companion-name-display');
            const form = document.getElementById('edit-form-' + companionId);
            const editBtn = nameCell.querySelector('.edit-companion-btn');

            form.classList.add('d-none');
            form.classList.remove('d-flex');
            display.classList.remove('d-none');
            editBtn.classList.remove('d-none');
          }
        });
      })();

      // Inline password editing
      (function() {
        document.addEventListener('click', function(e) {
          // Edit password button clicked
          if (e.target.closest('.edit-password-btn')) {
            const btn = e.target.closest('.edit-password-btn');
            const participantId = btn.dataset.participantId;
            const passwordCell = document.getElementById('password-cell-' + participantId);
            const display = passwordCell.querySelector('.password-display');
            const form = document.getElementById('password-form-' + participantId);

            display.classList.add('d-none');
            form.classList.remove('d-none');
            form.classList.add('d-flex');

            // Focus the input field
            const input = form.querySelector('input[name="new_password"]');
            input.focus();
          }

          // Cancel password edit button clicked
          if (e.target.closest('.cancel-password-btn')) {
            const btn = e.target.closest('.cancel-password-btn');
            const participantId = btn.dataset.participantId;
            const passwordCell = document.getElementById('password-cell-' + participantId);
            const display = passwordCell.querySelector('.password-display');
            const form = document.getElementById('password-form-' + participantId);

            form.classList.add('d-none');
            form.classList.remove('d-flex');
            display.classList.remove('d-none');

            // Clear input field
            const input = form.querySelector('input[name="new_password"]');
            input.value = '';
          }
        });
      })();
    </script>
<?php
    Layout::footer();
  }
}
