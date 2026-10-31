# Launch runbook: Monday 9 November 2026

The relaunch moves **staging's reviewed site to production**: the reviewed migrated experiences, the beta students' work and the master list all carry over. Decided 31 October 2026 (plan.md section 4). Server details (account name, the other sites' folders) are in `docs-private/environment.md`; this public file uses placeholders.

**Golden rules**

- `public_html` also holds other sites' folders (listed in `docs-private/environment.md`) and the staging folder. **Never delete, move or overwrite anything in `public_html` except the files this runbook names.**
- Never restore the old site's backup. Never run seed data on production.
- Take a backup before every step that changes data, and tick each step off as you go.

---

## Part 1: Before launch day (Saturday 7 and Sunday 8 November)

Everything here is done on staging or in accounts, without touching the live domain.

1. **Release PR.** Claude opens a pull request from `staging` into `main`. CI must be green. **Surabhi** reviews and merges it. That commit is the one that launches.
2. **Admins' two-step sign-in.** On staging, each admin opens **Wordfence → Login Security** and sets up an authenticator app. Store the recovery codes in the password manager.
3. **Backups on staging.** Install **UpdraftPlus** (free) on staging, connect it to the team's Google Drive, and set the schedule: database daily, files weekly, keep 14. Run **Backup Now** once, then test a restore of that backup on staging (launch checklist item).
4. **Email.** In cPanel **Email Deliverability**, check SPF and DKIM are valid for internfeel.com. On staging, request a password reset for a Gmail and an Outlook test address and check both arrive in the inbox.
5. **Speed.** Run Lighthouse (mobile) on staging's home, `/experiences/` and an experience page. The target is 90 or above. Send Claude the reports if any is below.
6. **Google.** Have ready: the AdSense publisher ID and one display unit ID per slot (`inline`, `end`, `sidebar`, `card`, `home`, `hub`), the GA4 measurement ID, and Search Console access for internfeel.com.
7. **Legal pages** are published on staging with every **[TO CONFIRM]** filled in (plan.md section 10, item 7).
8. **Production prerequisites in cPanel** (the live site is not touched):
   - **MultiPHP Manager:** internfeel.com set to PHP 8.5.
   - **Select PHP Version → Extensions** for 8.5: dom, xml, xmlreader, simplexml, mbstring, fileinfo, gd, intl, zip, and one of mysqli or nd_mysqli (development.md 5.1).
   - **File Manager:** create the private folder `internfeel-private` in the home folder, beside `public_html` (not inside it), with permissions 750.
   - **Git Version Control:** clone the repo a second time, to `repositories/internfeel` on branch `main` (development.md 6.1). **Do not deploy yet.**

---

## Part 2: Launch day, in order

Allow three hours. Announce a short maintenance window to the beta students: staging is frozen from step 1.

### A. Freeze and clean staging

1. Tell the admins and beta students to stop editing staging.
2. **Delete test data on staging** (wp-admin):
   - **Users:** delete the test accounts (addresses at example.com, and any account made only for testing), choosing **Delete all content** for each. Keep both admins, the **InternFeel Archive** user and the real beta students.
   - **Experiences:** check **Drafts**, **Pending**, **Rejected** and **Removed** for test entries and delete them permanently.
   - **Review → Log** keeps the history; nothing to do there.
3. **Back up staging:** UpdraftPlus → **Backup Now** (database and files). Also export the database: cPanel **phpMyAdmin**, staging database, **Export** (Quick, SQL), and keep the file offline.

### B. Take down the holding page

1. In File Manager, open `public_html`. **Download a copy** of the holding page's `index.html` and `.htaccess` first (they are the rollback).
2. Delete only those two files. Leave every folder in place.

### C. Install production WordPress

1. **Softaculous → WordPress → Install:** domain `internfeel.com`, **In Directory** empty, untick every bundled plugin, and set the **table prefix to the same as staging's**. The prefix is in staging's `wp-config.php` (`$table_prefix`).
2. Check `https://internfeel.com/` shows the default WordPress site. Re-apply PHP 8.5 in MultiPHP Manager if the install rewrote `.htaccess`.

### D. Move staging's database to production

1. **phpMyAdmin**, open the **production** database, select all its tables, then **Drop**.
2. **Import** the staging SQL export from step A.3 into the production database.
3. Edit production's `wp-config.php` (File Manager). Keep the production database name, user and password that Softaculous wrote. Then:
   - **Replace the eight keys and salts** with fresh ones from `https://api.wordpress.org/secret-key/1.1/salt/`. Never reuse staging's.
   - Add the production block from `dev/wp-config-sample-internfeel.php`: `WP_ENVIRONMENT_TYPE` `production`, `DISALLOW_FILE_EDIT`, `IF_PRIVATE_DIR` (one level up: `dirname( __DIR__ ) . '/internfeel-private'`), `IF_MAIL_FROM` and `IF_MAIL_FROM_NAME`, `IF_ADSENSE_CLIENT`, `IF_ADSENSE_SLOTS` and `IF_GA4_ID`.
   - Do **not** add `IF_NEWSLETTER_ENDPOINT` until a newsletter service is chosen.

### E. Copy files from staging

In File Manager, for each folder: compress it on staging, copy the zip, extract it on production, then delete both zips.

| From (staging) | To (production) |
| --- | --- |
| `public_html/staging.internfeel.com/wp-content/uploads/` | `public_html/wp-content/uploads/` |
| `public_html/staging.internfeel.com/wp-content/plugins/` (third-party plugins only: Wordfence, CookieYes, Yoast SEO, LiteSpeed Cache, Nextend Social Login, UpdraftPlus) | `public_html/wp-content/plugins/` |
| home folder `internfeel-private-staging/` (proofs and certificates) | home folder `internfeel-private/` (permissions 750 on folders, 640 on files) |

The InternFeel theme, plugin and must-use plugin are **not** copied; they arrive from Git in the next step.

### F. Deploy the code

1. **Git Version Control → internfeel → Pull or Deploy:** **Update from Remote**, then **Deploy HEAD Commit**.
2. Check the files exist: `public_html/wp-content/themes/internfeel`, `plugins/internfeel-core` and `mu-plugins/internfeel-hardening.php`.

### G. Switch the addresses from staging to production

1. Sign in to `https://internfeel.com/wp-admin/` with your staging admin account. The database is staging's, so the passwords are too, and two-step sign-in applies.
2. Install **Better Search Replace** (free). Replace `https://staging.internfeel.com` with `https://internfeel.com` in **all tables**, with **Run as dry run** ticked first, then for real. Repeat for `http://staging.internfeel.com`. Then deactivate and delete the plugin.
3. **Settings → General:** both addresses read `https://internfeel.com`.
4. **Settings → Reading:** **untick** "Discourage search engines from indexing this site" (staging had it ticked).
5. **Settings → Permalinks:** click **Save** once, so the rewrite rules are rebuilt.
6. **LiteSpeed Cache → Toolbox → Purge All.**

### H. Connect the outside services to the live domain

1. **Nextend Social Login:** add `https://internfeel.com/...` as an authorised redirect URI in the Google Cloud console, as Nextend's settings page shows it.
2. **CookieYes:** add internfeel.com as the site's domain in the CookieYes dashboard.
3. **Yoast SEO:** run its first-time configuration. The sitemap is at `/sitemap_index.xml`.
4. **AdSense:** check that `https://internfeel.com/ads.txt` shows one `google.com, pub-…` line, and that Auto ads are off.
5. **Search Console:** verify the domain if it isn't already, and submit `https://internfeel.com/sitemap_index.xml`.
6. **Wordfence:** open **Firewall** and run **Optimize the Wordfence Firewall** again. Its setup file still points at staging's folder.
7. **UpdraftPlus** on production: connect Google Drive (a separate folder from staging), set the same schedule, and run **Backup Now**.

### I. Smoke test (twenty minutes)

Work signed out in a private window, then signed in:

- [ ] `https://internfeel.com/` loads, with SSL valid and no "noindex" in the page source.
- [ ] Ten old URLs from `url-list.txt` load their rebuilt experiences, with images.
- [ ] Search and the filters on the home page and `/experiences/` work.
- [ ] Sign up a new account with a real address, complete the profile, and submit a short experience. Then delete it and the account afterwards.
- [ ] As admin, **Review** shows the queue, and **Review → Log** shows staging's history.
- [ ] A certificate's `/verify/IF-…/` page says Valid, and its PDF's QR code points at internfeel.com.
- [ ] `/ads.txt` and the cookie banner work, and ads appear only after accepting advertising cookies.
- [ ] A password-reset email arrives.
- [ ] Response headers include `X-Content-Type-Options` and a `Content-Security-Policy` (hardening is active).

### J. Announce

Tell the beta students and post the announcement. Then tick the launch checklist in plan.md section 8.

---

## Part 3: If something goes wrong

- **Before step D finishes:** put the holding page's `index.html` and `.htaccess` back in `public_html`. The domain shows the holding page again and staging is untouched.
- **After step D:** restore the holding page files as above (they win over WordPress), fix the problem, then remove them again.
- **A code problem after launch:** roll back in Git Version Control (development.md 6.4). Database changes are additive, so code can roll back without a restore.
- **A data problem after launch:** UpdraftPlus → **Existing backups** → restore the backup from step H.7.

## Part 4: After launch

- Keep staging, password-protected, for testing future changes. Its data is now behind production's; refresh it from a production backup only if needed, and never the other way round.
- Delete the old site's database once production is confirmed (launch checklist).
- Each month: download a cPanel home-folder backup that includes `internfeel-private` (proofs and certificates are outside UpdraftPlus; development.md 11.4).
