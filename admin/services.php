<?php
// =====================================================
// admin/services.php — Управление услугами
// =====================================================
require_once __DIR__ . '/includes/admin_auth.php';

$adminPageTitle = 'Услуги';
$errors  = [];
$editSvc = null;

// --- ДОБАВЛЕНИЕ / РЕДАКТИРОВАНИЕ / УДАЛЕНИЕ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id']          ?? 0);
        $name        = trim($_POST['name']         ?? '');
        $description = trim($_POST['description']  ?? '');
        $price       = (float)str_replace(',', '.', $_POST['price'] ?? '0');
        $duration    = (int)($_POST['duration']    ?? 30);
        $is_active   = isset($_POST['is_active'])  ? 1 : 0;

        // Валидация
        if (mb_strlen($name) < 2) {
            $errors[] = 'Введите название услуги (минимум 2 символа).';
        }
        if ($price < 0) {
            $errors[] = 'Цена не может быть отрицательной.';
        }
        if ($duration < 5) {
            $errors[] = 'Длительность должна быть не менее 5 минут.';
        }

        if (empty($errors)) {
            if ($id > 0) {
                // Редактирование существующей услуги
                $stmt = $db->prepare(
                    "UPDATE services
                     SET name = ?, description = ?, price = ?, duration = ?, is_active = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('ssdiii', $name, $description, $price, $duration, $is_active, $id);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Услуга «' . $name . '» обновлена.');
            } else {
                // Добавление новой услуги
                $stmt = $db->prepare(
                    "INSERT INTO services (name, description, price, duration, is_active)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('ssdii', $name, $description, $price, $duration, $is_active);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Услуга «' . $name . '» добавлена.');
            }
            redirect('/vetclinic/admin/services.php');
        } else {
            // При ошибке восстанавливаем форму
            $editSvc = [
                'id'          => $id,
                'name'        => $name,
                'description' => $description,
                'price'       => $price,
                'duration'    => $duration,
                'is_active'   => $is_active,
            ];
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Проверяем, используется ли услуга в активных записях
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS cnt FROM appointments
                 WHERE service_id = ? AND status IN ('new', 'confirmed')"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();

            if ($cnt > 0) {
                setFlash('danger',
                    'Нельзя удалить услугу — она используется в ' . $cnt . ' активных записях.'
                );
            } else {
                $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Услуга удалена.');
            }
        }
        redirect('/vetclinic/admin/services.php');

    } elseif ($action === 'toggle') {
        // Быстрое включение / отключение услуги
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare(
                "UPDATE services SET is_active = 1 - is_active WHERE id = ?"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Статус услуги изменён.');
        }
        redirect('/vetclinic/admin/services.php');
    }
}

// Получаем данные услуги для редактирования (по GET-параметру)
if (!$editSvc && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM services WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editSvc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Список всех услуг
$services = $db->query(
    "SELECT s.*,
            COUNT(a.id)                                               AS total_appointments,
            SUM(CASE WHEN a.status IN ('new','confirmed') THEN 1 ELSE 0 END) AS active_appointments
     FROM services s
     LEFT JOIN appointments a ON s.id = a.service_id
     GROUP BY s.id
     ORDER BY s.name ASC"
)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-two-col">

    <!-- ===== Форма добавления / редактирования ===== -->
    <div class="admin-form-panel">
        <h2><?= $editSvc ? 'Редактировать услугу' : 'Добавить услугу' ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <p><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/vetclinic/admin/services.php" novalidate>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id"
                   value="<?= $editSvc ? (int)$editSvc['id'] : 0 ?>">

            <div class="form-group">
                <label for="svc_name">Название услуги *</label>
                <input type="text"
                       id="svc_name"
                       name="name"
                       value="<?= e($editSvc['name'] ?? '') ?>"
                       placeholder="Первичный осмотр"
                       required
                       maxlength="150">
            </div>

            <div class="form-group">
                <label for="svc_desc">Описание</label>
                <textarea id="svc_desc"
                          name="description"
                          rows="3"
                          maxlength="2000"
                          placeholder="Краткое описание услуги..."><?= e($editSvc['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="svc_price">Цена (₽) *</label>
                    <input type="number"
                           id="svc_price"
                           name="price"
                           value="<?= isset($editSvc['price']) ? number_format((float)$editSvc['price'], 2, '.', '') : '0.00' ?>"
                           min="0"
                           step="0.01"
                           required>
                </div>

                <div class="form-group">
                    <label for="svc_duration">Длительность (мин) *</label>
                    <input type="number"
                           id="svc_duration"
                           name="duration"
                           value="<?= (int)($editSvc['duration'] ?? 30) ?>"
                           min="5"
                           max="480"
                           required>
                </div>
            </div>

            <div class="form-group form-check">
                <label>
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                        <?= !isset($editSvc) || !$editSvc || $editSvc['is_active'] ? 'checked' : '' ?>>
                    Активна (отображается на сайте и доступна для записи)
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editSvc ? 'Сохранить изменения' : 'Добавить услугу' ?>
                </button>
                <?php if ($editSvc): ?>
                    <a href="/vetclinic/admin/services.php" class="btn btn-ghost">
                        Отмена
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ===== Таблица услуг ===== -->
    <div class="admin-table-panel">
        <h2>Список услуг (<?= count($services) ?>)</h2>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Название</th>
                    <th>Цена</th>
                    <th>Длит.</th>
                    <th>Записей</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($services)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        Услуги ещё не добавлены
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($services as $svc): ?>
                <tr class="<?= !$svc['is_active'] ? 'row-inactive' : '' ?>">
                    <td><?= (int)$svc['id'] ?></td>
                    <td>
                        <strong><?= e($svc['name']) ?></strong>
                        <?php if ($svc['description']): ?>
                            <br>
                            <small class="text-muted">
                                <?= e(mb_strimwidth($svc['description'], 0, 60, '…')) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td class="nowrap">
                        <?= number_format((float)$svc['price'], 0, '.', ' ') ?> ₽
                    </td>
                    <td class="nowrap">
                        <?= (int)$svc['duration'] ?> мин
                    </td>
                    <td>
                        <?= (int)$svc['total_appointments'] ?>
                        <?php if ($svc['active_appointments'] > 0): ?>
                            <br>
                            <small class="text-muted">
                                активных: <?= (int)$svc['active_appointments'] ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($svc['is_active']): ?>
                            <span class="badge badge-confirmed">Активна</span>
                        <?php else: ?>
                            <span class="badge badge-cancelled">Отключена</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Кнопка редактирования -->
                        <a href="?edit=<?= (int)$svc['id'] ?>"
                           class="btn btn-sm btn-outline">
                            Изменить
                        </a>

                        <!-- Быстрое вкл/выкл -->
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$svc['id'] ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-ghost"
                                    title="<?= $svc['is_active'] ? 'Отключить' : 'Включить' ?>">
                                <?= $svc['is_active'] ? '🔴 Выкл' : '🟢 Вкл' ?>
                            </button>
                        </form>

                        <!-- Удаление -->
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Удалить услугу «<?= e($svc['name']) ?>»?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$svc['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                Удалить
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.admin-two-col -->

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
