<?php
// =====================================================
// admin/doctors.php — Управление врачами
// =====================================================
require_once __DIR__ . '/includes/admin_auth.php';

$adminPageTitle = 'Врачи';
$errors  = [];
$editDoc = null;

// --- ДОБАВЛЕНИЕ / РЕДАКТИРОВАНИЕ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $name       = trim($_POST['name']       ?? '');
        $specialty  = trim($_POST['specialty']  ?? '');
        $experience = (int)($_POST['experience'] ?? 0);
        $bio        = trim($_POST['bio']         ?? '');
        $photo      = trim($_POST['photo']       ?? 'default_doctor.jpg');
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if (mb_strlen($name) < 2)      $errors[] = 'Введите ФИО врача.';
        if (mb_strlen($specialty) < 2) $errors[] = 'Введите специализацию.';

        if (empty($errors)) {
            if ($id > 0) {
                // Редактирование
                $stmt = $db->prepare(
                    "UPDATE doctors
                     SET name=?, specialty=?, experience=?, bio=?, photo=?, is_active=?
                     WHERE id=?"
                );
                $stmt->bind_param('ssissii',
                    $name, $specialty, $experience, $bio, $photo, $is_active, $id
                );
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Врач обновлён.');
            } else {
                // Добавление
                $stmt = $db->prepare(
                    "INSERT INTO doctors (name, specialty, experience, bio, photo, is_active)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('ssissi',
                    $name, $specialty, $experience, $bio, $photo, $is_active
                );
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Врач добавлен.');
            }
            redirect('/vetclinic/admin/doctors.php');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Проверяем, есть ли активные записи
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS cnt FROM appointments
                 WHERE doctor_id = ? AND status IN ('new','confirmed')"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();

            if ($cnt > 0) {
                setFlash('danger',
                    'Нельзя удалить врача — у него есть активные записи (' . $cnt . ' шт.).'
                );
            } else {
                $stmt = $db->prepare("DELETE FROM doctors WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Врач удалён.');
            }
        }
        redirect('/vetclinic/admin/doctors.php');
    }
}

// Редактирование — получаем данные врача
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM doctors WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editDoc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Список врачей (через VIEW для статистики)
$doctors = $db->query(
    "SELECT * FROM view_doctor_schedule ORDER BY doctor_name ASC"
)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-two-col">

    <!-- Форма добавления / редактирования -->
    <div class="admin-form-panel">
        <h2><?= $editDoc ? 'Редактировать врача' : 'Добавить врача' ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e): ?>
                    <p><?= e($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/vetclinic/admin/doctors.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id"
                   value="<?= $editDoc ? (int)$editDoc['id'] : 0 ?>">

            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="name"
                       value="<?= e($editDoc['name'] ?? '') ?>"
                       required maxlength="100">
            </div>
            <div class="form-group">
                <label>Специализация *</label>
                <input type="text" name="specialty"
                       value="<?= e($editDoc['specialty'] ?? '') ?>"
                       required maxlength="150">
            </div>
            <div class="form-group">
                <label>Опыт (лет)</label>
                <input type="number" name="experience" min="0" max="60"
                       value="<?= (int)($editDoc['experience'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea name="bio" rows="3"><?= e($editDoc['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Файл фото (имя файла)</label>
                <input type="text" name="photo"
                       value="<?= e($editDoc['photo'] ?? 'default_doctor.jpg') ?>"
                       placeholder="doctor1.jpg">
            </div>
            <div class="form-group form-check">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                        <?= !$editDoc || $editDoc['is_active'] ? 'checked' : '' ?>>
                    Активен (отображается на сайте)
                </label>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= $editDoc ? 'Сохранить изменения' : 'Добавить врача' ?>
            </button>
            <?php if ($editDoc): ?>
                <a href="/vetclinic/admin/doctors.php" class="btn btn-ghost">Отмена</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Таблица врачей -->
    <div class="admin-table-panel">
        <h2>Список врачей</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ФИО / Специализация</th>
                    <th>Опыт</th>
                    <th>Записей</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($doctors as $doc): ?>
                <tr>
                    <td>
                        <strong><?= e($doc['doctor_name']) ?></strong><br>
                        <small class="text-muted"><?= e($doc['specialty']) ?></small>
                    </td>
                    <td><?= (int)$doc['experience'] ?> л.</td>
                    <td><?= (int)$doc['total_appointments'] ?></td>
                    <td>
                        <?php if ($doc['is_active']): ?>
                            <span class="badge badge-confirmed">Активен</span>
                        <?php else: ?>
                            <span class="badge badge-cancelled">Неактивен</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?edit=<?= (int)$doc['doctor_id'] ?>"
                           class="btn btn-sm btn-outline">
                            Изменить
                        </a>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Удалить врача?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$doc['doctor_id'] ?>">
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

</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
