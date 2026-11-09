/* ================================================
   YNSUMEDIC — main.js  |  Vanilla ES2020
   ================================================ */

document.addEventListener('DOMContentLoaded', () => {
  hydrateSiteConfig();
  initNavbar();
  initMobileMenu();
  initReveal();
  initCounters();
  initMarquee();
  initAjaxForms();
  initBackToTop();
  setActiveNavLink();
});

/* ── Business data hydration ────────────────── */
function hydrateSiteConfig() {
  const cfg = window.YNSUMEDIC || {};
  if (!Object.keys(cfg).length) return;

  const digits = (v) => String(v || '').replace(/[^\d]/g, '');

  document.querySelectorAll('[data-cfg]').forEach((el) => {
    const key = el.dataset.cfg;

    if (key === 'phone') {
      if (cfg.phoneDisplay) el.textContent = cfg.phoneDisplay;
      if (el.tagName === 'A' && cfg.phoneE164) el.href = 'tel:' + cfg.phoneE164;
    }

    if (key === 'whatsapp' && el.tagName === 'A' && cfg.whatsapp) {
      const text = el.dataset.waText || '';
      el.href = 'https://wa.me/' + digits(cfg.whatsapp) + (text ? '?text=' + encodeURIComponent(text) : '');
    }

    if (key === 'email') {
      if (cfg.email) el.textContent = cfg.email;
      if (el.tagName === 'A' && cfg.email) el.href = 'mailto:' + cfg.email;
    }

    if (key === 'address') {
      const value = cfg.addressFull || cfg.address;
      if (value) el.textContent = value;
    }
    if (key === 'address-short' && cfg.addressShort) el.textContent = cfg.addressShort;
    if (key === 'hours' && cfg.hours) el.textContent = cfg.hours;

    if (key === 'legal') {
      if (cfg.legalName) el.textContent = cfg.legalName;
    }

    if (key === 'ruc') {
      if (cfg.ruc) {
        el.textContent = cfg.ruc;
      } else {
        el.closest('[data-optional]')?.remove();
      }
    }

    if (key === 'libro' && el.tagName === 'A' && cfg.libroUrl) {
      el.href = cfg.libroUrl;
      if (/^https?:/i.test(cfg.libroUrl)) {
        el.target = '_blank';
        el.rel = 'noopener noreferrer';
      }
    }

    if (key === 'social' && el.tagName === 'A') {
      const url = cfg.social && cfg.social[el.dataset.network];
      if (url) el.href = url;
    }
  });

  /* Links whose visible label is not the raw number still need the
     real destination, so hydrate them by attribute/href. */
  document.querySelectorAll('a[href^="tel:"]').forEach((a) => {
    if (cfg.phoneE164) a.href = 'tel:' + cfg.phoneE164;
  });

  document.querySelectorAll('a[href*="wa.me/"]').forEach((a) => {
    if (!cfg.whatsapp) return;
    const text = a.dataset.waText || new URL(a.href, location.href).searchParams.get('text') || '';
    a.href = 'https://wa.me/' + digits(cfg.whatsapp) + (text ? '?text=' + encodeURIComponent(text) : '');
  });

  document.querySelectorAll('a[href^="mailto:"]').forEach((a) => {
    if (cfg.email) a.href = 'mailto:' + cfg.email;
  });
}

/* ── Navbar scroll ─────────────────────────── */
function initNavbar() {
  const nav = document.getElementById('navbar');
  if (!nav) return;
  /* Light-hero pages: keep the bar solid from the start,
     because white logo/links would not be readable. */
  if (nav.hasAttribute('data-nav-solid')) {
    nav.classList.add('scrolled');
    return;
  }
  const update = () => nav.classList.toggle('scrolled', window.scrollY > 55);
  window.addEventListener('scroll', update, { passive: true });
  update();
}

/* ── Mobile hamburger menu ──────────────────── */
function initMobileMenu() {
  const btn = document.getElementById('hamburger');
  const mobileNav = document.getElementById('mobile-nav');
  if (!btn || !mobileNav) return;

  btn.addEventListener('click', () => {
    const open = mobileNav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
    const [s1, s2, s3] = btn.querySelectorAll('span');
    if (open) {
      s1.style.transform = 'translateY(7px) rotate(45deg)';
      s2.style.opacity = '0';
      s3.style.transform = 'translateY(-7px) rotate(-45deg)';
    } else {
      s1.style.transform = s2.style.opacity = s3.style.transform = '';
    }
  });

  mobileNav.querySelectorAll('a').forEach((a) => {
    a.addEventListener('click', () => {
      mobileNav.classList.remove('open');
      btn.querySelectorAll('span').forEach((s) => s.removeAttribute('style'));
    });
  });
}

/* ── Scroll reveal (IntersectionObserver) ───── */
function initReveal() {
  const items = document.querySelectorAll('.reveal');
  if (!items.length) return;

  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -48px 0px' });

  items.forEach((el) => io.observe(el));
}

