<b-modal ref="addedit" id="addedit" centered scrollable size="md" title="{{ $menu->currentName }}" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true" :lazy="true">
    <b-container class="bv-example-row">
        <b-form @submit.prevent="addEditSubmitNew" v-if="show" id="frmaddedit" ref="frmaddedit">
            <b-form-row>
                <b-col>
                    <b-form-group
                        id="input-group-firstname"
                        label="ชื่อ:"
                        label-for="firstname"
                        description="ระบุ ชื่อ">
                        <b-form-input
                            id="firstname"
                            v-model="formaddedit.firstname"
                            type="text"
                            size="sm"
                            placeholder="ชื่อ"
                            autocomplete="off"
                            required
                        ></b-form-input>
                    </b-form-group>
                </b-col>
                <b-col>
                    <b-form-group
                        id="input-group-lastname"
                        label="นามสกุล:"
                        label-for="lastname"
                        description="ระบุ นามสกุล">
                        <b-form-input
                            id="lastname"
                            v-model="formaddedit.lastname"
                            type="text"
                            size="sm"
                            placeholder="นามสกุล"
                            autocomplete="off"
                            required
                        ></b-form-input>
                    </b-form-group>
                </b-col>

            </b-form-row>

            <b-form-row>
                <b-col>
                    <b-form-group
                        id="input-group-user_name"
                        label="User ID:"
                        label-for="user_name"
                        description="">
                        <b-form-input
                            id="user_name"
                            v-model="formaddedit.user_name"
                            type="text"
                            size="sm"
                            placeholder=""
                            autocomplete="off"
                            required
                            readonly
                        ></b-form-input>
                    </b-form-group>
                </b-col>
                <b-col>
                    <b-form-group
                        id="input-group-user_pass"
                        label="รหัสผ่าน:"
                        label-for="user_pass"
                        description="ระบุ รหัสผ่าน กรณีต้องการเปลี่ยนเท่านั้น">
                        <b-form-input
                            id="user_pass"
                            v-model="formaddedit.user_pass"
                            type="text"
                            size="sm"
                            placeholder="รหัสผ่าน"
                            autocomplete="off"

                        ></b-form-input>
                    </b-form-group>
                </b-col>

            </b-form-row>

            <b-form-row>
                <b-col>
                    <b-form-group
                        id="input-group-bank_code"
                        label="ธนาคาร:"
                        label-for="bank_code"
                        description="">

                        <b-form-select
                            id="bank_code"
                            v-model="formaddedit.bank_code"
                            :options="option.bank_code"
                            size="sm"
                            required
                        ></b-form-select>
                    </b-form-group>
                </b-col>
                <b-col>
                    <b-form-group
                        id="input-group-acc_no"
                        label="เลขที่บัญชี:"
                        label-for="acc_no"
                        description="">
                        <b-form-input
                            id="acc_no"
                            v-model="formaddedit.acc_no"
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

<b-modal ref="gamelog" id="gamelog" centered size="lg" :title="caption" :no-stacking="true" :no-close-on-backdrop="true"
         :ok-only="true" :lazy="true">
    <b-table striped hover small outlined sticky-header show-empty v-bind:items="myLog" :fields="fields" :busy="isBusy"
             ref="tbdatalog" v-if="show">
        <template #table-busy>
            <div class="text-center text-danger my-2">
                <b-spinner class="align-middle"></b-spinner>
                <strong>Loading...</strong>
            </div>
        </template>
        <template #cell(transfer)="data">
            <span v-html="data.value"></span>
        </template>
        <template #cell(credit_type)="data">
            <span v-html="data.value"></span>
        </template>
        <template #cell(action)="data">
            <span v-html="data.value"></span>
        </template>
    </b-table>
</b-modal>

