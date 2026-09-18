# CoWeb Theme — CLAUDE.md

Custom WordPress theme for coweb.co.il. Hebrew-only, RTL, dark UI.
Built with Timber/Twig + ACF Flexible Content. Design source of truth is the
Figma file `CoWeb — Redesign 2026 (Copy)` (key `5x6drQcEQ2Rbdk7RvXDZhj`), the revision
that added photography and section background images. It replaced the original
`CoWeb — Redesign 2026` (key `QXG17uCfGkmn2XOAfKTAzk`) as source of truth on 2026-09-15.

---

## Non-negotiables

These are the rules that break the build if ignored. Everything else is
preference.

1. **No hardcoded design values.** Every colour, spacing, radius and font size
   comes from `assets/scss/_variables.scss`. If a value isn't there, it doesn't
   go in the CSS — add the token first.
   There is exactly one documented exception, `COWEB_THEME_COLOR` in
   `inc/meta.php`: the `theme-color` meta tag is read before any stylesheet, so
   it cannot reference a custom property. It duplicates `color/surface/base`.
   Change one and change the other, or a dark site gets a white status bar.
2. **Logical properties only.** Never `margin-left`, `padding-right`, `left`,
   `right`, `text-align: right`, or `border-left`. See the RTL section below.
3. **All content comes from ACF.** No text, image, or link is hardcoded in a
   Twig partial. If it appears on screen, an editor can change it.
4. **ACF field groups are registered in PHP**, in `inc/acf-sections.php`. Never
   export/import JSON, never create fields in the admin UI.
5. **Every `include` uses `with { … } only`.** No implicit variable inheritance
   between partials.

---

## Stack

| Layer | Choice |
|---|---|
| Templating | Timber 2.x / Twig |
| Fields | ACF Pro — Flexible Content |
| Styles | SCSS, compiled to a single stylesheet |
| Fonts | IBM Plex Sans Hebrew + IBM Plex Mono (Google Fonts, enqueued) |
| Local | Flywheel Local — `coweb.local`, see Known state |
| Deploy | GitHub Actions → rsync over SSH → Cloudways staging → production |

Not used, deliberately: page builders, Bootstrap/Tailwind, jQuery for new code,
any plugin that renders front-end markup we don't control.

---

## Deploy

`.github/workflows/deploy.yml`. One `git push` moves the whole theme; there is
no per-file upload and no FTP client in the loop.

| Trigger | Target |
|---|---|
| push to `main` | staging, automatically |
| Actions → *Deploy theme* → Run workflow | staging or production, on demand |

The build happens in CI, not on the server. `assets/css/` and `vendor/` are
gitignored — they are never in the repo and are never on the server unless the
workflow puts them there — so the sync carries compiled CSS and a `--no-dev`
`vendor/` while `assets/scss/`, `node_modules/` and the tooling manifests are
excluded. Cloudways needs neither node nor composer installed.

Two guards worth keeping:

- **The workflow fails if `assets/css/main.css` is empty or missing.** That is
  the one failure mode that would otherwise report green and leave an unstyled
  site — the CSS is a build artifact, so a broken `sass` run produces no error
  anywhere downstream.
- **`rsync --delete` prunes whatever `THEME_PATH` points at.** A wrong path is
  a destructive deploy, not a failed one. Run once with `dry_run: true` after
  changing that secret, and read the file list before trusting it. Excluded
  paths are protected from deletion by rsync (`--delete-excluded` is
  deliberately not used), so `.git` or an `uploads` symlink inside the theme
  would survive — but nothing else would.

Secrets are per **environment** (`staging`, `production`), not repo-wide, which
is what makes the two targets differ and what lets production require a
reviewer before the job runs:

| Secret | Example |
|---|---|
| `SSH_HOST` | the Cloudways server IP |
| `SSH_USER` | the application SSH user |
| `SSH_KEY` | the private half of a key added under Cloudways → SSH Keys |
| `THEME_PATH` | `/home/master/applications/<app>/public_html/wp-content/themes/coweb` |

**Staging is live through this pipeline, 2026-09-14.** `test.coweb.co.il` is
Cloudways application `fdpkfwrsnd` (the panel also shows it as `cowebtest` —
one folder, two names) on `161.35.213.89`, deployed as the `master_*` server
user with a dedicated `github-actions` key. Three things the first deploy
taught:

- **Never derive `THEME_PATH` from a folder that already holds a `coweb`
  theme.** Two applications on that server did — both are `erl.co.il`, a
  client site running the old Bootstrap starter of the same name. Pointing
  `--delete` at either would have erased a client's live theme. Find the app
  by `server_name` in `applications/<app>/conf/server.nginx`, never by theme
  folder.
- **Set path secrets through stdin, not `--body`.** From Git Bash on Windows,
  `gh secret set --body "/home/..."` has MSYS rewrite the leading `/` into a
  Windows path; the stored value came back without its slash and rsync
  failed on `mkdir "/home/master/***"`. `printf '%s' "$path" | gh secret set`
  is immune. The workflow now refuses any `THEME_PATH` that does not match
  the Cloudways shape, so this fails before the SSH step.
- **A dry run proves the connection, not the path.** `--dry-run` creates no
  directories, so the first dry run passed on the broken value. Read the
  manifest for what it does show — deletions, and whether `vendor/` and
  `assets/css/` are in it — and treat the first real run as the path test.

The deploy key pair lives gitignored in the repo root as `coweb-deploy` /
`coweb-deploy.pub`; the private half is also the `SSH_KEY` secret. Production
has no environment yet — creating one is the same four secrets against the
production application's path, plus a required reviewer.

**Staging content is seeded, not authored, since 2026-09-18.** The same
`seed.php` that builds `coweb.local` ran on the server, from
`applications/fdpkfwrsnd/private_html/seed/` — outside the webroot, beside
`seed-media/` and a `wp db export` taken first (`before-seed-*.sql`). Three
things that run needed:

- **The seeder looks for `wp-load.php` beside itself or under `app/public`**,
  which is Local's layout. On Cloudways `seed/app/public` is a symlink to
  `public_html`, so the script is byte-identical on both machines — checked by
  checksum before running.
- **`scp` from Git Bash fails on that path** with "No such file or directory"
  although the directory exists. `tar -czf - … | ssh … 'tar -xzf - -C …'` does
  not care.
- It overwrites every page's `sections`, the menu and the options. That was
  agreed for staging, where nothing had been authored by hand. It is not a
  production procedure.

This deploys the **theme only**. Content, uploads and the database do not move
— a section added locally needs its ACF rows authored again on the live site,
because the layout ships in `inc/acf-sections.php` but the content never does.

---

## Structure

```
*.php                          thin Timber routers — no logic, no markup
functions.php                  requires inc/, defines COWEB_* constants
inc/
  breadcrumbs.php              trail derived from the request, never authored
  timber.php                   Timber bootstrap + global Twig context
  setup.php                    theme supports, menus, editor constraints
  post-types.php               `work` CPT, `work_tag` taxonomy, filter chips
  assets.php                   one stylesheet, three scripts, mtime cache-bust
  meta.php                     description, Open Graph, canonical, JSON-LD
  acf-sections.php             every field group, registered in PHP
  contact-form.php             own handler — no form plugin
templates/
  base.twig                    global shell
  page.twig                    flexible content loop — no hardcoded layout
  single-work.twig             extends page.twig; a case study is sections too
  archive-work.twig            project grid + tag filters
  index.twig / archive.twig / single.twig / search.twig / 404.twig
  partials/
    _missing.twig              editor-facing warning for an unbacked layout
    site-header.twig / site-footer.twig
    ui/                        button, section-header, project-card,
                               post-item, pagination, breadcrumbs, tag-chip,
                               stat-block, form-field, article-toc,
                               next-project, search-form
    sections/                  one file per ACF layout
assets/scss/
  _functions.scss              rem() and fluid()
  _variables.scss              design tokens — generated from Figma
  _mixins.scss                 one mixin per text style, layout helpers
  _base.scss                   reset, defaults, focus ring, reduced motion
  _layout.scss                 containers
  _ui.scss                     component primitives
  _blocks.scss                 section styles
  main.scss                    the only entry point
assets/js/
  menu.js                      mobile drawer
  toc.js                       article TOC, only enqueued on posts
  reveal.js                    section reveal on scroll — the one entry on the
                               behaviour map that needs a script
```

