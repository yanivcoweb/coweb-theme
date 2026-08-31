# CoWeb Theme — CLAUDE.md

Custom WordPress theme for coweb.co.il. Hebrew-only, RTL, dark UI.
Built with Timber/Twig + ACF Flexible Content. Design source of truth is the
Figma file `CoWeb — Redesign 2026` (key `QXG17uCfGkmn2XOAfKTAzk`).

---

## Non-negotiables

These are the rules that break the build if ignored. Everything else is
preference.

1. **No hardcoded design values.** Every colour, spacing, radius and font size
   comes from `assets/scss/_variables.scss`. If a value isn't there, it doesn't
   go in the CSS — add the token first.
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
| Local | Flywheel Local |
| Deploy | Git → Cloudways staging → production |

Not used, deliberately: page builders, Bootstrap/Tailwind, jQuery for new code,
any plugin that renders front-end markup we don't control.

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
  assets.php                   one stylesheet, two scripts, mtime cache-bust
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
                               stat-block, form-field, article-toc
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

Containers match the existing CoWeb convention so the ACF clone field keeps
working: `.container-narrow` (760px, article measure), `.container-medium`,
`.container` (1200px, the default), `.container-full-width`.

The 1200px container is `.container`, not `.container-wide` — it is the one
almost every section reaches for, so it gets the unqualified name and the
others carry the modifier. If the legacy site's ACF clone field emits
`container-wide`, that is the seam to check when porting content across.

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

Pages in the file:

| Page | Contents |
|---|---|
| `Foundations` | 41 variables in 2 modes, 11 text styles, 15 components / 38 variants |
| `Home / Desktop` | homepage, 1440px |
| `Inner Pages / Desktop` | nine screens, 1440px |
| `Mobile / 390` | the same ten screens, 390px |
| `States & Specs` | focus ring spec, button states, form states, mobile menu open + closed, wired prototype |
| `Motion` | duration and easing tokens, behaviour map, reduced motion, RTL transform trap |

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

**The theme is built and compiles.** Thirteen section layouts, every one with a
matching partial and block — the naming chain is unbroken end to end. Every
screen has a template behind it: home, work index and single, about, contact,
blog index/single/category, a generic content page, and 404.

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
`Referer` header. `wp_mail` returns false in the rig, so the valid path lands on
`failed` — that is the environment, not the theme.

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

**Tablet needs a decision, not a fix.** Nothing is broken between 768 and 1024:
no overflow, and the `auto-fit` grids reflow 1 → 2 → 3 columns sensibly. The one
open question is the orphan — three capability cards in a two-column grid leave
the third alone on row two (on the start edge, which is correct RTL). Whether it
should span both columns is a design call, and there is no frame to answer it.

**Unverified, and the reason is upstream:** the Figma file behind key
`QXG17uCfGkmn2XOAfKTAzk` lists exactly one page, `Foundations`, holding the 15
components. The `Home / Desktop`, `Inner Pages / Desktop`, `Mobile / 390`,
`States & Specs` and `Motion` pages this file describes are not in it. The
component library and the whole token layer check out; the screen frames could
not be compared because there are none to compare against. Confirm whether they
live in a different file before trusting the screen-level claims below.

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
