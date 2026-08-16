<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- >>> MYRA v2.6 [C] START --}}
        @php($myraBrand = \App\Brand\Facades\Brand::current())
        <title inertia>{{ $myraBrand->enabled ? $myraBrand->name : config('app.name', 'Laravel') }}</title>

        <meta name="brand" content="{{ json_encode($myraBrand->toArray()) }}">

        {!! \App\Brand\Facades\Brand::iconLinks() !!}

        @foreach(\App\Brand\Facades\Brand::metaTags() as $tag)
            <meta {{ $tag['attr'] }}="{{ $tag['key'] }}" content="{{ $tag['content'] }}">
        @endforeach
        {{-- <<< MYRA v2.6 [C] END --}}

        {{-- >>> MYRA v2.5 [D] START --}}
        @if(config('myra.pwa.enabled') === true)
            <link rel="manifest" href="/manifest.webmanifest">
            {{-- >>> MYRA v2.6 [C] START --}}
            <meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $myraBrand->palette->hex('background') }}">
            <meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $myraBrand->palette->hex('background-dark') }}">
            {{-- <<< MYRA v2.6 [C] END --}}
        @endif
        {{-- <<< MYRA v2.5 [D] END --}}

        {{-- >>> MYRA v2.6 [C] START — self-hosted only: production CSP is font-src 'self' data: --}}
        @foreach($myraBrand->typography->preloads() as $font)
            <link rel="preload" as="font" type="font/woff2" href="{{ $font['href'] }}" crossorigin>
        @endforeach

        {!! \App\Brand\Facades\Brand::bootScriptTag() !!}
        {{-- <<< MYRA v2.6 [C] END --}}

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts'])

        {{-- >>> MYRA v2.6 [C] START — after @vite so the brand tokens win the cascade --}}
        {!! \App\Brand\Facades\Brand::styleTag() !!}
        {{-- <<< MYRA v2.6 [C] END --}}

        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
