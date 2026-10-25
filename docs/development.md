# InternFeel development guide

How the InternFeel site is built, tested, deployed and run. Read [architecture.md](architecture.md) first for what the parts are, and [plan.md](plan.md) for scope and schedule.

**Builder:** Claude Code, working in the **public** GitHub repo `Surabhi3011/internfeel`.
**Reviewers and admins:** Surabhi Gupta, Arjit Raj.

> **The repo is public.** It must never contain passwords, keys, personal emails, the cPanel username, or details of other sites on the hosting account. Those live in `docs-private/environment.md`, which is listed in `.gitignore` and stays only in the working folder and the team's Project. Docs refer to the cPanel user as `<cpanel-user>`.

---

## 1. Repository layout

```
internfeel/
├── wp-content/
│   ├── themes/internfeel/              # custom theme (public pages, dashboard)
│   │   ├── assets/{css,js,img,fonts}/
│   │   ├── inc/                        # template helpers, ads.php
│   │   ├── template-parts/             # cards, key-facts box, filters, badges
│   │   ├── *.php                       # templates (see architecture.md 3.1)
│   │   ├── style.css                   # theme header + design tokens
│   │   └── theme.json
│   ├── plugins/internfeel-core/        # all custom behaviour
│   │   ├── internfeel-core.php         # bootstrap
│   │   ├── src/                        # one folder per module (Auth, Submit, Verify, Invites, ...)
│   │   ├── templates/                  # email and certificate templates
│   │   ├── vendor/                     # Composer dependencies, committed (no Composer on the server)
│   │   └── uninstall.php
│   └── mu-plugins/internfeel-hardening.php
├── tools/
│   ├── export/                         # notes and scripts from the 25 Oct 2026 content export
│   └── seed/                           # sample users and experiences for local and staging
├── tests/
│   ├── phpunit/                        # unit and integration tests (wp-env)
│   └── e2e/                            # Playwright end-to-end tests
├── dev/
│   ├── blueprint.json                  # WordPress Playground setup
│   └── wp-config-sample-internfeel.php # constants each environment must set
├── docs/                               # architecture.md, plan.md, development.md (public)
├── docs-private/                       # environment.md: emails, cPanel user, server layout (gitignored)
├── .github/workflows/ci.yml
├── .cpanel.yml                         # cPanel deployment tasks
├── .wp-env.json
├── composer.json                       # dev tools: PHPCS, PHPStan, PHPUnit
├── package.json                        # dev tools: Playwright, wp-env, Playground CLI
├── phpcs.xml.dist
└── README.md
```

**Never committed:** `docs-private/`, `wp-config.php`, `.env` files, the WordPress core, third-party plugins, uploads, proofs, certificates, database dumps, the old site's export or backup, and `holding-page/`.

**Public-repo safeguards:** turn on GitHub **secret scanning** and **push protection** (Settings → Code security). CI also fails if it finds an email address, `/home/` path or key-like string outside the allowed placeholders.

---

## 2. Conventions

| Topic | Rule |
| --- | --- |
| Prefixes | PHP namespace `InternFeel\`; functions, hooks and options `if_`; meta keys `_if_`; custom tables `{prefix}if_` |
| Text domain | `internfeel` (English only for now, but strings stay translatable) |
| PHP | Target PHP 8.5, minimum 8.3; strict types in plugin code |
| Style | WordPress Coding Standards (PHPCS), PHPStan level 6 |
| Security | Sanitise input, escape output, `$wpdb->prepare` for all SQL, nonce and capability check on every write |
| JavaScript | Plain ES modules, no jQuery, no build step |
| CSS | Custom properties for tokens; mobile-first; no CSS framework |
| Accessibility | WCAG 2.2 AA: labels on every input and icon button, visible focus, contrast checked |
| Commits | Conventional style: `feat(invites): single-use invite codes`, `fix(views): skip author views` |
| Branches | `main` deploys to production; `staging` deploys to staging; feature work in `feat/<name>`, merged into `staging` first |

---

## 3. Local development

Two options. Both need **Node.js 20 or newer** and **Git**.

### 3.1 WordPress Playground (no Docker; best for Claude Code and quick checks)

```bash
npm install
npx @wp-playground/cli@latest server \
  --wp=latest --php=8.4 \
  --mount=./wp-content/themes/internfeel:/wordpress/wp-content/themes/internfeel \
  --mount=./wp-content/plugins/internfeel-core:/wordpress/wp-content/plugins/internfeel-core \
  --blueprint=./dev/blueprint.json \
  --login