<b-modal ref="refill" id="refill" centered size="sm" title="ทำรายการฝากเงิน" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true">
    <b-form @submit="refillSubmit" v-if="show">
        <b-form-group
            id="input-group-1"
            label="จำนวนเงิน:"
            label-for="amount"
            description="ระบุจำนวนเงิน ระหว่าง 1 - 10,000">
            <b-form-input
                id="amount"
                v-model="formrefill.amount"
                type="number"
                size="sm"
                placeholder="จำนวนเงิน"
                min="1"
                max="10000"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-form-group id="input-group-2" label="ช่องทางที่ฝาก:" label-for="account_code">
            <b-form-select
                id="account_code"
                v-model="formrefill.account_code"
                :options="banks"
                size="sm"
                required
            ></b-form-select>
        </b-form-group>

        <b-form-group
            id="input-group-3"
            label="หมายเหตุ:"
            label-for="remark_admin"
            description="ระบุสาเหตุที่ทำรายการ">
            <b-form-input
                id="remark_admin"
                v-model="formrefill.remark_admin"
                type="text"
                placeholder="โปรดระบุ"
                size="sm"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>

    </b-form>
</b-modal>

<b-modal ref="money" id="money" centered size="sm" title="เพิ่ม - ลด Wallet" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true">
    <b-form @submit="moneySubmit" v-if="show">

        <b-form-group id="input-group-2" label="ประเภทรายการ:" label-for="type">
            <b-form-select
                id="account_code"
                v-model="formmoney.type"
                :options="typesmoney"
                size="sm"
                required
            ></b-form-select>
        </b-form-group>

        <b-form-group
            id="input-group-1"
            label="จำนวนเงิน:"
            label-for="amount"
            description="ระบุจำนวนเงิน ระหว่าง 1 - 10,000">
            <b-form-input
                id="amount"
                v-model="formmoney.amount"
                type="number"
                size="sm"
                placeholder="จำนวนเงิน"
                min="1"
                max="10000"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-form-group
            id="input-group-3"
            label="หมายเหตุ:"
            label-for="remark"
            description="ระบุสาเหตุที่ทำรายการ">
            <b-form-input
                id="remark"
                v-model="formmoney.remark"
                type="text"
                placeholder="โปรดระบุ"
                size="sm"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>

    </b-form>
</b-modal>

<b-modal ref="point" id="point" centered size="sm" title="เพิ่ม - ลด Point" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true">
    <b-form @submit="pointSubmit" v-if="show">

        <b-form-group id="input-group-2" label="ประเภทรายการ:" label-for="type">
            <b-form-select
                id="account_code"
                v-model="formpoint.type"
                :options="typespoint"
                size="sm"
                required
            ></b-form-select>
        </b-form-group>

        <b-form-group
            id="input-group-1"
            label="จำนวน:"
            label-for="amount"
            description="ระบุจำนวน ระหว่าง 1 - 10,000">
            <b-form-input
                id="amount"
                v-model="formpoint.amount"
                type="number"
                size="sm"
                placeholder="จำนวนเงิน"
                min="1"
                max="10000"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-form-group
            id="input-group-3"
            label="หมายเหตุ:"
            label-for="remark"
            description="ระบุสาเหตุที่ทำรายการ">
            <b-form-input
                id="remark"
                v-model="formpoint.remark"
                type="text"
                placeholder="โปรดระบุ"
                size="sm"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>

    </b-form>
</b-modal>

<b-modal ref="diamond" id="diamond" centered size="sm" title="เพิ่ม - ลด Diamond" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true">
    <b-form @submit="diamondSubmit" v-if="show">

        <b-form-group id="input-group-2" label="ประเภทรายการ:" label-for="type">
            <b-form-select
                id="account_code"
                v-model="formdiamond.type"
                :options="typesdiamond"
                size="sm"
                required
            ></b-form-select>
        </b-form-group>

        <b-form-group
            id="input-group-1"
            label="จำนวน:"
            label-for="amount"
            description="ระบุจำนวนเงิน ระหว่าง 1 - 10,000">
            <b-form-input
                id="amount"
                v-model="formdiamond.amount"
                type="number"
                size="sm"
                placeholder="จำนวนเงิน"
                min="1"
                max="10000"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-form-group
            id="input-group-3"
            label="หมายเหตุ:"
            label-for="remark"
            description="ระบุสาเหตุที่ทำรายการ">
            <b-form-input
                id="remark"
                v-model="formdiamond.remark"
                type="text"
                placeholder="โปรดระบุ"
                size="sm"
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>

    </b-form>
