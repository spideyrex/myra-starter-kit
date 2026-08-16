# Myra Continuous Improvement Prompt

Master prompt for autonomous multi-agent development of the Myra admin framework.
Invoke with `/improve-myra` (optionally `/improve-myra <focus area>`), or paste this file's
contents into Claude Code.

---

## MISSION

You are improving **Myra**, a Laravel 12 + Inertia + Vue 3 admin framework. The goal is a
platform that is **more capable than Filament v5** — better DX, better UI, better data
tooling — while staying secure, fast, and easy to scale.

Every run must leave the platform **measurably better and never broken**.

Use a workflow. Launch multiple agents in parallel. Do not do this serially.

---

## 1. GROUND TRUTH — read before planning

Do not guess the architecture. These are the load-bearing files:

| Layer | Path |
|---|---|
| Form schema engine | `resources/js/composables/useFormSchema.ts` (~1.5k lines) |
| Table schema engine | `resources/js/composables/useTableSchema.ts` |
| Infolist engine | `resources/js/composables/useInfolistSchema.ts` |
| Field renderer | `resources/js/components/admin/FormField.vue`, `FormFields.vue` |
| Table renderer | `resources/js/components/DataTable.vue`, `admin/SimpleTable.vue` |
| Widgets | `resources/js/components/admin/widgets/` (Stat, Chart, Table) + `DashboardGrid.vue` |
| shadcn-vue primitives | `resources/js/components/ui/` (57 dirs — reuse these, never hand-roll) |
| Feature demos | `resources/js/Pages/Admin/Demo/` (20 pages — the living catalogue) |
| Generators | `app/Console/Commands/Myra/` (`make:myra-page/resource/component/setting/export/import/policy/user`) |
| Scaffolding stubs | `packages/myra/framework/stubs/` |
| RBAC | `config/shield.php` + `shield:generate` |
| Tests | `tests/Feature/`, `tests/Unit/` (26 files), CI in `.github/workflows/tests.yml` |
| Backlog (state) | `docs/admin-framework/ROADMAP.md` |

**Two stub systems — do not conflate.** `stubs/admin/*.stub` (8 files) is TRACKED in this repo and
drives the `make:myra-*` generators — agents can and should edit it. `packages/myra/framework/stubs/`
(384 files) is a different, private repo and drives `myra:install` for brand-new projects; it is
unreachable from a git worktree and must be synced separately.

**Dual-repo trap.** `/packages/` and `/.claude/` are gitignored in `myra-starter-kit`.
`packages/myra/framework` is a **separate private git repo**
(`github.com/spideyrex/myra-framework`). Any change under `packages/` must be committed and
pushed **there**, separately. A fix applied to `resources/js/...` almost always needs porting
to the matching `packages/myra/framework/stubs/resources/js/...` template, or generated
projects ship the old code.

---

## 2. NON-NEGOTIABLES

1. **No regressions.** All 20 demo pages under `/admin/demo` must still render and work.
2. **Reuse before you build.** Check `resources/js/components/ui/` and the composables first.
   A new component that duplicates a shadcn-vue primitive is a defect.
3. **Security is a gate, not a feature.** Every new endpoint: permission-checked via Shield
   (`{module}.{ability}`), ownership-scoped, input-validated, output-sanitized
   (`app/Support/HtmlSanitizer.php`), CSV writes escaped (`app/Support/Csv.php`), sort columns
   whitelisted, pagination capped. Never widen a policy to make a feature work.
4. **Tests ship with the feature.** New PHP → feature/unit test. New UI schema type → a demo
   page exercising it. No test, no merge.
5. **Every field/column/entry type must be theme-aware (light + dark), keyboard accessible,
   responsive, and i18n-ready** (`lang/` has en/ms/zh).
6. **Schema-driven or it doesn't count.** Features are configured through the schema
   composables, not bespoke per-page code.
7. **Never** touch `.env` on the server, run `migrate:fresh`/`db:wipe` on production, delete
   user data, or weaken CSP/HSTS headers.

---

## 3. ORCHESTRATION — how to run a sweep

Full-sweep mode. Author a `Workflow` script; do not hand-run agents one at a time.

**Phase 1 — Recon (parallel, ~4 agents).**
- Agent A: read `ROADMAP.md`, pick the highest-value unchecked cluster; return the item list.
- Agent B: fetch the relevant Filament v5 docs (`https://filamentphp.com/docs/llms.txt` for the
  index, then the specific pages) and extract the exact API surface to beat.
- Agent C: map the current implementation of the target area — every file that will change.
- Agent D: hunt prior art in `resources/js/components/ui/` and npm for anything reusable.

**Phase 2 — Design (parallel judge panel, 3 agents).** Three independent API designs for the
feature cluster. Score on: DX ergonomics, consistency with existing schema DSL, bundle cost,
a11y. Synthesize the winner, grafting the best ideas from the runners-up. The public API must
read like the existing composables — a developer who knows Myra should need no new mental model.

**Phase 3 — Implement (pipeline, one agent per item, `isolation: 'worktree'`).**
Each agent owns one item end to end: engine change → renderer → demo page → generator/stub
update → test → `docs/admin-framework/*` entry. Worktree isolation is mandatory here — parallel
agents editing the same tree corrupt each other.

**Phase 4 — Adversarial verify (parallel, 3 lenses per item).** Each finding gets three
independent skeptics prompted to **refute**: (a) correctness/edge cases, (b) security, (c) does
it actually reproduce in the running app. Majority-refuted → the item goes back to Phase 3, it
does not ship.

**Phase 5 — Integrate & gate.** Merge worktrees, resolve conflicts, then run the gate below.

**Phase 6 — Ship.** Only if every gate is green.

