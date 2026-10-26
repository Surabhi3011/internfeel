# Approved page designs (Gate 1)

Signed off by Surabhi on **Mon 26 Oct 2026**. These are the designs to build from. They replace the mock-ups in `docs/designs/`, which were drafted before sign-off and should not be followed where the two differ.

Every page here is built from the InternFeel design system (`system/`). Names, numbers, ratings and the story text are sample content for layout only; organisations and programmes come from the migrated archive.

## Files

| File | Template it drives | Spec in this file |
| --- | --- | --- |
| [home.html](home.html) | `front-page.php` | [Home](#home) |
| [experience.html](experience.html) | `single.php` | [Experience page](#experience-page) |
| [dashboard.html](dashboard.html) | `page-dashboard.php` | [Dashboard](#student-dashboard) |
| [submit-step-1.html](submit-step-1.html) | `page-submit.php` | [Submission form](#submission-form) |
| [signup.html](signup.html) | `page-signup.php`, `page-login.php`, first-sign-in profile | [Sign up and log in](#sign-up-log-in-and-first-sign-in) |
| [certificate.html](certificate.html) | Dompdf certificate template | [Certificate](#certificate) |
| `system/tokens.css`, `system/tokens.json` | Design tokens (colour, type, space, radius, layout) | [Adopting the design system](#adopting-the-design-system-in-the-theme) |
| `system/bundle.css` | Component styles, class prefix `if-` | Same |
| `img/if-mark.png` | The mark alone (graduate with the scarf "F"), 960 × 960 on white | Certificate stamp, favicon source |

To view the pages, serve the repo root (`python -m http.server`) and open `/docs/approved-designs/home.html`. The logo is loaded from the theme (`wp-content/themes/internfeel/assets/img/internfeel-logo.png`), so open them from the repo, not on their own. Every page was checked for horizontal scrolling at 390, 768 and 1440 px. Run the axe checks (`@a11y`) against the built templates, not these files.

Each page's own `<style>` block holds page-level layout helpers prefixed `pg-`. They are mock-up helpers: move what you need into the theme under `if-` names (for example `.pg-wrap` is the theme's existing `.if-container`).

## Decisions made at sign-off

| Decision | Effect on the build |
| --- | --- |
| The verified count shows on the home page from launch, starting at 0 | Trust strip always shows three live counts: experiences, verified, contributing colleges |
| Migrated experiences start at 0 views | No historical view data exists; `view_count` starts at 0 for every migrated post |
| Newsletter sign-up stays above the footer on every page | Email service still to be chosen (free tier) before 5 Nov; build the form to post to a configurable endpoint |
| Footer sits on `--if-surface-tint`, not charcoal | There is no light (reversed) logo; the current theme's charcoal footer must change |
| Display name is free text the student types; blank shows the full name | No auto-generated "First L." name |
| Web fonts: Schibsted Grotesk, Atkinson Hyperlegible Next, IBM Plex Mono | Self-host them (below); the system font stack in the current `style.css` is replaced |

## Adopting the design system in the theme

1. **Tokens.** The theme's `style.css` already uses the same colour values and most of the same names. Bring it in line with `system/tokens.css` (or enqueue that file as `assets/css/tokens.css` first). Renames and additions:

   | Theme now | Design system |
   | --- | --- |
   | `--if-teal-50` | `--if-surface-tint` |
   | `--if-font-sans` | `--font-body`, plus `--font-display` and `--font-mono` |
   | `--if-text-sm` … `--if-text-3xl` | Type classes `.display`, `.heading-1/2/3`, `.body-lg`, `.body`, `.small`, `.label`, `.mono` |
   | (missing) | `--if-line-strong`, `--if-on-teal`, `--if-charcoal`, `--if-warning`, `--if-warning-soft`, `--if-danger-soft`, `--if-ad-ground`, `--if-radius-sm`, `--if-shadow-raise`, `--if-reading`, `--if-space-1…12` in px |

   `system/tokens.json` is the source; `tokens.css` is generated from it (light and dark values; the site launches light only, because there is no light logo for dark mode).

2. **Components.** Enqueue `system/bundle.css` as `assets/css/components.css` after the tokens, and **delete its first line** (the Google Fonts `@import`). Then rename the classes the theme already uses:

   | Theme now | Design system |
   | --- | --- |
   | `.if-button`, `.if-button--primary` | `.if-btn`, `.if-btn--primary` (also `--ghost`, `--danger`, `--sm`) |
   | `.if-button--block` | `.if-btn` with `width: 100%` |
   | `.if-field__hint` | `.if-field__help` |
   | `.if-form__status` (errors) | `.if-field__error` per field; keep a form-level status box styled the same way |
   | `.if-icon-button`, `.if-badge-count` | `.if-bell`, `.if-bell__count` (count is teal-700, not red) |
   | `.if-hide-mobile`, `.if-show-mobile` | Keep; they match the mock-ups' `pg-wide` and `pg-narrow` |
   | `.if-footer` on charcoal | `.if-footer` on surface tint, with the full-colour logo |

3. **Fonts.** Self-host WOFF2 files in `assets/fonts/` (all three families are SIL Open Font License; commit each licence file beside the fonts). No request goes to Google, which also keeps visitors' IP addresses away from a third party.
   - Schibsted Grotesk 700 and 800 (headings; 800 is the home hero only)
   - Atkinson Hyperlegible Next 400, 400 italic, 700 (all reading text)
   - IBM Plex Mono 500 (certificate numbers, invite links, counts)
   - Latin subset plus the rupee sign (U+20B9); check ₹ renders in the subset and fall back to the system font if a family lacks it.
   - `font-display: swap`; preload Atkinson 400 on every page and Schibsted 800 on the home page only.
   - Lighthouse mobile target is 90. If fonts push it below that, drop IBM Plex Mono first (use `ui-monospace`), then Schibsted 700 (headings fall back to Atkinson 700).

4. **Icons.** Inline SVG in `currentColor`, 24 px grid, 1.75 px stroke, round caps and joins, drawn at 20 px (16 px inside small text), Lucide style. Every icon sits beside a word except the bell and social links, which carry an `aria-label`. The social icons in the mock-ups are simple drawings; swap in the platforms' official monochrome glyphs if you prefer. The contributor badge is the filled graduation cap path used in `.if-badges`.

5. **Logo.** The header logo is 36 px tall (28 px on phones, as the theme does now). The logo has no reversed version, so it never sits on charcoal or dark grounds. Move `img/if-mark.png` to `assets/img/if-mark.png` for the certificate and favicon. A larger or vector logo is still needed for print and high-density screens.

## Breakpoints and layout

- Content width `--if-content` (72 rem), 16 px side gutter at every width. The story is at most `--if-reading` (42 rem).
- **Up to 640 px (phone):** one column; header shows logo, a short "Submit" button and the menu button; filter chips scroll sideways in one row; Log in and Sign up move into the menu.
- **Up to 1000 px (tablet):** the main navigation and the compact header search move into the menu; sidebars drop below the main column; card grids go to two columns (one below 640).
- **Above 1000 px:** full header, sidebars beside content, three cards across.
- The header is sticky and shrinks from 72 to 56 px once the page scrolls (the theme already does this).

## Header and footer

**Header, signed out:** logo · Experiences, Programmes, Listicles, About · Log in (ghost) · Sign up (outline) · Submit experience (primary). Inner pages add a compact search (220–260 px) before the buttons; the home page does not, because the hero has the big search.

**Header, signed in:** logo · navigation · compact search (inner pages) · Submit experience · bell with unread count · avatar button opening Dashboard, My experiences, Invites, Profile, Log out (and Admin for administrators).

**Newsletter band** (every page, above the footer, on `--if-surface` with a top hairline): heading "New experiences in your inbox", one line of copy, an email field with a visible label and a Subscribe button.

**Footer** on `--if-surface-tint`: four columns (Explore, Contribute, Company, Legal, as plan.md 5.4), then a bottom row with the logo and `contact@internfeel.com`, four social links and "© {year} InternFeel".

## Home

`front-page.php`, sections in this order:

1. **Hero** (two columns above 900 px; text column only on phones): label "Written by students, for students", the `.display` headline, one `body-lg` line, the big search field (input plus an outline Search button), a "Popular:" line of 3–4 admin-set links, then Submit your experience (primary) and How it works (ghost, jumps to section 6). The right column is the brand block: teal, charcoal and tint rectangles with two pill-shaped cut-outs echoing the scarf ribbons, with one real experience card overlaid. Pick that card from the newest verified experience; hide the whole right column below 640 px.
2. **Browse** band on `--if-surface`: "Browse experiences" with the live count on the right; a row of menu chips (Field, Type, Organisation, Location, Year, Paid or unpaid) and a Verified only toggle chip; a second row of quick type chips (All types, Research, Industry, Government, NGO, Virtual). Choosing anything replaces sections 4 and 5 with the results grid and updates the URL (plan.md 5.2).
3. **Trust strip:** one bordered box split in three: experiences shared, verified with proof, colleges contributing. Numbers in Schibsted 800 at 36 px, teal-800.
4. **Listicle rows** (`if_list` in home order, 3–5 rows): heading-2 title, one-line subtitle, "See all" with an arrow on the right, then three cards.
5. **Ad slot** "Featured opportunity" between the second and third listicle rows: `.if-ad` with the Advertisement label, height reserved (180 px desktop, 250 px phone).
6. **How InternFeel works:** three bordered tiles (Read, Share, Get certified), each with an icon tile, a heading-3 and two lines, then a second Submit your experience button.
7. **Invite banner** (signed-in students with credits only; not in the mock-up): a tint band with "You have {n} invites left", one line and a Copy invite link button.

**Experience card** (`.if-card`): organisation initial (or logo) tile and the organisation with its city and country; title as the card's single link (the whole card is clickable through it); facts line (type, field, year · weeks, paid or unpaid with stipend band); footer with the rating bar and value, the Verified badge when verified, and views. "Shared through an invite" sits under the footer when the experience came through an invite. Migrated experiences have no Verified badge.

## Experience page

`single.php`, top to bottom:

1. Breadcrumb: Experiences › {type} › {organisation}.
2. **Title block:** label with the type ("Research internship") and the Verified badge; the title as `heading-1` (28 px on phones); the author line (avatar initials, display name with one cap per verified experience, LinkedIn icon link, then college, course and class year); the referral credit line when it applies; a hairline, then the stats row (published date, views, comments, read time) and the share row (LinkedIn, WhatsApp, X, Facebook as ghost buttons with labels, icons only on phones, then an outline Copy link button).
3. **Key facts box** (`.if-facts`, full content width): Organisation (with department), Programme (with the body that runs it), Type (with mode), Location, Dates (with weeks), Stipend (with Paid or Unpaid), Selection (with "n of 5"), Field. Missing facts on migrated posts are left out, not shown as blanks.
4. **Two columns above 1000 px:** the story on the left, a 300 px sidebar on the right.
   - Story: each answer is a `.if-qa` block (question with the "IF" marker, answer in `body-lg`). **Ad 1** after the fourth answer. Then the **Advice** panel (label "Advice for students applying", text in `body-lg`, bordered surface box). **Ad 2** at the end of the story. Then "Something wrong with this experience? Report it".
   - Sidebar: **Ratings** card (overall number in Schibsted 800 at 40 px, then Learning, Mentorship, Work culture and Selection difficulty as rating bars) and **Ad 3**, the 300 × 250 unit, desktop only. On tablets and phones the ratings card follows the story and Ad 3 is not rendered, so phones carry two ads.
5. **Comments** (reading width): heading with the count; signed out, a tint box "Log in to ask {first name} a question or add your own tips" with a Log in to comment button (signed in, a textarea and Post comment instead); the list with avatar, display name and caps, time ("· edited" after an edit), text, then ghost Reply and Report buttons (and "Edit (once)" on the reader's own comment). One level of replies, indented with a left hairline; the author's replies carry an "Author" pill.
6. **More {type} internships:** three cards.

**Anonymous experiences:** "Anonymous" replaces the name, a neutral avatar replaces the initials, no LinkedIn link and no caps. College, course, year and the referral credit line stay. Migrated experiences show "First published on InternFeel in {year}" in the stats row.

## Student dashboard

`page-dashboard.php`, signed in only, no ads:

1. **Title row:** large avatar, "Your dashboard", display name with caps, then college, course and class year; Add experience (primary, with a plus icon) on the right.
2. **Four counters** (bordered tiles, numbers in Schibsted 800 at 32 px): experiences published, total views, comments received, friends who joined through you. Two across on phones.
3. **Tabs** (real `role="tablist"` buttons; the selected tab has a 3 px teal underline): My experiences, Comments, Invites, Profile.
4. **Two columns above 1000 px:** the tab panel on the left, a 360 px sidebar on the right with Updates (the latest five notifications, unread ones with a teal dot, "See all") and the Invite friends panel (on tint: the rule in one line, "{n} invites left", the next unused single-use link in a read-only mono field with Copy, WhatsApp and LinkedIn buttons, then links used, friends who joined and their published experiences).

**My experiences** shows one bordered item per experience, newest first. Each has status pills, the title, a meta line, counts, then actions under a hairline. The four states in the mock-up:

| State | Pills | Extra content | Actions |
| --- | --- | --- | --- |
| Published and verified | Published, Verified | Views, comments, certificate number in mono | Download certificate, Share, View, Edit once |
| Published, proof rejected | Published, Proof rejected | Danger-soft box: "Note from InternFeel: {verification_note}", then the upload area (PDF, JPG or PNG, up to 5 MB each, 3 files at most; only the author and admins can open them) | Same as above |
| In review | In review, Proof not added (or Proof pending) | "Submitted {date}. We review every experience within 24 hours." | Add proof, Preview |
| Draft | Draft | "Saved {time} · step {n} of 4, {step name}" | Continue draft (primary), Delete draft (ghost, danger colour) |

Also needed: Rejected and Removed (danger pills plus the moderator's note), Proof pending (warning pill), and Edit once turning into a disabled "Edited once" button with a one-line reason after the one edit.

**Comments tab:** every comment on the student's experiences, newest first, each with the experience title as a link above it and the same Reply and Report actions as on the experience page. **Invites tab:** the sidebar panel at full width plus a table of links (link, Unused or Used, who used it, when they joined, their published experiences). **Profile tab:** the first-sign-in fields, plus "Add a password" for Google sign-ups and Log out.

## Submission form

`page-submit.php`, signed in only, no ads. The mock-up shows step 1; steps 2 to 4 use the same frame.

- **Frame:** "‹ Back to your dashboard", `heading-1` "Share your internship experience", one line ("Takes about 20 minutes. Your draft saves every 10 seconds…"), the `.if-stepper` (Where and what, Your story, Ratings and advice, Review and submit; on phones only the current step's name shows). The form is a bordered surface card; above 1000 px a 340 px sidebar sits beside it with "Before you start" (tint) and "The questions in step 2". The card ends with "Draft saved {n} seconds ago", Save and exit (ghost) and Next (primary with an arrow); on phones the buttons stack full width, Next on top.
- **Step 1, where and what:** Organisation (required, typeahead over `if_master`, "Pick from the list or add a new one"), Programme or role (optional), Type (required, chips: Research, Industry, Government, NGO, Virtual), Field (required), Mode (required, segment: On-site, Remote, Hybrid), City and Country (required), Start month (required, month input), Duration in weeks (required; the mock-up shows its error state, "Enter a duration between 1 and 104 weeks."), Paid or unpaid (required, segment), Stipend a month (optional, ₹ prefix, "Cards show it as a band: under ₹10k, ₹10k–25k or above ₹25k."), How hard was it to get in? (required, segment 1 to 5).
- **Step 2, your story:** one labelled textarea per standard question, in this order: tell us about yourself; how you applied, and the stipend, duration and place; how competitive the selection was and what helped; the work you did; what was good and what was hard; key takeaways; whether it changed what you want to do next. Which questions are required, and any minimum length, still needs Surabhi's call. Until she confirms, make the first four required.
- **Step 3, ratings and advice:** four required 1-to-5 segment controls (Overall, Learning, Mentorship, Work culture) and the Advice textarea (optional).
- **Step 4, review and submit:** the anonymity choice (segment: Show my name / Post anonymously, with "Posting anonymously hides only your name, photo and LinkedIn…"), the proof upload area from the dashboard (optional here), a preview built from the same components as `single.php`, a required checkbox agreeing to the content guidelines, then Submit for review (primary). After submitting: "Submitted for review. We review every experience within 24 hours." and a link to the dashboard.

## Sign up, log in and first sign-in

`signup.html` shows two cards side by side (stacked below 900 px):

- **Create your account** (`page-signup.php`): a Sign up / Log in segment that switches between the two pages, Continue with Google (full-width outline), an "or with your email" divider, Email, Password ("At least 8 characters."), Create account (full-width primary), then the terms line with Log in and Forgot your password links. `page-login.php` is the same card with the segment on Log in, the button "Log in" and "Forgot your password?" under the password field.
- **Tell us a little about you** (first sign-in only): Full name (required, "Printed on your certificates."), Display name (optional, empty by default, "The name shown on your experiences and comments. Leave it blank to show your full name."), College, Course, Graduation year (required), LinkedIn profile (optional, "Hidden on experiences you post anonymously."), Are you under 18? (required, segment No/Yes, "If you are, we show you only non-personalised ads."), then Go to your dashboard.

## Certificate

A4 landscape (1123 × 794 px at 96 dpi), white. A 200 px brand strip runs down the left edge: tint, two teal blocks and a charcoal block, with two ribbon cut-outs. Then:

- the wordmark (44 px tall)
- the label "Certificate of contribution"
- "This certifies that"
- the student's **real** name in Schibsted 800 at 56 px (even for anonymous posts)
- one line saying they shared a first-hand account, reviewed and published
- a tint box with the experience title, then the organisation, programme and dates
- a bottom row under a hairline: Published {date}, Certificate no. `IF-YYYY-NNNNN` in mono, "Verify at internfeel.com/verify/{no}", the QR code (96 px) and the mark with "Issued by InternFeel"

Dompdf needs the fonts embedded with `@font-face` pointing at local files, and simple CSS: rebuild the flex rows as tables if Dompdf mis-lays them.

## Pages not drawn

Build these from the same parts:

- **Experiences archive and search results** (`/experiences/?…`): the Browse band from the home page at the top, then a results count and sort, then the card grid. Every 8 cards, a card-shaped ad slot labelled Sponsored. "Load more" below. An empty state says what to change ("No experiences match. Remove a filter or try another field.").
- **Listicle** (`/lists/{slug}/`): heading-1 title, intro at reading width, the cards in the admin's order. One ad slot after the list, as on hub pages.
- **Programme hub:** programme and organisation as the title, a key-facts box of summary facts (experiences, average rating, typical stipend band, usual months), the cards, then one ad slot after the list.
- **Verify a certificate** (`/verify/{no}`, no ads): one bordered card at reading width with a status pill. Valid (teal) shows the name, title, organisation, published date and number. Revoked (danger) shows the revocation date. Not found shows "Check the number on the certificate".
- **Legal pages** (privacy, terms, content guidelines, ad guidelines; no ads): heading-1 and body text at reading width, with a "Last updated" date.
