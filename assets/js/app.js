/**
 * OEMS — Global frontend behaviors
 */
(function () {
  'use strict';

  const base = document.body?.dataset?.base || '';

  // Landing + portal scroll reveal
  const reveals = document.querySelectorAll('.reveal');
  if (reveals.length) {
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
      reveals.forEach((el) => io.observe(el));
    } else {
      reveals.forEach((el) => el.classList.add('is-visible'));
    }
  }

  // Mobile sidebar
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const openBtn = document.getElementById('sidebarOpen');
  const closeBtn = document.getElementById('sidebarClose');

  function openSidebar() {
    sidebar?.classList.add('is-open');
    if (backdrop) backdrop.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar?.classList.remove('is-open');
    if (backdrop) backdrop.hidden = true;
    document.body.style.overflow = '';
  }
  openBtn?.addEventListener('click', openSidebar);
  closeBtn?.addEventListener('click', closeSidebar);
  backdrop?.addEventListener('click', closeSidebar);

  // Auto-dismiss alerts
  document.querySelectorAll('.toast-stack .alert').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity .35s ease, transform .35s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 400);
    }, 4500);
  });

  // Confirm destructive actions
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      const msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // Modal helpers
  function closeModal(el) {
    const modal = el?.closest?.('.modal-backdrop') || el;
    if (modal && modal.classList.contains('modal-backdrop')) {
      modal.hidden = true;
      modal.setAttribute('hidden', '');
      if (!document.querySelector('.modal-backdrop:not([hidden])')) {
        document.body.style.overflow = '';
      }
    }
  }
  function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.hidden = false;
      modal.removeAttribute('hidden');
      document.body.style.overflow = 'hidden';
    }
  }
  document.querySelectorAll('[data-modal-open]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openModal(btn.getAttribute('data-modal-open'));
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeModal(btn);
    });
  });
  document.querySelectorAll('.modal-backdrop').forEach((backdropEl) => {
    backdropEl.addEventListener('click', (e) => {
      if (e.target === backdropEl) closeModal(backdropEl);
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop:not([hidden])').forEach((m) => closeModal(m));
    }
  });

  // CSRF helper for fetch
  window.oemsFetch = async function (path, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
      || document.querySelector('input[name="_token"]')?.value
      || '';
    const headers = Object.assign(
      { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
      options.headers || {}
    );
    const res = await fetch(base + path, Object.assign({}, options, { headers }));
    const contentType = res.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      return res.json();
    }
    return res.text();
  };

  // Exam auto-save + timer + lockdown (attempt page)
  const examRoot = document.getElementById('examAttempt');
  if (examRoot) {
    const endsAt = parseInt(examRoot.dataset.endsAt, 10) * 1000;
    const timerEl = document.getElementById('examCountdown');
    const form = document.getElementById('examForm');
    const lockdown = examRoot.dataset.lockdown === '1';
    let saving = false;
    let dirty = false;
    let blurCount = 0;

    function formatTime(ms) {
      const total = Math.max(0, Math.floor(ms / 1000));
      const h = Math.floor(total / 3600);
      const m = Math.floor((total % 3600) / 60);
      const s = total % 60;
      return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':');
    }

    function tick() {
      if (!Number.isFinite(endsAt)) return;
      const left = endsAt - Date.now();
      if (timerEl) {
        timerEl.textContent = formatTime(left);
        const wrap = timerEl.parentElement;
        wrap?.classList.toggle('warn', left < 5 * 60 * 1000 && left >= 60 * 1000);
        wrap?.classList.toggle('danger', left < 60 * 1000);
      }
      if (left <= 0 && form) {
        const auto = document.createElement('input');
        auto.type = 'hidden';
        auto.name = 'auto_submit';
        auto.value = '1';
        form.appendChild(auto);
        form.submit();
      }
    }
    tick();
    setInterval(tick, 1000);

    form?.addEventListener('change', () => { dirty = true; updateAnswered(); });
    form?.addEventListener('input', () => { dirty = true; });

    async function autoSave() {
      if (!dirty || saving || !form) return;
      saving = true;
      const status = document.getElementById('saveStatus');
      if (status) status.textContent = 'Saving…';
      try {
        const fd = new FormData(form);
        fd.append('ajax_save', '1');
        const data = await window.oemsFetch('/api/exam_autosave.php', {
          method: 'POST',
          body: fd,
        });
        if (data && data.ok) {
          dirty = false;
          if (status) status.textContent = 'Saved ' + new Date().toLocaleTimeString();
        } else if (status) {
          status.textContent = 'Save failed';
        }
      } catch (err) {
        if (status) status.textContent = 'Offline — will retry';
      } finally {
        saving = false;
      }
    }
    setInterval(autoSave, 8000);

    function updateAnswered() {
      if (!form) return;
      document.querySelectorAll('.nav-pills [data-q]').forEach((btn) => {
        const qid = btn.getAttribute('data-q');
        const radio = form.querySelector(`input[name="answers[${qid}]"]:checked`);
        const text = form.querySelector(`textarea[name="answers[${qid}]"]`);
        const ok = !!radio || (text && text.value.trim() !== '');
        btn.classList.toggle('answered', ok);
      });
    }
    updateAnswered();

    document.querySelectorAll('.nav-pills [data-q]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = document.getElementById('q-' + btn.getAttribute('data-q'));
        target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelectorAll('.nav-pills button').forEach((b) => b.classList.remove('current'));
        btn.classList.add('current');
      });
    });

    // Fullscreen + tab/window leave warnings
    if (lockdown) {
      const banner = document.getElementById('examLockBanner');
      const overlay = document.getElementById('examLockOverlay');
      const countEl = document.getElementById('examBlurCount');
      const resumeBtn = document.getElementById('examResumeFs');

      function requestFs() {
        const el = document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
        if (req) {
          try { req.call(el); } catch (e) { /* ignore */ }
        }
      }

      function showWarning(msg) {
        blurCount += 1;
        if (countEl) countEl.textContent = String(blurCount);
        if (banner) {
          banner.classList.add('is-visible');
          const text = banner.querySelector('[data-lock-msg]');
          if (text) text.textContent = msg;
        }
        overlay?.classList.add('is-visible');
      }

      function hideOverlay() {
        overlay?.classList.remove('is-visible');
      }

      // Ask once on load
      setTimeout(requestFs, 400);

      resumeBtn?.addEventListener('click', () => {
        hideOverlay();
        requestFs();
      });

      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          showWarning('Tab switch detected. Stay on this exam page. Warnings: ' + blurCount);
        }
      });
      window.addEventListener('blur', () => {
        // Ignore brief blur from fullscreen prompt
        setTimeout(() => {
          if (!document.hasFocus() && !document.hidden) {
            showWarning('Window focus lost. Return to the exam. Warnings: ' + blurCount);
          }
        }, 300);
      });
      document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
          showWarning('Fullscreen exited. Re-enter fullscreen to continue securely.');
        } else {
          hideOverlay();
        }
      });

      // Soft block copy/paste / context menu during exam
      ['copy', 'cut', 'paste', 'contextmenu'].forEach((evt) => {
        document.addEventListener(evt, (e) => {
          e.preventDefault();
        });
      });
    }
  }
})();
