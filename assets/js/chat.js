// chat.js — мини-чат с клиникой

(function() {
    'use strict';

    var widget = document.getElementById('chatWidget');
    if (!widget) return;

    var toggle  = document.getElementById('chatToggle');
    var win     = document.getElementById('chatWindow');
    var closeBtn= document.getElementById('chatClose');
    var msgsEl  = document.getElementById('chatMessages');
    var form    = document.getElementById('chatForm');
    var input   = document.getElementById('chatInput');
    var sendBtn = document.getElementById('chatSend');
    var badge   = document.getElementById('chatUnreadBadge');

    var csrfToken = widget.getAttribute('data-csrf') || '';

    var state = {
        threadId: 0,
        lastMessageId: 0,
        lastDate: null,
        isOpen: false,
        pollTimer: null,
        sending: false,
    };

    // ═══ Управление окном ═══
    function openWindow() {
        win.hidden = false;
        state.isOpen = true;
        toggle.setAttribute('aria-expanded', 'true');
        // Прокрутить вниз
        setTimeout(function() {
            msgsEl.scrollTop = msgsEl.scrollHeight;
            input.focus();
        }, 50);
        // Сразу обновить и пометить прочитанными
        fetchMessages(true);
        // Запустить периодическое обновление
        startPolling();
    }

    function closeWindow() {
        win.hidden = true;
        state.isOpen = false;
        toggle.setAttribute('aria-expanded', 'false');
        stopPolling();
    }

    toggle.addEventListener('click', function() {
        state.isOpen ? closeWindow() : openWindow();
    });
    closeBtn.addEventListener('click', closeWindow);

    // ═══ Polling ═══
    function startPolling() {
        if (state.pollTimer) return;
        state.pollTimer = setInterval(fetchMessages, 5000);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    // Фоновое обновление бейджа даже когда окно закрыто (раз в 30 секунд)
    setInterval(function() {
        if (!state.isOpen) fetchMessages(false, true);
    }, 30000);

    // ═══ Получение сообщений ═══
    function fetchMessages(markAsRead, badgeOnly) {
        var url = '/vetclinic/chat_api.php?since_id=' + state.lastMessageId;
        if (markAsRead) url += '&mark_read=1';

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .catch(function() { return null; })
            .then(function(data) {
                if (!data || !data.ok) return;

                state.threadId = data.thread_id || 0;

                // Обновление бейджа
                updateBadge(data.unread || 0);

                // Если режим "только бейдж" — выходим
                if (badgeOnly) return;

                // Добавить новые сообщения
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(addMessageToDOM);
                    state.lastMessageId = data.messages[data.messages.length - 1].id;
                    msgsEl.scrollTop = msgsEl.scrollHeight;

                    // Убираем заглушку
                    var empty = msgsEl.querySelector('.chat-empty');
                    if (empty) empty.remove();
                }
            });
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    // ═══ Добавление сообщения в DOM ═══
    function addMessageToDOM(msg) {
        // Дата-разделитель если новая дата
        if (state.lastDate !== msg.date_label) {
            var divider = document.createElement('div');
            divider.className = 'chat-date-divider';
            divider.textContent = formatDateLabel(msg.date_label);
            msgsEl.appendChild(divider);
            state.lastDate = msg.date_label;
        }

        var el = document.createElement('div');
        el.className = 'chat-msg chat-msg-' + msg.sender;
        el.innerHTML = msg.message_html
            + '<span class="chat-msg-time">' + msg.time_label + '</span>';
        msgsEl.appendChild(el);
    }

    function formatDateLabel(d) {
        var today = new Date();
        var todayStr = pad(today.getDate()) + '.' + pad(today.getMonth() + 1) + '.' + today.getFullYear();
        if (d === todayStr) return 'Сегодня';

        var yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        var yStr = pad(yesterday.getDate()) + '.' + pad(yesterday.getMonth() + 1) + '.' + yesterday.getFullYear();
        if (d === yStr) return 'Вчера';

        return d;
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    // ═══ Отправка ═══
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (state.sending) return;

        var text = input.value.trim();
        if (!text) return;

        state.sending = true;
        sendBtn.disabled = true;

        var fd = new FormData();
        fd.append('text', text);
        fd.append('csrf_token', csrfToken);

        fetch('/vetclinic/chat_api.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            state.sending = false;
            sendBtn.disabled = false;

            if (data && data.ok) {
                input.value = '';
                input.style.height = 'auto';
                // Сразу обновляем
                fetchMessages(true);
            } else {
                alert((data && data.error) || 'Не удалось отправить сообщение');
            }
        })
        .catch(function() {
            state.sending = false;
            sendBtn.disabled = false;
            alert('Сетевая ошибка');
        });
    });

    // Авторазмер textarea
    input.addEventListener('input', function() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    });

    // Enter — отправить, Shift+Enter — перенос строки
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    // ═══ Стартовое обновление бейджа при загрузке страницы ═══
    fetchMessages(false, true);
})();
