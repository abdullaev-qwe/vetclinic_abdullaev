// avatar.js — авто-отправка формы при выборе файла
(function() {
    'use strict';

    document.addEventListener('change', function(e) {
        if (e.target && e.target.matches('input[type="file"][data-auto-submit]')) {
            var form = e.target.form;
            if (form) form.submit();
        }
    });
})();
