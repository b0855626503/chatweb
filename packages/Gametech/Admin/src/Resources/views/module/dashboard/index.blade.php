@extends('admin::layouts.master')

{{-- page title --}}
@section('title','Dashboard')


@section('content')
    <!--suppress ALL -->
    <section class="content text-xs">
        <div class="row">
            <div class="col-lg-3 col-6">
                <deposit-slot ref="deposit"></deposit-slot>
            </div>
            <div class="col-lg-3 col-6">
                <withdraw-slot ref="withdraw"></withdraw-slot>
            </div>
            <div class="col-lg-3 col-6">
                <bonus-slot ref="bonus"></bonus-slot>
            </div>

            <div class="col-lg-3 col-6">
                <balance-slot ref="balance"></balance-slot>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <deposit_wait-slot ref="deposit_wait"></deposit_wait-slot>
            </div>
            <div class="col">
                <setdeposit-slot ref="setdeposit"></setdeposit-slot>
            </div>
            <div class="col">
                <setwithdraw-slot ref="setwithdraw"></setwithdraw-slot>
            </div>

        </div>

        <div class="row">
            <div class="col-lg-6">
                <income-slot ref="income"></income-slot>
            </div>
            <div class="col-lg-6">
                <topup-slot ref="topup"></topup-slot>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <regis-slot ref="regis"></regis-slot>
            </div>
            <div class="col-lg-6">
                <bankin-slot ref="bankin"></bankin-slot>
            </div>
            <div class="col-lg-6">
                <bankout-slot ref="bankout"></bankout-slot>
            </div>

        </div>
    </section>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('/vendor/chart.js/Chart.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('/vendor/chart.js/Chart.js') }}"></script>

    <script type="text/x-template" id="setdeposit-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-plus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดทีมงานเพิ่ม Wallet</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>
    <script>
        Vue.component('setdeposit-slot', {
            template: '#setdeposit-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsum') }}", {method: 'setdeposit'});
                        this.sum = res.data.sum;
                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="setwithdraw-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-minus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดทีมงานลด Wallet</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>
    <script>
        Vue.component('setwithdraw-slot', {
            template: '#setwithdraw-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsum') }}", {method: 'setwithdraw'});
                        this.sum = res.data.sum;
                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="deposit-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-plus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดฝาก</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>
    <script>
        Vue.component('deposit-slot', {
            template: '#deposit-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsum') }}", {method: 'deposit'});
                        this.sum = res.data.sum;
                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="deposit_wait-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-times-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดฝาก (มีปัญหา)</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>
    <script>
        Vue.component('deposit_wait-slot', {
            template: '#deposit_wait-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsum') }}", {method: 'deposit_wait'});
                        this.sum = res.data.sum;
                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="withdraw-slot-template">
        <div class="info-box">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-minus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดถอน</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>
    <script>
        Vue.component('withdraw-slot', {
            template: '#withdraw-slot-template',

            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsum') }}", {method: 'withdraw'});
                        this.sum = res.data.sum;
                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>



    <script type="text/x-template" id="bonus-slot-template">
        <div class="info-box">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-gift"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดรับโปร</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>
    <!--suppress JSAnnotator -->
    <script>
        Vue.component('bonus-slot', {
            template: '#bonus-slot-template',

            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsum') }}", {method: 'bonus'});
                        this.sum = res.data.sum;
                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="balance-slot-template">
        <div class="info-box">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-dollar-sign"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดคงเหลือ</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>
    <script>
        Vue.component('balance-slot', {
            template: '#balance-slot-template',

            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsum') }}", {method: 'balance'});
                        this.sum = res.data.sum;
                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="income-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">รายได้ 7 วันหลังสุด</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="position-relative mb-4">
                    <canvas id="income-chart" height="200"></canvas>
                </div>
            </div>
        </div>

    </script>
    <script>
        Vue.component('income-slot', {
            template: '#income-slot-template',

            data: function () {
                return {
                    chart: '',
                }
            },
            mounted() {
                this.chart = $('#income-chart');
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsumall') }}", {method: 'income'});
                        let ctx = this.chart;
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: res.data.label,
                                datasets: [{
                                    label: 'ฝาก ',
                                    data: res.data.line_deposit,

                                    borderColor: [
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)'
                                    ],
                                    borderWidth: 1
                                }, {
                                    label: 'ถอน ',
                                    data: res.data.line_withdraw,

                                    borderColor: [
                                        'rgba(255,0,0,1)',
                                        'rgba(255,0,0,1)',
                                        'rgba(255,0,0,1)',
                                        'rgba(255,0,0,1)',
                                        'rgba(255,0,0,1)',
                                        'rgba(255,0,0,1)',
                                        'rgba(255,0,0,1)'
                                    ],
                                    borderWidth: 1
                                }, {
                                    label: 'โปร ',
                                    data: res.data.line_bonus,

                                    borderColor: [
                                        'rgba(255,193,0,1)',
                                        'rgba(255,193,0,1)',
                                        'rgba(255,193,0,1)',
                                        'rgba(255,193,0,1)',
                                        'rgba(255,193,0,1)',
                                        'rgba(255,193,0,1)',
                                        'rgba(255,193,0,1)',
                                    ],
                                    borderWidth: 1
                                }, {
                                    label: 'คงเหลือ ',
                                    data: res.data.line_balance,

                                    borderColor: [
                                        'rgba(0,153,0,1)',
                                        'rgba(0,153,0,1)',
                                        'rgba(0,153,0,1)',
                                        'rgba(0,153,0,1)',
                                        'rgba(0,153,0,1)',
                                        'rgba(0,153,0,1)',
                                        'rgba(0,153,0,1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        }
                                    }]
                                }
                            }
                        });

                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="topup-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">เติมเงิน 7 วันหลังสุด</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="position-relative mb-4">
                    <canvas id="topup-chart" height="200"></canvas>
                </div>
            </div>
        </div>

    </script>
    <script>
        Vue.component('topup-slot', {
            template: '#topup-slot-template',

            data: function () {
                return {
                    chart: '',
                }
            },
            mounted() {
                this.chart = $('#topup-chart');
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsumall') }}", {method: 'topup'});
                        let ctx = this.chart;
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: res.data.label,
                                datasets: [{
                                    label: 'เติมเงิน ',
                                    data: res.data.bar,

                                    backgroundColor: [
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)',
                                        'rgba(0,51,255,1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        }
                                    }]
                                }
                            }
                        });

                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="regis-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">สมาชิกใหม่ ย้อนหลัง 7 วัน</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="position-relative mb-4">
                    <canvas id="regis-chart" height="100"></canvas>
                </div>
            </div>
        </div>

    </script>
    <script>
        Vue.component('regis-slot', {
            template: '#regis-slot-template',

            data: function () {
                return {
                    chart: '',
                }
            },
            mounted() {
                this.chart = $('#regis-chart');
                this.loadData();
            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadsumall') }}", {method: 'register'});
                        let ctx = this.chart;
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: res.data.label,
                                datasets: [{
                                    label: 'สมาชิกใหม่ ',
                                    data: res.data.bar,

                                    backgroundColor: [
                                        'rgba(0,51,0,1)',
                                        'rgba(0,51,0,1)',
                                        'rgba(0,51,0,1)',
                                        'rgba(0,51,0,1)',
                                        'rgba(0,51,0,1)',
                                        'rgba(0,51,0,1)',
                                        'rgba(0,51,0,1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true
                                        }
                                    }]
                                }
                            }
                        });

                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="bankin-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">บัญชีเงินเข้า</h3>
                </div>
            </div>
            <div class="card-body">
                <b-table striped hover small outlined show-empty v-bind:items="loadData" :fields="fields" :busy="isBusy"
                         ref="tbdatalog" v-if="show">
                    <template #table-busy>
                        <div class="text-center text-danger my-2">
                            <b-spinner class="align-middle"></b-spinner>
                            <strong>Loading...</strong>
                        </div>
                    </template>
                    <template #cell(bank)="data">
                        <span v-html="data.value"></span>
                    </template>
                </b-table>
            </div>
        </div>

    </script>
    <script>
        Vue.component('bankin-slot', {
            template: '#bankin-slot-template',

            data: function () {
                return {
                    show: true,
                    isBusy: false,
                    fields: [],
                    items: [],
                }
            },
            mounted() {

            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadbank') }}", {method: 'bankin'});
                        this.fields = [
                            {key: 'bank', label: 'ธนาคาร'},
                            {key: 'acc_no', label: 'เลขที่บัญชี'},
                            {key: 'balance', label: 'ยอดเงิน', class: 'text-right'},
                            {key: 'date_update', label: 'อัพเดทเมื่อ' , class: 'text-center'}
                        ];

                        this.items = res.data.list;
                        return this.items;

                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script type="text/x-template" id="bankout-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">บัญชีเงินออก</h3>
                </div>
            </div>
            <div class="card-body">
                <b-table striped hover small outlined show-empty v-bind:items="loadData" :fields="fields" :busy="isBusy"
                         ref="tbdatalog" v-if="show">
                    <template #table-busy>
                        <div class="text-center text-danger my-2">
                            <b-spinner class="align-middle"></b-spinner>
                            <strong>Loading...</strong>
                        </div>
                    </template>
                    <template #cell(bank)="data">
                        <span v-html="data.value"></span>
                    </template>
                </b-table>
            </div>
        </div>

    </script>
    <script>
        Vue.component('bankout-slot', {
            template: '#bankout-slot-template',

            data: function () {
                return {
                    show: true,
                    isBusy: false,
                    fields: [],
                    items: [],
                }
            },
            mounted() {

            },
            methods: {
                async loadData() {
                    try {
                        const res = await axios.post("{{ url($menu->currentRoute.'/loadbank') }}", {method: 'bankout'});
                        this.fields = [
                            {key: 'bank', label: 'ธนาคาร'},
                            {key: 'acc_no', label: 'เลขที่บัญชี'},
                            {key: 'balance', label: 'ยอดเงิน', class: 'text-right'},
                            {key: 'date_update', label: 'อัพเดทเมื่อ' , class: 'text-center'}
                        ];

                        this.items = res.data.list;
                        return this.items;

                    } catch (e) {
                        return 0;
                    }
                }
            }
        });
    </script>

    <script>

        (() => {

            window.app = new Vue({
                data: function () {
                    return {
                        loopcnts: 0,
                        announce: '',
                        pushmenu: '',
                        toast: '',
                        withdraw_cnt: 0,
                        played:false
                    }
                },
                created() {
                    const self = this;
                    setTimeout(() => {
                        self.autoCnt(false);
                    }, 5000);
                },
                watch: {
                    withdraw_cnt: function (event) {
                        if (event > 0) {
                            this.ToastPlay();
                        }
                    }
                },
                methods: {

                    autoCnt(draw) {
                        const self = this;
                        this.toast = new Toasty({
                            classname: "toast",
                            transition: "fade",
                            insertBefore: true,
                            duration: 1000,
                            enableSounds: true,
                            autoClose: true,
                            progressBar: true,
                            sounds: {
                                info: "sound/alert.mp3",
                                success: "sound/alert.mp3",
                                warning: "vendor/toasty/dist/sounds/warning/1.mp3",
                                error: "storage/sound/alert.mp3",
                            }
                        });
                        this.loadCnt();

                        setInterval(function () {
                            self.loadCnt();
                            self.loopcnts++;
                            // self.$refs.deposit.loadData();
                        }, 50000);

                    },

                    runMarquee() {
                        this.announce = $('#announce');
                        this.announce.marquee({
                            duration: 20000,
                            startVisible: false
                        });
                    },
                    ToastPlay() {

                        this.toast.error('<span class="text-danger">มีการถอนรายการใหม่</span>');
                    },
                    async loadCnt() {
                        const response = await axios.get("{{ url('loadcnt') }}");
                        document.getElementById('badge_bank_in').textContent = response.data.bank_in_today +' / '+ response.data.bank_in;
                        document.getElementById('badge_bank_out').textContent = response.data.bank_out;
                        document.getElementById('badge_withdraw').textContent = response.data.withdraw;
                        document.getElementById('badge_withdraw_free').textContent = response.data.withdraw_free;
                        document.getElementById('badge_confirm_wallet').textContent = response.data.payment_waiting;
                        document.getElementById('badge_member_confirm').textContent = response.data.member_confirm;
                        if (this.loopcnts == 0) {
                            document.getElementById('announce').textContent = response.data.announce;
                            this.runMarquee();
                        } else {
                            if (response.data.announce_new == 'Y') {
                                this.announce.on('finished', (event) => {
                                    document.getElementById('announce').textContent = response.data.announce;
                                    this.announce.trigger('destroy');
                                    this.announce.off('finished');
                                    this.runMarquee();
                                });

                            }
                        }

                        this.withdraw_cnt = response.data.withdraw;

                    }
                }
            });
        })()
    </script>
@endpush



