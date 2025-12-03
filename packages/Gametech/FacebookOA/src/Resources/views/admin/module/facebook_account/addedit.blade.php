<b-modal ref="addedit" id="addedit" centered scrollable size="lg" title="เพิ่มข้อมูล Facebook OA"
         :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true">
    <b-form @submit.prevent="addEditSubmit" v-if="show">

        {{-- Name --}}
        <b-form-group
                id="input-group-name"
                label="ชื่อเรียก OA ในระบบ ใส่ค่านี้เสร็จแล้วกด บันทึกไปก่อน แล้วกดแก้ไขเข้ามา:"
                label-for="name"
                description="เช่น ชื่อเวบที่ใช้ หรืออะไรก็ได้ ที่อยากตั้ง"
        >
            <b-form-input
                    id="name"
                    v-model="formaddedit.name"
                    type="text"
                    size="sm"
                    autocomplete="off"
                    required
            ></b-form-input>
        </b-form-group>

        {{-- How-to steps for Facebook --}}
        <p>
            1. สร้าง Facebook Page ให้เรียบร้อยก่อน
            (ผ่าน <a href="https://business.facebook.com" target="_blank">Business Manager</a> หรือหน้า Facebook ปกติ)
        </p>
        <p>
            2. เข้า <a href="https://developers.facebook.com/apps" target="_blank">Facebook Developers</a>
            เพื่อสร้าง Facebook App แล้วเปิดใช้ผลิตภัณฑ์ Messenger
        </p>
        <p>
            3. ผูก Facebook Page เข้ากับ App แล้วจด <strong>App ID</strong> และ <strong>Page ID</strong> ไว้
        </p>
        <p>
            4. ออก <strong>Page Access Token (long-lived)</strong> จากหน้า Messenger Settings
            และนำค่าไปวางในช่อง Page Access Token ด้านล่าง
        </p>
        <p>
            5. ตั้งค่า Webhook ใน Messenger → Webhooks โดยใช้ค่า Webhook URL และ Verify Token จากหน้านี้
        </p>

        {{-- Page ID --}}
        <b-form-group
                id="input-group-page-id"
                label="Page ID:"
                label-for="page_id"
                description="ระบุ Facebook Page ID เช่น 123456789012345"
        >
            <b-form-input
                    id="page_id"
                    v-model="formaddedit.page_id"
                    type="text"
                    size="sm"
                    autocomplete="off"
                    required
            ></b-form-input>
        </b-form-group>

        {{-- App ID --}}
        <b-form-group
                id="input-group-app-id"
                label="App ID:"
                label-for="app_id"
                description="ระบุ Facebook App ID ที่ใช้กับ Messenger"
        >
            <b-form-input
                    id="app_id"
                    v-model="formaddedit.app_id"
                    type="text"
                    size="sm"
                    autocomplete="off"
                    required
            ></b-form-input>
        </b-form-group>

        {{-- Page Access Token --}}
        <b-form-group
                id="input-group-page-access-token"
                label="Page Access Token:"
                label-for="page_access_token"
                description="วาง Page Access Token (long-lived) จาก Messenger Settings"
        >
            <b-form-textarea
                    id="page_access_token"
                    v-model="formaddedit.page_access_token"
                    rows="3"
                    max-rows="6"
                    size="sm"
                    autocomplete="off"
                    required
            ></b-form-textarea>
        </b-form-group>

        {{-- Webhook Verify Token --}}
        <b-form-group
                id="input-group-webhook-verify-token"
                label="Webhook Verify Token:"
                label-for="webhook_verify_token"
                description="กำหนดรหัสลับสำหรับใช้ Verify Webhook (ต้องใช้ค่าตรงกันใน Facebook Developers)"
        >
            <b-form-input
                    id="webhook_verify_token"
                    v-model="formaddedit.webhook_verify_token"
                    type="text"
                    size="sm"
                    autocomplete="off"
                    required
            ></b-form-input>
            <small class="text-muted">
                แนะนำให้ตั้งเป็นค่าแบบเดายาก เช่น fb_oa_{{ '{' }}slug_เว็บ{{ '}' }}_verify_2025
            </small>
        </b-form-group>

        {{-- Webhook URL (readonly) --}}
        <b-form-group
                id="input-group-webhook-url"
                label="Webhook URL:"
                label-for="webhook_url"
        >
            <template v-if="formmethod === 'edit' && formaddedit.webhook_token">
                <b-input-group size="sm">
                    <b-form-input
                            id="webhook_url"
                            :value="fullWebhookUrl"
                            readonly
                    ></b-form-input>
                    <b-input-group-append>
                        <b-button variant="outline-secondary" @click="copyWebhookUrl">
                            คัดลอก
                        </b-button>
                    </b-input-group-append>
                </b-input-group>
                <small class="text-muted">
                    URL นี้ใช้ตั้งค่าใน Facebook Developers (Messenger &gt; Webhooks &gt; Callback URL)
                </small>
            </template>
            <template v-else>
                <p class="mb-0">
                    <small class="text-muted">
                        Webhook token จะถูกสร้างอัตโนมัติหลังจากบันทึกข้อมูล OA แล้ว
                        จากนั้นสามารถกลับมาเปิดหน้าจอนี้เพื่อคัดลอก URL ได้
                    </small>
                </p>
            </template>
        </b-form-group>

        {{-- Default Languages --}}
        <b-form-group
                id="input-group-default-outgoing-language"
                label="ภาษาที่ใช้ตอบกลับลูกค้า (Outgoing):"
                label-for="default_outgoing_language"
        >
            <b-form-select
                    id="default_outgoing_language"
                    v-model="formaddedit.default_outgoing_language"
                    :options="languageOptions"
                    size="sm"
            ></b-form-select>
        </b-form-group>

        <b-form-group
                id="input-group-default-incoming-language"
                label="ภาษาที่คาดว่าลูกค้าพิมพ์มา (Incoming):"
                label-for="default_incoming_language"
        >
            <b-form-select
                    id="default_incoming_language"
                    v-model="formaddedit.default_incoming_language"
                    :options="languageOptions"
                    size="sm"
            ></b-form-select>
        </b-form-group>

        {{-- Timezone --}}
        <b-form-group
                id="input-group-timezone"
                label="Timezone:"
                label-for="timezone"
                description="ระบุ timezone ที่ใช้แสดงเวลา เช่น Asia/Bangkok"
        >
            <b-form-input
                    id="timezone"
                    v-model="formaddedit.timezone"
                    type="text"
                    size="sm"
                    autocomplete="off"
                    placeholder="Asia/Bangkok"
            ></b-form-input>
        </b-form-group>

        {{-- Status --}}
        <b-form-group
                id="input-group-status"
                label="สถานะ OA:"
                label-for="status"
        >
            <b-form-select
                    id="status"
                    v-model="formaddedit.status"
                    :options="[
                    { value: 'active', text: 'เปิดใช้งาน' },
                    { value: 'inactive', text: 'ปิดใช้งาน' },
                ]"
                    size="sm"
                    required
            ></b-form-select>
        </b-form-group>

        {{-- Remark --}}
        <b-form-group
                id="input-group-remark"
                label="หมายเหตุ:"
                label-for="remark"
                description="ระบุเพิ่มเติม เช่น ใช้กับเว็บอะไร, กลุ่มลูกค้าไหน"
        >
            <b-form-textarea
                    id="remark"
                    v-model="formaddedit.remark"
                    rows="2"
                    max-rows="6"
                    size="sm"
                    autocomplete="off"
            ></b-form-textarea>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>

    </b-form>
