/* ================================================
   YNSUMEDIC — site-config.js
   Single source of truth for public business data.
   Change these values once; main.js hydrates every
   element marked with data-cfg="…".
   ================================================ */

window.YNSUMEDIC = {
  /* ── Contact ─────────────────────────────────── */
  // TODO(owner): replace with the real landline / WhatsApp number.
  phoneDisplay: '+51 074 000 000',
  phoneE164:    '+51074000000',
  whatsapp:     '51074000000',

  email:   'info@ynsumedic.com',
  address: 'Chiclayo, Lambayeque — Perú',
  addressFull: '', // TODO(owner): street address, district and city
  hours:   'Lun–Vie: 8:00 am – 6:00 pm',

  /* ── Legal entity ────────────────────────────── */
  legalName: 'YNSUMEDIC S.A.C.',
  ruc: '', // TODO(owner): 11-digit RUC

  /* ── Libro de Reclamaciones ──────────────────── */
  // Internal page, or the provider URL if the owner hires one.
  libroUrl: '/reclamaciones.html',

  /* ── Social profiles ─────────────────────────── */
  social: {
    facebook:  'https://www.facebook.com/profile.php?id=61590267059171',
    instagram: 'https://www.instagram.com/ynsumedic/',
    tiktok:    'https://www.tiktok.com/@ynsumedic',
    youtube:   'https://www.youtube.com/@Ynsumedic'
  }
};
