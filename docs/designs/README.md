# Page designs for Gate 1

Static mock-ups of the home page and the experience page, built on the theme's real design tokens (`wp-content/themes/internfeel/style.css`). Every organisation, name and number is sample content.

| Page | Desktop | Phone (375 px) |
| --- | --- | --- |
| Home ([home.html](home.html)) | [screenshot](screenshots/home-desktop.png) | [screenshot](screenshots/home-mobile.png) |
| Experience ([experience.html](experience.html)) | [screenshot](screenshots/experience-desktop.png) | [screenshot](screenshots/experience-mobile.png) |

To open the HTML versions, serve the repo root (for example `python -m http.server`) and go to `/docs/designs/home.html`. The stylesheet paths are relative, so opening the files directly may lose the styles.

Both pages pass axe (WCAG 2.2 AA) at desktop and phone widths and have no horizontal scrolling at 375 px. The remaining designs (listing and search, programme hub, submission form, dashboard, sign-up and log-in, certificate and verify page) follow on day 3.

## What to sign off (plan.md section 7, Gate 1)

**Home (plan.md 5.2):**
- [ ] Section order: hero with search and Submit, filters, trust strip, listicle rows, how it works, invite banner.
- [ ] The experience card: organisation mark, title, programme, field, location, year, paid or unpaid with stipend band, rating, Verified badge, views, "Shared through an invite".
- [ ] The one home ad slot, "Featured opportunity", between the first two listicle rows.

**Experience page (architecture.md 3.1):**
- [ ] Layout: title, author line with badge symbols, referral credit line, key-facts box, ratings, story, advice, share, comments; sidebar on desktop.
- [ ] Ad slots: after the fourth story answer, at the end of the story before comments, and one sidebar unit on desktop only.
- [ ] Comments: one level of replies, "edited" label, "Edit (once)" and "Report".

**Header and footer (plan.md 5.3, 5.4):**
- [ ] Header: logo, navigation, Submit as the primary button, bell with unread count and avatar when signed in, Log in and Sign up when signed out; compact search on inner pages only; on phones, logo, Submit and a menu icon.
- [ ] Footer: four link columns, logo and tagline, social icons, contact email; newsletter sign-up above it.

**Visual direction:**
- [ ] Teal `#339999` for accents, borders and large text; darker teal `#297777` for body links and buttons, so text meets AA contrast (`#339999` is only 3.4:1 on white).
- [ ] System font stack (no web-font download, which helps the Lighthouse 90 target).

## Notes for building the templates

- Anonymous experiences replace the name, photo and LinkedIn with "Anonymous" and show no badges. College, course, year and the referral credit line stay.
- Migrated experiences show "First published on InternFeel in {year}" in the stats row and are never Verified.
- Every ad slot keeps its reserved height and its Advertisement label even when no ad fills it.
