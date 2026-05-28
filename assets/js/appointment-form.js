// appointment-form.js — динамика страницы анкеты

(function() {
    'use strict';

    // ── Счётчик символов в textarea жалобы ──
    var complaint = document.getElementById('complaint');
    var counter   = document.getElementById('complaintCount');
    if (complaint && counter) {
        function update() { counter.textContent = String(complaint.value.length); }
        complaint.addEventListener('input', update);
        update();
    }

    // ── Превью файла ──
    var fileInput = document.getElementById('attachment');
    var preview   = document.getElementById('attachmentPreview');
    if (fileInput && preview) {
        fileInput.addEventListener('change', function() {
            preview.innerHTML = '';
            var file = this.files[0];
            if (!file) return;

            var sizeKb = Math.round(file.size / 1024);
            var sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            var sizeText = file.size > 1024 * 1024 ? sizeMb + ' МБ' : sizeKb + ' КБ';

            var info = document.createElement('div');
            info.textContent = '📎 ' + file.name + ' (' + sizeText + ')';
            preview.appendChild(info);

            // Если изображение — рисуем превью
            if (file.type.indexOf('image/') === 0) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = 'Превью';
                preview.appendChild(img);
            }

            // Предупреждение если файл больше 5 МБ
            if (file.size > 5 * 1024 * 1024) {
                var warn = document.createElement('div');
                warn.textContent = '⚠️ Файл больше 5 МБ — будет отклонён сервером';
                warn.style.color = '#c00';
                warn.style.marginTop = '6px';
                preview.appendChild(warn);
            }
        });
    }

    // ── Раскрытие/сворачивание блока анкеты в админке ──
    document.addEventListener('click', function(e) {
        if (e.target && e.target.matches('.toggle-form-btn')) {
            var formId = e.target.getAttribute('data-form-id');
            var details = document.getElementById('form-details-' + formId);
            if (details) {
                var isHidden = details.style.display === 'none' || details.style.display === '';
                details.style.display = isHidden ? 'block' : 'none';
                e.target.textContent = isHidden ? '▼ Скрыть анкету' : '▶ Показать анкету';
            }
        }
    });
})();
