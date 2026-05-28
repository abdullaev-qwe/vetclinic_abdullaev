/* home-wow.js — оживление главной (Этап 1) */
(function () {
  'use strict';

  var root = document.querySelector('.wow-home');
  if (!root) return;

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ──────────────────────────────────────────────
     1. IntersectionObserver — появление блоков
     ────────────────────────────────────────────── */
  var revealEls = root.querySelectorAll('.wow-reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    // Fallback: просто показать всё
    revealEls.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* ──────────────────────────────────────────────
     2. Count-up на цифрах статистики
     ────────────────────────────────────────────── */
  function easeOutCubic(t) { return 1 - Math.pow(1 - t, 3); }

  function animateCount(el) {
    var target = parseInt(el.getAttribute('data-count') || '0', 10);
    var suffix = el.getAttribute('data-suffix') || '';
    if (!target || isNaN(target)) return;

    if (reduced) {
      el.textContent = target + suffix;
      return;
    }

    var duration = 1600 + Math.min(target / 4, 800); // подлиннее для крупных чисел
    var start = null;

    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      var val = Math.floor(easeOutCubic(p) * target);
      el.textContent = val + suffix;
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = target + suffix;
    }
    requestAnimationFrame(step);
  }

  var statEls = root.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window && statEls.length) {
    var statIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          statIO.unobserve(entry.target);
        }
      });
    }, { threshold: 0.6 });
    statEls.forEach(function (el) {
      el.textContent = '0' + (el.getAttribute('data-suffix') || '');
      statIO.observe(el);
    });
  } else {
    statEls.forEach(animateCount);
  }

  /* ──────────────────────────────────────────────
     3. Magnetic-кнопка: тянется за курсором
     ────────────────────────────────────────────── */
  if (!reduced) {
    var magnets = root.querySelectorAll('.wow-btn-magnet');
    magnets.forEach(function (btn) {
      var rect, raf;

      function onEnter() { rect = btn.getBoundingClientRect(); }
      function onMove(e) {
        if (!rect) rect = btn.getBoundingClientRect();
        var mx = e.clientX - rect.left;
        var my = e.clientY - rect.top;
        var dx = (mx - rect.width / 2)  * 0.25;
        var dy = (my - rect.height / 2) * 0.35;

        if (raf) cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () {
          btn.style.setProperty('--tx', dx.toFixed(2) + 'px');
          btn.style.setProperty('--ty', dy.toFixed(2) + 'px');
          btn.style.setProperty('--mx', ((mx / rect.width)  * 100).toFixed(1) + '%');
          btn.style.setProperty('--my', ((my / rect.height) * 100).toFixed(1) + '%');
        });
      }
      function onLeave() {
        btn.style.setProperty('--tx', '0px');
        btn.style.setProperty('--ty', '0px');
        rect = null;
      }

      btn.addEventListener('mouseenter', onEnter);
      btn.addEventListener('mousemove',  onMove);
      btn.addEventListener('mouseleave', onLeave);
    });
  }

  /* ──────────────────────────────────────────────
     4. Marquee — клонируем содержимое для бесшовной прокрутки
     ────────────────────────────────────────────── */
  var marqueeTrack = root.querySelector('.wow-marquee__track');
  if (marqueeTrack && !marqueeTrack.dataset.cloned) {
    marqueeTrack.innerHTML += marqueeTrack.innerHTML;
    marqueeTrack.dataset.cloned = '1';
  }

  /* ──────────────────────────────────────────────
     5. Видео в hero: запускаем только при видимости и НЕ на reduced-motion
     ────────────────────────────────────────────── */
  var heroVideo = root.querySelector('.wow-hero__video');
  if (heroVideo) {
    if (reduced || window.innerWidth < 760) {
      heroVideo.remove();
    } else if ('IntersectionObserver' in window) {
      var vIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) heroVideo.play().catch(function () {});
          else heroVideo.pause();
        });
      }, { threshold: 0.05 });
      vIO.observe(heroVideo);
    }
  }

  /* ──────────────────────────────────────────────
     6. Лёгкий parallax photo-grid в hero (только desktop, не reduced)
     ────────────────────────────────────────────── */
  if (!reduced && window.matchMedia('(min-width: 1100px)').matches) {
    var art = root.querySelector('.wow-hero__art');
    if (art) {
      var photos = art.querySelectorAll('.wow-hero__photo');
      var raf2;
      art.addEventListener('mousemove', function (e) {
        var r = art.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width  - 0.5;
        var y = (e.clientY - r.top)  / r.height - 0.5;

        if (raf2) cancelAnimationFrame(raf2);
        raf2 = requestAnimationFrame(function () {
          photos.forEach(function (p, i) {
            var depth = (i + 1) * 6;
            p.style.transform =
              'translate3d(' + (x * depth).toFixed(1) + 'px,' +
              (y * depth).toFixed(1) + 'px,0)';
          });
        });
      });
      art.addEventListener('mouseleave', function () {
        photos.forEach(function (p) { p.style.transform = ''; });
      });
    }
  }

  /* ──────────────────────────────────────────────
     ════════════════════ ЭТАП 2 ════════════════════
     ────────────────────────────────────────────── */

  /* 7. Радиальный glow за курсором в bento-плитках */
  if (!reduced) {
    var bentoCells = root.querySelectorAll('.wow-bento__cell');
    bentoCells.forEach(function (cell) {
      cell.addEventListener('mousemove', function (e) {
        var r = cell.getBoundingClientRect();
        cell.style.setProperty('--mx', ((e.clientX - r.left) / r.width  * 100).toFixed(1) + '%');
        cell.style.setProperty('--my', ((e.clientY - r.top)  / r.height * 100).toFixed(1) + '%');
      });
    });
  }

  /* 8. Sticky-story: переключение активного шага и фото при скролле */
  var story = root.querySelector('.wow-story');
  if (story) {
    var steps    = story.querySelectorAll('.wow-story__step');
    var visuals  = story.querySelectorAll('.wow-story__visual img');
    var counter  = story.querySelector('.wow-story__counter');

    function setActive(idx) {
      steps.forEach(function (s, i) {
        s.classList.toggle('is-active', i === idx);
      });
      visuals.forEach(function (v, i) {
        v.classList.toggle('is-active', i === idx);
      });
      if (counter) counter.textContent = '0' + (idx + 1);
    }

    // Активируем первый шаг по умолчанию
    setActive(0);

    if ('IntersectionObserver' in window && steps.length) {
      var stepIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            var idx = parseInt(entry.target.getAttribute('data-step'), 10);
            if (!isNaN(idx)) setActive(idx);
          }
        });
      }, {
        // Триггерим переход примерно на середине экрана
        rootMargin: '-40% 0px -40% 0px',
        threshold: 0,
      });
      steps.forEach(function (s) { stepIO.observe(s); });
    }

    // На клик — тоже активируем (для тапа на мобиле)
    steps.forEach(function (s, i) {
      s.addEventListener('click', function () { setActive(i); });
    });
  }
})();