```

- `dev/blueprint.json` installs the free plugins from architecture.md 3.3, activates the theme and plugin, sets pretty permalinks, and runs `tools/seed` to create sample students, organisations and experiences.
- Playground uses SQLite instead of MySQL, so database-specific code (the moderation log table, atomic view updates) must also pass on wp-env or staging.
- To confirm on day 1: the exact Playground flags, and whether it offers PHP 8.5. Until then, use 8.4 locally and test PHP 8.5 on staging.

### 3.2 wp-env (Docker; matches production more closely, used in CI)

```bash
npm install
npx wp-env start          # site on http://localhost:8888, admin / password
npx wp-env run cli wp plugin list
npx wp-env stop
```

`.wp-env.json` pins WordPress 7.0 and maps the theme and plugin. It lists the free plugins by their WordPress.org zip URLs and sets `phpVersion` to the newest supported version. To confirm on day 1: whether wp-env offers PHP 8.5.

### 3.3 Seed data

`tools/seed` creates 2 admins, 10 students (including one under 18), 20 organisations and programmes, and 30 experiences across all statuses, verification states and referral cases. Run it on local and staging only, never on production.

---

## 4. Configuration and secrets

Each environment's `wp-config.php` sets these constants. They are listed, with fake values, in `dev/wp-config-sample-internfeel.php`.

| Constant | Purpose |
| --- | --- |
| `WP_ENVIRONMENT_TYPE` | `local`, `staging` or `production` |
| `DISALLOW_FILE_EDIT` | `true` |
| `IF_PRIVATE_DIR` | Absolute path to private storage, e.g. `/home/<cpanel-user>/internfeel-private` (production) or `/home/<cpanel-user>/internfeel-private-staging` |
| `IF_MAIL_FROM`, `IF_MAIL_FROM_NAME` | Sender of the password-reset email: `no-reply@internfeel.com`, `InternFeel` (sent through cPanel mail) |
| `IF_TURNSTILE_SITE_KEY`, `IF_TURNSTILE_SECRET` | Cloudflare Turnstile, after launch; leave unset until then and the forms skip the check |
| `IF_ADSENSE_CLIENT` | `ca-pub-…`; empty on staging so no ads show |
| `IF_GA4_ID` | Production only |

Google sign-in keys are stored in Nextend Social Login's own settings in wp-admin. Each environment gets its own OAuth redirect URI.

**Rules for secrets:**
- They live only on the server and in the owners' password manager; never in the repo, chat or tickets.
- Rotate the Turnstile secret and Google OAuth secret if anyone who had access leaves.

---

## 5. Server setup (one time, on VaporHost)

Done on day 1 with Surabhi's cPanel access.

1. **Old site, done on 25 October 2026:**
   - full cPanel backup downloaded and kept offline;
   - old WordPress files moved out of `public_html` into `old-site-quarantine`, to be deleted after the virus scan;
   - cPanel password changed;
   - holding page `public_html/index.html` live;
   - `public_html/.htaccess` returns "503 temporarily unavailable" with `Retry-After` for every internfeel.com address except `/index.html` and `/.well-known/`, so search engines keep the old pages listed;
   - the old database is kept until the migration is confirmed on staging (about 30 October), then deleted. Its user's password has been changed.

   **On launch day, delete the holding `index.html` and `.htaccess` before installing production WordPress.** Another website's folder also lives in `public_html`; never move or delete it.
2. **PHP:** set PHP 8.5 (`ea-php85`) for `internfeel.com` and the staging subdomain in MultiPHP Manager. Set `upload_max_filesize` and `post_max_size` to 16M, `memory_limit` to 256M and `max_execution_time` to 120.
3. **Subdomain:** create `staging.internfeel.com`. This host only allows document roots inside `public_html`, so staging lives at `/home/<cpanel-user>/public_html/staging.internfeel.com`. Run AutoSSL first, then protect the folder with Directory Privacy (the password prompt can block certificate checks).
   - `public_html` also holds folders for other sites on the account (listed in `docs-private/environment.md`). Never move, delete or overwrite them, including when production WordPress is installed in `public_html`.
   - Private storage (`internfeel-private`, `internfeel-private-staging`) stays in the home folder, outside `public_html`.
4. **Databases:** create one MySQL database and user per environment, with a strong password.
5. **WordPress:** install WordPress 7.0 fresh in each document root, using the cPanel installer or a manual upload. Use new admin accounts and new salts, and a table prefix other than `wp_`.
6. **Private storage:** create `internfeel-private` and `internfeel-private-staging` in the home folder, outside `public_html`.
7. **Free plugins:** install LiteSpeed Cache, Wordfence, Two-Factor, Yoast SEO, Nextend Social Login, CookieYes and UpdraftPlus. Simple Cloudflare Turnstile is added after launch.
8. **Email:** create the `contact@internfeel.com` (public) and `no-reply@internfeel.com` mailboxes. In cPanel → **Email Deliverability**, fix anything flagged for internfeel.com (SPF and DKIM). DNS is managed in the VaporHost cPanel Zone Editor, so Email Deliverability can apply the records directly.
9. **Git:** connect the repository (section 6).

---

## 6. Deployment through cPanel Git Version Control

The server has no Node.js and the plan has no SSH access. Because the repo is public, cPanel clones it over HTTPS with no key, then runs the tasks in `.cpanel.yml`.

### 6.1 One-time setup

In cPanel **Git Version Control**, choose **Create**, turn on **Clone a Repository**, and clone `https://github.com/Surabhi3011/internfeel.git` twice:

1. **Staging (day 1):** repository path `repositories/internfeel-staging`, name `internfeel-staging`. After cloning, open **Manage**, set **Checked-Out Branch** to `staging`, and click **Update**.
2. **Production (day 14):** repository path `repositories/internfeel`, name `internfeel`, branch `main`.

If the plan ever gets SSH access, the repo can go private: add a cPanel SSH key as a read-only GitHub deploy key and re-clone over `git@github.com:`.

### 6.2 `.cpanel.yml`

The same file in both branches; the target folder is picked from the checked-out branch. It uses `$HOME`, so no account name appears in the public repo.

```yaml
---
deployment:
  tasks:
    - export BRANCH=$(git rev-parse --abbrev-ref HEAD)
    - if [ "$BRANCH" = "main" ]; then export DOCROOT=$HOME/public_html; else export DOCROOT=$HOME/public_html/staging.internfeel.com; fi
    - /bin/mkdir -p $DOCROOT/wp-content/themes/internfeel $DOCROOT/wp-content/plugins/internfeel-core $DOCROOT/wp-content/mu-plugins
    - /bin/rm -rf $DOCROOT/wp-content/themes/internfeel.new $DOCROOT/wp-content/plugins/internfeel-core.new
    - /bin/cp -R wp-content/themes/internfeel $DOCROOT/wp-content/themes/internfeel.new
    - /bin/cp -R wp-content/plugins/internfeel-core $DOCROOT/wp-content/plugins/internfeel-core.new
    - /bin/rm -rf $DOCROOT/wp-content/themes/internfeel && /bin/mv $DOCROOT/wp-content/themes/internfeel.new $DOCROOT/wp-content/themes/internfeel
    - /bin/rm -rf $DOCROOT/wp-content/plugins/internfeel-core && /bin/mv $DOCROOT/wp-content/plugins/internfeel-core.new $DOCROOT/wp-content/plugins/internfeel-core
    - /bin/cp wp-content/mu-plugins/internfeel-hardening.php $DOCROOT/wp-content/mu-plugins/
```

Copying to `.new` first and then swapping keeps the live site from serving half-copied files.

### 6.3 Every deploy

1. Merge into `staging` on GitHub; CI must pass.
2. In cPanel Git Version Control, open the **internfeel-staging** repository, then **Pull or Deploy**: click **Update from Remote**, then **Deploy HEAD Commit**.
3. Check staging: smoke test, then any feature-specific checks.
4. Open a pull request from `staging` to `main`, and merge it once Surabhi approves.
5. Repeat step 2 for the **internfeel** (production) repository.
6. In LiteSpeed Cache, choose **Purge All**.