Build with `npm run build` (or `npm run dev` to watch). `assets/css/` is
generated and gitignored; never edit it, and never add rules to `style.css` —
it exists only so WordPress can identify the theme.

Timber is a Composer dependency of the theme, not a plugin. After a fresh
clone: `composer install`. `vendor/` is gitignored; `composer.json` and
`composer.lock` are committed.

### Timber 2.x traps

Three of these cost real time on the first local run, and none of them
produce an error message that points at the cause:

- **`Timber::$dirname` defaults to `views`.** Our templates are in
  `templates/`, so without `Timber::$dirname = ['templates']` every render
  returns an empty string. Not an exception, not a warning — HTTP 200 with a
  blank body. It is set in `inc/timber.php`; don't remove it.
- **`function('name')` calls a PHP function; a registered Twig function is
  called directly.** Registering `breadcrumbs` through `timber/twig/functions`
  and then writing `function('breadcrumbs')` fails, because that looks for a
  *PHP* function of that name. Write `breadcrumbs()`. `function('wp_head')` is
  correct precisely because `wp_head` really is a PHP function.
- **`site.language` returns the raw locale (`he_IL`), which is not a valid
  HTML language tag.** `base.twig` uses `get_bloginfo('language')`, which
  returns the hyphenated `he-IL`.

### Naming

The chain must stay unbroken in all three places:

```
Figma component   hero-main
Twig partial      templates/partials/sections/hero-main.twig
ACF layout        hero_main
```

kebab-case in Figma and on disk, snake_case in ACF. `page.twig` converts
between them with `|replace({'_': '-'})`. Never break this by renaming one
side only.

---

## RTL

The site ships in Hebrew only, but the theme stays direction-agnostic so an
English version later costs nothing.

| Never | Always |
|---|---|
| `margin-left` | `margin-inline-start` |
| `margin-right` | `margin-inline-end` |
| `padding-left/right` | `padding-inline` |
| `padding-top/bottom` | `padding-block` |
| `left: 0` | `inset-inline-start: 0` |
| `text-align: right` | `text-align: start` |
| `border-left` | `border-inline-start` |

Five traps worth naming, because each one has already cost time:

- **Don't add `flex-direction: row-reverse` to "fix" RTL.** Flex and grid
  already reverse under `dir="rtl"`. Adding it reverses twice.
- **Code, phone numbers and email addresses stay LTR.** Use the `text-code`
  mixin, or `direction: ltr; unicode-bidi: isolate;`.
- **Active-state markers sit on the start edge**, which is the *right* in RTL.
  `border-inline-start`, not `border-left`.
- **A paragraph that opens with a Latin word flips its base direction.** The
  Unicode bidi algorithm takes direction from the first strong character, so
  `WPML ו־Polylang, ניהול slugs…` lays out left-to-right and strands `WPML` on
  the wrong edge. In CSS the fix is the container's `dir="rtl"`; **in Figma
  there is no direction property**, so prefix the string with U+200F (RLM).
  Applies to copy starting with `WPML`, `ACF`, `Core Web Vitals`, `REST API`.
  Better still, rewrite the line to open in Hebrew — `מצב מנוחה · default`
  beats `default · מצב מנוחה` and needs no invisible character to survive.
- **`transform` is the one property with no logical form.** Everything else
  mirrors itself under `dir="rtl"`; `translateX` does not. A menu that should
  enter from the start edge needs its sign flipped by hand — see the RTL card
  on the `Motion` page for the `--slide` pattern. `translateY` is unaffected,
  which is exactly why this gets missed.

Hebrew typography: the negative letter-spacing on large headings suits Latin
but can crowd Hebrew finals. If a headline looks cramped, raise the tracking
rather than shrinking the font.

---

## Design tokens

Two forms of every token. Prefer the CSS custom property; fall back to the SCSS
variable only inside media queries or `calc()` where Sass needs a compile-time
value.

```scss
// Figma       color/surface/base
$color-surface-base;            // SCSS
var(--color-surface-base);      // CSS
```

Type is fluid — headings interpolate between the 390px and 1440px sizes with
`clamp()` via the `fluid()` function. There is no per-heading media query to
maintain. Use the mixins: `text-display`, `text-h1`…`text-h3`, `text-body-lg`,
`text-body`, `text-small`, `text-button`, `text-nav`, `text-mono-label`,
`text-code`.

Those two ends of the `clamp()` are the two modes of the `CoWeb Tokens`
collection in Figma — `Dark` carries the 1440px sizes, `Mobile` the 390px ones:

| Token | Dark | Mobile |
|---|---|---|
| `text/size/display` | 64 | 38 |
| `text/size/h1` | 48 | 30 |
| `text/size/h2` | 36 | 25 |
| `text/size/h3` | 26 | 20 |
| `text/size/body-lg` | 20 | 17 |
| `text/size/body` | 17 | 16 |
| `text/size/small` | 15 | 14 |
| `text/size/mono` | 13 | 12 |

Every text style binds its `fontSize` to the matching token, so a frame pinned
to the `Mobile` mode re-renders the whole type ramp with no per-node overrides.
`UI/Nav` (16) and `Mono/Code` (14) are deliberately unbound — they do not
scale. Keep it that way: a hardcoded size in a text style silently breaks the
mode, and the mock stops predicting the CSS.

A hero standfirst is a measured column, not the full container. Every frame
draws `page-hero`'s intro flush with the start edge at 760 (`blog / category`)
or 820 (the other four) of the 1200. It was `46ch`, which on a 20px Hebrew body
resolves near 550 — the line wrapped a third earlier than any frame draws it.
It is `--size-measure` now.

Containers match the existing CoWeb convention so the ACF clone field keeps
working: `.container-narrow` (760px, article measure), `.container-medium`,
`.container` (1200px, the default), `.container-full-width`.

The 1200px container is `.container`, not `.container-wide` — it is the one
almost every section reaches for, so it gets the unqualified name and the
others carry the modifier. If the legacy site's ACF clone field emits
`container-wide`, that is the seam to check when porting content across.

**Those numbers are content width, and the gutter is added on top of the cap:**

```scss
max-inline-size: calc(var(--size-container) + 2 * var(--size-gutter));
```

`box-sizing: border-box` is global, so writing `max-inline-size: 1200px` with
`padding-inline: 24px` yields 1152px of content and every screen sits 48px
narrower than Figma draws it. That was the state until 2026-09-01, and the
article measure was the worse half: `--size-measure` is documented as 760px,
~65 Hebrew characters, and was rendering at 712.

It hides well. Below the cap `inline-size: 100%` wins and the gutter comes out
of the viewport either way, so 390 always measured correctly — only viewports
past the cap disagree, and only by the gutter. The confirmation that the fix is
right was `article__columns`: at 1200 of content its `space-between` resolved to
a 180px gap between the 760px body and the 260px TOC, which was Figma's spacing
to the pixel.

