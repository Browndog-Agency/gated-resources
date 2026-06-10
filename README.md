# Gated Resources

WordPress plugin: a gated PDF resource library for Bartec Municipal. Visitors complete a
short form once to unlock the whole library for 30 days; leads are sent to HubSpot, with
Cloudflare Turnstile anti-spam. PDFs are served through a protected endpoint.

## Requirements
- WordPress 6.0+, PHP 7.4+
- For PDF cover thumbnails: Imagick PHP extension + Ghostscript (optional; falls back to
  featured image, then a placeholder)

## Setup
1. Install & activate the plugin.
2. **Resources → Settings**: enter HubSpot Portal ID + Form GUID, Turnstile site + secret keys,
   privacy policy URL, consent label + HubSpot subscription ID, unlock duration.
3. Add Resources (title, PDF upload, optional description). Optionally set a featured image as
   a thumbnail fallback.
4. Show the grid at `/resources/` or with the shortcode: `[gated_resources count="9" columns="3"]`.

## nginx note (protected files)
The protected directory ships an Apache `.htaccess` deny rule. On **nginx**, add:

```nginx
location ~* /wp-content/uploads/gated-resources/ { deny all; return 403; }
```

Files also live under an unguessable hashed path as defence in depth, and are only served
through the gated PHP endpoint (`?gr_file=ID`).

## Updating
This plugin self-updates from `Browndog-Agency/gated-resources`. To release:
1. Bump `Version:` in `gated-resources.php`.
2. `git tag vX.Y.Z && git push --tags`
3. Publish a GitHub **Release** for that tag.
WordPress will then show a one-click update.

## Development
```bash
composer install
composer test
```
