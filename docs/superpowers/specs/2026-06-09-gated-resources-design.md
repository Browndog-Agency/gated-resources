# Gated Resources — WordPress Plugin Design Spec

- **Date:** 2026-06-09
- **Client:** Bartec Municipal
- **Author:** Browndog Agency
- **Slug:** `gated-resources`
- **Status:** Approved for planning

## 1. Purpose & Overview

A self-contained WordPress plugin that provides a **gated resource library**. Visitors
browse a responsive grid of resources (PDFs). To access a resource's content they must
complete a short form once; on success the lead data is sent securely to HubSpot and the
visitor is granted access to the **entire** library for **30 days** without re-submitting.

Key properties:

- Custom form submitted server-side to the **HubSpot Forms Submissions API**.
- **Cloudflare Turnstile** spam protection, verified server-side.
- **True protected file delivery** — PDFs are never directly reachable by URL.
- **Global 30-day unlock** — fill the form once, access all resources.
- Auto-generated **PDF cover thumbnail** (page 1) with graceful fallbacks.
- ICO/UK-GDPR-correct **optional** marketing consent.
- Responsive grid (3 / 2 / 1 columns) with portrait covers, matching the Bartec Insights layout.
- **Self-updating** from a public GitHub repo (`Browndog-Agency/gated-resources`).

## 2. Confirmed Decisions

| Topic | Decision |
|-------|----------|
| HubSpot integration | Custom form → HubSpot Forms Submissions API (v3), server-side |
| Spam protection | Cloudflare Turnstile (server-side `siteverify`) + honeypot + nonce + rate-limit |
| Unlock scope | Global — one submission unlocks the whole library |
| Unlock duration | 30 days (configurable) |
| File security | True protected delivery via PHP endpoint + protected directory |
| Thumbnail | Auto-render PDF page 1 (Imagick + Ghostscript); fallback to featured image, then placeholder |
| Form fields | First name, last name, work email, organisation/council, job title |
| Consent | Optional, unticked opt-in checkbox + privacy-notice line (legitimate-interest delivery) |
| PDF delivery | Inline browser viewer + download button |
| Grid placement | CPT archive at `/resources/` + `[gated_resources]` shortcode |
| Meta fields | Native meta box (no ACF dependency) |
| Unlock storage | Custom DB table (`wp_gr_unlocks`) |
| Auto-update | Bundled plugin-update-checker (YahnisElsts) pointed at the public repo |

## 3. Content Model — Resources CPT

- CPT `gated_resource`, label **Resources**, `public => true`, `has_archive => true`,
  rewrite slug `resources`, supports `title` + `thumbnail` (featured image).
- Fields via a native meta box:
  - **Resource Title** → the WordPress post title.
  - **Resource PDF** → uploaded via a custom AJAX uploader that streams the file directly
    into the protected directory. Post meta stores `_gr_pdf_path` (relative), `_gr_pdf_name`
    (original filename), `_gr_pdf_size`.
  - **Resource Description** → optional textarea, stored in `_gr_description`.
  - **Cover preview** → generated path stored in `_gr_preview_image` (+ `_gr_preview_status`).

### Why a custom AJAX uploader (not the media-library picker)

1. Block-editor saves do not submit multipart `<form>` data, so a plain file input is unreliable.
2. Anything added to the media library lives at a public URL, which would defeat true protected
   delivery. The uploader posts the PDF to a REST/AJAX endpoint that writes it straight into the
   protected directory and returns metadata to a hidden field saved with the post.

## 4. Protected File Delivery

- Storage path: `wp-content/uploads/gated-resources/{random-hash}/{filename}.pdf`.
- On activation, create the base directory with defence in depth:
  - `.htaccess` containing `Require all denied` (with legacy `Deny from all`) for Apache.
  - `index.php` stub to prevent directory listing.
  - A per-file **random hashed sub-path** so the URL is unguessable even if a server ignores
    the deny rule (e.g. misconfigured nginx).
  - README documents the nginx `location` deny rule for the client's server.
