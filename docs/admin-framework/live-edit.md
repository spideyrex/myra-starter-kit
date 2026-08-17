# Live editing in the page-builder preview

Click text in the preview and type. The change lands in the draft, in the undo
stack, and in the left panel — there is still one source of truth.

Toggle it with **Live edit** in the preview toolbar.

## What is editable where

| Kind | Behaviour |
|------|-----------|
| `text` | Edited in place. Enter commits, Escape reverts. |
| `multiline` | Edited in place. Enter inserts a newline. |
| `image` | Selects the section and scrolls its image control into view. |
| `html` | Same — a browser would invent markup, so rich text stays in the panel. |
| `link` | Same — the visible text is a label, not the URL. |

## Marking a field

A section renderer marks the element that shows a field:

```vue
<h2 data-myra-field="title" data-myra-kind="text">{{ title }}</h2>
```

Repeater items address themselves by path, and the index comes from the loop:

```vue
<CardTitle :data-myra-field="`items.${i}.title`" data-myra-kind="text">
    {{ feature.title }}
</CardTitle>
```

`PageSections.vue` tags each section root with `data-myra-block="<row id>"`, so
the agent resolves a field to its row by walking up to the nearest marked
ancestor. **A field with no `[data-myra-block]` ancestor is ignored** — there is
no row to write back to. That is why the same components are inert on the
settings-driven homepage.

Nothing else is needed. A new section type becomes editable the moment its
renderer carries the markers.

## How the two documents talk

The preview iframe is the REAL public page, not a bespoke renderer. The bridge
is `postMessage`, tagged with a channel so nothing else on the bus is read, and
origin-checked on both ends. The host additionally ignores any message whose
`source` is not its own frame.

```
frame → host   ready | change | select | activate
host  → frame  enable | highlight
```

The agent (`resources/js/pagebuilder/liveEditAgent.ts`) is dynamically imported
and only when **all** of: the page is framed, `?preview=` is present, and the
builder sends `enable`. An anonymous visitor never fetches it.

## Two things this had to get right

**Typing must not reload the frame.** Every draft change republishes the draft
and reloads the preview, which would throw the caret away mid-word. The preview
records what the frame already shows and skips exactly those republishes; a
left-panel edit still differs from that snapshot and still reloads.

**Repeater paths must clone with JSON, never `structuredClone`.** `applyPath`
receives Vue reactive state, and a reactive Proxy cannot be structured-cloned —
it throws `DataCloneError`. Scalar fields never reach that line, so the mistake
breaks only repeater edits while headings keep working, which makes it look
like the bridge is fine. `tests/js/liveEdit.spec.ts` covers it with `reactive()`
rather than a plain object; the plain-object cases pass either way.

## Hero image, split alignment

`image_url` renders as a full-bleed backdrop in centre and left alignment. In
**split** alignment it renders inside the art box beside the copy — that box
used to be a decorative `aria-hidden` placeholder bound to nothing. Never both,
or the same picture renders twice.
