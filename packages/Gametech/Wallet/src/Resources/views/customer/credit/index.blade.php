@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@section('back')
    <a class="nav-link p-2 text-light mx-auto hand-point" href="{{ route('customer.home.index') }}">
        <i class="fas fa-chevron-left"></i> กลับ</a>
@endsection

@section('content')
    <div class="container">
        <h3 class="text-center text-light">แคชแบ็ก</h3>
        <p class="text-center text-color-fixed">Cashback</p>
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">
                <div class="card text-light card-trans">
                    <div class="card-body py-3 px-2">

                        <credit></credit>

                    </div>
                </div>

                <section class="main-menu">
                    <div class="card card-trans">
                        <div class="card-body py-1">
                            <div class="row">
                                <div class="col-4 main-menu-item px-0">
                                    <a href="{{ route('customer.credit.transfer.game.index') }}"><i class="fas fa-exchange fa-2x"></i><br>
                                        <span class="text-main"> โยกเงิน</span>
                                    </a>
                                </div>
                                <div class="col-4 main-menu-item px-0">
                                    <a href="{{ route('customer.credit.withdraw.index') }}"><i class="fas fa-hand-holding-usd fa-2x"></i><br>
                                        <span class="text-main"> ถอนเงิน</span>
                                    </a>
                                </div>
                                <div class="col-4 main-menu-item px-0">
                                    <a href="{{ route('customer.credit.history.index') }}"><i class="fal fa-history fa-2x"></i><br>
                                        <span class=" text-main"> ประวัติ</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="content mt-3">

                    @foreach($games as $i => $game)

                        <div class="card card-trans">
                            <div class="card-body">
                                <h5 class="content-heading">{{ ucfirst($i) }}</h5>
                                <div class="row">

                                    @foreach($games[$i] as $k => $item)
                                        <gamefree-list :product="{{ json_encode($item) }}"></gamefree-list>
                                    @endforeach

                                </div>
                            </div>
                        </div>
                    @endforeach

                </section>
            </div>
        </div>
    </div>

@endsection

