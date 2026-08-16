# Landing page builder

Since v2.7 the public homepage is **a template plus an ordered list of section blocks**.

The template chooser is unchanged. It still owns the navbar, the footer, the page wrapper and the
per-section presentation variants. Blocks own what renders inside `<main>`.

| Concern | Owner |
|---|---|
| Navbar, footer, wrapper classes, presentation variants | the template (`HomepageSettings::$template`) |
| The sections a visitor reads, and their order | `HomepageSettings::$blocks` |
| Chrome copy (`footer_text`, `navbar_links`, …) | the flat settings, **not** the builder |

## The model

`HomepageSettings` gained exactly one property in v2.7:

```php
public array $blocks;
```

Every pre-existing flat property (`hero_title`, `features_enabled`, …) stays on disk, unmodified.
They are the rollback path and the parity fixture, and the branch on `$blocks` is what makes the
upgrade safe:

- `blocks === []` — the pre-v2.7 `OrderedSections` path runs, byte-identically.
- `blocks !== []` — the new `PageSections` path runs.

## Stored shape

`blocks` is a JSON list. One row:

```json
{
  "id":      "01JBQ8Z5X3K7WQ2M4N6P8R0T",
  "type":    "hero",
  "enabled": true,
  "variant": { "align": "center", "compact": false },
  "data":    {
    "title":      "Ship faster",
    "subtitle":   "…",
    "cta_text":   "Start",
    "cta_url":    "/register",
    "image_path": "homepage/01JB.webp"
  }
}
```

Four envelope keys. `id`, `enabled` and `variant` live on the **envelope**, never inside `data`, so
no author-defined field name can shadow them. `data` is a flat map except for `list` fields, which
are arrays of flat maps. Keys `data` does not declare are dropped on write. `variant` keys are
allowlisted against the type's `variants()`.

`id` is a server-assigned ULID, never an array index — collapse state, drag state and the Vue `:key`
are all keyed by it.

`MAX_BLOCKS` is 100, enforced on read (truncate) and on write (validation).

### What the public client receives

The normalised payload differs from storage in three ways:

- `enabled: false` rows are already gone.
- unknown-`type` rows are already gone (they remain in storage).
- every `image` field `foo_path` is accompanied by `foo_url`, a string only when the file actually
  exists on the public disk, and `null` otherwise.

```json
{ "id": "01JB…", "type": "hero", "variant": {},
  "data": { "title": "…", "image_path": "homepage/x.webp", "image_url": null } }
```

## Degradation guarantees

The homepage is the front door. It must never render blank, so every layer degrades rather than
throws:

| Input | Result |
|---|---|
| `blocks` is `null`, a string, a number or an associative array | normalises to `[]`, legacy path renders |
| A row is not an array, or has no usable `type` | that row is skipped |
| A row names a type no longer registered | skipped on render, **kept in storage** |
| A row's `data` is not an array, or a field has the wrong type | coerced to the field's declared default |
| A `list` field is not a list, or holds scalars | coerced to `[]` / bad rows dropped |
| A `variant` value is outside its allowlist | falls back to the declared default |
| An `image_path` points at a file that is not on disk | `image_url` is `null`; the section uses its no-image branch |
| A section component throws while rendering | `SectionBoundary` removes **only that section** |
| An unknown template key | `Home.vue` falls back to Classic, as it has since v2.6 |

`normalize()` is total: it must not throw for any input whatsoever, including objects, resources,
deeply nested arrays, `NAN` and `INF`.

Template `supports` does **not** filter the block path. It is advisory only — every section
component is a `<section>` inside `<main>` and composes in all six templates. This removes the
"filter left zero blocks, page is blank" edge entirely instead of special-casing it. The editor
shows an advisory badge; the public page always renders what was authored. The legacy
`OrderedSections` path keeps filtering by `supports` exactly as before.

## Security

Authored content is untrusted at render. It is written in the admin and served to anonymous
visitors.

- **HTML** — `rich_text` is the only field type that reaches the DOM through `v-html`. It is
  sanitised server-side on write with `HtmlSanitizer::clean()` **and** client-side on render with
  `sanitizeHtml()`. Belt and braces. `script`, `iframe`, `object`, `embed`, `form`, `svg` and
  friends have their whole subtree removed, not merely unwrapped.
- **URLs** — every `url` field passes `UrlGuard::safe()`. The allowlist is `http`, `https`,
  `mailto`, `tel`, a leading `/`, a leading `#`, and the empty string. `javascript:`, `data:`,
  `vbscript:` and protocol-relative `//host` are rejected to `''`.