- Access endpoint: `GET /?gr_file={resource_id}&disp={inline|download}` (rewrite-friendly).
  Handler logic:
  1. Read the access cookie token; validate against `wp_gr_unlocks` (exists, not expired).
  2. If invalid → `HTTP 403`.
  3. Resolve the resource's protected PDF path; stream via `readfile()` with headers:
     - `Content-Type: application/pdf`
     - `Content-Disposition: inline; filename="..."` (viewer) or `attachment; ...` (download)
     - `X-Content-Type-Options: nosniff`, no-cache headers.
  4. `exit`.

## 5. PDF Cover Thumbnail

On PDF upload (and via a per-resource "Regenerate thumbnail" button):

1. If `class_exists('Imagick')` and PDF rendering is available (Ghostscript):
   - `readImage(path . '[0]')` at ~150 dpi, flatten transparency, resize to a portrait
     preview (e.g. max width 600px), output JPG to a **public** previews directory
     (`wp-content/uploads/gated-resources-previews/`). Store URL/path + `status = generated`.
2. Wrap in try/catch. On any failure set `status = failed` and use the fallback chain.

**Fallback chain:** generated cover → post featured image → bundled styled SVG placeholder.

## 6. Gate & 30-Day Global Unlock

- A single resource page renders **either** the gate form (locked) **or** the inline viewer +
  download button (unlocked).
- On successful form submission:
  - Mint a random 32-byte hex token.
  - Insert a row into `wp_gr_unlocks`:
    `token`, `email`, `consent` (bool), `created_at`, `expires_at` (= now + unlock duration),
    `ip_hash` (hashed, for abuse auditing only).
  - Set cookie `gr_access=<token>` with attributes `HttpOnly; Secure; SameSite=Lax`,
    expiry = unlock duration.
- **Unlocked check** = cookie token present AND matched to a non-expired row. Global across all
  resources.
- A daily WP-Cron job prunes expired rows.

### Why a custom table (not transients)

Transients can be evicted early by an object cache (Redis/Memcached), which would break the
30-day guarantee. A custom table gives a reliable window, is queryable, and supports auditing
and cleanup. The table is created on activation and removed on uninstall.

## 7. Form Submission Pipeline (security-critical)

Front-end form fields: first name, last name, work email, organisation/council, job title,
optional unticked marketing-consent checkbox, privacy-notice line with policy link, Turnstile
widget, hidden honeypot field.

On AJAX submit, the server performs in order:

1. Verify WP nonce.
2. Check honeypot is empty.
3. Light per-IP rate-limit (transient counter).
4. **Verify Turnstile** token via `https://challenges.cloudflare.com/turnstile/v0/siteverify`
   (secret key server-side only). Reject on failure.
5. Validate + sanitise all fields (email required and valid).
6. **Submit to HubSpot** Forms Submissions API v3:
   `POST https://api.hsforms.com/submissions/v3/integration/submit/{portalId}/{formGuid}`
   - Map fields: org/council → `company`, job title → `jobtitle`, plus `firstname`,
     `lastname`, `email`.
   - Include `context.hutk` (HubSpot tracking cookie if present), `pageUri`, `pageName`.
   - Include `legalConsentOptions` **only** when the consent box is ticked.
7. On HubSpot success → create unlock row + set cookie → return the viewer markup (JSON).
8. On HubSpot failure → return an error; do not unlock.

Security summary: keys (Turnstile secret, HubSpot identifiers) live server-side; Turnstile is
verified before any HubSpot call; spam is filtered at multiple layers; only minimal PII is
stored locally.

## 8. Admin Settings Page

WordPress Settings API page (own top-level "Resources" submenu) with:

- HubSpot **Portal ID** and **Form GUID**.
- Cloudflare Turnstile **site key** and **secret key**.
- **Unlock duration** (days, default 30).
- **Privacy-policy URL** (for the consent/privacy line).
- **Consent label** + HubSpot legal-consent / subscription type ID.
- Optional thumbnail dpi/size.

Values stored in `wp_options` (standard for WordPress; documented).