</b-modal>

<b-modal ref="remark" id="remark" centered size="md" title="หมายเหตุ" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true">
    <b-table striped hover small outlined sticky-header show-empty v-bind:items="myRemark" :fields="fieldsRemark" :busy="isBusyRemark"
             ref="tbdataremark" v-if="showremark">
        <template #table-busy>
            <div class="text-center text-danger my-2">
                <b-spinner class="align-middle"></b-spinner>
                <strong>Loading...</strong>
            </div>
        </template>

        <template #cell(action)="data">
            <span v-html="data.value"></span>
        </template>

        <template #thead-top="data">
            <b-tr>
                <b-th colspan="3"></b-th>
                <b-th variant="secondary" class="text-center">
                    <button type="button" class="btn btn-xs btn-primary"
                            @click="addSubModal()"><i class="fa fa-plus"></i> Add
                    </button>
                </b-th>

            </b-tr>
        </template>

    </b-table>
</b-modal>

<b-modal ref="addeditsub" id="addeditsub" centered size="sm" title="เพิ่มรายการ" :no-stacking="false"
         :no-close-on-backdrop="true" :hide-footer="true">
    <b-form @submit.stop.prevent="addEditSubmitNewSub" v-if="showsub">
        <b-form-group
            id="input-group-remark"
            label="หมายเหตุ:"
            label-for="remark"
            description="">
            <b-form-textarea
                id="remark"
                name="remark"
                v-model="formsub.remark"
                placeholder=""
                rows="3"
                max-rows="6"
                required
            ></b-form-textarea>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>

    </b-form>
</b-modal>


