// =====================================================
// assets/js/admin.js — JavaScript административной панели
// =====================================================

document.addEventListener('DOMContentLoaded', function () {

  // --- Автозакрытие flash-сообщений ---
  document.querySelectorAll('.alert').forEach(function (msg) {
    setTimeout(function () {
      msg.style.transition = 'opacity .5s';
      msg.style.opacity    = '0';
      setTimeout(function () { msg.remove(); }, 500);
    }, 4000);
  });

  // --- Подтверждение удаления ---
  document.querySelectorAll('form[onsubmit]').forEach(function (form) {
    // уже обрабатывается через onsubmit в HTML
  });

  // --- Подсветка активного пункта меню ---
  const currentPath = window.location.pathname;
  document.querySelectorAll('.admin-nav a').forEach(function (link) {
    const href = link.getAttribute('href');
    if (href && currentPath.endsWith(href.split('/').pop())) {
      link.classList.add('active');
    }
  });

  // --- Поиск в таблицах (клиентский, для мелких таблиц) ---
  const quickSearch = document.getElementById('quickSearch');
  if (quickSearch) {
    const tbody = document.querySelector('.admin-table tbody');
    quickSearch.addEventListener('input', function () {
      const val = this.value.toLowerCase();
      tbody.querySelectorAll('tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
      });
    });
  }

});