## 9. Front-end Grid

- `archive-gated_resource.php` and `[gated_resources count="" columns=""]` shortcode render the
  same grid component.
- Layout: **3 columns desktop / 2 tablet / 1 mobile**, **portrait** covers
  (`aspect-ratio: 3/4`, `object-fit: cover`). Card = cover, purple title, teal "Read more"
  underline accent — matching the Bartec Insights screenshot.
- Templates are theme-overridable (`locate_template` / filter).
- Single template: title, description, cover, then gate form OR viewer + download.
- Scoped/prefixed CSS; accessible labels, alt text, and focus states.
- Brand colours sampled from the reference (dark indigo title, teal accent); exact hex to be
  confirmed against the live site during build.

## 10. Auto-Update from GitHub

- Bundle **plugin-update-checker** (YahnisElsts, MIT) in `lib/plugin-update-checker/`.
- Configure it against `https://github.com/Browndog-Agency/gated-resources` (public, no token).
- Release flow: bump the `Version:` header → `git tag vX.Y.Z` → publish a GitHub **Release**.
  WordPress then shows a one-click update in the dashboard.

## 11. Plugin Structure

```
gated-resources/
  gated-resources.php            # header, bootstrap, update-checker init
  uninstall.php                  # drop table, remove dirs/options
  includes/
    class-plugin.php             # bootstrap / DI
    class-cpt.php                # register CPT + meta box
    class-pdf-upload.php         # AJAX upload → protected dir
    class-thumbnail.php          # Imagick page-1 render + fallbacks
    class-protected-files.php    # storage paths, .htaccess, readfile endpoint
    class-gate.php               # unlock table, cookie/token, access checks
    class-form.php               # render form + AJAX submit handler
    class-hubspot.php            # Forms API client
    class-turnstile.php          # siteverify
    class-settings.php           # admin settings page
    class-shortcode.php          # [gated_resources]
    class-assets.php             # enqueue CSS/JS
    class-activator.php          # create table, dirs, .htaccess, flush rewrites
  templates/
    archive-gated_resource.php
    single-gated_resource.php
    parts/card.php
    parts/gate-form.php
    parts/viewer.php
  assets/
    css/gated-resources.css
    js/gated-resources.js        # front-end form submit + Turnstile
    js/admin-uploader.js         # admin PDF uploader
    images/placeholder.svg
  lib/plugin-update-checker/     # bundled (MIT)
  docs/                          # this spec + README/release notes
```

Namespace: `BrownDog\GatedResources`. One class per responsibility for isolation and testability.

## 12. Testing Strategy

- **Isolated PHPUnit** (WP_Mock / Brain Monkey) for the security-critical, logic-heavy units:
  - token generation, expiry, and access-check logic (`class-gate.php`),
  - Turnstile `siteverify` response parsing (`class-turnstile.php`),
  - HubSpot payload building incl. conditional `legalConsentOptions` (`class-hubspot.php`),
  - fallback-chain selection for thumbnails (`class-thumbnail.php`).
- **Manual QA checklist** for WP-integrated paths: upload → thumbnail → gate → submit →
  HubSpot record → unlock → viewer/download → 30-day persistence → expiry → uninstall cleanup,
  across desktop/tablet/mobile breakpoints.
- TDD applied where it adds value (token/access logic, payload building).

## 13. Privacy & GDPR

- Marketing consent is optional and unticked; content delivery does not depend on it
  (legitimate interest), avoiding bundled consent.
- Consent, when given, is recorded in HubSpot via `legalConsentOptions`.
- Local storage is minimal (email + hashed IP) and is removed on uninstall; expired rows are
  pruned by cron.
- Privacy-notice line with a configurable policy link appears on the form.

## 14. Out of Scope (YAGNI)

- Gutenberg block (shortcode covers embedding; can be added later).
- Resource categories/taxonomy filtering on the grid (can be added later).
- Multi-step / progressive profiling forms.
- Account-based (logged-in) gating — gate is for anonymous visitors via cookie/token.