That evidence has since been spent, not withdrawn. The 2026-09 revision's
`article-row` is an auto-layout with a 72 gap, so the TOC moved in beside the
article and 108px of slack now falls at the end edge; `article__columns` is
`justify-content: start` with a `--space-3xl` column gap. The 1200 and the 760
are unchanged — only the space between them moved — so the container fix still
stands, it just no longer proves itself here.

---

## Motion

Motion tokens live in a second Figma collection, `CoWeb Motion`, deliberately
scoped to nothing — Figma has no duration or easing primitive, so they can't
bind to anything and exist only to be exported to `_variables.scss`.

| Token | Value | Used for |
|---|---|---|
| `duration/instant` | 0ms | the focus ring, and nothing else |
| `duration/fast` | 120ms | hover, exit, any colour change |
| `duration/base` | 200ms | menu, TOC marker, accordion — the default |
| `duration/slow` | 320ms | scroll reveal only |
| `ease/standard` | `cubic-bezier(0.2, 0, 0, 1)` | state changes in place |
| `ease/entrance` | `cubic-bezier(0, 0, 0, 1)` | anything arriving |
| `ease/exit` | `cubic-bezier(0.3, 0, 1, 1)` | anything leaving |

Entrance is always slower than exit. A fast exit reads as responsive; a fast
entrance reads as jumpy.

Rules that are not negotiable:

- **Animate only `transform` and `opacity`.** Animating `width`, `height`,
  `top` or `margin` forces layout on every frame. The accordion is the single
  exception and runs on `grid-template-rows`, not `height`.
- **Never animate the focus ring.** It appears instantly or the AA claim is
  theatre.
- **`prefers-reduced-motion` is a requirement**, not a nicety — IS 5568 routes
  to WCAG 2.3.3. Use `0.01ms`, never `0`: zero cancels `transitionend` and
  silently breaks anything waiting on it. The end state must be identical
  either way; reduced motion skips the tween, it does not skip the step.
- The behaviour map on the `Motion` page is closed. An element that isn't on
  it doesn't animate — that's a decision, not an omission.
- **Open and close are different rows on that map**, so they cannot share one
  transition: `menu open` is `base` + `entrance`, `menu close` is `fast` +
  `exit`. In CSS that means the value lives on the *closed* rule (it plays on
  the way out) and the `[data-open]` rule carries the entrance. The drawer had
  one shared `base`/`entrance` transition until 2026-09-01.
- `section reveal` is the only row needing JavaScript: `opacity` +
  `translateY(8px)`, `slow`, `entrance`, once per element, 60ms between items
  in the same row. `assets/js/reveal.js` hides an element *only* after its
  IntersectionObserver reports it outside the viewport, so nothing on screen is
  ever hidden and a page whose observer never fires renders fully at rest.
  Deciding what to hide from `window.innerHeight` instead looks equivalent and
  is not — any environment reporting a zero height blanks the page.

---

## Sections

Every page is a `sections` Flexible Content field. There is no other page
layout mechanism.

Adding a section means three coordinated changes:

1. A layout in `inc/acf-sections.php`
2. A partial at `templates/partials/sections/<name>.twig`
3. A block in `assets/scss/_blocks.scss`