- **Uploads** — `POST /admin/landing/builder/image` validates magic bytes with `getimagesize()`.
  The extension is derived from the `IMAGETYPE_*` constant and **never** from the client filename.
  The stored name is a ULID. Files land on the public disk under `homepage/` because the private
  inline-upload route is permission-gated and therefore unusable for anonymous visitors — the
  magic-byte check is replicated there, not assumed.
- **Authorisation** — every builder endpoint calls `Gate::authorize('settings.edit')`. v2.7 adds no
  new permission.

## Declaring a section type in a package

Section types are declared in PHP and shipped to the client as JSON, exactly like templates. A
package therefore contributes a section type with no frontend change to the framework.

```php
namespace Acme\Blocks;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionType;

final class BannerSectionType
{
    public static function define(): SectionType
    {
        return SectionType::make('banner')
            ->icon('Megaphone')
            ->group('conversion')
            ->titleField('title')
            ->maxPerPage(2)
            ->fields(
                SectionField::text('title')->required()->max(120),
                SectionField::url('link_url'),
                SectionField::select('tone', ['brand', 'muted']),
            )
            ->variants(['compact' => 'bool'])
            ->since('1.0.0');
    }
}
```

Register it by appending the class to the package's own config array:

```php
// config/myra.php
'pagebuilder_extra' => [
    'extra_sections' => [
        \Acme\Blocks\BannerSectionType::class,
    ],
],
```

`SectionRegistry::seed()` reads `myra.pagebuilder.sections` and then
`myra.pagebuilder_extra.extra_sections`, so core and contributed lists never edit each other.

Two things the package must also ship:

1. `resources/js/Pages/Public/Templates/_shared/sections/banner.vue` — **the filename is the type
   key**. `PageSections` resolves components by an eager `import.meta.glob` over that directory, and
   a type with no component file is skipped rather than fatal.
2. i18n keys for `labelKey` / `descriptionKey` (by default `pageBuilder.sections.banner.label` and
   `.description`) in every shipped locale.

Every section component takes the same three props, so nothing else has to know the type exists:

```ts
defineProps<{
    block: Record<string, unknown>;   // normalised: every declared field present and typed
    settings: HomepageData;           // brand and chrome context; NOT the content source
    variant?: Record<string, unknown>;
}>()
```

### Field types

| Type | Coerced to | Default |
|---|---|---|
| `text`, `textarea` | string, truncated at `max()` | `''` |
| `html` | string, sanitised on write | `''` |
| `url` | `UrlGuard::safe()` | `''` |
| `image` | string storage path | `''` |
| `bool` | `filter_var(…, FILTER_VALIDATE_BOOL)` | `false` |
| `number` | int or float | `0` |
| `select` | one of `options`, else the first option | first option |
| `icon` | one of the 18 allowlisted lucide names | `''` |
| `list` | array of coerced rows, capped at `max()` | `[]` |

A `select` never defaults to `''` — reka-ui rejects `<SelectItem value="">`. Declare an explicit
non-empty sentinel option if the field genuinely needs a "none".

## Preview

There is one renderer for preview and production: the preview iframe loads the **real** public `/`.
No postMessage bridge, no bespoke preview renderer.

The coupling is a session slot, not a shared class:

- key — `myra.pagebuilder.preview`
- value — `['token' => string, 'blocks' => array, 'expires_at' => int]`
- TTL — 15 minutes, one slot per session; a new POST overwrites it.

`POST /admin/landing/builder/preview` (behind `settings.edit`) stores the draft and returns a token.
`PreviewSlot::pull()` returns those blocks only when **all** of the following hold: the request
carries a non-empty `?preview=` token, the requesting user can `settings.edit`, the token matches
under `hash_equals()`, and the slot has not expired. Otherwise it returns `null` and the stored
blocks render. The slot is session-scoped, so a guessed token is worthless. This mirrors
`TemplateRegistry::mayPreview()`.

Nothing is persisted by a preview.

## Migration and rollback

`2026_08_17_000001_add_blocks_to_homepage_settings.php` is strictly additive: it touches no column
and drops no row. It reads the raw `settings` rows through the query builder (never
`app(HomepageSettings::class)`, which could not hydrate the property it is adding) and writes one
new `homepage.blocks` row built by `LegacyHomepageBlocks::fromRows()`.

`LegacyHomepageBlocks` is the single conversion function, used by **both** the migration and
`SettingsSeeder`, so a fresh install and an upgrade can never diverge. It:

