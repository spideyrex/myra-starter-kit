# Self-hosted brand fonts

Production CSP is `font-src 'self' data:`, so a CDN family works in dev and
silently fails in production. Only whitelisted, self-hosted families are ever
referenced (`App\Brand\BrandTypography::SELF_HOSTED`).

Drop the variable woff2 here to activate a family:

| Whitelist key    | Expected file                  |
|------------------|--------------------------------|
| `figtree`        | `figtree-variable.woff2`       |
| `inter`          | `inter-variable.woff2`         |
| `geist`          | `geist-variable.woff2`         |
| `jetbrains-mono` | `jetbrains-mono-variable.woff2`|

A missing file is not an error: `BrandTypography::preloads()` skips it, no
`@font-face` is emitted, and the stack degrades to its system tail.
