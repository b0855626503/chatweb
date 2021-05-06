<!-- Navbar -->
<nav
    class="main-header navbar navbar-expand {{ ($config->admin_navbar_color?$config->admin_navbar_color:'navbar-white navbar-light') }}">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="่javascript:void(0)" role="button" id="pushmenu"><i
                    class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-sm-inline-block">
            <a class="nav-link disabled active"><i class="far fa-user-circle"></i> Welcome
                : {{ auth()->guard('admin')->user()->user_name }}</a>
        </li>
    </ul>


    {{--    <!-- Right navbar links -->--}}
    <ul class="navbar-nav ml-auto">
        @if(!$patch)
            <li class="nav-item d-sm-inline-block">
                <a class="nav-link disabled">Version : {!! $version !!} </a>
            </li>
        @endif
        <li class="nav-item d-sm-inline-block">
            <a href="{{ route('admin.session.destroy') }}" class="nav-link"><i class="fas fa-sign-out"></i> Logout</a>
        </li>
    </ul>
</nav>
<!-- /.navbar -->
<nav class="main-header navbar navbar-expand navbar-light announce text-danger" id="announce"></nav>
{{--<newupdate></newupdate>--}}
{{--<newupdate :isLoading="true" :fullPage="true" :useSlot="true" version="2.3" link="aaa"></newupdate>--}}
{{--<loading active="true"></loading>--}}
{{--<loading :active="true" lockscroll="true" :z-index="999999"></loading>--}}

<newupdate></newupdate>

@if($patch)
    {{--<loading :active="true"></loading>--}}

    {{--  <newupdate :fullPage="true" :active="true" version="{{ $version }}" link="{{ route('admin.update.index') }}"></newupdate>--}}
    {{--    <newupdate :show="true" version="{{ $version }}" link="{{ route('admin.update.index') }}"></newupdate>--}}
    {{--    <nav class="main-header navbar navbar-expand navbar-light text-danger">{!! $version !!}</nav>--}}
@endif
