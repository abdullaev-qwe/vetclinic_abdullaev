<?php
/**
 * render_appointment_form.php — отрисовка анкеты для админки и личного кабинета
 *
 * Использование:
 *   require_once __DIR__ . '/includes/render_appointment_form.php';
 *   echo renderFormDetails($db, $appointmentId);
 */

require_once __DIR__ . '/appointment_form.php';

function renderFormDetails(mysqli $db, int $appointmentId): string
{
    $form = getFormByAppointment($db, $appointmentId);
    if (!$form) return '';

    $sList   = appointmentSymptomsList();
    $uList   = appointmentUrgencyList();
    $picked  = !empty($form['symptoms']) ? explode(',', $form['symptoms']) : [];

    ob_start();
    ?>
    <div class="form-details" id="form-details-<?= (int)$form['id'] ?>" style="display:none;">
      <h4>📋 Анкета пациента</h4>
      <dl>
        <dt>Жалоба:</dt>
        <dd><?= nl2br(htmlspecialchars($form['complaint'])) ?></dd>

        <?php if (!empty($picked)): ?>
          <dt>Симптомы:</dt>
          <dd>
            <div class="symptoms-list">
              <?php foreach ($picked as $s):
                if (isset($sList[$s])): ?>
                  <span class="symptom-tag"><?= htmlspecialchars($sList[$s]) ?></span>
              <?php endif; endforeach; ?>
            </div>
          </dd>
        <?php endif; ?>

        <?php if (!empty($form['symptoms_other'])): ?>
          <dt>Другое:</dt>
          <dd><?= htmlspecialchars($form['symptoms_other']) ?></dd>
        <?php endif; ?>

        <?php if (!empty($form['pet_age_value'])): ?>
          <dt>Возраст:</dt>
          <dd>
            <?= (int)$form['pet_age_value'] ?>
            <?= $form['pet_age_unit'] === 'months' ? 'мес.' : 'лет' ?>
          </dd>
        <?php endif; ?>

        <dt>Срочность:</dt>
        <dd><?= urgencyBadge($form['urgency']) ?></dd>

        <dt>Анализы/фото:</dt>
        <dd>
          <?php if (!empty($form['has_tests'])): ?>
            ✅ Есть
          <?php else: ?>
            ❌ Нет
          <?php endif; ?>
        </dd>

        <?php if (!empty($form['attachment_file'])): ?>
          <dt>Прикреплён файл:</dt>
          <dd>
            <a href="/vetclinic/uploads/appointment_attachments/<?= htmlspecialchars($form['attachment_file']) ?>"
               target="_blank">
              📎 <?= htmlspecialchars($form['attachment_file']) ?>
            </a>
            <small style="color:var(--ink-muted);">
              (<?= $form['attachment_type'] === 'pdf' ? 'PDF документ' : 'Изображение' ?>)
            </small>
          </dd>
        <?php endif; ?>

        <dt>Заполнена:</dt>
        <dd><?= date('d.m.Y H:i', strtotime($form['created_at'])) ?></dd>
      </dl>
    </div>
    <?php
    return ob_get_clean();
}

/** Кнопка раскрытия в админке */
function renderFormToggleButton(int $formId): string
{
    return '<button type="button" class="toggle-form-btn" data-form-id="' . $formId . '">'
         . '▶ Показать анкету</button>';
}
