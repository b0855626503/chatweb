@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@section('back')
    <a class="nav-link p-2 text-light mx-auto hand-point" href="{{ route('customer.home.index') }}">
        <i class="fas fa-chevron-left"></i> กลับ</a>
@endsection

@push('styles')
    <style>
        .section {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .img-select {
            border: 2px solid #fff;
            border-radius: 10%;
            background-color: #ffffff3a;
        }

        .img-bank {
            width: 40px;
            height: 40px;
        }

        .hidden {
            display: none;
        }

        a {
            color: #007bff;
            text-decoration: none;
            background-color: transparent;
        }
    </style>
@endpush

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">
                <div class="card text-light card-trans">
                    <div class="card-body py-3 px-2">

                        <wallet></wallet>

                    </div>
                </div>

                {{--                <section class="main-menu">--}}
                {{--                    <div class="card card-trans">--}}
                {{--                        <div class="card-body py-1">--}}
                {{--                            <div class="row">--}}
                {{--                                <div class="col-4 main-menu-item px-0">--}}
                {{--                                    <a href="{{ route('customer.topup.index') }}"><i--}}
                {{--                                            class="fal fa-wallet fa-2x"></i><br>--}}
                {{--                                        <span class="text-main"> เติมเงิน</span>--}}
                {{--                                    </a>--}}
                {{--                                </div>--}}

                {{--                            </div>--}}
                {{--                        </div>--}}
                {{--                    </div>--}}
                {{--                </section>--}}

                <section class="content mt-3">

                    <div class="card card-trans">
                        <div class="card-body">

                                <reward-list :rewards="{{ json_encode($rewards) }}"></reward-list>

                        </div>
                    </div>

                </section>
            </div>
        </div>
    </div>
@endsection




