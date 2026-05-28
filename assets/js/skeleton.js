// skeleton.js — Управление skeleton-плейсхолдерами

(function() {
    'use strict';

    /**
     * Спрятать skeleton, показать реальный контент.
     * Использование: data-skeleton="container-id" на родителе
     */
    function hideSkeletons() {
        // Все skeleton-контейнеры показываем по таймауту минимум 400мс,
        // чтобы избежать мигания при быстрой загрузке
        var minDelay = 400;
        var startTime = window.skeletonStartTime || Date.now();
        var elapsed = Date.now() - startTime;
        var delay = Math.max(0, minDelay - elapsed);

        setTimeout(function() {
            document.querySelectorAll('[data-skeleton-target]').forEach(function(target) {
                target.classList.remove('skeleton-loading');
                target.classList.add('skeleton-loaded');
            });
        }, delay);
    }

    // Засекаем время старта при загрузке страницы
    window.skeletonStartTime = Date.now();

    // По умолчанию прячем после полной загрузки страницы
    if (document.readyState === 'complete') {
        hideSkeletons();
    } else {
        window.addEventListener('load', hideSkeletons);
    }

    // Экспортируем для ручного управления
    window.hideSkeletons = hideSkeletons;
    window.showSkeletons = function() {
        document.querySelectorAll('[data-skeleton-target]').forEach(function(target) {
            target.classList.remove('skeleton-loaded');
            target.classList.add('skeleton-loading');
        });
        window.skeletonStartTime = Date.now();
    };
})();
