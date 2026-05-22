/* ================================================
   YNSUMEDIC — main.js  |  Vanilla ES2020
   ================================================ */

document.addEventListener('DOMContentLoaded', () => {
  initNavbar();
  initMobileMenu();
  initReveal();
  initCounters();
  initMarquee();
  initFormValidation();
  initBackToTop();
  setActiveNavLink();
});

/* ── Navbar scroll ─────────────────────────── */
function initNavbar() {
  const nav = document.getElementById('navbar');
  if (!nav) return;
  const update = () => nav.classList.toggle('scrolled', window.scrollY > 55);
  window.addEventListener('scroll', update, { passive: true });
  update();
}

/* ── Mobile hamburger menu ──────────────────── */
function initMobileMenu() {
  const btn     = document.getElementById('hamburger');
  const mobileNav = document.getElementById('mobile-nav');
  if (!btn || !mobileNav) return;

  btn.addEventListener('click', () => {
    const open = mobileNav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
    const [s1, s2, s3] = btn.querySelectorAll('span');
    if (open) {
      s1.style.transform = 'translateY(7px) rotate(45deg)';
      s2.style.opacity   = '0';
      s3.style.transform = 'translateY(-7px) rotate(-45deg)';
    } else {
      s1.style.transform = s2.style.opacity = s3.style.transform = '';
    }
  });

  mobileNav.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      mobileNav.classList.remove('open');
      btn.querySelectorAll('span').forEach(s => s.removeAttribute('style'));
    });
  });
}

/* ── Scroll reveal (IntersectionObserver) ───── */
function initReveal() {
  const items = document.querySelectorAll('.reveal');
  if (!items.length) return;

  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -48px 0px' });

  items.forEach(el => io.observe(el));
}

/* ── Animated counters ──────────────────────── */
function initCounters() {
  const els = document.querySelectorAll('[data-count]');
  if (!els.length) return;

  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        runCounter(e.target);
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.5 });

  els.forEach(el => io.observe(el));
}

function runCounter(el) {
  const target   = parseInt(el.dataset.count, 10);
  const suffix   = el.dataset.suffix || '';
  const duration = 2200;
  const t0       = performance.now();

  (function tick(now) {
    const p = Math.min((now - t0) / duration, 1);
    const v = Math.round((1 - Math.pow(1 - p, 3)) * target);
    el.textContent = v.toLocaleString('es-PE') + suffix;
    if (p < 1) requestAnimationFrame(tick);
  })(t0);
}

/* ── Infinite logo marquee ──────────────────── */
function initMarquee() {
  document.querySelectorAll('.trust-marquee').forEach(mar => {
    const track = mar.querySelector('.trust-track');
    if (!track) return;
    const clone = track.cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    mar.appendChild(clone);
  });
}

/* ── Contact form validation ────────────────── */
function initFormValidation() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  /* Live feedback on blur */
  form.querySelectorAll('.form-ctrl').forEach(f => {
    f.addEventListener('blur',  () => validate(f));
    f.addEventListener('input', () => {
      if (f.value.trim()) f.closest('.form-group')?.classList.remove('error');
    });
  });

  form.addEventListener('submit', e => {
    e.preventDefault();
    let ok = true;

    form.querySelectorAll('[required]').forEach(f => {
      if (!validate(f)) ok = false;
    });

    if (ok) showSuccess(form);
  });
}

function validate(field) {
  const grp   = field.closest('.form-group');
  const val   = field.value.trim();
  let   valid = true;

  if (field.required && !val) {
    valid = false;
  } else if (field.type === 'email' && val) {
    valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
  } else if (field.name === 'ruc' && val) {
    valid = /^\d{11}$/.test(val);
  } else if (field.name === 'phone' && val) {
    valid = /^[\d\s\+\-\(\)]{7,15}$/.test(val);
  }

  grp?.classList.toggle('error', !valid);
  return valid;
}

function showSuccess(form) {
  const wrap = form.parentElement;
  form.remove();
  wrap.insertAdjacentHTML('beforeend', `
    <div class="form-success">
      <div class="form-success-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
      <h3>¡Solicitud enviada exitosamente!</h3>
      <p>Un especialista de YNSUMEDIC se pondrá en contacto con su empresa<br>en las próximas 24 horas hábiles.</p>
    </div>
  `);
}

/* ── Back to top button ──────────────────────── */
function initBackToTop() {
  const btn = document.getElementById('btt');
  if (!btn) return;
  window.addEventListener('scroll', () => btn.classList.toggle('visible', window.scrollY > 500), { passive: true });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

/* ── Mark active nav link ───────────────────── */
function setActiveNavLink() {
  const page = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nb-nav a, .nb-mobile a').forEach(a => {
    const href = a.getAttribute('href');
    if (href === page || (page === '' && href === 'index.html')) {
      a.classList.add('active');
    }
  });
}