</b-modal>

@push('scripts')
    <script type="module">
        window.FB_WEBHOOK_BASE = @json(
            route('api.facebook-oa.webhook', ['token' => '__TOKEN__'])
        );

        window.app = new Vue({
            el: '#app',
            data() {
                return {
                    show: false,
                    formmethod: 'add',
                    code: null,

                    // base URL สำหรับ webhook (ไม่เอา token)
                    webhookBaseUrl: window.FB_WEBHOOK_BASE,

                    languageOptions: [
                        { value: 'th', text: 'Thai (th)' },
                        { value: 'en', text: 'English (en)' },
                    ],

                    formaddedit: {
                        name: '',
                        page_id: '',
                        app_id: '',
                        page_access_token: '',
                        webhook_token: '',
                        webhook_verify_token: '',
                        default_outgoing_language: 'th',
                        default_incoming_language: 'th',
                        timezone: 'Asia/Bangkok',
                        status: 'active',
                        remark: '',
                    },
                };
            },
            created() {
                this.audio = document.getElementById('alertsound');
                this.autoCnt(false);
            },
            computed: {
                fullWebhookUrl() {
                    if (!this.formaddedit.webhook_token) return '';
                    return this.webhookBaseUrl.replace('__TOKEN__', this.formaddedit.webhook_token);
                }
            },
            methods: {
                editModal(code) {
                    this.code = null;
                    this.resetForm();

                    this.formmethod = 'edit';
                    this.show = false;

                    this.$nextTick(() => {
                        this.code = code;
                        this.loadData();
                        this.$refs.addedit.show();
                        this.show = true;
                    });
                },
                addModal() {
                    this.code = null;
                    this.resetForm();

                    this.formmethod = 'add';
                    this.show = false;

                    this.$nextTick(() => {
                        this.$refs.addedit.show();
                        this.show = true;
                    });
                },
                resetForm() {
                    this.formaddedit = {
                        name: '',
                        page_id: '',
                        app_id: '',
                        page_access_token: '',
                        webhook_token: '',
                        webhook_verify_token: '',
                        default_outgoing_language: 'th',
                        default_incoming_language: 'th',
                        timezone: 'Asia/Bangkok',
                        status: 'active',
                        remark: '',
                    };
                },
                async loadData() {
                    const response = await axios.post(
                        "{{ route('admin.'.$menu->currentRoute.'.loaddata') }}",
                        { id: this.code }
                    );

                    const data = response.data.data || {};

                    this.formaddedit.name = data.name || '';
                    this.formaddedit.page_id = data.page_id || '';
                    this.formaddedit.app_id = data.app_id || '';
                    this.formaddedit.page_access_token = data.page_access_token || '';
                    this.formaddedit.webhook_token = data.webhook_token || '';
                    this.formaddedit.webhook_verify_token = data.webhook_verify_token || '';
                    this.formaddedit.default_outgoing_language = data.default_outgoing_language || 'th';
                    this.formaddedit.default_incoming_language = data.default_incoming_language || 'th';
                    this.formaddedit.timezone = data.timezone || 'Asia/Bangkok';
                    this.formaddedit.status = data.status || 'active';
                    this.formaddedit.remark = data.remark || '';
                },
                addEditSubmit(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);

                    let url;
                    if (this.formmethod === 'add') {
                        url = "{{ route('admin.'.$menu->currentRoute.'.create') }}";
                    } else if (this.formmethod === 'edit') {
                        url = "{{ route('admin.'.$menu->currentRoute.'.update') }}";
                    }

                    this.$http.post(url, { id: this.code, data: this.formaddedit })
                        .then(response => {
                            this.$refs.addedit.hide();

                            this.$bvModal.msgBoxOk(response.data.message, {
                                title: 'ผลการดำเนินการ',
                                size: 'sm',
                                buttonSize: 'sm',
                                okVariant: 'success',
                                headerClass: 'p-2 border-bottom-0',
                                footerClass: 'p-2 border-top-0',
                                centered: true
                            });

                            if (window.LaravelDataTables && window.LaravelDataTables["dataTableBuilder"]) {
                                window.LaravelDataTables["dataTableBuilder"].draw(false);
                            }

                            this.toggleButtonDisable(false);
                        })
                        .catch(exception => {
                            console.log('error', exception);
                            this.toggleButtonDisable(false);
                        });
                },
                copyWebhookUrl() {
                    const text = this.fullWebhookUrl;
                    if (!text) return;

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(() => {
                            this.$bvToast && this.$bvToast.toast('คัดลอก Webhook URL แล้ว', {
                                title: 'สำเร็จ',
                                variant: 'success',
                                solid: true,
                                autoHideDelay: 2000,
                            });
                        }).catch(() => {
                            this.fallbackCopyText(text);
                        });
                    } else {
                        this.fallbackCopyText(text);
                    }
                },
                fallbackCopyText(text) {
                    const el = document.createElement('textarea');
                    el.value = text;
                    el.setAttribute('readonly', '');
                    el.style.position = 'absolute';
                    el.style.left = '-9999px';
                    document.body.appendChild(el);
                    el.select();
                    try {
                        document.execCommand('copy');
                        this.$bvToast && this.$bvToast.toast('คัดลอก Webhook URL แล้ว', {
                            title: 'สำเร็จ',
                            variant: 'success',
                            solid: true,
                            autoHideDelay: 2000,
                        });
                    } catch (e) {
                        console.error('ไม่สามารถคัดลอกข้อความได้', e);
                    }
                    document.body.removeChild(el);
                },
            },
        });
    </script>
@endpush