**If cPanel refuses to deploy:** `.cpanel.yml` must sit at the repo root and the clone must have no uncommitted changes. Never edit files in the clone on the server.

### 6.4 Rollback

In cPanel, check out the previous commit in the production clone (or revert on GitHub, then pull), deploy, and purge the cache. Database changes are additive (section 8), so code can roll back without a database restore.

---

## 7. Continuous integration (GitHub Actions)

`.github/workflows/ci.yml` runs on every push and pull request:

| Job | What it runs |
| --- | --- |
| `lint` | PHPCS (WordPress standards), PHPStan, `php -l` on PHP 8.5 |
| `unit` | wp-env + PHPUnit: referral attribution, invite credits, badge counts, edit-once rules, view counting, certificate numbers, private-file permissions |
| `e2e` | wp-env + Playwright: sign-up and sign-in with email and password, password reset (email captured from the mail log), notifications, submit all 4 steps, admin approve, verify proof, certificate download and QR verify page, invite, sign-up through invite, referral credit on the post, comment edit once, report |
| `a11y` | Playwright + axe on home, experience, submit and dashboard |

Merging into `main` needs all four jobs green and Surabhi's approval.

---

## 8. Database changes

- **Version tracking:** the plugin keeps a schema version in the option `if_db_version`. On activation and on `plugins_loaded`, it runs any pending upgrade steps, written with `dbDelta` and in order.
- **Additive only:** add tables and columns, and never rename or drop them in the same release. A later release removes unused columns once nothing reads them.
- **Meta changes** run as batched upgrade steps that are safe to run twice.
- **Before a production deploy that includes an upgrade step:** run an UpdraftPlus database backup.

---

## 9. Migrating the old content

The export (25 October 2026) is the zip `internfeel-export-content.zip` plus `internfeel-images.tar`.

1. **Images:** upload `internfeel-images.tar` with cPanel File Manager to `wp-content/`. Extract it so the files land in `wp-content/uploads/YYYY/MM/`, then delete the tar. Old image URLs keep working.
   - **One file needs renaming after extraction.** Its name is over 100 characters, so the archive cut it short and dropped the extension. In `wp-content/uploads/2016/07/`, rename `C__Data_Users_DefApps_AppData_INTERNETEXPLORER_Temp_Saved-Images_13113252_1000497300028647_186085766` to `C__Data_Users_DefApps_AppData_INTERNETEXPLORER_Temp_Saved-Images_13113252_1000497300028647_1860857661_o1.jpg` (133,849 bytes). `images-manifest.json` in the archive lists every original URL and name.
   - Two files really do have double dots in their names (`2014/11/415415..jpg` and `2014/11/virg..jpg`); leave them as they are.
2. **Data:** upload `content/posts.json`, `categories.json`, `tags.json` and `comments.json` through **Tools → InternFeel import** in wp-admin. The files are stored in `IF_PRIVATE_DIR/import/`.
3. **Mapping:** the importer shows a table mapping old categories to type, field and location, and suggests organisations and programmes for the master list. An admin confirms it.
4. **Import:** click Run. The importer works in batches of 20 through admin AJAX and is safe to re-run, skipping posts whose `legacy_wp_id` already exists. For each post it:
   - creates the experience with the original slug, date and title, owned by the InternFeel Archive user;
   - cleans the HTML and rewrites `www.` and `http://` links to `https://internfeel.com`;
   - registers the featured image as an attachment pointing at the existing file;
   - extracts stipend, duration and rating where it can;
   - imports the comments.
5. **Skip list:** `kymlmu93um02iur24` (gibberish) and `bad-internship-experience-anonymous-livon-virtual-internship` (empty).
6. **Review:** experiences with missing fields appear under **Experiences → Needs review**. Surabhi completes them and publishes.
7. **Check:** `tools/export/check-urls` (Playwright) requests every post URL from `url-list.txt` and expects 200, and checks every `<img>` on those pages loads.

Run the import on staging first. Once the check passes, run it on production.

---

## 10. Testing before each release

**Smoke test on staging, about 10 minutes:**

