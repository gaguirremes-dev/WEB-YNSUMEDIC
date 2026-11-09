/* ================================================
   YNSUMEDIC — site-config.js
   Single source of truth for public business data.
   Change these values once; main.js hydrates every
   element marked with data-cfg="…".
   ================================================ */

window.YNSUMEDIC = {
  /* ── Contact ─────────────────────────────────── */
  phoneDisplay: '943 704 298',
  phoneE164:    '+51943704298',
  whatsapp:     '51943704298',

  email:   'comercial@ynsumedic.com',
  address: 'Av. República de Chile Nro. 324, Int. 601, Urb. Santa Beatriz (Edificio Polaris), Jesús María, Lima',
  addressShort: 'Jesús María, Lima — Perú',
  hours:   'Lun–Vie: 8:00 am – 6:00 pm',

  /* ── Legal entity ────────────────────────────── */
  // The owner has not registered a legal entity or RUC yet.
  legalName: 'YNSUMEDIC',
  ruc: '',

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
