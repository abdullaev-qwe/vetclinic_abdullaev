<?php
require_once __DIR__ . '/includes/admin_auth.php';

$adminPageTitle = 'Пользователи';

$search      = trim($_GET['search'] ?? '');
$perPage     = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Считаем общее количество
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmtC = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?");
    $stmtC->bind_param('sss', $like, $like, $like);
} else {
    $stmtC = $db->prepare("SELECT COUNT(*) AS cnt FROM users");
}
$stmtC->execute();
$totalRows  = (int)$stmtC->get_result()->fetch_assoc()['cnt'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$stmtC->close();

// Получаем пользователей
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $db->prepare(
        "SELECT u.*, COUNT(a.id) AS appointments_count
         FROM users u LEFT JOIN appointments a ON u.id = a.user_id
         WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
         GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('sssii', $like, $like, $like, $perPage, $offset);
} else {
    $stmt = $db->prepare(
        "SELECT u.*, COUNT(a.id) AS appointments_count
         FROM users u LEFT JOIN appointments a ON u.id = a.user_id
         GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function pageUrl(int $page): string {
    $p = $_GET; $p['page'] = $page;
    return '?' . http_build_query($p);
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-filters">
  <form method="GET" action="/vetclinic/admin/users.php" class="filter-form">
    <input type="text" name="search" class="search-input"
           value="<?= e($search) ?>"
           placeholder="Поиск по имени, email, телефону...">
    <button type="submit" class="btn btn-outline btn-sm">Найти</button>
    <?php if ($search): ?>
      <a href="/vetclinic/admin/users.php" class="btn btn-ghost btn-sm">Сбросить</a>
    <?php endif; ?>
  </form>
  <span class="filter-count">Пользователей: <?= $totalRows ?></span>
</div>

<div class="admin-section" style="padding:0;overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th><th>Имя</th><th>Email</th>
        <th>Телефон</th><th>Город</th><th>Записей</th><th>Зарегистрирован</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($users)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">Пользователи не найдены</td></tr>
    <?php endif; ?>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><strong><?= e($u['name']) ?></strong></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['phone']) ?: '—' ?></td>
        <td><?= e($u['city'] ?? '') ?: '—' ?></td>
        <td>
          <?php if ($u['appointments_count'] > 0): ?>
            <a href="/vetclinic/admin/appointments.php"><?= (int)$u['appointments_count'] ?></a>
          <?php else: ?> 0 <?php endif; ?>
        </td>
        <td><?= formatDate($u['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($currentPage > 1): ?>
    <a href="<?= pageUrl($currentPage - 1) ?>" class="page-btn">← Назад</a>
  <?php endif; ?>
  <?php
  $start = max(1, $currentPage - 2);
  $end   = min($totalPages, $currentPage + 2);
  if ($start > 1): ?><span class="page-dots">1</span><span class="page-dots">…</span><?php endif;
  for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="<?= pageUrl($i) ?>" class="page-btn <?= $i===$currentPage?'active':'' ?>"><?= $i ?></a>
  <?php endfor;
  if ($end < $totalPages): ?><span class="page-dots">…</span><span class="page-dots"><?= $totalPages ?></span><?php endif; ?>
  <?php if ($currentPage < $totalPages): ?>
    <a href="<?= pageUrl($currentPage + 1) ?>" class="page-btn">Вперёд →</a>
  <?php endif; ?>
  <span class="page-info">Страница <?= $currentPage ?> из <?= $totalPages ?></span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
