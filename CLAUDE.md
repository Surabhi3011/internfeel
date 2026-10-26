# CLAUDE.md

InternFeel (internfeel.com) relaunch: a fresh WordPress 7.1 site on PHP 8.5, hosted on VaporHost cPanel. It relaunches Monday 9 November 2026. All custom code is one theme, one plugin and one must-use plugin.

## Read first

| Doc | What it is |
| --- | --- |
| [docs/plan.md](docs/plan.md) | Scope, decisions table (section 4), feature specs with "Done when" tests (section 5), day-by-day schedule (section 7), launch checklist |
| [docs/architecture.md](docs/architecture.md) | Components, data model (section 4), flows, REST API, security, privacy |
| [docs/development.md](docs/development.md) | Repo layout, conventions, local dev, secrets, cPanel deploy, CI, runbook |

**Designs:** build every template from [docs/approved-designs/](docs/approved-designs/README.md) (Gate 1, signed off 26 October 2026); its README is the spec for each page. `docs/designs/` is superseded. The theme loads the design system as `assets/css/fonts.css`, `tokens.css` and `components.css` (copies of `docs/approved-designs/system/`, minus the Google Fonts import), then `style.css` for theme-only rules. Use the components' classes (`if-btn`, `if-field`, `if-field__help`, `if-segment`, `if-card`, ...) rather than new ones.

These are the approved brief. If a decision changes, update the decisions table in plan.md **in the same pull request** as the code.

`docs-private/environment.md` holds the private details: admin emails, the cPanel username, the server layout, and the other sites on the hosting account. It is gitignored and exists only in the working folder. Read it when you need those details, but never copy anything from it into the repo, a commit message, a PR or an issue.

## THIS REPO IS PUBLIC

The hosting plan has no SSH, so cPanel can only pull a public repo over HTTPS. Anyone can read every file, commit message and PR.

- **Before every commit**, run `bash tools/check-public.sh <private-values-file>`. The file lists private values (one per line) and lives outside the repo or in `docs-private/`. Also check the staged diff and the commit message yourself. CI runs the same check without the file.
- **Never commit:** personal email addresses, `/home/<user>` paths, the cPanel username, server hostnames, other sites on the account, AdSense or other account IDs, keys, tokens, salts or passwords.
- **Placeholders instead:** `$HOME` in `.cpanel.yml`, `dirname( __DIR__ )` in wp-config samples, `/home/<cpanel-user>` in docs, `ca-pub-0000000000000000`, `G-XXXXXXXXXX`. The only email addresses allowed are the public `contact@internfeel.com` and `no-reply@internfeel.com`, plus noreply addresses in commit metadata.
- Commits are authored with the GitHub noreply address (`git config user.email`), never a personal email.
- GitHub secret scanning and push protection are on. If push protection blocks a push, remove the secret from history; never bypass it.

## Where code goes

- `wp-content/themes/internfeel/`: classic PHP theme for every public page and the student dashboard. Presentation only.
- `wp-content/plugins/internfeel-core/`: all behaviour. Namespace `InternFeel\`, PSR-4 from `src/` (one folder per module: `src/Auth/`, `src/Invites/`, ...). Each module implements `InternFeel\Module` and is listed in `Plugin::modules()`. Runtime Composer packages (Dompdf, QR) are **committed** in the plugin's own `vendor/`, because the server has no Composer.
- `wp-content/mu-plugins/internfeel-hardening.php`: the Hardening module from architecture.md 3.2. It is a must-use plugin so it can't be switched off.
- Schema changes go through `InternFeel\Schema::steps()`, which runs on `init`. Steps are additive only and safe to run twice. The version is stored in `if_db_version`.
- REST routes live under `internfeel/v1` (`InternFeel\Support\Rest::NS`). Public forms (sign-up, log-in) check a same-origin request, the `if_auth` nonce, a honeypot field named `website` and a rate limit (`InternFeel\Support\RateLimit`). Signed-in routes use `Rest::require_login` plus the `wp_rest` nonce.
- Theme pages with forms or personal details call `if_no_page_cache()` so LiteSpeed never caches them.
- `tools/seed/` holds sample data (local and staging only). `tests/phpunit/` and `tests/e2e/` hold tests; tag accessibility tests `@a11y`.

## Conventions (development.md section 2)

- Prefixes: namespace `InternFeel\`; functions, hooks and options `if_`; meta keys `_if_`; tables `{prefix}if_`; constants `IF_`. Text domain `internfeel`.
- PHP: target 8.5, minimum 8.3. Use `declare(strict_types=1);` in plugin and mu-plugin code. Follow WPCS (`composer lint`) and PHPStan level 6 (`composer analyse`). WPCS's prefix sniff is off because it rejects prefixes shorter than 4 characters, so check `if_` yourself.
- Security: sanitise input, escape output, `$wpdb->prepare` for all SQL, and a nonce plus capability check on every write. Private meta (`proof_files`, `verification_note`, `verified_by`) uses `show_in_rest => false` and is never printed.
- Front end: plain ES modules (`wp_enqueue_script_module`), no jQuery, no build step. Design tokens from `assets/css/tokens.css`, mobile-first, no framework. Fonts are self-hosted WOFF2 (OFL licences beside them); never load Google Fonts.
- Accessibility: WCAG 2.2 AA. Brand teal `#339999` fails contrast on white for body text, so use `--if-teal-700` (`#297777`) for text, links and buttons. The logo has no reversed version, so it never sits on charcoal or dark grounds.
- Commits: conventional style, e.g. `feat(invites): single-use invite codes`, `fix(views): skip author views`.

## Branches and deploys

- `main` is production and `staging` is staging. Feature work goes on `feat/<name>`, with a PR into `staging`. **Only Surabhi merges into `main`.**
- Deploys go through cPanel Git Version Control, which clones the public repo over HTTPS, and `.cpanel.yml`, which picks the target folder from the branch (development.md section 6): `main` goes to `$HOME/public_html` and `staging` to `$HOME/public_html/staging.internfeel.com`. Never edit files in the server clones.
- Each build day ends with a working build on staging and a short PR note: what changed, how to check it, and what's next.

## Never

- Never commit `docs-private/`, `wp-config.php`, `.env*`, WordPress core, third-party plugins, uploads, proofs, certificates, database dumps, the old site's export (`tools/export/*.zip`, `*.tar`, `internfeel-content-export.md`), `old-site-backup/` or `holding-page/`.
- Never ask for passwords, API keys or OAuth secrets in chat. Say where to enter them on the server instead: `wp-config.php` through cPanel File Manager, or the plugin's own wp-admin settings (development.md section 4).
- Never run seed data on production, and never restore the old site's backup onto the new site.
- Never move, delete or overwrite the other sites' folders inside `public_html` (listed in `docs-private/environment.md`).

## Local development

```bash
npm install
npm run playground    # WordPress Playground on PHP 8.5, http://127.0.0.1:9400, no Docker
npx wp-env start      # Docker, http://localhost:8888 (admin / password); what CI uses
npm run test:e2e
npm run test:a11y
composer install && composer lint && composer analyse   # needs PHP 8.3+ and Composer
npm run test:unit     # PHPUnit inside wp-env; RESETS the wp-env database
```

Playground runs on SQLite, so anything database-specific (the moderation log, atomic view counts) must also pass on wp-env or staging.
