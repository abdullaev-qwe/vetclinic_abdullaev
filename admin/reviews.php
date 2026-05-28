<?php
require_once __DIR__ . '/includes/admin_auth.php';

$adminPageTitle = 'Отзывы';

// Действия
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        if ($action === 'toggle') {
            $stmt = $db->prepare("UPDATE reviews SET is_visible = 1 - is_visible WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            logAdminAction($db, 'Изменена видимость отзыва #' . $id);
            setFlash('success', 'Видимость отзыва изменена.');
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            logAdminAction($db, 'Удалён отзыв #' . $id);
            setFlash('success', 'Отзыв удалён.');
        }
    }
    redirect('/vetclinic/admin/reviews.php');
}

// Фильтр
$filterVisible = $_GET['visible'] ?? 'all';
$perPage       = 20;
$currentPage   = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($currentPage - 1) * $perPage;

$where = $filterVisible === 'all' ? '' : 'WHERE r.is_visible = ' . ($filterVisible === '1' ? '1' : '0');

$stmtC = $db->prepare("SELECT COUNT(*) AS cnt FROM reviews r $where");
$stmtC->execute();
$totalRows  = (int)$stmtC->get_result()->fetch_assoc()['cnt'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$stmtC->close();

$stmt = $db->prepare(
    "SELECT r.*, u.name AS user_name, u.email AS user_email,
            d.name AS doctor_name
     FROM reviews r
     JOIN users u ON r.user_id = u.id
     LEFT JOIN doctors d ON r.doctor_id = d.id
     $where
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function pageUrl(int $page): string {
    $p = $_GET; $p['page'] = $page;
    return '?' . http_build_query($p);
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-filters">
  <form method="GET" action="/vetclinic/admin/reviews.php" class="filter-form">
    <select name="visible">
      <option value="all" <?= $filterVisible==='all'?'selected':'' ?>>Все отзывы</option>
      <option value="1"   <?= $filterVisible==='1'  ?'selected':'' ?>>Опубликованные</option>
      <option value="0"   <?= $filterVisible==='0'  ?'selected':'' ?>>Скрытые</option>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Применить</button>
    <a href="/vetclinic/admin/reviews.php" class="btn btn-ghost btn-sm">Сбросить</a>
  </form>
  <span class="filter-count">Отзывов: <?= $totalRows ?></span>
</div>

<div class="admin-section" style="padding:0;overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th><th>Пользователь</th><th>Врач</th>
        <th>Оценка</th><th>Отзыв</th><th>Дата</th>
        <th>Статус</th><th>Действия</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($reviews)): ?>
      <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">Отзывов не найдено</td></tr>
    <?php endif; ?>
    <?php foreach ($reviews as $rev): ?>
      <tr>
        <td><?= (int)$rev['id'] ?></td>
        <td>
          <strong><?= e($rev['user_name']) ?></strong>
          <small><?= e($rev['user_email']) ?></small>
        </td>
        <td><?= $rev['doctor_name'] ? e($rev['doctor_name']) : '<span class="text-muted">—</span>' ?></td>
        <td>
          <span style="color:var(--gold);letter-spacing:1px;">
            <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5-(int)$rev['rating']) ?>
          </span>
          <small><?= (int)$rev['rating'] ?>/5</small>
        </td>
        <td style="max-width:280px;">
          <span style="font-size:.85rem;line-height:1.5;">
            <?= e(mb_strimwidth($rev['review_text'], 0, 120, '…')) ?>
          </span>
        </td>
        <td><?= formatDate($rev['created_at']) ?></td>
        <td>
          <?php if ($rev['is_visible']): ?>
            <span class="badge badge-confirmed">Опубликован</span>
          <?php else: ?>
            <span class="badge badge-cancelled">Скрыт</span>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap;">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id"     value="<?= (int)$rev['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline">
              <?= $rev['is_visible'] ? 'Скрыть' : 'Опубликовать' ?>
            </button>
          </form>
          <form method="POST" style="display:inline;"
                onsubmit="return confirm('Удалить отзыв?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id"     value="<?= (int)$rev['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
          </form>
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