Rules for section partials:
- One root element, one BEM block, class name matching the file name.
- Generated values stay generated. Step numbers and card indices come from
  `{{ '%02d'|format(loop.index) }}`, never from an ACF field — an authored
  number always drifts the moment someone reorders rows. (Note the argument
  order: Twig's `format` filter applies to the format string, not the value.)
- A missing partial renders `_missing.twig`, which warns logged-in admins
  instead of failing silently. Keep it that way.
- **Background photos use one pattern everywhere.** A section with a photo
  behind it in Figma gets `coweb_background_image_field( '<prefix>' )` (ACF
  name `background_image`, array return) as its first sub-field; a
  `<block>--background` modifier carrying the `has-section-background` mixin,
  added only when the field is set; and
  `partials/ui/section-background.twig` with `image`, `scrim`
  (`none|medium|deep|strong|mobile`), `glow` and `eager` — included as the
  **last** child of `<section>`, because `reveal.js` animates the children of
  the section's *first* child. No image means no markup and no modifier, so
  the section renders as it did before. **An archive has no section row to
  put the field in** — the work index and the blog index are queries, not
  pages — so those pass a name to the same helper and register it on the
  options page instead (`work_archive_background_image`,
  `blog_archive_background_image`, one per archive); the partial, the
  scrim and the modifier are unchanged. A card sitting on the photo takes the
  `raised-over-image` mixin inside that modifier, never on the base component
  — the Foundations components are still flat.
  **A component the revision lifts on *every* screen is a different thing** and
  takes `raised-card` instead: the same overlay → raised gradient with
  `border/strong` and `--shadow-card` rather than `--shadow-raised`. The first
  four users of it (`project-card`, `media-full`, `media-text`, the contact
  panel) still write the four declarations out inline because the mixin did not
  exist yet; anything new uses the mixin. Do not reach for `raised-over-image`
  to get a resting card — the shadow is wrong and the border is only a colour
  change, so it needs a `border-width` from somewhere else.
  Images are content: the seeder
  imports them from `E:\Local Sites\coweb\seed-media\` with
  `coweb_seed_image( $file, $alt )`, keyed by filename so a re-run never
  duplicates an attachment.

---

## The document head

`inc/meta.php`. No SEO plugin — a plugin brings its own admin UI, its own
storage outside ACF, and front-end markup the theme doesn't control, for a
handful of tags derived from content that already exists.

Everything is **derived**, for the same reason breadcrumbs are: a per-page "SEO
title" field drifts away from the heading an editor actually sees, and nobody
notices for months.

| Tag | Source |
|---|---|
| `<title>` | core, via `add_theme_support('title-tag')` |
| `description` | post excerpt → first section `intro`/`sub`/`text` → post content → archive description → site tagline |
| `og:*`, `twitter:card` | the same title/description, plus the share image |
| `canonical` | built from the queried object, on **every** view |
| `theme-color` | `COWEB_THEME_COLOR` — see the non-negotiables |
| JSON-LD | `ProfessionalService` on the front page, `BreadcrumbList` wherever the trail has depth, `BlogPosting`/`CreativeWork` on a single |

Four things worth knowing before editing it:

- **Core's `rel_canonical` is removed.** It only covers singular views, so
  every archive went without one. Ours covers everything — and two canonical
  links is worse than none, because then the crawler picks.
- **The canonical is built from the queried object, never `REQUEST_URI`**,
  which carries whatever tracking parameters the visitor arrived with.
- **A page's description comes from its first section's intro.** Pages are
  Flexible Content, so `post_content` is empty and `get_the_excerpt()` returns
  nothing — the hero intro is the page's own summary, already written, already
  on screen.
- **404 and search emit none of it.** A preview card for a dead URL is noise in
  a share sheet.

Trimming is `mb_*` throughout. `substr` on Hebrew cuts a multi-byte character
in half and puts a replacement glyph in the description.

The share image falls back to a `share_image` field on the options page, and
the card type follows it: `summary_large_image` only when there is an image,
because a large card without one renders as an empty grey box.

---

## Accessibility

The site carries a published accessibility statement claiming IS 5568 level AA.
That makes the following requirements, not suggestions:

- Visible focus ring on every interactive element:
  `outline: 2px solid var(--color-text-primary); outline-offset: 2px;`
  Never `outline: none` without a visible replacement. Use `:focus-visible`,
  not `:focus`. The ring is deliberately `text/primary` and not the brand
  colour — an ember ring vanishes on an ember button. It is **one CSS rule**;
  only `button` and `form-field` carry a `focus` variant in Figma, because
  those were worth drawing. Never give a component its own different ring.
- Body text contrast ≥ 4.5:1, UI elements ≥ 3:1. The token palette already
  satisfies this — breaking it means introducing a non-token colour.
- **A disabled control must not look identical to an enabled one.** All three
  `button` disabled variants were once pixel-for-pixel copies of `default`;
  they now drop to `brand/ember-dim` / `border/subtle` with `text/muted`.
  Disabled text is exempt from the 4.5:1 minimum — that is the point of it.
- Colour alone never carries meaning. The error state pairs
  `color/feedback/error` with `aria-invalid` and an `aria-describedby`
  pointing at the message; a red border on its own says nothing to a screen
  reader.
- Semantic headings in order. Sections use `<h2>`; only the page title is `<h1>`.
- Alt text on every content image, pulled from the ACF image field.
- Full keyboard navigation, including the mobile menu.

---

## Working with the Figma file

Pages in the file — **address them by node ID**:

| Page | nodeId | Contents |
|---|---|---|
| `Foundations` | `0:1` | 41 variables in 2 modes, 11 text styles, 15 components / 38 variants |
| `Home / Desktop` | `6:2` | homepage, 1440px |
| `Inner Pages / Desktop` | `13:3` | nine screens, 1440px |
| `Mobile / 390` | `31:3` | the same ten screens, 390px |
| `States & Specs` | `24:15` | focus ring spec, button states, form states, mobile menu open + closed, wired prototype |
| `Motion` | `104:5` | duration and easing tokens, behaviour map, reduced motion, RTL transform trap |

**`get_metadata` with no `nodeId` lists only `Foundations`.** That listing is
broken, not the file — every page above returns a full subtree when asked for
directly. A previous session trusted the listing, concluded the screens didn't
exist, and wrote that into this file as a launch blocker. Don't repeat it.

Components on `Foundations` — every one carries a description naming its partial
path and ACF mapping, so read it before writing the Twig:

`button` (3 variants × 4 states), `form-field` (2 types × 4 states), `tag-chip`
(default / active), `section-header`, `capability-card`, `project-card`,
`process-step`, `page-hero`, `stat-block`, `pagination`, `article-toc`, and
four that carry `Breakpoint=desktop|mobile` — `site-header`, `site-footer`,
`cta-band`, `post-item`.

Screens, on both the desktop and mobile pages: `work-index`, `work-single`,
`about`, `contact`, `blog-index`, `blog-single`, `blog-category`,
`page-content`, `404`, plus the homepage.

When implementing from Figma:
- Work one frame or component at a time. A whole page in one prompt produces
  one unmaintainable blob.
- Read variables with `get_variable_defs` and map them to existing tokens
  rather than emitting new hex values.
- Component descriptions in Figma carry the intended partial path and ACF
  mapping — read them before writing anything.
- MCP tool calls are rate-limited by Figma. Batch work into single calls.
- **A FILL child inside a HUG parent collapses.** If a Figma frame reports a
  width of 1px and an absurd height, this is why — the parent hugs the child
  while the child fills the parent. Give the parent a fixed size on that axis.
  This had already silently broken `post-item`.
- **Instance sub-frames refuse height overrides.** Resizing a frame nested
  inside an instance is ignored, `resizeWithoutConstraints` included. When a
  size needs to differ, add a variant to the component — that is why
  `form-field` has `Type=input` and `Type=textarea` rather than one component
  stretched by hand.

---

## Known state

**The theme is built and compiles.** Seventeen section layouts, every one with a
matching partial and block — the naming chain is unbroken end to end. Every
screen has a template behind it: home, work index and single, about, contact,
blog index/single/category, a generic content page, and 404.

**`stack_list` and `timeline` are the last two `about` bands**, Figma
`stack-list` (15:74) and `timeline` (15:95), seeded straight after
`media_text` in the frame's order. Both drop their top padding like
`doc-note` — the frame draws every band under the `about` hero with bottom
padding only. What is not obvious from the markup:

- **The stack chips are not `tag-chip`.** They are plain frames in the file,
  filled `surface/overlay` and set in Mono/Label, so they are
  `.stack-list__item` — text in a `role="list"` `<ul>`, never links. The label
  over them is the section's `<h2>`, drawn at label size.
- **The timeline's year is authored; its heading level is generated.** The
  frame draws no heading, so `heading` is optional and visually hidden: set,
  entry titles are `<h3>`; empty, they become `<h2>`. The seeded
  `ציר זמן` is the one line in either row that is not Figma copy.
- **The year sits on the start edge because it is first in the DOM**, not
  through `order` or `row-reverse`. Below 768 it stacks over the entry as an
  eyebrow; from 768 it is the frame's row. No width is reserved for it —
  Mono digits are one width, so four-digit years keep every title on one
  column, and a range like `2016–2018` would push only its own row.
- **Neither animates beyond the ordinary section reveal.** The behaviour map
  has no timeline row. The `<ol>` is a plain block, not a grid, deliberately: `reveal.js`
  staggers the children of a *grid* by column, which would have made a
  one-column list alternate 60/120ms.

Search results are `search.php` + `templates/search.twig`, and the field
itself is `partials/ui/search-form.twig` — it appears on the results page and
on the 404, which is the only entry point to search the site has. The partial
takes `hide_label` (the results page has an `<h1>` that names the field; the
404 does not) and `variant` (primary where submitting is the page's main
action, secondary on the 404 where "back to home" already owns primary). Without that pair
WordPress falls back to `index.php`, which renders the blog archive under the
heading "בלוג" — no term, no count, and no way to tell an empty result from an
empty blog.

`posts` is a Timber `PostQuery`, which extends `ArrayObject`: Countable, but an
object, so `{% if posts %}` is true even when it holds nothing. Test
`posts|length` — every empty state in the theme was unreachable until this was
found.

**`archive.twig` extends `index.twig`**, so one file renders the posts index,
every category, every tag and every date archive. Anything the `blog / index`
frame has and `blog / category` does not — the hero photo, the featured card —
has to be gated on `is_home()` rather than simply written into the markup, and
`is_home` and `is_paged` are in the Twig allow-list in `inc/timber.php` for
exactly that. The featured card is the newest post, promoted out of the list so
it is never shown twice, and it is off past page one, where `posts|first` is no
longer the newest article. Derived, like breadcrumbs and `next-project`: an
authored "featured post" field goes stale the moment something newer is
published, and nothing says so.

Reading `blog / category` (22:207) against it settled the gate and added three
things:

- **The category archive has no photo.** Its `hero-bg` (2010:385) is a flat
  `surface/base` fill where every other hero in the revision carries an image
  fill, so the `is_home()` gate on the photo is right as written.
- **The chip row moved into the hero band.** Figma `category-filters`
  (2065:222) sits above the eyebrow, full bleed, closed by a `border/subtle`
  rule — not down beside the list, which is where the work index keeps its own
  (`work-filters`, 13:19, a separate band *below* its hero; don't move that
  one). It renders on the posts index too, where the frame drops it entirely,
  for the reason it always did: `post-item` prints its category as text, so
  chips are the only way into a category archive. `.archive-filters` is its own
  section rather than a child of the hero because the rule runs edge to edge
  and because `reveal.js` animates the children of a section's *first* child —
  folding it in would have taken the heading out of that set. The 37 the frame
  leaves under the rule comes from `.archive-filters + .page-hero-section`
  rather than a modifier the template has to remember to pass.
- **`result-count` (22:234)** is drawn on the category archive and nowhere
  else, so it is gated the other way — `not is_home()`. It reads
  `posts.found_posts`, the size of the query rather than of the page, so page
  two still says how many the category holds.

**Every archive shipped its heading alone.** `index.twig` has always rendered
`{% if description %}` and nothing ever filled it, so the Body/Large standfirst
both blog frames draw (21:106, 22:221) was missing on all of them.
`coweb_archive_description()` in `inc/timber.php` answers for term archives from
the term's own description — core already stores one, and an authored "archive
intro" would be a second place to write the same sentence. A post-type archive
has no equivalent field, so `work / index` and the posts index still come back
empty rather than getting one invented for them; both frames draw an intro, and
that is the gap left.

A category's trail now goes through the blog: `coweb_breadcrumbs()` inserts
`page_for_posts` ahead of a `category` or `tag` term, which is what the eyebrow
(22:219) reads and what a single post in that same category had all along. A
`work_tag` archive belongs under the work index and a date archive under
neither, so neither is included.

**It has been looked at.** 2026-08-31, in real Chrome, at 390 / 768 / 1024 /
1440. This is the thing the previous two sessions could not do — the in-app
browser pane still cannot composite frames, so the screenshots come from the
Chrome extension driving a same-origin iframe harness (`vp.html` in the test
rig), which is what gives an exact CSS viewport at each width. Inside that
harness `getComputedStyle` reads back correctly, so computed styles are
trustworthy again; the staleness noted in earlier sessions was specific to the
pane.

Measured, not assumed: at 1440 the hero `h1` computes to 64px on `#0b0c0e`; at
390 the same heading computes to 38px. Those are the `Dark` and `Mobile` values
of `text/size/display`, so the `clamp()` does what the two Figma modes promise.
Every colour token in `_variables.scss` was compared against
`get_variable_defs` and all nine match to the hex. 36 route × width checks
(9 routes × 4 widths, search results and empty-search included) show zero
horizontal overflow, exactly one `<h1>` per page, no skipped heading levels, no
physical `text-align`, no unlabelled images, no empty links. Every screen was
also looked at individually, not only swept: the TOC fills from the rendered
`h2`s and its active marker sits on the start edge, the work archive's filter
chips put the active one on the right, and the 404 renders its single button
because the optional second link has no URL in the rig. The focus ring was confirmed by real keyboard Tab —
`2px solid #f4f5f7` at 2px offset, one global `:focus-visible` rule.

The contact form round-trips through all four paths (expired / sent / error /
failed) and now returns to the form's own page even when the request carries no
`Referer` header. `wp_mail` returned false in the SQLite rig, so the valid path
landed on `failed` there — the environment, not the theme. That gap is closed:
on `coweb.local` the valid path reaches `sent` and the mail arrives, see below.

**Fixed in that pass**, each one invisible to a template-only reading:

- `work-selected` passed ACF's raw `WP_Post` to `project-card`, which reads
  `.title` / `.link` / `.thumbnail` / `.terms()`. Every card rendered as an
  empty shell with an empty `href` and no error anywhere. It goes through
  `get_post()` now.
- Timber appends an English "Read More" to every excerpt, in a `.read-more`
  class the stylesheet has no rule for. Switched off globally in `inc/timber.php`.
- The breadcrumb on a case study read "Work" — the CPT labels go through
  `__()` and `languages/` was empty. `he_IL.po`/`.mo` now exist; the `.mo` is
  committed because WordPress needs it at runtime.
- `.contact-detail__value--ltr` set `direction: ltr` on a *block*, so
  `text-align: start` resolved left while the label above resolved right and
  the pair sat on opposite edges of the column. The value is a `<bdi>` now,
  which isolates character order without touching alignment.
- The mobile drawer had `justify-content: space-between` with exactly two
  children, which stranded the two nav links at the bottom of a full-height
  panel and made the list's own `margin-block-start` a no-op.
- `project-card` hardcoded `<h3>`, so the work archive skipped `<h1>` → `<h3>`.
  It takes a `level` now — the level, not the size; `text-h3` still fixes the size.
- Four `include` tags had no `with { … } only`. All 26 now do.

**The grids are pinned to the design's column counts**, not to whatever
`auto-fit` happens to fit. That distinction was invisible until the screens
could be read: `auto-fit` derives its count from the track floor, so it matched
the design only while a section held fewer items than one row. Six capability
cards came out 4+2 against Figma's 3+3, and six projects came out 3+3 against
2+2+2. Measured after the change, at 390 / 768 / 1024 / 1440:

| Grid | 390 | 768 | 1024 | 1440 | Figma |
|---|---|---|---|---|---|
| `capabilities-grid` | 1 | 2 | 3 | 3 | 1 / — / 3 |
| `process-steps` | 1 | 2 | 4 | 4 | 1 / — / 4 |
| `work-selected` | 1 | 2 | 2 | 2 | 1 / — / 2 |
| `work-archive` | 1 | 2 | 2 | 2 | 1 / — / 2 |
| `stat-row` | 2 | 4 | 4 | 4 | 2 / — / 4 |

**Tablet is still undesigned**, and the 768 column is a judgement call — there
is no frame between 390 and 1440. The orphan question survives in one place
only: `about` has three principles, so a two-column tablet grid leaves the
third alone on row two (on the start edge, which is correct RTL). The home
page's six cards divide evenly and never had the problem.

**All six Figma pages exist.** An earlier session recorded the opposite, and
that claim was wrong: `get_metadata` called with no `nodeId` returns only
`0:1: Foundations`, and the absence was read as the file's rather than the
listing's. Address pages by node ID and they all return full subtrees — see the
table in the Figma section below.

**Four gaps closed 2026-09-01**, all of them found by reading the screen frames
against the code. None was visible from the templates alone, and none would
have been found without the screens:

- **Grid column counts.** See the table above. `auto-fit` agreed with the
  design only by coincidence.
- **Section reveal did not exist.** The behaviour map specifies it and
  `--motion-slow` was defined in `_variables.scss` and referenced nowhere — a
  token minted for a feature nobody built. Now `assets/js/reveal.js`.
- **`work-single` had no next-project navigation.** Figma has the frame on both
  breakpoints. It is derived from the query, not ACF — an authored field would
  drift the moment a project is inserted between two others — so it lives in
  `partials/ui/`, beside breadcrumbs and pagination, and `single-work.twig`
  appends it after `{{ parent() }}` rather than becoming a section.
- **The mobile drawer was missing its whole bottom block** — CTA, email and
  phone — and the wordmark from its top bar. This reframes something recorded
  earlier as fixed: the drawer's `justify-content: space-between` was not a
  stray rule, it was correct for the three children the design has and wrong
  for the two that were built. Removing it treated the symptom.

Reading the behaviour map to build the reveal turned up a fifth: the drawer ran
one shared `base`/`entrance` transition for both directions, where the map gives
close `fast`/`exit`. Measured after the fix — open `0.2s`, close `0.12s`.

**The reveal was watched running, 2026-09-08.** Sampled in a visible browser
pane at 1522×746, scrolling `capabilities-grid` into view:

| t | opacity — header · three cards | translateY |
|---|---|---|
| before scroll | `0.00 0.00 0.00 0.00` | `8 8 8 8` |
| 64ms | `0.57 0.00 0.00 0.00` | `3 8 8 8` |
| 151ms | `0.85 0.69 0.34 0.00` | `1 2 5 8` |
| 274ms | `0.99 0.94 0.86 0.70` | `0 0 1 2` |
| 709ms | `1.00 1.00 1.00 1.00` | `0 0 0 0` |

At 64ms the header is over half in and no card has moved, which is the 60ms
step; the last card's 180ms delay plus the 320ms `slow` duration lands it
around 500ms. `translateY` runs 8px → 0 exactly as the map specifies. Scrolling
past, back to the top and through a second time leaves it at 1.00 — it runs
once. Above the fold, `hero-main` and `logo-strip` were never marked at all, so
nothing on screen is hidden for a frame.

**A surface has to be visible to verify any of it.** A hidden browser pane, and
a Chrome tab in a background window, both report
`document.visibilityState: "hidden"`, run no rendering steps, fire no
`requestAnimationFrame` and deliver no IntersectionObserver report — the pane
also reports a 0×0 viewport. That is one cause, not two, and it applies equally
to `toc.js`. Computed styles, geometry and DOM assertions read fine either way.
The fail-safe was seen from both sides: with nothing rendering, nothing is
hidden that was on screen, and the reveal arrives as soon as the surface is
shown.

Everything else measured in the pane at 390 / 768 / 1024 / 1440: zero
horizontal overflow on home, work archive and work single; the next-project
block puts its label and title on the start edge and the arrow on the end, in
`#ff7a45` over a `#23272e` rule — both the exact Figma values; the closed
drawer computes `visibility: hidden` on a clean load, so it stays out of the
tab order with three more focusables in it.

**The dev environment moved, 2026-09-08.** The theme now runs on a Local
(Flywheel) site at `E:\Local Sites\coweb`, served at `http://coweb.local/`,
with `wp-content/themes/coweb` a junction back to the repo — the same
arrangement the disposable SQLite rig used, and the same warning: never delete
the site folder recursively without removing the link first. The rig still
exists and is still the fastest surface for a pure template check, but anything
touching mail, MySQL or real content belongs here now. Seeded by `seed.php`,
kept one level above the webroot at `E:\Local Sites\coweb\seed.php` so nothing
dev-only is ever web-served — it is idempotent, and **has to run twice on a
fresh site**:
`switch_theme()` does not load the new theme's `functions.php` in the same
request, so the `work` CPT is not registered yet and its archive menu item is
silently skipped.

**`wp_mail` works there**, through Mailpit, and that closed the one path the rig
could never show. All four contact-form paths were confirmed against delivered
mail: `sent`, `error`, `expired`, and the honeypot answering `sent` while the
mailbox count stays put.

Which is how the last real bug in that form surfaced. **A malformed email came
back empty while every other field repopulated** — `sanitize_email()` reduces
anything invalid to an empty string, and the value was being stored after
sanitising, so the one field the visitor has to correct was the one that came
back blank. The handler now keeps what was typed for the form and a separate
sanitised `$email` for validation, the mail body and `Reply-To`. Confirmed both
ways: `broken-at-example` returns with all four fields intact, and a valid
address padded with spaces still sends, trimmed.

**Re-measured on `coweb.local`, 2026-09-08**, ten routes × four widths, zero
deviations across all forty: no horizontal overflow, one `<h1>` per page, no
skipped heading levels, no physical `text-align`, no unlabelled image, no empty
link. `.container` resolves to 1248px (1200 of content plus two 24px gutters);
the article body is 760px beside a 260px TOC with a 180px gap, Figma's spacing
to the pixel at the time — the 2026-09 revision moved that gap to 72, see the
Design tokens section; the hero `h1` is 64px at 1440 and 38px at 390, the article's 48
and 30. The closed drawer computes `visibility: hidden` with `translateX(+390)`
— a positive sign, which is the start edge in RTL and the whole point of the
`transform` trap — and closes in 0.12s. `next-project` puts its title on the
start edge and its `#ff7a45` arrow on the end, over a `#23272e` rule.

Two things this pass had to teach:

- **Log out, or hide the admin bar, before measuring.** A signed-in Chrome
  renders `#wpadminbar`, which adds 32px of height and physical
  `text-align: right` on eight elements — the first sweep reported a
  right-alignment "bug" on all ten routes. A visitor never sees it.
- **The tablet orphan does not appear in the current content.** Six capability
  cards divide 2+2+2 at 768, four process steps 2+2, and the three blocks on
  `about` are `text_blocks`, which stack one per row at every width rather than
  forming a two-column grid. The question stays open for any future section with
  an odd count.

The section reveal took two attempts, and the first one is the lesson: with the
Chrome window in the background `document.visibilityState` read `hidden`,
nothing rendered, and every element came back `opacity: 1` — the fail-safe
behaving correctly, not a result. Bringing the window to the front and sampling
per animation frame gives the real thing:

| t | header · first three cards |
|---|---|
| before scroll | `0.00/8 0.00/8 0.00/8 0.00/8` |
| 65ms | `0.47/4 0.00/8 0.00/8 0.00/8` |
| 205ms | `0.91/1 0.80/2 0.58/3 0.00/8` |
| 343ms | `1.00/0 0.98/0 0.94/1 0.84/1` |
| 552ms | all `1.00/0` |

(opacity/translateY in px.) At 65ms the header is nearly half in and no card has
moved — the 60ms step; the last of them lands around 500ms, which is its 180ms
delay plus the 320ms `slow` duration. Scrolling past, back to the top and
through a second time leaves everything at 1.00, so it still runs once.
`hero-main` and `logo-strip` are never marked, so nothing above the fold is
hidden for a frame.

One detail worth writing down, because it is easy to mistake for a bug: sampled
mid-flight the six capability cards read `0.69 0.33 0.00 0.69 0.33 0.00`. The
stagger is **per row**, not per card — cards 1 and 4 open together because they
share a column position. That is what "60ms between items in the same row" on
the behaviour map means, and the first card to arrive is the one on the right,
which is the start edge.

**The screen-by-screen pass is done.** All ten screens compared against both
their desktop and mobile frames. Seven more divergences, all of them template
or PHP rather than CSS polish:

- **Container width was 48px narrow everywhere.** See the Design tokens
  section — the single largest finding, and the one that had been invisible
  longest because mobile measures correctly either way.
- **No category chips on the blog.** Figma draws the same `tag-chip` row on
  `blog-index` and `blog-category` that `work-index` has. `coweb_post_filters()`
  now sits beside `coweb_work_filters()` in `inc/post-types.php`; they are one
  idea and should stay together.
- **A category archive was headed "בלוג".** `archive.twig` is `index.twig` with
  a different query, and Timber fills `title` for a post-type archive but not
  for a category, so every category fell through to index.twig's own default.
  The category name was in the `<title>` tag the whole time, which is how it
  survived. `coweb_archive_title()` in `inc/timber.php` now answers for both.
- **`get_the_archive_title()`'s prefix leaked into the breadcrumbs** — "בית /
  ארכיונים: עבודות", "בית / קטגוריה: WPML". Dropped once at the source with
  `get_the_archive_title_prefix`, so every consumer agrees.
- **The posts index had no breadcrumb at all.** `is_home()` is neither
  `is_singular()` nor `is_archive()`, so it fell through every branch in
  `coweb_breadcrumbs()` and came out with a one-entry trail the partial
  declines to render.
- **The TOC was hidden below desktop.** Figma puts it full width *above* the
  article at 390. The aside is now first in the DOM so a single column stacks
  it correctly and the tab order still matches; the two-column case places both
  children explicitly. `.article__aside:has([hidden])` collapses it when
  `toc.js` has fewer than two headings to list, so a short post gets no empty
  row — measured at zero.
- **`blog-single` had no cta-band.** Figma closes it with one on both
  breakpoints. An article has no `sections` field, so the copy comes from a new
  `article_cta` group on the options page and goes into the existing partial.

**Checked and correct, against an earlier misreading:** `contact` matches on
both breakpoints. Figma puts the form at x=540..1320 — the *right*, which is
the start edge in Hebrew — with the details on the left, and stacks the form
first at 390. That is exactly the DOM order. The frames were read edge-inverted
the first time; x=0 in a Figma frame is the left, which in RTL is the end.

Measured after all of it, at 390 / 768 / 1024 / 1440: zero horizontal overflow
and exactly one `<h1>` on home, work index, work single, contact, blog single
and a category archive; twelve routes returning 200/404 with an empty PHP error
log; `.container` resolving to 1200px of content and `.container-narrow` to
760px past the cap.

**That deviation is closed.** The 404 used to carry a search field that was in
no frame — kept because it is the only entry point to search the site has, and
flagged as something to draw into Figma rather than leave undocumented. The
2026-09 revision draws it: `error-search` (22:152), 440 wide, below the two
links rather than above them, with the `radius/lg` corner instead of a
form-field's `radius/sm`. It is drawn as a bare field — no label above it and
no submit button beside it — so `search-form.twig` gained `show_button` and
`placeholder` to say so. The label stays in the DOM and is only visually
hidden, because a placeholder is not a label; Enter still submits, since a form
whose only field is a text input has implicit submission with or without a
button.

**The generic content page is three sections now, not two.** Figma
`page / content` (22:160) is the accessibility statement, and the 2026-09
revision restructured it end to end:

- **Its hero stopped being the `page-hero` component.** The frame is still
  *named* `page-hero`, but it is a plain frame: an accent eyebrow where the
  component draws the muted breadcrumb trail, Display 64 where the component
  draws H1 48, a last-updated line under the title, a full-bleed photo and the
  ember glow the component never carries. That is the same call `post-hero`
  made on `blog / single`, so it is its own layout — `doc_hero`, because
  `page-hero` is taken by the component that is still in use on `about` and
  `contact`. The hairline Figma hangs on `doc-body` as its `border-top` lives
  on `doc-hero` as a `border-block-end` instead: same rule at the same y, and
  it keeps `rich_text` — which also renders on `about` — free of it.
- **`rich_text` was centring a column the design has never centred.** Both
  revisions put the 760 measure at x=560..1320, flush with the *start* edge of
  the 1200 container. `container-narrow` centres, so the body sat in the middle
  of the page. It is a capped block inside `.container` now, which start-aligns
  itself. Nothing else moved: `text_blocks` and a narrow `media_full` still use
  `container-narrow`, where centring is what their frames draw.
- **`doc_note` is new** — Figma `accessibility-contact` (22:196), the closing
  panel, on the `raised-card` surface rather than `raised-over-image`: it is
  lifted on a screen with no photo behind it, which is the whole distinction
  between those two mixins. It carries the `<h2>` above it as a field rather
  than leaving it as the last line of the prose, because in Flexible Content
  each section owns its own rhythm and a heading left upstream lands a full
  band away from the panel it names.
- **The bullet marker is an accent hyphen**, scoped to `.rich-text`, via
  `list-style-type: "-"` and a coloured `::marker` — not `list-style: none` and
  a `::before`. Removing the list style removes the list *semantics* in
  Safari's screen reader, and an editor writing in a wysiwyg has no way to put
  `role="list"` back. An older browser falls back to a disc, which is a marker
  of the wrong shape rather than a list that is no longer a list.
- **A consequence in the head.** `doc_hero` has no `intro`, so
  `coweb_meta_section_intro()` walked past it and would have described the page
  with `doc_note`'s `text` — the accessibility coordinator's phone number.
  `content` is in that key list now, ahead of `text`, so the description comes
  from the first sentence of the body, which is what a hero intro is everywhere
  else.

There had never been a route for this screen: no generic content page was
seeded, so `rich_text` had only ever rendered as a stray row on `about`. The
seeder builds `/accessibility/` now, from the frame's own copy, behind
`coweb-doc-hero-access.jpg`.

**The head layer landed 2026-09-01** — see the section above. Measured across
seven routes: exactly one canonical on every indexable view and none on 404 or
search, Open Graph and `twitter:card` on all five indexable ones, `theme-color`
everywhere including the 404, and JSON-LD that parses on every route that emits
it. `COWEB_THEME_COLOR` was checked against the compiled
`--color-surface-base`; both are `#0b0c0e`.

Two things in the head are **blocked on assets, not code**:

- **No favicon.** This needs no theme code at all — core's `wp_site_icon()`
  already emits the tags once a square image is uploaded under Settings. It is
  waiting on the wordmark becoming a real vector, which is the same thing
  blocking the 26px hardcoded logo size.
- **No OG image.** The plumbing prefers a post's featured image and falls back
  to `share_image` on the options page. Neither exists yet, so every share
  currently renders as a small card with no picture. 1200×630.

One consequence of the derived-description rule worth watching: an archive with
no term description falls through to the site tagline, so **the tagline is now
load-bearing** — it is the description for the work index and the blog index.
It is empty in the rig, which is why those two routes emit no description tag
at all rather than a wrong one.

**The site has two pillars since 2026-09-18: WordPress and business
automation, at equal weight.** The term is `אוטומציה עסקית` for the discipline
and `אוטומציות לעסקים` for the page title and hero button — the second is the
phrase people search for. It is content only; no layout, partial or block
changed. What moved, in `seed.php` and in the Figma frames together:

- **Home.** Eyebrow, heading, intro, the capabilities header, and the six cards
  — row one is the WordPress pillar, row two automation (`אוטומציית תהליכים`,
  `סוכני AI`, `אינטגרציות ופיתוח מותאם`). Performance and accessibility merged
  into one card and `הגירות ושדרוגים` went, because the grid is pinned to 3+3.
  The hero's secondary button points at `/automation/` rather than `/work/`;
  the work is still one section down and first in the menu.
- **About.** Hero intro, a fourth bio paragraph, six more stack chips, the 2026
  timeline entry, and the middle principle — `קוד לפני תוספים` became
  `תהליך לפני אוטומציה`, since three is what the grid holds.
- **`/automation/` is new**, built only from existing layouts: `page_hero`,
  `capabilities_grid` (six), `process_steps` (four), `text_blocks`, `cta_band`.
  No stat row on purpose — there is no measured figure to put in one. It is
  second in the primary menu, which is five items now; they sit on one row from
  768 up. Figma has `automation` (2117:435) and `automation / mobile`
  (2118:402), both assembled from clones of sections already in the file.
- **The shared `cta_band` copy changed**, so it changed on the case studies too.
- **Figma's nav read `יכולות` where the site never had that item**; it reads
  `אוטומציות` now, in the `site-header` component, every mobile header and the
  drawer on `States & Specs`. Figma still draws four nav items and no `בלוג`.
- **`Mobile / 390`'s home frame is an older draft** — four cards, three steps,
  its own copy — so the desktop strings matched nothing there and it was
  rewritten node by node inside the structure it has. Its chip row on `about`
  was already in reverse reading order; the new chips follow it rather than
  fix it. On desktop the wrapped chip row is ordered by hand: the row is LTR
  with `MAX` alignment, so reading order is the layer order reversed *per row*,
  and it will need redoing if the chip count changes the break.

A second pass the same day closed the strings that still spoke for one pillar:
the tagline is `פיתוח וורדפרס ואוטומציה עסקית` (so that is what both archives
now carry as their description), the author bio, and `article_cta`, reworded to
close a post about either subject. The JSON-LD needed no edit — the
`ProfessionalService` description is `coweb_meta_description()`, so it followed
the hero intro on its own, which is the derived-head rule paying out. The
`stack_list` and `timeline` headers on `about` lost the `הביו שלי` placeholder
the frame had copied across three sections; Figma was changed with them.
`blog / single` in Figma draws no closing cta-band at all, on either
breakpoint, so that copy exists on the site only.

**The first content that is not placeholder went in the same day**, and it is
the automation pillar's evidence: the `invoice-automation` case study and the
post `prompt-to-software` in a new `automation` category. Every figure in both
— 14 suppliers, the 06:30 run, three dedup layers, 223 tests, 10 of 11 on the
first dry run — was read out of `C:\Work\invoice-automation` (README, CHANGELOG
0.14.2, `docs/audit-existing-automation.md`), and Yaniv cleared the supplier
names and all the numbers for publication. It runs in production and is
presented as a product whose first customer is its author. Re-read those files
before editing the copy; do not round the numbers from memory. What follows
from it:

- **Home's `work_selected` is two projects by slug, one per pillar** — Polytex
  and the invoice automation — not `$work_ids`. Three would strand a card alone
  on the pinned two-column grid. The work index does show three, so the orphan
  question is live there at every width from 768 up, not only on tablet.
- **The seeder's post rows take an optional seventh element, the body.** The
  five invented posts still share the layout-demo `$body`.
- The case study has no photograph and borrows the process mesh, for its card,
  its `media_full` and the post's thumbnail.
- The post is the newest, so it is the featured card on the blog index.
- No Figma change: `work / single` and `blog / single` are templates, and both
  still draw their original sample copy.

**A third pass added the second real post, a fourth project and the first code
change of the repositioning:**

- `when-the-supplier-blocks-you` — Bezeq, reCAPTCHA, failing loudly, the cloud +
  local agent split, and the billing-period bug from CHANGELOG 0.14.2. Same
  source rules as the first post. It is the featured card now.
- **`logit` is the fourth project**, from `C:\Work\logit\README.md`: a product,
  not a client job and not an automation. Four cards also close the orphan the
  third opened on the work index. It borrows `work_hero` for a picture. The
  README says MIT, but nothing says the repository is public, so the page does
  not call it open source.
- **The contact form has a `topic` select** — site / automation / both / not
  sure — between email and message. It is not in the `contact` frame. Three
  things about it that are not obvious:
  - `coweb_contact_topics()` in `inc/contact-form.php` is the one list. Twig
    reads it through `contact_topics()` and the handler accepts a key only if it
    is in it; an unknown key is dropped rather than rejected, because the field
    is optional and "no answer" is a valid answer.
  - `form-field.twig` gained `type: 'select'` with `options` (key => label) and
    `placeholder` as the empty first option. It stays a **native** select: the
    closed control takes the input's class, the open list is the browser's. A
    custom listbox needs a script and its own keyboard model to stay AA.
  - **A native select ignores `line-height`** — Chrome computes it back to
    `normal` — so it rendered 58px beside a 62.6px input. `--select` carries a
    `min-block-size` that spells the input's box out. Measured after: 62.64
    against 62.63.

  Confirmed against Mailpit: a malformed email returns with the topic still
  selected, and the valid path delivers `נושא: אוטומציה עסקית` in the body.

**Six real WordPress posts joined them**, written from the Claude Code memory
of Yaniv's client projects: an ACF block that renders empty with no error, a
`term_order` that saves and does not show, a protected page that got cached,
the `<?php//` fatal under PHP 8, RTL without `dir="rtl"`, and a custom
WooCommerce variation picker. Two new categories, `core` and `woocommerce`.
Rules they were written under, which hold for anything added to them:

- **No client is named.** `אתר לקוח`, always. The logo strip names clients; a
  post describing what was broken on one of their sites does not.
- **The protected-page post leaves out how that site's access check works.**
  The lesson is the cache; the mechanism is nobody's business.
- The events are real, the publishing dates are chosen, and all six sit before
  the two automation posts so those keep the featured card.
- **A shortcode in a code sample runs.** `the_content` processes shortcodes
  inside `<pre>` too, and with Contact Form 7 active on both sites a literal
  `[contact-form]` rendered as "Error: Contact form not found." in the middle
  of the snippet. Brackets in code samples are `&#91;` / `&#93;`.

The blog is thirteen posts and paginates now — `/blog/page/2/` had never
existed before.

**The five invented posts are retired** — `hello-acf`, `wpml-slugs`,
`lcp-hebrew`, `acf-blocks`, `multisite-or-not`. The seeder trashes them rather
than deleting, so `wp_untrash_post()` restores any of them; `$body` stays as the
fallback for a future row with no body of its own. Anything in this file that
says to check `/hello-acf/` means `/acf-block-renders-empty/` now.

**`coweb.local` is not only seeded content.** It carries 25 posts from the live
coweb.co.il, 2016–2025, under categories the seeder never made (אחסון, ניהול,
עיצוב, קידום, תכנות) — which is why its blog runs to four pages while staging's
is eight posts on one. They are SEO guides in a different voice from the new
posts, and three from 2019 are about API and CRM integrations. Nothing here
touches them; what to keep is an editorial call that has not been made.

**Polytex is rewritten from the project's own notes**: five languages with a
site each, 18 landing pages moved to a CPT, six resource templates, one theme.
The old figures were guesses and `WPML` was simply wrong — the network is one
site per language with no translation plugin. One thing in it is unconfirmed:
the notes record the landing-page migration on the local network with staging
pending on 2026-08-30, so whether it has reached production needs asking.

**Four more client case studies, all names cleared by Yaniv**: `all2zimmer`
(הכל לצימר — a 948-product catalogue rebuilt as 9 / 42 / 159, and a colour and
size picker), `cdbox` (a packing-advisor plugin with a WordPress-free engine and
91 tests), `dogsstate` (48 → 35 plugins on a live store, and a checkout that
recalculates once instead of twice) and `ojma` (three pages added inside another
team's ACF-block theme). The work index is eight projects, 2 × 4. Written under
these rules:

- **Every number is one the project's notes state.** Nothing was rounded up or
  filled in; where the notes were unsure, the sentence went.
- **Nothing claims to be live.** The notes record the catalogue restructure and
  the advisor on local sites. The copy says what was built, in the past tense.
  Ask before adding a launch date or a link to a client's site.
- **Left out on purpose:** which plugins Dogs State dropped, who wrote the OJMA
  theme, and how the closed third-party checkout plugin is protected.
- A `stat-block` value is laid out left to right, so `48 → 35` reads in the
  right order on an RTL page. Looked at, not assumed — an arrow between two
  numbers is exactly the case bidi reorders.

Still not touched: Rosslare, whose figures (3 markets, 180+ product pages) are
as invented as before — the notes for that project hold bug fixes, not
architecture. Staging now carries the same seeded content as local; see Deploy.

Content is placeholder throughout: invented post dates and titles, unverified
case-study metrics, grey rectangles for every image. Before launch, all of it
needs replacing with real material — and layouts should be re-checked against
real copy, since Hebrew headlines that run longer than the placeholder will
change how sections break.

The two `work` posts had no `sections` rows at all until this pass, so
`single-work.twig` had only ever rendered a 200 with an empty body — the exact
trap the Timber section warns about. They are seeded now in the rig only. The
four figures on `polytex` (4 languages, 6 sites, 1.4s LCP, AA) are the same
unverified numbers as before: they exist to exercise `stat-block`, and nobody
has measured them. Replace each with a real measurement or delete it.

Client names are approved for use, but nothing about them is verified: the
wordmarks in `logo-strip` are text placeholders standing in for real vector
logos.

The blog is entirely fictional: invented posts, invented dates in 2026, and an
article body written to demonstrate the layout.

The `coweb` wordmark is still a 26px text node with a hardcoded size — the one
place in the file that ignores the token system. It stays that way until the
logo becomes a real vector, at which point the size question disappears.

Not yet designed: tablet orphan behaviour, logo as a real vector, favicon, OG
images.

What blocks launch is content, not design: the blog is invented, the
case-study figures are guesses, and every image is a grey rectangle.
