# YNSUMEDIC Web — Work Plan

Target: static site in this repo (HTML + `css/styles.css` + `js/main.js`), deployed on cPanel (the repo is cloned into the document root; "Update from Remote" is the deploy). Domain: https://ynsumedic.com

Site copy stays in Spanish (neutral/professional). Code, comments and commit messages in English. Conventional commits, no AI attribution, no `Co-Authored-By`.

## Ground rules

- No frameworks, no jQuery, no Bootstrap (see `Promt.md`). Keep the current visual style and CSS variables.
- One phase = one or more small commits. Do not push; the owner reviews and deploys.
- Never invent business data (phone, address, RUC, testimonials). Anything marked **[OWNER INPUT]** must be requested from the owner; until then leave a clearly marked placeholder and list it in the final report.
- Never commit secrets (SMTP passwords, API keys). Secrets live in a server-only file listed in `.gitignore`.
- Do not touch `_brand-source/` or the logo/favicon assets.

## Inputs needed from the owner (collect first)

| ID | Item |
|----|------|
| I1 | Real phone / WhatsApp number (replaces `+51 074 000 000` and `wa.me/51074000000`) |
| I2 | Full street address of the Chiclayo office (and Lima, if one exists; the home page mentions Lima stock) |
| I3 | Destination mailbox for quotes (default assumed: `comercial@ynsumedic.com`) |
| I4 | SMTP credentials or confirmation that cPanel `mail()` works (server-side only) |
| I5 | Link to the official Libro de Reclamaciones (virtual form or provider) |
| I6 | Legal entity data for terms/privacy: razón social, RUC, domicilio fiscal |
| I7 | Confirmation that the testimonial in `index.html` (~line 697) is real, with the client's permission |
| I8 | Blog articles (or approval to publish drafted ones) |

---

## Phase 1 — Forms actually send (BLOCKER)

Problem: `js/main.js:130` calls `preventDefault()` and `showSuccess()` with no network call. Leads are lost silently.

Forms affected: `#contact-form` in `index.html` (~855) and `contacto.html` (116); newsletter form in `blog.html` (191).

Tasks:
1. Create `api/contact.php` (POST only, JSON response):
   - Validate server-side: required fields, email format, RUC = 11 digits, phone pattern (mirror `validate()` in `main.js`).
   - Honeypot field + simple per-IP rate limit (file-based, e.g. 5 requests / 10 min).
   - Send via SMTP/`mail()` to I3. Subject includes the service requested. Reply-To = submitter email.
   - Read credentials from `api/config.smtp.php` (git-ignored). Commit `api/config.smtp.example.php` with dummy values.
   - Return `{ok:true}` or `{ok:false, errors:{...}}` with proper HTTP status.
2. Create `api/newsletter.php` (or reuse the same endpoint with a `type` field) storing/sending the subscription.
3. Update `main.js`: `fetch('api/contact.php', {method:'POST', body:new FormData(form)})`; show success only on `ok:true`; on failure show an inline error with the WhatsApp number as fallback; disable the button while sending.
4. Add a consent checkbox (opt-in) linked to `privacidad.html` on every form (Ley 29733).
5. `.htaccess`: block direct access to `api/config*.php`; keep `Disallow: /api/` out of robots only if the endpoint must stay crawlable (it should not — add `Disallow: /api/`).
6. Add `api/config.smtp.php` to `.gitignore`; confirm `cpanel.yml` copy step does not overwrite the server-side config (the file must live only on the server).

Acceptance: a test submission arrives in the mailbox with all fields; invalid input is rejected server-side; no secret is tracked by git (`git ls-files | rg smtp` shows only the example).

## Phase 2 — Real contact data (BLOCKER)

