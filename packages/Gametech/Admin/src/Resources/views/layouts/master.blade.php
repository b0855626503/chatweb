<!DOCTYPE html>
<html lang="{{ config('app.locale') }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <title>{{ ucwords($config->sitename) }} - @yield('title')</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ core()->imgurl('favicon.png','img') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $config->description }}">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.7.2/css/all.css"
          integrity="sha384-6jHF7Z3XI3fF4XZixAuSu0gGKrXwoX/w3uFPxC56OtjChio7wtTGJWRW53Nhx6Ev" crossorigin="anonymous">
    @stack('styles')

    <link rel="stylesheet" href="{{ asset('assets/admin/css/web.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/ui/css/ui.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/toasty/dist/toasty.min.css') }}">

    @yield('css')


</head>

<body class="hold-transition sidebar-mini text-sm">

<div id="app">

    <div class="wrapper">

        @include('admin::layouts.header')

        @include('admin::layouts.sidebar')

        @include('admin::layouts.content')

        @include('admin::layouts.footer')

    </div>


</div>

<script type="text/javascript">
    window.flashMessages = [];
    window.serverErrors = [];

    @foreach (['success', 'warning', 'error', 'info'] as $key)
    @if ($value = session($key))
    window.flashMessages.push({'type': '{{ $key }}', 'message': "{{ $value }}"});
    @endif
        @endforeach

        @if (isset($errors))
        @if (count($errors))
        window.serverErrors = @json($errors->getMessages());
    @endif
    @endif
</script>
<audio hidden preload="auto" muted="false" src="{{ asset('storage/sound/alert.mp3') }}" id="alertsound"></audio>
<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.0/dist/alpine.min.js" defer></script>
<script src="{{ asset('assets/admin/js/manifest.js') }}"></script>
<script src="{{ asset('assets/admin/js/vendor.js') }}"></script>
<script baseUrl="{{ url()->to('/') }}" src="{{ asset('assets/admin/js/app.js') }}"></script>
<script src="{{ asset('assets/ui/js/ui.js') }}"></script>
<script src="{{ asset('vendor/toasty/dist/toasty.min.js') }}"></script>


@yield('script')
@stack('scripts')
</body>
</html>
