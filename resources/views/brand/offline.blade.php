{{-- Render target for public/offline.html. It must stay a STATIC file for the
     service worker to precache, so `brand:publish` writes it out. --}}
@php($primary = $brand->palette->primaryHex)
@php($onPrimary = $brand->palette->foregroundOn($primary))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('brand.offline.title') }} &middot; {{ $brand->name }}</title>
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 1.5rem; background: #ffffff; color: #09090b;
    font-family: {{ $brand->typography->stack('sans') }};
  }
  main { max-width: 26rem; text-align: center; }
  .mark {
    width: 3rem; height: 3rem; border-radius: .75rem; margin: 0 auto 1rem;
    display: flex; align-items: center; justify-content: center;
    background: {{ $primary }}; color: {{ $onPrimary }}; font-weight: 700; font-size: 1.5rem;
  }
  h1 { font-size: 1.25rem; line-height: 1.75rem; margin: 0 0 .5rem; font-weight: 600; }
  p { margin: 0 0 1.25rem; font-size: .875rem; line-height: 1.5rem; color: #52525b; }
  button {
    font: inherit; font-size: .875rem; font-weight: 500; cursor: pointer;
    padding: .5rem 1rem; border-radius: {{ $brand->radius }}; border: 1px solid #e4e4e7;
    background: {{ $primary }}; color: {{ $onPrimary }};
  }
  button:focus-visible { outline: 2px solid {{ $primary }}; outline-offset: 2px; }
  @media (prefers-color-scheme: dark) {
    body { background: #09090b; color: #fafafa; }
    p { color: #a1a1aa; }
  }
</style>
</head>
<body>
<main role="status">
  <div class="mark" aria-hidden="true">{{ $brand->initial() }}</div>
  <h1>{{ __('brand.offline.title') }}</h1>
  <p>{{ __('brand.offline.body') }}</p>
  <button type="button" onclick="location.reload()">{{ __('brand.offline.retry') }}</button>
</main>
</body>
</html>
