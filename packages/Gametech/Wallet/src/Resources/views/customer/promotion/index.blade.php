@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@section('back')
    <a class="nav-link p-2 text-light mx-auto hand-point" href="{{ route('customer.home.index') }}">
        <i class="fas fa-chevron-left"></i> กลับ</a>
@endsection


@section('content')
    <div class="container">
        <h1 class="text-center text-light">โปรโมชั่น</h1>
        <p class="text-center text-color-fixed">Promotion</p>
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">

                <section class="content mt-3">
                    @foreach($promotions as $i => $item)

                        <div class="card card-trans">
                            <div class="card-body">
                                {!! core()->showImg($item->filepic,'promotion_img','','','w-100') !!}

                                <h5 class="content-heading text-color-fixed p-2 text-center">{{ $item->name_th }}</h5>
                                <div class="text-main">{!! $item->content !!}</div>
                            </div>
                        </div>

                    @endforeach
                    @foreach($pro_contents as $i => $item)

                        <div class="card card-trans">
                            <div class="card-body">
                                {!! core()->showImg($item->filepic,'procontent_img','','','w-100') !!}

                                <h5 class="content-heading text-color-fixed p-2 text-center">{{ $item->name_th }}</h5>
                                <div class="text-main">{!! $item->content !!}</div>
                            </div>
                        </div>

                    @endforeach
                </section>

            </div>
        </div>
    </div>

@endsection





