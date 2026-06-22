@php
    $brandName = \Option::get('company_name', config('app.name'));
    $tagline   = \Option::get('secrets.tagline') ?: __('Securely share secrets');
    $accent    = \Option::get('secrets.accent_color') ?: '#2563eb';
    // Guard the value that goes into the inline <style>.
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) { $accent = '#2563eb'; }
    $logo = \Option::get('secrets.logo_url') ?: '';
@endphp<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Secure secret')) — {{ $brandName }}</title>
    <link rel="stylesheet" href="{{ \Module::getPublicPath('secrets') }}/css/secrets.css">
    <style>:root{--secrets-accent: {{ $accent }};}</style>
</head>
<body class="secrets-public">
    <main class="secrets-wrap">
        <div class="secrets-brand">
            @if($logo)
                <img class="secrets-logo" src="{{ $logo }}" alt="{{ $brandName }}">
            @else
                <span class="secrets-lock" aria-hidden="true">&#128274;</span>
            @endif
            <span class="secrets-brand-text">
                <span class="secrets-brand-name">{{ $brandName }}</span>
                @if($tagline)<span class="secrets-tagline">{{ $tagline }}</span>@endif
            </span>
        </div>

        @yield('content')

        <footer class="secrets-foot">
            <span>{{ __('End-to-end encrypted in your browser.') }}</span>
        </footer>
    </main>

    <script src="{{ \Module::getPublicPath('secrets') }}/js/crypto.js"></script>
    @yield('page_scripts')
</body>
</html>