- emits blocks in the stored `section_order`, appending any canonical section the order forgot;
- always emits `hero` (it has no toggle);
- emits `features` / `testimonials` / `pricing` / `cta` with `enabled` taken from the matching
  `*_enabled` flag — a disabled section survives as a **hidden block**, not a deletion;
- keeps `pricing_plans[].features` a comma-joined **string**, as the renderer expects;
- assigns ULIDs, leaves `variant` empty, and is idempotent apart from those ids.

### Rolling back

Write `blocks = []`. The flat singletons were never mutated, so the revert is exact and instant, and
the pre-v2.7 page returns unchanged. The editor exposes this as "Revert to classic settings" behind
a confirmation.

`php artisan migrate:rollback` deletes only the `homepage.blocks` settings row.

## Where things live

| Path | What |
|---|---|
| `app/Homepage/Sections/` | `SectionField`, `SectionType`, `SectionRegistry`, `SectionNormalizer`, `SectionWriter`, `LegacyHomepageBlocks`, `PreviewSlot` |
| `app/Homepage/Sections/Types/` | the ten built-in type declarations |
| `app/Support/UrlGuard.php` | the URL scheme allowlist |
| `resources/js/Pages/Public/Templates/_shared/TemplateBody.vue` | the single branch between the two render paths |
| `resources/js/Pages/Public/Templates/_shared/PageSections.vue` | the block renderer |
| `resources/js/Pages/Public/Templates/_shared/SectionBoundary.vue` | per-section error containment |
| `resources/js/Pages/Public/Templates/_shared/sections/*.vue` | one component per type; **filename is the key** |
| `resources/js/Pages/Admin/Landing/Builder.vue` | the editor |
| `resources/js/composables/useSectionList.ts` | the editor's only mutation surface |
| `resources/js/Pages/Admin/Demo/PageBuilder.vue` | the read-only gallery page (`/admin/demo/page-builder`) |
| `tests/Feature/PageBuilder/EndToEndPageBuilderTest.php` | saves through the real endpoint, reads the real public props |
| `tests/js/fixtures/page-builder-blocks.json` | that payload, asserted by the PHP test and mounted by `tests/js/pageBuilderPayload.spec.ts` |

The fixture is the only link between the PHP job and the vitest job, which run on separate
checkouts. `SyncsPageBuilderFixtures::syncFixture()` asserts every value in the committed file
against the payload the server just shipped — keys the server adds are tolerated, a changed or
missing one fails — and rewrites it only under `MYRA_WRITE_FIXTURES=1`. Row ids are restated as
`row-N` because the real ones are ULIDs, reissued per save; the ids themselves are asserted in PHP.

Naming note: the tree already has an `App\Admin\Blocks\BlockRegistry` (the vendored shadcn
catalogue) and a `Block` field in `useFormSchema.ts` (the form-field block). The page builder is
therefore **Section**-named throughout, and no class here is called `BlockRegistry` or `BlockEntry`.

## Known follow-ups

Deliberately out of scope for v2.7, recorded here so they are not rediscovered:

1. **`resources/js/components/admin/BuilderField.vue` has five defects.** It is the v2.0 form-field
   block builder and has a live, untested consumer, so v2.7 does not touch it:
   - rows are `:key`ed by array index, so Vue reuses the wrong DOM node after a move or delete;
   - `collapsedRows` is also index-keyed and is never remapped after a reorder, so the wrong row
     collapses;
   - `''` is used as the default for every field type, including `bool`, `list` and `select` —
     a `select` in particular then hands reka-ui an empty value;
   - the `GripVertical` handle is decorative: no drag wiring and no keyboard reordering;
   - `block.icon` is declared but never rendered.
   The page builder's `useSectionList.ts` fixes all five for its own surface; porting them back is
   a v2.8 item.
2. **`SettingController::updateHomepage` does not guard its URL fields.** `hero_cta_url`,
   `cta_button_url`, `navbar_cta_url` and the link arrays are stored as submitted. The builder's own
   write path uses `UrlGuard`; retro-fitting it onto the legacy settings form is a separate, narrower
   change and is a v2.8 item.
3. **One editor, never two.** The legacy homepage form and the builder write the same settings
   group, and the branch on `blocks` decides which one the public page honours — so a legacy save
   that the public page ignores would be a silent no-op. Whatever the shipped resolution is
   (legacy authoritative while `blocks` is empty and said so in the UI, legacy migrated onto
   blocks, or legacy redirected to the builder), it is not acceptable to leave both live with one
   of them doing nothing. See `SettingController::updateHomepage`.
