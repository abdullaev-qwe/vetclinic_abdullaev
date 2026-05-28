<?php
require_once __DIR__ . '/includes/admin_auth.php';

$adminPageTitle = 'Журнал действий';

$perPage     = 30;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$stmtC = $db->query("SELECT COUNT(*) AS cnt FROM admin_logs");
$totalRows  = (int)$stmtC->fetch_assoc()['cnt'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $db->prepare(
    "SELECT l.*, a.username
     FROM admin_logs l
     JOIN admins a ON l.admin_id = a.id
     ORDER BY l.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function pageUrl(int $page): string {
    $p = $_GET; $p['page'] = $page;
    return '?' . http_build_query($p);
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-section" style="padding:0;overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Администратор</th>
        <th>Действие</th>
        <th>IP-адрес</th>
        <th>Дата и время</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($logs)): ?>
      <tr>
        <td colspan="5" class="text-center text-muted" style="padding:32px;">
          Журнал пуст — действия будут появляться здесь по мере работы
        </td>
      </tr>
    <?php endif; ?>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= (int)$log['id'] ?></td>
        <td><strong><?= e($log['username']) ?></strong></td>
        <td><?= e($log['action']) ?></td>
        <td><code style="font-size:.8rem;background:var(--emerald-ghost);
                          padding:2px 7px;border-radius:4px;color:var(--emerald);">
          <?= e($log['ip']) ?>
        </code></td>
        <td>
          <?= formatDate($log['created_at']) ?>
          <small><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($currentPage > 1): ?>
    <a href="<?= pageUrl($currentPage-1) ?>" class="page-btn">← Назад</a>
  <?php endif; ?>
  <?php for ($i = max(1,$currentPage-2); $i <= min($totalPages,$currentPage+2); $i++): ?>
    <a href="<?= pageUrl($i) ?>" class="page-btn <?= $i===$currentPage?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($currentPage < $totalPages): ?>
    <a href="<?= pageUrl($currentPage+1) ?>" class="page-btn">Вперёд →</a>
  <?php endif; ?>
  <span class="page-info">Страница <?= $currentPage ?> из <?= $totalPages ?></span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
