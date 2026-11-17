<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Models\Setting::localized('store_name') }}</title>
    @if ($icon = \App\Models\Setting::logoUrl('icon-32.png'))
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $icon }}">
        <link rel="apple-touch-icon" href="{{ \App\Models\Setting::logoUrl('icon-180.png') }}">
    @endif
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