/* ── Animated counters ──────────────────────── */
function initCounters() {
  const els = document.querySelectorAll('[data-count]');
  if (!els.length) return;

  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        runCounter(e.target);
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.5 });

  els.forEach((el) => io.observe(el));
}

function runCounter(el) {
  const target = parseInt(el.dataset.count, 10);
  const suffix = el.dataset.suffix || '';
  const duration = 2200;
  const t0 = performance.now();

  (function tick(now) {
    const p = Math.min((now - t0) / duration, 1);
    const v = Math.round((1 - Math.pow(1 - p, 3)) * target);
    el.textContent = v.toLocaleString('es-PE') + suffix;
    if (p < 1) requestAnimationFrame(tick);
  })(t0);
}

/* ── Infinite logo marquee ──────────────────── */
function initMarquee() {
  document.querySelectorAll('.trust-marquee').forEach((mar) => {
    const track = mar.querySelector('.trust-track');
    if (!track) return;
    const clone = track.cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    mar.appendChild(clone);
  });
}

/* ── AJAX forms (contact, newsletter, reclamaciones) ── */
function initAjaxForms() {
  const forms = document.querySelectorAll('form[data-ajax]');
  if (!forms.length) return;

  forms.forEach((form) => {
    form.querySelectorAll('.form-ctrl').forEach((field) => {
      field.addEventListener('blur', () => validateField(field, form));
      field.addEventListener('input', () => clearFieldError(field, form));
      field.addEventListener('change', () => clearFieldError(field, form));
    });

    form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach((field) => {
      field.addEventListener('change', () => clearFieldError(field, form));
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      submitAjaxForm(form);
    });
  });
}