Scale the fleet to the cluster size. Log anything you drop — silent truncation reads as
"covered everything" when it didn't.

---

## 3b. RECURRING MISTAKES — check these before you commit

These have each cost a sweep more than once. They are cheap to avoid and expensive to find.

1. **Never name a test helper after an inherited framework method.** `post()`, `get()`, `put()`,
   `delete()`, `run()`, `instance()`, `json()`, `call()` are all inherited from
   `Illuminate\Foundation\Testing\TestCase` / `MakesHttpRequests` / `PHPUnit\Framework\TestCase`.
   Declaring `private function post(...)` narrows an inherited public method's visibility, which is
   a **fatal error at class load** — it kills the entire suite, not just that file. Name helpers
   `postState()`, `runReport()`, `makeInstance()`. This has fataled three separate sweeps.
2. **`$fillable` must include every column written through `create()`/`updateOrCreate()`.**
   A column in the array passed to `updateOrCreate()` but missing from `$fillable` is silently
   dropped — commonly the ownership column, which then leaves rows unowned.
3. **A regression guard that asserts on your own source file will fail the moment you edit that
   file.** If you assert `not.toContain('X')` over a component you also modified, re-check it.
4. **i18n messages are compiled.** A literal `{` in a message is parsed as a named placeholder;
   `"Reply {unchanged:true}"` is not a compilable vue-i18n message.
5. **Fan-out must not walk a whole table synchronously in a request.** Queue it.
6. **The Bash tool silently eats backslashes**, including inside `<<'QUOTED'` heredocs and
   single-quoted `sed`/`perl` scripts. `App\Support\Myra` arrives as `AppSupportMyra` and
   `str_replace('\\', '/')` becomes an unterminated string. For anything containing a
   backslash, write the script to a file with the Write tool and execute the file — do not
   pipe it through the shell. Same for `--filter="A|B"`: the `|` is eaten by the `.bat`
   wrapper, so run one filter per invocation.
7. **Run Pint only on files you touched.** The codebase is not Pint-clean, so a bare
   `vendor/bin/pint` reformats ~120 unrelated files and buries the real diff. Revert
   everything outside your own file list before committing.
8. **A grep for the declaration site is not a complete survey.** Searching `prefix('admin')`
   found 8 of 9 route groups; `admin.stop-impersonate` was declared with a literal URI
   outside any group and was caught only by a test that asserted the invariant over the
   real route table. Assert the property, don't enumerate the call sites.

---

## 4. THE GATE — all must pass, in order

```bash
npx vue-tsc --noEmit                 # zero type errors
npm run build                        # must succeed
php artisan test                     # full suite green
php artisan myra:about               # sanity: version, modules, permissions
```

Then a live smoke check of every demo route plus any new page. Any red → fix or revert the
offending item. Never ship a partial sweep by lowering the bar.

---

## 5. SHIPPING (autonomous)

Authorised to merge, push and deploy without asking, **only behind a green gate**.

```bash
# 1. Record the rollback point FIRST
PREV=$(git rev-parse HEAD)

# 2. Land it
git checkout main && git merge --no-ff <branch>
git push origin main
# packages/ changes go to the framework repo separately:
cd packages/myra/framework && git push origin main && cd -

# 3. Deploy — VPS 72.62.243.131, app at /home/ntfier-listen/htdocs/listen.ntfier.com
#    Access: PuTTY plink/pscp as root, hostkey SHA256:qQJBQksPa2q5eatXCqC3gxF3EEdTmWzzTKeKZQMhsTU
#    Run every app command as the site user:
#      sudo -u ntfier-listen <cmd>       and       git -c safe.directory=<app root>
sudo -u ntfier-listen git pull --ff-only origin main
sudo -u ntfier-listen npm ci && sudo -u ntfier-listen npm run build
sudo -u ntfier-listen composer install --no-dev -o
sudo -u ntfier-listen php artisan migrate --force
sudo -u ntfier-listen php artisan optimize

# 4. Verify: / and /login return 200, and the new code is in the SERVED bundle.
#    Vite code-splits — grep public/build/assets/*.js, not just app-*.js.

# 5. ROLLBACK on any failure — non-negotiable, no confirmation needed:
sudo -u ntfier-listen git reset --hard $PREV
sudo -u ntfier-listen npm run build && sudo -u ntfier-listen php artisan optimize
# then report the failure and what you rolled back
```

Migrations on the live `notfyer` DB must be **additive only** (new tables/nullable columns).
A destructive migration is the one thing that stops the loop and asks.

---

## 6. AFTER SHIPPING — leave the trail

1. Tick the completed items in `docs/admin-framework/ROADMAP.md`; add anything new you
   discovered to the backlog.
2. Add a CHANGELOG entry under the next version.
3. Update `docs/admin-framework/` for any new API.
4. Bump `config/myra.php` version on a feature release.
5. Report: what shipped, what the gate said, what you deliberately left, what's next.

---

## 7. QUALITY BAR — what "better than Filament" means

Parity is the floor, not the goal. For each area ask: *what does Filament make hard that we can
make easy?* Ship the answer. Concretely:

- **Discoverability** — a developer should find the right component in seconds. The demo
  catalogue is a product surface, not a test page.
- **Zero-config defaults** — the common case needs no configuration; the rare case stays possible.
- **Data at scale** — tables must stay responsive at 100k+ rows: server-side everything,
  virtualised rendering, streaming exports.
- **Reporting depth** — Filament stops at stat + chart widgets. Go further: composable report
  builder, drill-down, scheduled delivery, PDF/Excel export.
- **Real-time by default** — Echo/websockets are already wired; new surfaces should use them.
- **AI-native** — an AI provider layer already exists; use it for schema generation, natural
  language filters, and summarisation where it genuinely helps.

Never ship a worse version of something Filament already does well.
