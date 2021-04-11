<b-modal ref="addedit" id="addedit" centered size="sm" title="{{ $menu->currentName }}" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true" :lazy="true">
    <b-container class="bv-example-row">
        <b-form @submit.prevent="addEditSubmitNew" v-if="show" id="frmaddedit" ref="frmaddedit">
            <b-form-row>
                <div class="info-box">
                    <span class="info-box-icon"><b-img :src="formaddedit.member_bank_pic"></b-img></span>

                    <div class="info-box-content">
                        <span class="info-box-text" v-text="formaddedit.member_bank"></span>
                        <span class="info-box-number" v-text="formaddedit.member_account"></span>
                    </div>
                    <!-- /.info-box-content -->
                </div>
                <table class="table">
                    <tbody>
                    <tr>
                        <td width="50%">User :</td>
                        <td width="50%" align="right" v-text="formaddedit.member_username"></td>
                    </tr>
                    <tr>
                        <td>ชื่อลูกค้า :</td>
                        <td align="right" v-text="formaddedit.member_name"></td>
                    </tr>
                    <tr>
                        <td>จำนวนเงิน :</td>
                        <td align="right" v-text="formaddedit.amount"></td>
                    </tr>
                    </tbody>
                </table>
            </b-form-row>
            <b-form-row>
                <b-col cols="12">
                    <b-form-group
                        id="input-group-1"
                        label="ค่าธรรมเนียม:"
                        label-for="fee"
                        description="">
                        <b-form-input
                            id="fee"
                            v-model="formaddedit.fee"
                            type="number"
                            size="sm"
                            placeholder=""
                            autocomplete="off"
                        ></b-form-input>
                    </b-form-group>
                </b-col>
                <b-col cols="12">
                    <b-form-group
                        id="input-group-2"
                        label="วันที่โอน:"
                        label-for="date_bank"
                        description="">
                        <b-form-datepicker
                            id="date_bank"
                            v-model="formaddedit.date_bank"
                            size="sm"
                            placeholder=""
                            autocomplete="off"
                            locale="en-US"
                            @context="onContext"
                        ></b-form-datepicker>
                    </b-form-group>
                </b-col>

            </b-form-row>

            <b-form-row>
                <b-col cols="12">
                    <b-form-group
                        id="input-group-3"
                        label="เวลาที่โอน:"
                        label-for="time_bank"
                        description="">
                        <b-form-timepicker
                            id="time_bank"
                            v-model="formaddedit.time_bank"
                            type="text"
                            size="sm"
                            placeholder=""
                            autocomplete="off"
                            :hour12="false"
                        ></b-form-timepicker>
                    </b-form-group>
                </b-col>
                <b-col cols="12">
                    <b-form-group
                        id="input-group-3"
                        label="หมายเหตุ:"
                        label-for="remark_admin"
                        description="">
                        <b-form-input
                            id="remark_admin"
                            v-model="formaddedit.remark_admin"
                            type="text"
                            size="sm"
                            placeholder=""
                            autocomplete="off"
                            required
                        ></b-form-input>
                    </b-form-group>
                </b-col>
            </b-form-row>


            <b-button type="submit" variant="primary">บันทึก</b-button>

        </b-form>
    </b-container>
</b-modal>

<b-modal ref="clear" id="clear" centered size="md" title="โปรดระบุหมายเหตุ ในการทำรายการ" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true" :lazy="true">
    <b-form @submit.stop.prevent="clearSubmit" v-if="show" id="frmclear" ref="frmclear">
        <b-form-group
            id="input-group-remark"
            label="หมายเหตุ:"
            label-for="remark"
            description="">
            <b-form-input
                id="remark"
                v-model="formclear.remark"
                type="text"
                size="sm"
                placeholder=""
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>
    </b-form>
</b-modal>


