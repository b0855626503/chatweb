<b-modal ref="addedit" id="addedit" centered size="md" title="เติมเงิน" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true" :lazy="true">
    <b-container class="bv-example-row">

        <b-form @submit.prevent="addEditSubmitNew" v-if="show" id="frmaddedit" ref="frmaddedit">
            <input type="hidden" id="member_topup" :value="formaddedit.member_topup" required>
            <b-form-row>
                <b-col>
                    <b-form-group
                        id="input-group-banks"
                        label="User Name:"
                        label-for="banks"
                        description="ระบุ User ID ที่ต้องการ เติมเงินรายการนี้">
                        <b-input-group>
                            <b-form-input
                                id="user_name"
                                v-model="formaddedit.user_name"
                                type="text"
                                size="md"
                                placeholder="User ID"
                                autocomplete="off"

                            ></b-form-input>
                            <b-input-group-append>
                                <b-button variant="success" @click="loadUser">ค้นหา</b-button>
                            </b-input-group-append>
                        </b-input-group>
                    </b-form-group>
                </b-col>


            </b-form-row>

            <b-form-row>
                <b-col>
                    <b-form-group
                        id="input-group-name"
                        label="ชื่อลูกค้า:"
                        label-for="name"
                        description="">
                        <b-form-input
                            id="name"
                            v-model="formaddedit.name"
                            type="text"
                            size="sm"
                            placeholder=""
                            autocomplete="off"
                            required
                            plaintext
                        ></b-form-input>
                    </b-form-group>
                </b-col>
            </b-form-row>

            <b-form-row>
                <b-col>
                    <b-form-group
                        id="input-group-remark"
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
                            user_name: '',
                            name: '',
                            member_topup: '',
                            remark_admin: ''

                        },
                        formclear: {
                            remark: ''

                        },
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
                            user_name: '',
                            name: '',
                            member_topup: '',
                            remark_admin: ''
                        }
                        this.formmethod = 'edit';

                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.code = code;
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
                    async loadUser(event) {
                        const response = await axios.post("{{ url($menu->currentRoute.'/loaddata') }}", {id: this.formaddedit.user_name});
                        this.formaddedit = {
                            name: response.data.data.name,
                            member_topup: response.data.data.code
                        }
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
                            member_topup: this.formaddedit.member_topup,
                            remark_admin: this.formaddedit.remark_admin
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