async function submitAjaxForm(form) {
  clearAlert(form);

  let firstInvalid = null;
  form.querySelectorAll('.form-ctrl, input[type="checkbox"][required], input[type="radio"][required]').forEach((field) => {
    if (field.type === 'radio' && field.name) {
      if (form.querySelector(`input[name="${cssEscape(field.name)}"]:checked`)) return;
    }
    if (!validateField(field, form) && !firstInvalid) firstInvalid = field;
  });

  if (firstInvalid) {
    firstInvalid.focus({ preventScroll: true });
    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    showAlert(form, 'Revise los campos marcados en rojo antes de enviar.');
    return;
  }

  const endpoint = form.getAttribute('action') || form.dataset.endpoint;
  if (!endpoint) {
    showAlert(form, 'No se configuró el destino del formulario.');
    return;
  }

  const button = form.querySelector('[type="submit"]');
  const original = button ? button.innerHTML : '';
  if (button) {
    button.disabled = true;
    button.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span> Enviando…';
  }

  try {
    const data = new FormData(form);
    if (!data.has('page')) data.append('page', location.pathname + location.search);

    const response = await fetch(endpoint, {
      method: 'POST',
      body: data,
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok || !payload.ok) {
      if (payload.errors) {
        Object.entries(payload.errors).forEach(([name, message]) => {
          markServerError(form, name, message);
        });
      }
      showAlert(
        form,
        payload.message || 'No pudimos enviar el formulario. Inténtelo nuevamente o escríbanos por WhatsApp.',
        payload.errors ? 'Revise los datos marcados.' : ''
      );
      if (payload.errors) {
        const firstKey = Object.keys(payload.errors)[0];
        form.querySelector(`[name="${cssEscape(firstKey)}"]`)?.focus({ preventScroll: true });
      }
      return;
    }

    showFormSuccess(form, payload);
  } catch (error) {
    showAlert(form, 'No se pudo conectar con el servidor. Revise su conexión o escríbanos por WhatsApp.');
  } finally {
    if (button && document.body.contains(button)) {
      button.disabled = false;
      button.innerHTML = original;
    }
  }
}

/* ── Field validation ───────────────────────── */
function validateField(field, form) {
  if (!field || field.disabled) return true;
  if (field.type === 'hidden') return true;

  const value = (field.value || '').trim();
  const name = field.name || field.id;
  let valid = true;

  if (field.type === 'radio') {
    valid = !!form.querySelector(`input[name="${cssEscape(name)}"]:checked`);
  } else if (field.type === 'checkbox') {
    valid = !field.required || field.checked;
  } else if (field.required && value === '') {
    valid = false;
  } else if (value !== '') {
    if (field.type === 'email' || name === 'email') {
      valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    } else if (name === 'ruc' || (name === 'doc_number' && form.querySelector('[name="doc_type"]')?.value === 'RUC')) {
      valid = /^\d{11}$/.test(value.replace(/\D/g, ''));
    } else if (name === 'doc_number') {
      const docType = form.querySelector('[name="doc_type"]')?.value || 'DNI';
      const clean = value.replace(/[^A-Za-z0-9]/g, '');
      valid = docType === 'DNI' ? /^\d{8}$/.test(clean) : clean.length >= 6 && clean.length <= 20;
    } else if (name === 'phone' || name === 'telefono') {
      valid = /^[\d\s\+\-\(\)]{7,16}$/.test(value);
    } else if (['requerimiento', 'detalle'].includes(name)) {
      valid = value.length >= (name === 'detalle' ? 20 : 10);
    } else if (name === 'pedido') {
      valid = value.length >= 10;
    } else if (name === 'empresa' || name === 'nombre' || name === 'bien_desc') {
      valid = value.length >= 3;
    } else if (name === 'domicilio') {
      valid = value.length >= 5;
    } else if (name === 'monto') {
      valid = /^\d{1,9}([.,]\d{1,2})?$/.test(value);
    }
  }

  const group = field.closest('.form-group');
  if (group) group.classList.toggle('error', !valid);
  if (valid) clearFieldError(field, form);
  return valid;
}

function clearFieldError(field, form) {
  const group = field.closest('.form-group');
  if (!group) return;
  if (field.type === 'radio' && field.name) {
    const checked = form.querySelector(`input[name="${cssEscape(field.name)}"]:checked`);
    if (!checked) return;
  }
  if (field.type === 'checkbox' && field.required && !field.checked) return;
  group.classList.remove('error');
}

function markServerError(form, name, message) {
  const field = form.querySelector(`[name="${cssEscape(name)}"]`);
  const target = field || form.querySelector(`#${cssEscape(name)}`);
  const group = target?.closest('.form-group');
  if (!group) return;
  group.classList.add('error');
  const err = group.querySelector('.form-err');
  if (err) err.textContent = message;
}

/* ── Alerts / success states ────────────────── */
function clearAlert(form) {
  form.querySelectorAll('.form-alert').forEach((el) => el.remove());
}

function showAlert(form, message, detail) {
  clearAlert(form);
  const alert = document.createElement('div');
  alert.className = 'form-alert form-alert--err';
  alert.setAttribute('role', 'alert');
  alert.innerHTML =
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
    '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
    '<div><strong>' + escapeHtml(message) + '</strong>' +
    (detail ? '<br><span>' + escapeHtml(detail) + '</span>' : '') +
    ' <a href="' + whatsappUrl('Hola, necesito ayuda con el formulario de la web.') + '" target="_blank" rel="noopener noreferrer">Escribir por WhatsApp</a></div>';
  form.prepend(alert);
  alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function showFormSuccess(form, payload) {
  const wrap = form.parentElement;
  const title = form.dataset.successTitle || '¡Solicitud enviada exitosamente!';
  const message = form.dataset.successMsg ||
    'Un especialista de YNSUMEDIC se pondrá en contacto con usted en las próximas 24 horas hábiles.';
  const compact = form.dataset.successStyle === 'compact';

  form.remove();
  wrap.querySelectorAll('[data-form-title]').forEach((el) => el.remove());

  const box = document.createElement('div');
  box.className = 'form-success' + (compact ? ' form-success--compact' : '') + (form.dataset.successStyle === 'dark' ? ' form-success--dark' : '');
  box.setAttribute('role', 'status');

  let refHtml = '';
  if (payload.referencia) {
    refHtml = '<p class="form-success-ref">N.º de constancia: <strong>' + escapeHtml(payload.referencia) + '</strong></p>';
  }

  /* The copy to the customer can be rejected by the mail server even when
     the internal notification succeeded: warn instead of hiding it. */
  let mailWarnHtml = '';
  if (payload.emailSent === false || payload.copySent === false) {
    mailWarnHtml =
      '<p class="form-success-warn">No pudimos enviar el correo de confirmación a su dirección. ' +
      'Guarde o anote el número de constancia y compártalo con nosotros por WhatsApp o teléfono.</p>';
  }

  box.innerHTML =
    '<div class="form-success-ico">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">' +
    '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
    '</div>' +
    '<h3>' + escapeHtml(title) + '</h3>' +
    '<p>' + escapeHtml(payload.message || message) + '</p>' +
    refHtml +
    mailWarnHtml +
    '<p class="form-success-note">' + escapeHtml(message) + '</p>';

  wrap.appendChild(box);
  box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ── Helpers ────────────────────────────────── */
function whatsappUrl(text) {
  const number = String(window.YNSUMEDIC?.whatsapp || '').replace(/\D/g, '');
  return 'https://wa.me/' + number + (text ? '?text=' + encodeURIComponent(text) : '');
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function cssEscape(value) {
  if (window.CSS && CSS.escape) return CSS.escape(value);
  return String(value).replace(/["\\]/g, '\\$&');
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
  const inBlog = /\/blog\//.test(location.pathname);

  document.querySelectorAll('.nb-nav a, .nb-mobile a').forEach((a) => {
    const href = a.getAttribute('href') || '';
    if (/^https?:/i.test(href)) return;
    const file = href.split('#')[0].split('?')[0].split('/').pop();
    if (file === page || (inBlog && file === 'blog.html')) {
      a.classList.add('active');
    }
  });
}
