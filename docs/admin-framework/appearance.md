# Appearance — guest layouts and page surfaces

Myra v2.8 makes "what is behind the content" configurable for two surfaces:

| Surface | Screens | Default |
|---------|---------|---------|
| `auth`  | Login, Register, Forgot password, Reset password, Confirm password, Two-factor challenge, Verify email | Split layout on the brand colour |
| `page`  | The public homepage, both the legacy path and the page builder | No surface at all |

**Both defaults emit zero CSS.** An install that upgrades and changes nothing
renders the same DOM it rendered before. That is not a promise, it is a test:
`Background::cssVars()` returns `[]` for `brand` and `none`, so the surface
style tag is byte-empty and every shell falls back to the Tailwind literals it
has always used.

---

## The four guest layouts

All seven guest screens share one layout. There are no per-page overrides.

| Key | Shell | Flippable | Media | Shape |
|-----|-------|-----------|-------|-------|
| `split` | `SplitLayout.vue` | yes | yes | Today's look: a full-height brand panel beside the form, hidden below `lg` |
| `centered` | `CenteredLayout.vue` | no | no | One column, brand above the card. No image, nothing that can 404 |
| `cover` | `CoverLayout.vue` | no | yes | Full-bleed surface across the viewport, the card floating over the scrim |
| `card` | `CardLayout.vue` | yes | yes | The split lives *inside* the card; the media half hides below `md` |

`split` is the default **and** the fallback target. Anything the server cannot
resolve — an unknown key, a renamed layout, a traversal-shaped string, a `null`,
an integer, a half-saved settings row — resolves to `split`.

### Constraints every shell obeys

These are correctness properties, not taste:

- `min-h-screen`, never `h-screen`, never `overflow-hidden` on the root, never
  `background-attachment: fixed`. Register is four fields plus a five-segment
  strength meter and has to be able to grow and scroll.
- The card is never narrower than `max-w-md` above `sm`. The two-factor code
  input is `tracking-[0.5em]` and needs the width.
- The form is always inside `Card` → `bg-card` / `text-card-foreground`,
  whatever the surface does behind it.
- The brand is visible below the media breakpoint in every shell.
- No shell introduces a focusable element. `centered` and `cover` render the
  brand as a non-interactive mark; `card` keeps exactly the one mobile brand
  link `split` has always had.
- Decorative image gets `alt=""`; the scrim gets `aria-hidden="true"`.
- One uploaded asset serves both themes via `dark:brightness-[0.25]
  dark:grayscale`.
- Three of the seven screens (Verify email, Confirm password, Two-factor
  challenge) are rendered to a **logged-in** user. No shell may assume
  `auth.user === null`.

`tests/Feature/Appearance/AuthMatrixTest.php` crosses the seven routes with the
four layouts and the six background types and asserts each of the above.

---

## The six background types

| Type | Stored | Emits |
|------|--------|-------|
| `brand` | nothing | nothing — `bg-primary` / `text-primary-foreground`, exactly as before |
| `solid` | one `#rrggbb` | a base colour and a **computed** foreground |
| `gradient` | an allowlisted recipe *key* | the recipe CSS from a PHP const |
| `pattern` | an allowlisted recipe *key* | the same, rendered at `opacity-[0.12]` |
| `image` | a path on the public disk | an `<img>` plus a mandatory scrim |
| `none` | nothing | nothing |

Gradients and patterns are written over `var(--primary)` / `var(--accent)`, so
they re-tint when the brand colour changes and are theme-aware for free.

**Authored CSS is not a feature.** No authored gradient string, no hand-typed
`background-image: url(...)`, no custom CSS value, no video. The guest pages are
unauthenticated; the only things an admin can choose are a key from an
allowlist, a validated hex, and an uploaded file validated by magic bytes.

---

## Why the text stays legible

The guarantee is **a server-computed foreground plus a scrim floor plus card
isolation** — not a validation refusal and not a client-side check.

1. `BrandPalette::foregroundOn($hex)` picks black or white by measured WCAG
   contrast ratio. Because it always takes the better of the two, its worst
   achievable ratio is about 4.58:1. Legibility is a theorem, not a hope.
2. An image-backed surface cannot be measured, so its scrim is floored at
   `light` **during normalisation** — a hand-edited row, a seeder or a settings
   import can never produce an unscrimmed image.
3. The form never leaves `bg-card` / `text-card-foreground`. The worst case of a
   hostile background is an ugly tagline, never an unusable form.

Saving is never blocked. The admin sees the achieved ratio and a warning below
the configured minimum — advisory only. A rule that can reject an appearance is
a rule that can leave an admin staring at a screen they cannot fix.

---

## How the login page survives a bad choice

Six independent layers, each of which alone yields a working login page:

1. **Server resolution.** An unresolvable layout key never reaches the client.
2. **Client guard.** `BY_NAME[component] ?? Split` in the dispatcher. A layout
   registered on the server whose `.vue` file is not in this build lands here.
3. **No payload at all.** A missing meta tag and a missing Inertia prop leave
   `useAppearance()` on its hardcoded default: split on the brand colour.
4. **Image 404.** `<img @error>` hides the element; the base colour was painted
   by CSS before any JS ran, so the page stays legible.
5. **Junk payload.** Every shell runs its `auth` prop through `normalizeAuth()`,
   which is total: `null`, a string, an array or half an object all become the
   stock payload.
6. **Card isolation.** The form is legible whatever happens behind it.

The shells are bundled **statically** — `import.meta.glob(..., { eager: true })`,
never `defineAsyncComponent`. A blank homepage is survivable; a login shell
behind a network fetch is a lockout waiting for a bad deploy.

### Break glass

Two paths that work when you are already locked out:

```
MYRA_AUTH_APPEARANCE=false        # stock split + brand, with no DB read at all
php artisan myra:appearance reset # rewrite the rows to their defaults
```

---

## Adding a layout

1. Drop `resources/js/Layouts/Guest/YourLayout.vue`. The dispatcher globs the
   directory eagerly, so **no edit to `GuestLayout.vue` is needed**.
2. Declare it in `app/Appearance/Layouts/YourLayout.php` and list the class in
   `config/myra.php` under `appearance.auth_layouts`.
3. Give it `appearanceAdmin.layouts.{key}.title` and `.description` in all three
   locales.
4. Inline the surface block; do not import a shared surface component.
5. Re-run `tests/Feature/Appearance/AuthMatrixTest.php` — it will pick the new
   shell up from the filesystem and hold it to every constraint above.

A layout that is registered but whose component is missing is not a bug you have
to catch: the client guard falls back to `split` and the gallery lists the
missing component by name.

---

## The gallery

**Demo → Guest layouts** (`/admin/demo/auth-layouts`) renders every shell in this
build at quarter scale, in light and dark, with a dummy form body, over every
background the server can resolve. Each preview is driven by a **real**
`Background` payload produced by `AppearanceManager::fromInput()` — the gallery
cannot drift from what the login page actually renders. Nothing on the page is
saved.

If the appearance engine is not part of the build, the page says so and every
preview falls back to the default brand surface.

---

## The public page

`SiteSurface.vue` wraps navbar + main + footer in all six landing templates. The
legacy/page-builder branch happens further down, inside `TemplateBody.vue`, so
a page surface needs zero branch logic and behaves identically whether `blocks`
is empty or not. With the default `type: 'none'` it renders exactly the root div
the templates rendered before:

```html
<div class="min-h-screen bg-background text-foreground"><slot /></div>
```

`SiteNavbar` gains a `translucent` flag so it stops occluding the surface.