@push('scripts')
    <script type="text/javascript">
        function clearModal(id) {
            window.app.clearModal(id);
        }

        (() => {
            window.app = new Vue({
                el: '#app',
                data() {
                    return {
                        show: false,
                        trigger: 0,
                        formmethod: 'edit',
                        formaddedit: {
                            fee: 0,
                            date_bank: '',
                            time_bank: '',
                            remark_admin: '',
                            member_username: '',
                            member_code: '',
                            member_name: '',
                            member_account: '',
                            member_bank: '',
                            member_bank_pic: '',
                            amount: 0,
                        },
                        formclear: {
                            remark: ''

                        },
                        option: {
                            banks: [],
                        },
                        selected: '',
                        option: {
                            banks: [],
                        },
                    };
                },
                created() {
                    this.audio = document.getElementById('alertsound');
                    this.autoCnt(true);
                },
                methods: {
                    onContext(ctx) {
                        // The date formatted in the locale, or the `label-no-date-selected` string
                        this.formatted = ctx.selectedFormatted
                        // The following will be an empty string until a valid date is entered
                        this.selected = ctx.selectedYMD
                    },
                    clearModal(code) {
                        this.code = null;
                        this.formclear = {
                            remark: '',

                        }
                        this.formmethod = 'clear';

                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.code = code;
                            this.$refs.clear.show();

                        })
                    },
                    editModal(code) {
                        this.code = null;
                        this.formaddedit = {
                            fee: 0,
                            date_bank: '',
                            time_bank: '',
                            remark_admin: '',
                            member_username: '',
                            member_code: '',
                            member_name: '',
                            member_account: '',
                            member_bank: '',
                            member_bank_pic: '',
                            amount: 0,
                        }
                        this.formmethod = 'edit';

                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.code = code;
                            this.loadData();
                            this.$refs.addedit.show();

                        })
                    },
                    addModal() {
                        this.code = null;
                        this.formaddedit = {
                            acc_name: '',
                            acc_no: '',
                            banks: '',
                            user_name: '',
                            user_pass: '',
                            sort: 1,
                        }
                        this.formmethod = 'add';

                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.$refs.addedit.show();

                        })
                    },
                    async loadData() {
                        const response = await axios.post("{{ url($menu->currentRoute.'/loaddata') }}", {id: this.code});
                        this.formaddedit = {
                            member_username: response.data.data.member.user_name,
                            member_code: response.data.data.member.code,
                            member_name: response.data.data.member.name,
                            member_account: response.data.data.member.acc_no,
                            member_bank: response.data.data.bank.name_th,
                            member_bank_pic: '/storage/bank_img/' + response.data.data.bank.filepic,
                            amount: response.data.data.amount,
                            fee: 0

                        };

                    },
                    async loadBank() {
                        const response = await axios.post("{{ url($menu->currentRoute.'/loadbank') }}");
                        this.option = {
                            banks: response.data.banks
                        };
                    },
                    addEditSubmitNew(event) {
                        event.preventDefault();

                        if (this.formmethod === 'add') {
                            var url = "{{ url($menu->currentRoute.'/create') }}";
                        } else if (this.formmethod === 'edit') {
                            var url = "{{ url($menu->currentRoute.'/update') }}/" + this.code;
                        }

                        let formData = new FormData();
                        const json = JSON.stringify({
                            fee: this.formaddedit.fee,
                            date_bank: this.formaddedit.date_bank,
                            time_bank: this.formaddedit.time_bank,
                            remark_admin: this.formaddedit.remark_admin,
                        });

                        formData.append('data', json);
                        // formData.append('filepic', $('input[name="filepic[image_0]"]')[1].files[0]);

                        const config = {headers: {'Content-Type': `multipart/form-data; boundary=${formData._boundary}`}};

                        axios.post(url, formData, config)
                            .then(response => {
                                if (response.data.success === true) {
                                    this.$bvModal.msgBoxOk(response.data.message, {
                                        title: 'ผลการดำเนินการ',
                                        size: 'sm',
                                        buttonSize: 'sm',
                                        okVariant: 'success',
                                        headerClass: 'p-2 border-bottom-0',
                                        footerClass: 'p-2 border-top-0',
                                        centered: true
                                    });
                                    window.LaravelDataTables["dataTableBuilder"].draw(false);
                                } else {
                                    $.each(response.data.message, function (index, value) {
                                        document.getElementById(index).classList.add("is-invalid");
                                    });
                                    $('input').on('focus', function (event) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        var id = $(this).attr('id');
                                        document.getElementById(id).classList.remove("is-invalid");
                                    });
                                }

                            })
                            .catch(errors => console.log(errors));
                    },
                    clearSubmit(event) {
                        event.preventDefault();

                        this.$http.post("{{ url($menu->currentRoute.'/clear') }}", {
                            id: this.code,
                            remark: this.formclear.remark
                        })
                            .then(response => {
                                this.$bvModal.msgBoxOk(response.data.message, {
                                    title: 'ผลการดำเนินการ',
                                    size: 'sm',
                                    buttonSize: 'sm',
                                    okVariant: 'success',
                                    headerClass: 'p-2 border-bottom-0',
                                    footerClass: 'p-2 border-top-0',
                                    centered: true
                                });
                                window.LaravelDataTables["dataTableBuilder"].draw(false);
                            })
                            .catch(exception => {
                                console.log('error');
                            });
                    }
                },
            });
            $("body").tooltip({
                selector: '[data-toggle="tooltip"]',
                container: 'body'
            });

            // $('body').addClass('sidebar-collapse');

        })()
    </script>
@endpush