Needs I1, I2.
1. Replace `+51 074 000 000`, `wa.me/51074000000`, `tel:+51074000000` in `index.html` (~828, 962, 1004, 1031), `contacto.html` (97, 107, 194), and the JSON-LD block (`index.html` ~60–95: telephone, streetAddress, geo if present).
2. Use one source of truth where possible (a single constant list in the plan's final report is enough; no templating engine).
3. Complete the address in the footer, `contacto.html` and JSON-LD. Verify with `rg -n "000 000|51074000000"` → zero results.

## Phase 3 — Libro de Reclamaciones and legal pages (BLOCKER for compliance)

Needs I5, I6.
1. Replace every `href="#"` on "Libro de Reclamaciones" (footer of all 7 pages + `index.html` ~1011) with the real link (I5), `target="_blank" rel="noopener"`.
   - If the owner has no provider yet: create `reclamaciones.html` with the INDECOPI-compliant form (nombre, DNI/RUC, domicilio, bien/servicio, monto, reclamo vs queja, detalle, pedido) wired to `api/` and a confirmation copy sent to the customer. Flag that the owner must validate wording with legal counsel.
2. Create `terminos.html` (linked from the footer, currently 404). Same layout/head pattern as `privacidad.html`. Content from I6; do not copy legal text from other companies.
3. Review `privacidad.html` against Ley 29733: identify the data controller (I6), purposes, retention, ARCO rights contact.
4. Add both pages to `sitemap.xml`.

## Phase 4 — Fix broken internal links

1. `servicios.html`: add the missing section ids `asesoria`, `implementos`, `seguridad`, `suministros` (existing: `salud-ocupacional`, `equipamiento`). Either add the sections with real copy or retarget the home-page links to existing sections. Prefer retargeting if the owner does not provide copy.
2. Re-run a local link check after every phase (script in Phase 8).
3. `404.html`: verify it uses root-absolute asset paths so it renders from any depth.

## Phase 5 — Blog

Needs I8.
1. Replace the six `href="#"` "Leer artículo" links in `blog.html` (97–179). Two options, pick based on I8:
   - Real articles: create `blog/<slug>.html` per article (own `<title>`, description, canonical, `Article` JSON-LD, breadcrumb), link from the list and from the home blog cards (`index.html` ~736), add to the sitemap.
   - No articles yet: hide the unfinished cards and show only what exists. Do not ship dead links.
2. Newsletter form → Phase 1 endpoint.

## Phase 6 — Content and media

1. Hero video: either add `assets/videos/hero-corporate.mp4` (owner provides, ≤ 4 MB, with poster image) and uncomment the block (`index.html` ~182), or delete the commented block. Do not leave dead references.
2. Testimonial (I7): keep only if confirmed; otherwise remove the section or mark for replacement.
3. Product/service pages: ask the owner for real photos; until then keep current visuals. If images are added: WebP, explicit `width`/`height`, `loading="lazy"` below the fold, descriptive `alt`.
4. Remove claims that cannot be substantiated (check "Más de 8 años", "certificación DIGEMID", "MINEM" statements in `nosotros.html` and `index.html` ~559 with the owner).

## Phase 7 — SEO and metadata hygiene

1. Every page: unique `<title>`, `<meta name="description">`, `<link rel="canonical">`, Open Graph/Twitter tags (already unified in commit `cab395f`; verify the new pages follow it).
2. One `<h1>` per page (already true; keep it for new pages).
3. `sitemap.xml`: update `lastmod` to the real date of change; add `terminos.html`, `reclamaciones.html` (if created), blog articles.
4. JSON-LD on home: `Organization`/`LocalBusiness` with the real phone/address from Phase 2; validate with Google's Rich Results Test (manual step for the owner).
5. Submit the sitemap in Google Search Console (manual, owner).

## Phase 8 — QA and release

1. Local link/asset check (write a throwaway script outside the repo or in `scripts/`, which is already excluded from deploy):
   - every `href`/`src` pointing to a local file exists;
   - every `#anchor` has a matching `id`;
   - no `href="#"` left outside JS-driven controls.
2. Placeholder sweep: `rg -n "000 000|51074000000|href=\"#\"|TODO|lorem"` must be empty (except intentional JS toggles).
3. Manual browser pass at 375 px, 768 px, 1280 px: nav, forms (valid + invalid), success/error states, WhatsApp button, footer links.
4. Lighthouse (mobile) on `index.html`: record Performance/SEO/Accessibility; fix regressions introduced by this plan only.
5. Final report to the owner: what changed per phase, unresolved **[OWNER INPUT]** items, and the deploy steps:
   - commit and push;
   - cPanel → Git Version Control → "Update from Remote";
   - create `api/config.smtp.php` on the server by hand;
   - send a real test quote and test the Libro de Reclamaciones flow.

## Housekeeping (low priority)

- `Contexto.md` is a copy of the Life360 plan and does not describe Ynsumedic. Replace it with a short, accurate project context (sector, products/services, offices, compliance notes) or delete it. Ask the owner first.
- `cpanel.yml` copies with `cp -R *`; confirm with the owner how cPanel is wired (a misconfigured deploy file can overwrite another site's document root). Do not change it without confirmation.

## Order of execution

1 → 2 → 3 (blockers, ship together) → 4 → 5 → 6 → 7 → 8. Phases 1–3 should be deployed before anything else, because the site currently loses every lead.