- [ ] Home loads; search and two filters work; the URL updates
- [ ] An experience page shows key facts, story, ratings, share buttons and comments; one view is counted
- [ ] Sign up and sign in with email and password, and with Google; complete a profile; a password-reset email arrives
- [ ] The header bell shows new notifications after an admin action
- [ ] Submit a test experience with proof; an admin approves and verifies it
- [ ] The certificate downloads, and its QR code opens the verify page
- [ ] Invite credits appear; an invite link signs up a second student; the referral credit shows on their published post
- [ ] Badges show next to the name
- [ ] Comment, edit once, report
- [ ] No ads on staging; consent banner works
- [ ] Mobile layout at 375 px

**Before relaunch (from plan.md section 8):**
- Lighthouse mobile 90 or above on home, listing and experience pages, with ads on in a production rehearsal.
- An axe pass with no serious issues.
- One backup restore tested on staging.

---

## 11. Operations runbook

### 11.1 Daily (admins, about 15 minutes)

1. Open **Experiences → Review queue**. Anything highlighted has been waiting more than 18 hours (the promise is 24).
2. For each submission:
   - **Approve:** publish it;
   - **Request changes:** add a note (the student sees it as a dashboard notification);
   - **Reject:** give a reason.
3. For each proof, compare it with the experience (name, organisation, dates). Choose **Verify** or **Reject proof** with a note. Proofs are deleted automatically 90 days after the decision.
4. Clear the **Reports** tab: uphold (remove the item) or dismiss.
5. Every action is written to the moderation log automatically.

### 11.2 Takedown request (organisation or student)

1. Log the request in the moderation log with the requester's details.
2. If the content breaks the content guidelines, or the author asks, set the experience to `removed`; its certificate is revoked automatically.
3. If an organisation disputes facts but the post is within the guidelines, offer a right of reply. A short statement is added below the post with the author's knowledge.
4. Reply to the requester within 72 hours.

### 11.3 Data requests (DPDP Act)

- **Download my data:** students use Profile → Download my data, which exports a JSON file of their profile, experiences, comments and invites.
- **Delete my account:** Profile → Delete account removes personal fields and proofs, and makes published experiences anonymous unless the student asks for them to be removed too.

### 11.4 Backups and restore

- **Schedule:** UpdraftPlus backs up the database daily and the files weekly to Google Drive, keeping 14 copies.
- **Restore:** UpdraftPlus → Existing backups → Restore. Test on staging first.
- **Private files:** proofs and certificates live outside the web root and outside UpdraftPlus. Include `internfeel-private` in a monthly cPanel home-directory backup.

### 11.5 Incidents

| Symptom | First steps |
| --- | --- |
| Site down or error 500 | cPanel **Errors** and `error_log`; roll back the last deploy (section 6.4); purge the cache |
| Password-reset emails not arriving | cPanel **Email Deliverability** and **Track Delivery**; check spam folders; reset the password from wp-admin meanwhile |
| Spam sign-ups | Tighten the rate limits; review sign-ups flagged by referral abuse rules; bring forward adding Turnstile |
| Suspected hack | Put up the holding page; Wordfence scan; rotate all passwords and keys; restore the last clean backup to staging and compare |
| Slow pages | LiteSpeed Cache settings; check ad slots; large images |

### 11.6 Routine maintenance

| How often | Task |
| --- | --- |
| Weekly | Wordfence scan results; plugin updates applied; backups present |
| Monthly | Check Google Drive backup space; review the master list for duplicate organisations |
| Each quarter | Lighthouse and axe checks; restore test; review admin accounts and two-step sign-in |
| Before 13 May 2027 | Ship the parental-consent step for under-18 students |

---

## 12. Working with Claude Code

- Each build day starts from `docs/plan.md` section 7 and ends with a working build on staging. It also ends with a short note in the pull request: what changed, how to check it, and what's next.
- Claude Code works on `feat/*` branches and opens pull requests into `staging`. Only Surabhi merges into `main`.
- Credentials are never pasted into chat. cPanel, GoDaddy and Google actions that need a sign-in are done by Surabhi, or by Claude Code through the browser with Surabhi present.
- When a decision in `docs/plan.md` changes, update the decisions table in the same pull request as the code.
