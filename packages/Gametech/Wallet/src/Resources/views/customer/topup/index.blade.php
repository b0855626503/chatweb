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
    <div class="container text-light mt-5">
        <h3 class="text-center text-light">ฝากเงิน</h3>
        <p class="text-center text-color-fixed">Deposit</p>
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">


                <banks>
                    @foreach($banks as $bank)
                        <bank :item="{{ json_encode($bank) }}" {{ $loop->first ? ':selected="true"' : '' }}></bank>
                    @endforeach
                </banks>

                <div class="card card-trans">
                    <div class="card-header text-center">
                        <p class="text-center text-color-fixed m-0">กรุณาโอนมาจากบัญชีที่ลงทะเบียนเท่านั้น</p>
                    </div>
                    <div class="card-body p-2">
                        <ul class="list-group text-dark bg-light">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                {!! (!is_null($profile->bank) ? core()->showImg($profile->bank->filepic,'bank_img','','','img-bank img-fluid rounded-circle text-center bg-light d-block mx-auto ng-star-inserted') : '') !!}
                                <br>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <p class="text-dark m-0">ชื่อบัญชี :</p>
                                <span class="float-right"> {{ $profile->name }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center"> เลขบัญชี:
                                <span class="float-right"> {{ $profile->acc_no }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center"> ธนาคาร:
                                <span class="float-right"> {{ (!is_null($profile->bank) ? $profile->bank->name_th : '') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('scripts')
        <script type="text/x-template" id="topup-content-top-template">
            <div class="card card-trans">
                <div class="card-header text-center"> บัญชีฝากเงิน</div>
                <div class="card-body">
                    <div class="row section">

                        <div class="col-3" v-for="(bank, index) in banks" @click="selectTab(bank)" :key="index">
                            <div class="section" :class="{ 'img-select': bank.isActive }" :title="bank.filepic">

                                <img class="rounded-circle transfer-slide-img m-2 d-block mx-auto img-bank pointer"
                                     :src="bank.filepic">
                            </div>
                        </div>

                    </div>
                </div>

                <slot></slot>

            </div>
        </script>

        <script type="text/x-template" id="topup-content-down-template">
            <div class="card-body p-2" v-show="isActive">
                <ul class="list-group text-dark bg-light mb-2" v-for="sub in subs">
                    <li class="list-group-item text-center">
                        <strong v-text="sub.acc_name"></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        เลขบัญชี:<span :id="sub.code" class="float-right" v-text="sub.acc_no"> </span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between align-items-center pb-2">
                                <span class="text-center d-block mx-auto">
                                    <button class="btn btn-sm btn-info float-right btn-outline" :data-id="sub.code"  onclick="myFunction(this)"
                                            popover="คัดลอกสำเร็จ">
                                        <i class="fa fa-clone"></i> คัดลอกเลขบัญชี
                                    </button>
                                </span>
                    </li>
                </ul>
            </div>
        </script>

        <script type="text/javascript">
            function myFunction(e) {
                /* Get the text field */
                var val = $(e).attr('data-id');
                console.log(val);
                var copyText = document.getElementById(val).textContent;

                var aux = document.createElement("input");

                // Assign it the value of the specified element
                aux.setAttribute("value", copyText);

                // Append it to the body
                document.body.appendChild(aux);

                // Highlight its content
                aux.select();
                aux.setSelectionRange(0, 99999); /* For mobile devices */

                /* Copy the text inside the text field */
                document.execCommand("copy");
                document.body.removeChild(aux);

                /* Alert the copied text */
                // alert("Copied the text: " + copyText.value);
            }
            (() => {


                Vue.component('banks', {
                    'template': '#topup-content-top-template',
                    data: function () {
                        return {
                            banks: []
                        }
                    },
                    created() {
                        this.banks = this.$children;
                    },

                    methods: {
                        selectTab(selectedTab) {
                            this.banks.forEach(bank => {
                                bank.isActive = (bank.shortcode == selectedTab.shortcode);
                            });
                        }
                    }
                })

                Vue.component('bank', {
                    'template': '#topup-content-down-template',
                    props: {
                        item: {},
                        selected: {
                            default: false
                        }
                    },

                    data() {
                        return {
                            isActive: false,
                            subs: {}

                        };
                    },

                    mounted() {
                        this.isActive = this.selected;
                        this.shortcode = this.item.shortcode;
                        this.filepic = this.item.filepic;
                        this.subs = this.item.banks_account;

                    }
                })


            })()
        </script>
    @endpush

@endsection





