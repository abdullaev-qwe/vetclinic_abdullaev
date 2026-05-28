<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/waiting_list.php';

requireUserAuth();

$userId = (int)$_SESSION['user_id'];

// Удаление заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $entryId = (int)($_POST['entry_id'] ?? 0);

    // CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    $csrfValid = false;
    if (function_exists('checkCsrfToken')) {
        $csrfValid = checkCsrfToken($csrfToken);
    } elseif (function_exists('verifyCsrf')) {
        try { verifyCsrf(); $csrfValid = true; } catch (\Throwable $e) {}
    } else {
        $csrfValid = !empty($_SESSION['csrf_token'])
                  && hash_equals($_SESSION['csrf_token'], $csrfToken);
    }

    if ($csrfValid && $entryId > 0) {
        $stmt = $db->prepare("UPDATE waiting_list SET status='cancelled' WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $entryId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Заявка отменена.');
    }
    redirect('/vetclinic/waiting_list_my.php');
}

$stmt = $db->prepare(
    "SELECT * FROM view_waiting_list WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrfToken = function_exists('generateCsrfToken')
    ? generateCsrfToken()
    : ($_SESSION['csrf_token'] ?? '');

$pageTitle = 'Мой лист ожидания';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>⏳ Мой лист ожидания</h1>
    <p>Заявки на запись когда освободится подходящее окно</p>
  </div>
</div>

<section class="page-section">
  <div class="container">
    <?php showFlash(); ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:10px;">
      <a href="/vetclinic/appointments.php" class="btn btn-outline btn-sm">← Мои записи</a>
      <a href="/vetclinic/waiting_list_join.php" class="btn btn-gold">+ Новая заявка</a>
    </div>

    <?php if (empty($entries)): ?>
      <div class="empty-state" style="text-align:center;padding:40px;background:var(--white);border-radius:12px;border:1px solid var(--border);">
        <p style="font-size:1.1rem;margin-bottom:14px;">У вас нет заявок в листе ожидания</p>
        <a href="/vetclinic/waiting_list_join.php" class="btn btn-primary">Добавить заявку</a>
      </div>
    <?php else: ?>
      <div class="waiting-grid">
        <?php foreach ($entries as $e): ?>
          <div class="waiting-card">
            <div class="waiting-card-head">
              <?= waitingStatusBadge($e['status']) ?>
              <small style="color:var(--ink-soft);">
                Заявка от <?= date('d.m.Y', strtotime($e['created_at'])) ?>
              </small>
            </div>

            <div class="waiting-card-body">
              <h3>🐾 <?= e($e['pet_name']) ?> (<?= e($e['pet_type']) ?>)</h3>
              <dl>
                <dt>Врач:</dt>
                <dd><?= $e['doctor_name'] ? e($e['doctor_name']) : '<em>любой</em>' ?></dd>

                <dt>Услуга:</dt>
                <dd><?= $e['service_name'] ? e($e['service_name']) : '<em>любая</em>' ?></dd>

                <?php if ($e['preferred_date_from'] || $e['preferred_date_to']): ?>
                  <dt>Период:</dt>
                  <dd>
                    <?php if ($e['preferred_date_from'] && $e['preferred_date_to']): ?>
                      с <?= date('d.m.Y', strtotime($e['preferred_date_from'])) ?>
                      по <?= date('d.m.Y', strtotime($e['preferred_date_to'])) ?>
                    <?php elseif ($e['preferred_date_from']): ?>
                      с <?= date('d.m.Y', strtotime($e['preferred_date_from'])) ?>
                    <?php else: ?>
                      по <?= date('d.m.Y', strtotime($e['preferred_date_to'])) ?>
                    <?php endif; ?>
                  </dd>
                <?php endif; ?>

                <dt>Время:</dt>
                <dd><?= e(waitingTimePreferences()[$e['preferred_time']] ?? 'Любое') ?></dd>

                <?php if ($e['notes']): ?>
                  <dt>Комментарий:</dt>
                  <dd><?= nl2br(e($e['notes'])) ?></dd>
                <?php endif; ?>

                <?php if ($e['notified_at']): ?>
                  <dt>Уведомлены:</dt>
                  <dd><?= date('d.m.Y H:i', strtotime($e['notified_at'])) ?></dd>
                <?php endif; ?>
              </dl>
            </div>

            <?php if ($e['status'] === 'waiting' || $e['status'] === 'offered'): ?>
              <div class="waiting-card-foot">
                <form method="POST" action="/vetclinic/waiting_list_my.php" style="display:inline;"
                      onsubmit="return confirm('Отменить заявку?');">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Отменить заявку</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
