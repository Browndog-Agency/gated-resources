# Manual QA Checklist

## Setup
- [ ] Activate plugin; confirm `wp_gr_unlocks` table, `/uploads/gated-resources/.htaccess`,
      and `/uploads/gated-resources-previews/` exist.
- [ ] Settings save and persist (HubSpot, Turnstile, consent, unlock days).

## Authoring
- [ ] Create a Resource; upload a PDF via the meta box (async upload succeeds).
- [ ] Cover thumbnail generates from page 1 (or falls back to featured image / placeholder).
- [ ] Non-PDF and oversize uploads are rejected with a clear message.

## Gating + HubSpot
- [ ] Visiting a single resource while locked shows the form (with Turnstile).
- [ ] Submitting with Turnstile passing creates a HubSpot contact (check HubSpot).
- [ ] Consent ticked -> consent recorded in HubSpot; unticked -> no marketing consent.
- [ ] Invalid email / missing fields show inline validation.
- [ ] Honeypot-filled submission is blocked.
- [ ] After success, page reloads to the inline viewer + working download button.

## File protection
- [ ] Direct hit on the file's real `/uploads/gated-resources/...` URL is denied (403/blocked).
- [ ] `?gr_file=ID` returns 403 when no valid unlock cookie is present.
- [ ] `?gr_file=ID` streams the PDF when unlocked.

## Persistence
- [ ] After unlocking, other resources open without re-prompting (global unlock).
- [ ] Unlock persists across browser restart for 30 days (cookie + DB row).
- [ ] Manually expire a row -> access prompts the form again.

## Responsive
- [ ] Grid is 3 cols desktop / 2 tablet (<=1024px) / 1 mobile (<=600px).
- [ ] Covers are portrait, not distorted.

## Lifecycle
- [ ] Deactivate clears the cron event.
- [ ] Uninstall drops the table, options, dirs, and resource posts.