@push('scripts')
    <script type="text/javascript">
        function showModalNew(id, method) {
            window.app.showModalNew(id, method);
        }

        function refill(id) {
            window.app.refill(id);
        }

        function money(id) {
            window.app.money(id);
        }

        function point(id) {
            window.app.point(id);
        }

        function diamond(id) {
            window.app.diamond(id);
        }

        function commentModal(id) {
            window.app.commentModal(id);
        }

        function delSub(id, table) {
            window.app.delSub(id, table);
        }

        function editdatasub(id, status, method) {
            window.app.editdatasub(id, status, method);
        }

        (() => {
            window.app = new Vue({
                el: '#app',
                data() {
                    return {
                        show: false,
                        showsub: false,
                        showremark: false,
                        fieldsRemark: [],
                        fields: [],
                        items: [],
                        caption: null,
                        isBusy: false,
                        isBusyRemark: false,
                        formmethodsub: 'edit',
                        formsub: {
                            remark: ''
                        },
                        formmethod: 'edit',
                        formaddedit: {
                            firstname: '',
                            lastname: '',
                            bank_code: '',
                            user_name: '',
                            user_pass: '',
                            acc_no: '',
                        },
                        option: {
                            bank_code: ''
                        },
                        formrefill: {
                            id: null,
                            amount: 0,
                            account_code: '',
                            remark_admin: ''
                        },
                        formmoney: {
                            id: null,
                            amount: 0,
                            type: 'D',
                            remark: ''
                        },
                        formpoint: {
                            id: null,
                            amount: 0,
                            type: 'D',
                            remark: ''
                        },
                        formdiamond: {
                            id: null,
                            amount: 0,
                            type: 'D',
                            remark: ''
                        },
                        banks: [{value: '', text: '== ธนาคาร =='}],
                        typesmoney: [{value: 'D', text: 'เพิ่ม Wallet'}, {value: 'W', text: 'ลด Wallet'}],
                        typespoint: [{value: 'D', text: 'เพิ่ม Point'}, {value: 'W', text: 'ลด Point'}],
                        typesdiamond: [{value: 'D', text: 'เพิ่ม Diamond'}, {value: 'W', text: 'ลด Diamond'}]
                    };
                },
                created() {
                    this.audio = document.getElementById('alertsound');
                    this.autoCnt(false);
                },
                mounted() {
                    this.loadBank();
                    this.loadBankAccount();
                },
                methods: {
                    editdatasub(code, status, method) {

                        this.$bvModal.msgBoxConfirm('ต้องการดำเนินการ ยกเลิก GAME ID นี้หรือไม่ เมื่อยกเลิกแล้ว ลูกค้าสามารถกด สมัครเข้ามาใหม่ได้.', {
                            title: 'โปรดยืนยันการทำรายการ',
                            size: 'sm',
                            buttonSize: 'sm',
                            okVariant: 'danger',
                            okTitle: 'ตกลง',
                            cancelTitle: 'ยกเลิก',
                            footerClass: 'p-2',
                            hideHeaderClose: false,
                            centered: true
                        })
                            .then(value => {
                                if (value) {
                                    this.$http.post("{{ url($menu->currentRoute.'/editsub') }}", {
                                        id: code,
                                        status: status,
                                        method: method
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
                                            this.$refs.gamelog.refresh()
                                            // window.LaravelDataTables["dataTableBuilder"].draw(false);
                                        })
                                        .catch(exception => {
                                            console.log('error');
                                        });
                                }
                            })
                            .catch(err => {
                                // An error occurred
                            })

                    },
                    showModalNew(code, method) {
                        this.code = code;
                        this.method = method;
                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.$refs.gamelog.show();
                        })

                    },
                    commentModal(code) {
                        this.code = code;

                        this.showremark = false;
                        this.$nextTick(() => {
                            this.showremark = true;
                            this.$refs.remark.show();
                        })

                    },
                    refill(code) {
                        this.code = null;
                        this.formrefill = {
                            id: null,
                            amount: 0,
                            account_code: '',
                            remark_admin: ''
                        }
                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.formrefill.id = code;
                            this.$refs.refill.show();

                        })
                    },
                    money(code) {
                        this.formmoney.id = null;
                        this.formmoney.amount = 0;
                        this.formmoney.remark = '';
                        this.formmoney.type = 'D';
                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.formmoney.id = code;
                            this.$refs.money.show();

                        })
                    },
                    point(code) {
                        this.formpoint.id = null;
                        this.formpoint.amount = 0;
                        this.formpoint.remark = '';
                        this.formpoint.type = 'D';
                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.formpoint.id = code;
                            this.$refs.point.show();
                        })

                    },
                    diamond(code) {
                        this.formdiamond.id = null;
                        this.formdiamond.amount = 0;
                        this.formdiamond.remark = '';
                        this.formdiamond.type = 'D';
                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.formdiamond.id = code;
                            this.$refs.diamond.show();
                        })

                    },
                    refillSubmit(event) {
                        event.preventDefault()
                        this.$http.post("{{ url($menu->currentRoute.'/refill') }}", this.formrefill)
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
                                // window.LaravelDataTables["dataTableBuilder"].draw(false);
                            })
                            .catch(exception => {
                                console.log('error');
                            });
                    },
                    moneySubmit(event) {
                        event.preventDefault();

                        this.$http.post("{{ url($menu->currentRoute.'/setwallet') }}", this.formmoney)
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

                    },
                    pointSubmit(event) {
                        event.preventDefault();

                        this.$http.post("{{ url($menu->currentRoute.'/setpoint') }}", this.formpoint)
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

                    },
                    diamondSubmit(event) {
                        event.preventDefault();

                        this.$http.post("{{ url($menu->currentRoute.'/setdiamond') }}", this.formdiamond)
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

                    },
                    editModal(code) {
                        this.code = null;
                        this.formaddedit = {
                            firstname: '',
                            lastname: '',
                            bank_code: '',
                            user_name: '',
                            user_pass: '',
                            acc_no: '',
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
                            firstname: '',
                            lastname: '',
                            bank_code: '',
                            user_name: '',
                            user_pass: '',
                            acc_no: '',
                        }
                        this.formmethod = 'add';

                        this.show = false;
                        this.$nextTick(() => {
                            this.show = true;
                            this.$refs.addedit.show();

                        })
                    },
                    async loadData() {
                        const response = await axios.get("{{ url($menu->currentRoute.'/loaddata') }}", {
                            params: {
                                id: this.code
                            }
                        });
                        this.formaddedit = {
                            firstname: response.data.data.firstname,
                            lastname: response.data.data.lastname,
                            bank_code: response.data.data.bank_code,
                            user_name: response.data.data.user_name,
                            user_pass: '',
                            acc_no: response.data.data.acc_no,
                        }

                    },
                    async loadBank() {
                        const response = await axios.get("{{ url($menu->currentRoute.'/loadbank') }}");
                        this.option.bank_code = response.data.banks;
                    },
                    async loadBankAccount() {
                        const response = await axios.get("{{ url($menu->currentRoute.'/loadbankaccount') }}");
                        this.banks = response.data.banks;
                    },
                    async myLog() {
                        const response = await axios.get("{{ url($menu->currentRoute.'/gamelog') }}", {
                            params: {
                                id: this.code,
                                method: this.method
                            }
                        });



                        this.caption = response.data.name;
                        if (this.method === 'transfer') {
                            this.fields = [
                                {key: 'date_create', label: 'วันที่'},
                                {key: 'id', label: 'บิลเลขที่'},
                                {key: 'transfer', label: 'ประเภท'},
                                {key: 'game_name', label: 'เกม'},
                                {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},

                            ];
                        } else if (this.method === 'gameuser') {
                            this.fields = [
                                {key: 'game', label: 'เกม'},
                                {key: 'user_name', label: 'บัญชีเกม'},
                                {key: 'balance', label: 'ยอดคงเหลือ', class: 'text-right'},
                                {key: 'promotion', label: 'โปรที่รับมา', class: 'text-left'},
                                {key: 'turn', label: 'Turn', class: 'text-center'},
                                {key: 'amount_balance', label: 'ยอดเทินขั้นต่ำ', class: 'text-right'},
                                {key: 'withdraw_limit', label: 'ถอนได้รับไม่เกิน', class: 'text-right'},
                                {key: 'action', label: 'ยกเลิก ID', class: 'text-center'},
                            ];
                        } else if (this.method === 'deposit') {
                            this.fields = [
                                {key: 'date_create', label: 'วันที่'},
                                {key: 'id', label: 'บิลเลขที่'},
                                {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                                {key: 'credit_before', label: 'ก่อนฝาก', class: 'text-right'},
                                {key: 'credit_after', label: 'หลังฝาก', class: 'text-right'},

                            ];
                        } else if (this.method === 'withdraw') {
                            this.fields = [
                                {key: 'date_create', label: 'วันที่'},
                                {key: 'id', label: 'บิลเลขที่'},
                                {key: 'status_display', label: 'สถานะ'},
                                {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                                {key: 'credit_before', label: 'ก่อนถอน', class: 'text-right'},
                                {key: 'credit_after', label: 'หลังถอน', class: 'text-right'}
                            ];
                        } else if (this.method === 'setwallet') {
                            this.fields = [
                                {key: 'date_create', label: 'วันที่'},
                                {key: 'credit_type', label: 'ประเภทรายการ'},
                                {key: 'remark', label: 'หมายเหตุ'},
                                {key: 'credit_amount', label: 'จำนวน Wallet', class: 'text-right'},
                                {key: 'credit_before', label: 'Wallet ก่อนหน้า', class: 'text-right'},
                                {key: 'credit_balance', label: 'รวม Wallet', class: 'text-right'}
                            ];
                        } else if (this.method === 'setpoint') {
                            this.fields = [
                                {key: 'date_create', label: 'วันที่'},
                                {key: 'credit_type', label: 'ประเภทรายการ'},
                                {key: 'remark', label: 'หมายเหตุ'},
                                {key: 'credit_amount', label: 'จำนวน Point', class: 'text-right'},
                                {key: 'credit_before', label: 'Point ก่อนหน้า', class: 'text-right'},
                                {key: 'credit_balance', label: 'รวม Point', class: 'text-right'}
                            ];
                        } else if (this.method === 'setdiamond') {
                            this.fields = [
                                {key: 'date_create', label: 'วันที่'},
                                {key: 'credit_type', label: 'ประเภทรายการ'},
                                {key: 'remark', label: 'หมายเหตุ'},
                                {key: 'credit_amount', label: 'จำนวน Diamond', class: 'text-right'},
                                {key: 'credit_before', label: 'Diamond ก่อนหน้า', class: 'text-right'},
                                {key: 'credit_balance', label: 'รวม Diamond', class: 'text-right'}
                            ];

                        } else {
                            this.fields = [];
                        }

                        this.items = response.data.list;
                        return this.items;

                    },
                    async myRemark() {
                        const response = await axios.get("{{ url($menu->currentRoute.'/remark') }}", {
                            params: {
                                id: this.code
                            }
                        });


                        this.fieldsRemark = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'remark', label: 'หมายเหตุ'},
                            {key: 'emp_code', label: 'ผู้เพิ่มรายการ'},
                            {key: 'action', label: '', class: 'text-center'}
                        ];

                        this.items = response.data.list;
                        return this.items;

                    },
                    addSubModal() {

                        this.formsub = {
                            remark: ''
                        }
                        this.formmethodsub = 'add';

                        this.showsub = false;
                        this.$nextTick(() => {
                            this.showsub = true;
                            this.$refs.addeditsub.show();

                        })
                    },
                    delSub(code, table) {
                        this.$bvModal.msgBoxConfirm('ต้องการดำเนินการ ลบข้อมูลหรือไม่.', {
                            title: 'โปรดยืนยันการทำรายการ',
                            size: 'sm',
                            buttonSize: 'sm',
                            okVariant: 'danger',
                            okTitle: 'ตกลง',
                            cancelTitle: 'ยกเลิก',
                            footerClass: 'p-2',
                            hideHeaderClose: false,
                            centered: true
                        })
                            .then(value => {
                                if (value) {
                                    this.$http.post("{{ url($menu->currentRoute.'/deletesub') }}", {
                                        id: code, method: table
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
                                            this.$refs.tbdata.refresh();

                                        })
                                        .catch(errors => console.log(errors));
                                }
                            })
                            .catch(errors => console.log(errors));
                    },
                    addEditSubmitNew(event) {
                        event.preventDefault();
                        var url = "{{ url($menu->currentRoute.'/update') }}/" + this.code;


                        let formData = new FormData();
                        const json = JSON.stringify({
                            firstname: this.formaddedit.firstname,
                            lastname: this.formaddedit.lastname,
                            bank_code: this.formaddedit.bank_code,
                            user_pass: this.formaddedit.user_pass,
                            acc_no: this.formaddedit.acc_no,
                        });

                        formData.append('data', json);

                        // formData.append('fileupload', this.fileupload);

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
                            .catch(errors => {
                                Toast.fire({
                                    icon: 'error',
                                    title: errors.response.data
                                })
                            });

                    },
                    addEditSubmitNewSub(event) {
                        event.preventDefault();

                        var url = "{{ url($menu->currentRoute.'/createsub') }}";

                        this.$http.post(url, {id: this.code, data: this.formsub})
                            .then(response => {
                                this.$bvModal.hide('addeditsub');
                                this.$bvModal.msgBoxOk(response.data.message, {
                                    title: 'ผลการดำเนินการ',
                                    size: 'sm',
                                    buttonSize: 'sm',
                                    okVariant: 'success',
                                    headerClass: 'p-2 border-bottom-0',
                                    footerClass: 'p-2 border-top-0',
                                    centered: true
                                });

                                this.$refs.tbdataremark.refresh()

                            })
                            .catch(errors => console.log(errors));

                    },
                },
            });

            $('body').addClass('sidebar-collapse');
        })()
    </script>
@endpush

