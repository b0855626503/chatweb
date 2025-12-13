{{-- Modal Add/Edit SMS Campaign --}}
<b-modal ref="addedit" id="addedit" centered scrollable size="lg"
         title="เพิ่ม/แก้ไข SMS Campaign"
         :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true">
    {{-- ✅ ตัด v-if="show" ออก เพื่อไม่ destroy/recreate --}}
    <b-form @submit.prevent="addEditSubmit">

        {{-- Campaign Name --}}
        <b-form-group
                id="input-group-name"
                label="ชื่อแคมเปญ:"
                label-for="name"
                description="ตั้งชื่อเพื่อให้ทีมงานจำได้ เช่น โปรโมชันปลายปี / แจ้งระบบ / เตือนฝาก">
            <b-form-input
                    id="name"
                    v-model="formaddedit.name"
                    type="text"
                    size="sm"
                    autocomplete="off"
                    required
            ></b-form-input>
        </b-form-group>

        {{-- Sender --}}
        <b-form-group
                id="input-group-sender"
                label="Sender (from):"
                label-for="sender_name"
                description="ชื่อผู้ส่งตามที่อนุญาตใน Vonage (หรือค่า default ที่ตั้งใน config)">
            <b-form-input
                    id="sender_name"
                    v-model="formaddedit.sender_name"
                    type="text"
                    size="sm"
                    autocomplete="off"
                    placeholder="เช่น GAMETECH"
            ></b-form-input>
        </b-form-group>

        {{-- Message --}}
        <b-form-group
                id="input-group-message"
                label="ข้อความ SMS:"
                label-for="message"
                description="ระวังความยาว (SMS แบ่งเป็นหลายส่วนได้) แนะนำให้สั้น กระชับ และมีจุดประสงค์ชัด">
            <b-form-textarea
                    id="message"
                    v-model="formaddedit.message"
                    rows="4"
                    max-rows="8"
                    size="sm"
                    autocomplete="off"
                    required
            ></b-form-textarea>
            <small class="text-muted">
                ความยาว: @{{ (formaddedit.message || '').length }} ตัวอักษร
            </small>
        </b-form-group>

        <hr>

        {{-- Audience mode --}}
        <b-form-group
                id="input-group-audience"
                label="กลุ่มเป้าหมาย (Audience Mode):"
                label-for="audience_mode"
                description="เลือกว่าจะยิงจากสมาชิก, จากไฟล์, หรือผสม">
            <b-form-select
                    id="audience_mode"
                    v-model="formaddedit.audience_mode"
                    :options="audienceModeOptions"
                    size="sm"
                    required
            ></b-form-select>
        </b-form-group>

        {{-- Opt-out / Consent --}}
        <b-form-group
                id="input-group-rules"
                label="กฎการส่ง:"
                label-for="rules">
            <b-form-checkbox v-model="formaddedit.respect_opt_out" switch size="sm">
                เคารพ Opt-out (STOP/Unsub) — แนะนำให้เปิดตลอด
            </b-form-checkbox>

            <b-form-checkbox v-model="formaddedit.require_consent" switch size="sm" class="mt-1">
                Require Consent (ใช้เมื่อระบบคุณมีฟิลด์ consent จริง)
            </b-form-checkbox>
        </b-form-group>

        <hr>

        {{-- Upload import (เฉพาะ upload_only/mixed) --}}
        <b-form-group
                v-if="showUploadSection"
                id="input-group-upload"
                label="อัปโหลดไฟล์เบอร์ (CSV/XLS/XLSX):"
                label-for="import_file"
                description="อัปโหลดเพื่อสร้าง Import Batch และนำไป Build recipients"
        >
            <b-input-group size="sm" class="mb-2">
                <b-form-file
                        id="import_file"
                        v-model="importFile"
                        accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        browse-text="เลือกไฟล์"
                        placeholder="ยังไม่ได้เลือกไฟล์"
                ></b-form-file>

                <b-input-group-append>
                    <b-button variant="outline-primary" :disabled="!importFile || importUploading"
                              @click="uploadImport">
                        @{{ importUploading ? 'กำลังอัปโหลด...' : 'อัปโหลด/พรีวิว' }}
                    </b-button>
                </b-input-group-append>
            </b-input-group>

            <b-form-row>
                <b-col cols="4">
                    <b-form-group label="Country code" label-for="country_code" class="mb-0">
                        <b-form-input id="country_code" v-model="importOptions.country_code" size="sm"></b-form-input>
                    </b-form-group>
                </b-col>
                <b-col cols="4">
                    <b-form-group label="Has header" class="mb-0">
                        <b-form-select v-model="importOptions.has_header" :options="yesNoOptions"
                                       size="sm"></b-form-select>
                    </b-form-group>
                </b-col>
                <b-col cols="4">
                    <b-form-group label="Phone column (optional)" class="mb-0">
                        <b-form-input v-model="importOptions.phone_column" size="sm"
                                      placeholder="phone/tel/mobile"></b-form-input>
                    </b-form-group>
                </b-col>
            </b-form-row>

            <div v-if="importBatch.id" class="mt-2">
                <div class="small text-muted">
                    Import Batch: <strong>#@{{ importBatch.id }}</strong>
                    (valid: @{{ importBatch.valid_phones }}, invalid: @{{ importBatch.invalid_phones }}, dup: @{{ importBatch.duplicate_phones }})
                </div>
            </div>

            <div v-if="importBatch.preview && importBatch.preview.length" class="mt-2">
                <div class="font-weight-bold mb-1">Preview</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Raw</th>
                            <th>E164</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(p, idx) in importBatch.preview" :key="idx">
                            <td>@{{ idx+1 }}</td>
                            <td>@{{ p.raw }}</td>
                            <td class="text-monospace">@{{ p.e164 }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <small class="text-muted d-block mt-2">
                หมายเหตุ: อัปโหลดไฟล์ได้ตั้งแต่ตอน Add (campaign_id ยังเป็น null ได้) แต่การ Build/Dispatch ต้องทำตอน Edit
            </small>
        </b-form-group>

        {{-- Recipient summary --}}
        <b-card v-if="formmethod === 'edit'" class="mb-3" body-class="p-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="font-weight-bold">สรุปผู้รับของแคมเปญ</div>
                <b-button size="sm" variant="outline-secondary" :disabled="loadingStats" @click="refreshStats">
                    @{{ loadingStats ? 'กำลังโหลด...' : 'รีเฟรช' }}
                </b-button>
            </div>
            <div class="small text-muted mt-1">
                ผู้รับทั้งหมด: <strong>@{{ stats.recipients_total }}</strong>
                | ค้างส่ง (queued): <strong>@{{ stats.queued_total }}</strong>
                | Delivered: <strong>@{{ stats.delivered_total }}</strong>
                | Failed: <strong>@{{ stats.failed_total }}</strong>
            </div>
            <div class="small text-muted" v-if="stats.recipients_total === 0">
                ยังไม่มีรายชื่อผู้รับ — กรุณา Build recipients ก่อน
            </div>
        </b-card>

        {{-- Build recipients actions --}}
        <b-form-group
                id="input-group-build"
                label="สร้างรายชื่อผู้รับ (Build recipients):"
                description="ระบบจะสร้างแถวใน sms_recipients ตามแคมเปญนี้ (แนะนำให้บันทึกก่อน แล้วค่อย Build)">
            <b-button size="sm" variant="outline-success" class="mr-1"
                      v-if="showBuildMembersBtn"
                      @click="buildRecipients('member_all')"
                      :disabled="!canBuildBase || building">
                @{{ building ? 'กำลังทำ...' : 'Build จากสมาชิกทั้งหมด' }}
            </b-button>

            <b-button size="sm" variant="outline-success" class="mr-1"
                      v-if="showBuildUploadBtn"
                      @click="buildRecipients('upload_only')"
                      :disabled="!canBuildUpload || building">
                @{{ building ? 'กำลังทำ...' : 'Build จากไฟล์ที่อัปโหลด' }}
            </b-button>

            <b-button size="sm" variant="outline-success"
                      v-if="showBuildMixedBtn"
                      @click="buildRecipients('mixed')"
                      :disabled="!canBuildMixed || building">
                @{{ building ? 'กำลังทำ...' : 'Build แบบผสม (สมาชิก+ไฟล์)' }}
            </b-button>

            <small class="text-muted d-block mt-2">
                หมายเหตุ: ปุ่ม Build ต้องทำตอน “แก้ไข” เพราะต้องมี campaign id ก่อน
            </small>
        </b-form-group>

        {{-- Dispatch --}}
        <b-form-group
                id="input-group-dispatch"
                label="เริ่มส่ง (Dispatch queued):"
                description="ปล่อยคิวส่งเข้า queue:sms ตามจำนวนที่กำหนดต่อครั้ง">
            <b-input-group size="sm">
                <b-form-input type="number" min="1" max="5000" v-model.number="dispatchLimit"></b-form-input>
                <b-input-group-append>
                    <b-button variant="primary" @click="dispatchQueued"
                              :disabled="!canDispatch || dispatching">
                        @{{ dispatching ? 'กำลังปล่อยคิว...' : 'Dispatch' }}
                    </b-button>
                </b-input-group-append>
            </b-input-group>

            <small class="text-muted d-block mt-2">
                แนะนำ: dispatch @{{ recommendedDispatch }} รายการ (อิงจาก queued ที่ค้างส่ง)
            </small>
        </b-form-group>

        {{-- Remark --}}
        <b-form-group
                id="input-group-remark"
                label="หมายเหตุ:"
                label-for="remark"
                description="บันทึกเพิ่มเติม เช่น ใช้กับเว็บไหน กลุ่มลูกค้าไหน">
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
        <b-button type="button" variant="outline-secondary" class="ml-1" @click="$refs.addedit.hide()">ปิด</b-button>

    </b-form>
</b-modal>


@push('scripts')
    <script type="module">
        window.app = new Vue({
            el: '#app',
            data() {
                return {
                    // ✅ ไม่ใช้ show เพื่อ render form แล้ว (เก็บไว้ได้แต่ไม่จำเป็น)
                    show: true,

                    formmethod: 'add',
                    code: null,

                    building: false,
                    dispatching: false,
                    loadingStats: false,
                    loadingData: false,

                    // guard กัน race + call ซ้ำ
                    loadToken: 0,

                    dispatchLimit: 1000,

                    importFile: null,
                    importUploading: false,
                    importOptions: {
                        country_code: '66',
                        has_header: 1,
                        phone_column: '',
                    },
                    importBatch: {
                        id: null,
                        valid_phones: 0,
                        invalid_phones: 0,
                        duplicate_phones: 0,
                        preview: [],
                    },

                    stats: {
                        recipients_total: 0,
                        queued_total: 0,
                        delivered_total: 0,
                        failed_total: 0,
                    },

                    yesNoOptions: [
                        {value: 1, text: 'Yes'},
                        {value: 0, text: 'No'},
                    ],
                    audienceModeOptions: [
                        {value: 'member_all', text: 'สมาชิกทั้งหมด (member_all)'},
                        {value: 'member_filter', text: 'กรองสมาชิก (member_filter) - ยังไม่เปิดในตัวอย่างนี้'},
                        {value: 'upload_only', text: 'จากไฟล์ (upload_only)'},
                        {value: 'mixed', text: 'ผสม (mixed)'},
                    ],

                    formaddedit: {
                        name: '',
                        sender_name: '',
                        message: '',
                        audience_mode: 'member_all',
                        respect_opt_out: true,
                        require_consent: false,
                        remark: '',
                    },
                };
            },

            computed: {
                showUploadSection() {
                    return this.formaddedit.audience_mode === 'upload_only' || this.formaddedit.audience_mode === 'mixed';
                },

                canBuildBase() {
                    return this.formmethod === 'edit' && !!this.code && !this.loadingData;
                },

                canBuildUpload() {
                    return this.canBuildBase && !!this.importBatch.id;
                },

                canBuildMixed() {
                    return this.canBuildBase && !!this.importBatch.id;
                },

                // ✅ ตรงนี้ “นิ่ง” แล้ว: จะโชว์อะไรตาม audience_mode จริง ๆ
                showBuildMembersBtn() {
                    return this.formaddedit.audience_mode === 'member_all'
                        || this.formaddedit.audience_mode === 'member_filter'
                        || this.formaddedit.audience_mode === 'mixed';
                },

                showBuildUploadBtn() {
                    return this.formaddedit.audience_mode === 'upload_only'
                        || this.formaddedit.audience_mode === 'mixed';
                },

                showBuildMixedBtn() {
                    return this.formaddedit.audience_mode === 'mixed';
                },

                canDispatch() {
                    if (this.formmethod !== 'edit' || !this.code || this.loadingData) return false;

                    if ((this.stats.queued_total || 0) > 0) return true;

                    return (this.stats.recipients_total || 0) > 0;
                },

                recommendedDispatch() {
                    const q = parseInt(this.stats.queued_total || 0, 10);
                    if (q <= 0) return 0;
                    return Math.min(1000, q);
                },
            },

            watch: {
                'formaddedit.audience_mode'(val) {
                    // ถ้าเปลี่ยนไปไม่ใช้ไฟล์ → ล้างไฟล์/preview กันเข้าใจผิด
                    if (val !== 'upload_only' && val !== 'mixed') {
                        this.importFile = null;
                        this.importBatch = {id: null, valid_phones: 0, invalid_phones: 0, duplicate_phones: 0, preview: []};
                    }
                },

                'stats.queued_total'(val) {
                    const q = parseInt(val || 0, 10);
                    if (q > 0) {
                        // ปรับให้สวย: ไม่ดันทับถ้าผู้ใช้ตั้งเองเกินคิว
                        this.dispatchLimit = Math.min(this.dispatchLimit || 1000, q, 5000);
                        if (!this.dispatchLimit || this.dispatchLimit < 1) this.dispatchLimit = Math.min(1000, q);
                    }
                }
            },

            methods: {
                // ✅ เปิด modal แบบนิ่ง: ไม่ toggle show ไม่ nextTick
                async editModal(code) {
                    this.resetForm();
                    this.formmethod = 'edit';
                    this.code = code;

                    this.$refs.addedit.show();

                    // กันกดรัว ๆ / กัน load ซ้ำ
                    await this.loadDataOnce();
                    await this.refreshStatsOnce();
                },

                addModal() {
                    this.resetForm();
                    this.formmethod = 'add';
                    this.code = null;

                    this.$refs.addedit.show();
                },

                resetForm() {
                    this.formaddedit = {
                        name: '',
                        sender_name: '',
                        message: '',
                        audience_mode: 'member_all',
                        respect_opt_out: true,
                        require_consent: false,
                        remark: '',
                    };

                    this.importFile = null;
                    this.importUploading = false;
                    this.importBatch = {id: null, valid_phones: 0, invalid_phones: 0, duplicate_phones: 0, preview: []};

                    this.stats = {recipients_total: 0, queued_total: 0, delivered_total: 0, failed_total: 0};
                    this.loadingStats = false;
                    this.loadingData = false;

                    this.dispatchLimit = 1000;
                    this.building = false;
                    this.dispatching = false;
                },

                async loadDataOnce() {
                    if (this.loadingData) return;
                    this.loadingData = true;

                    const token = ++this.loadToken;

                    try {
                        const response = await axios.post(
                            "{{ route('admin.'.$menu->currentRoute.'.loaddata') }}",
                            { id: this.code }
                        );

                        // ถ้ามีการเรียกใหม่แทรก → ทิ้งผลลัพธ์เก่า
                        if (token !== this.loadToken) return;

                        const data = response.data.data || {};

                        this.formaddedit.name = data.name || '';
                        this.formaddedit.sender_name = data.sender_name || '';
                        this.formaddedit.message = data.message || '';
                        this.formaddedit.audience_mode = data.audience_mode || 'member_all';
                        this.formaddedit.respect_opt_out = (data.respect_opt_out ?? 1) ? true : false;
                        this.formaddedit.require_consent = (data.require_consent ?? 0) ? true : false;
                        this.formaddedit.remark = data.remark || '';

                        // best-effort stats จาก loaddata (ถ้า backend ส่งมา)
                        if (typeof data.recipients_total !== 'undefined') this.stats.recipients_total = parseInt(data.recipients_total || 0, 10);
                        if (typeof data.queued_total !== 'undefined') this.stats.queued_total = parseInt(data.queued_total || 0, 10);
                        if (typeof data.delivered_total !== 'undefined') this.stats.delivered_total = parseInt(data.delivered_total || 0, 10);
                        if (typeof data.failed_total !== 'undefined') this.stats.failed_total = parseInt(data.failed_total || 0, 10);

                        // ✅ สำคัญ: ทำให้ import batch “ไม่ว่าง” เมื่อเคย upload มาก่อน
                        // backend คุณควรส่ง last_import_batch_id มาด้วย (ที่เราปรับ controller ไปแล้ว)
                        if (data.last_import_batch_id && !this.importBatch.id) {
                            this.importBatch.id = parseInt(data.last_import_batch_id, 10);
                        }
                    } catch (e) {
                        console.log('loadData error', e);
                    } finally {
                        // ยังต้องเช็ค token กัน case เรียกซ้อน
                        if (token === this.loadToken) {
                            this.loadingData = false;
                        }
                    }
                },

                async refreshStatsOnce() {
                    // ใช้ route stats ถ้ามี, ถ้าไม่มีก็ fallback เป็น loadDataOnce()
                    if (this.formmethod !== 'edit' || !this.code) return;
                    if (this.loadingStats) return;

                    this.loadingStats = true;

                    try {
                        // ถ้าคุณทำ route admin.sms_campaign.stats แล้ว ให้เปิดบล็อกนี้
                         const res = await axios.post("{{ route('admin.'.$menu->currentRoute.'.stats') }}", { id: this.code });
                         const s = res.data.data || {};
                         this.stats = {
                             recipients_total: parseInt(s.recipients_total || 0, 10),
                             queued_total: parseInt(s.queued_total || 0, 10),
                             delivered_total: parseInt(s.delivered_total || 0, 10),
                             failed_total: parseInt(s.failed_total || 0, 10),
                         };

                        //await this.loadDataOnce();
                    } catch (e) {
                        console.log('refreshStats error', e);
                    } finally {
                        this.loadingStats = false;
                    }
                },

                async refreshStats() {
                    await this.refreshStatsOnce();
                },

                addEditSubmit(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);

                    let url;
                    if (this.formmethod === 'add') {
                        url = "{{ route('admin.'.$menu->currentRoute.'.create') }}";
                    } else {
                        url = "{{ route('admin.'.$menu->currentRoute.'.update') }}";
                    }

                    this.$http.post(url, { id: this.code, data: this.formaddedit , import_batch_id: this.importBatch.id || null,})
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

                async uploadImport() {
                    if (!this.importFile) return;

                    this.importUploading = true;

                    try {
                        const fd = new FormData();
                        fd.append('file', this.importFile);

                        if (this.formmethod === 'edit' && this.code) {
                            fd.append('campaign_id', this.code);
                        }

                        fd.append('country_code', this.importOptions.country_code || '66');
                        fd.append('has_header', this.importOptions.has_header ? 1 : 0);
                        if (this.importOptions.phone_column) fd.append('phone_column', this.importOptions.phone_column);

                        const res = await axios.post("{{ route('admin.sms_import.parse') }}", fd, {
                            headers: {'Content-Type': 'multipart/form-data'}
                        });

                        const payload = res.data.data || res.data || {};
                        const batch = payload.batch || payload;

                        this.importBatch.id = batch.id || payload.batch_id || null;

                        if (payload.counters) {
                            this.importBatch.valid_phones = payload.counters.valid_phones || 0;
                            this.importBatch.invalid_phones = payload.counters.invalid_phones || 0;
                            this.importBatch.duplicate_phones = payload.counters.duplicate_phones || 0;
                        } else {
                            this.importBatch.valid_phones = batch.valid_phones || 0;
                            this.importBatch.invalid_phones = batch.invalid_phones || 0;
                            this.importBatch.duplicate_phones = batch.duplicate_phones || 0;
                        }

                        this.importBatch.preview = payload.preview || batch.preview || (batch.meta && batch.meta.preview) || [];

                        this.$bvToast && this.$bvToast.toast('อัปโหลดสำเร็จ', {
                            title: 'สำเร็จ',
                            variant: 'success',
                            solid: true,
                            autoHideDelay: 2000,
                        });

                    } catch (e) {
                        console.log('uploadImport error', e);

                        this.$bvModal.msgBoxOk('อัปโหลดไม่สำเร็จ โปรดตรวจสอบชนิดไฟล์/ขนาดไฟล์', {
                            title: 'ผิดพลาด',
                            size: 'sm',
                            buttonSize: 'sm',
                            okVariant: 'danger',
                            centered: true
                        });
                    } finally {
                        this.importUploading = false;
                    }
                },

                async buildRecipients(mode) {
                    if (this.formmethod !== 'edit' || !this.code) return;

                    if ((mode === 'upload_only' || mode === 'mixed') && !this.importBatch.id) {
                        this.$bvModal.msgBoxOk('กรุณาอัปโหลดไฟล์ให้ได้ Import Batch ก่อน', {
                            title: 'แจ้งเตือน',
                            size: 'sm',
                            okVariant: 'warning',
                            centered: true
                        });
                        return;
                    }

                    this.building = true;

                    try {
                        const res = await axios.post("{{ route('admin.'.$menu->currentRoute.'.build_recipients') }}", {
                            id: this.code,
                            mode: mode,
                            import_batch_id: this.importBatch.id || null
                        });

                        this.$bvModal.msgBoxOk(res.data.message || 'ดำเนินการเสร็จสิ้น', {
                            title: 'ผลการดำเนินการ',
                            size: 'sm',
                            okVariant: 'success',
                            centered: true
                        });

                        await this.refreshStatsOnce();

                    } catch (e) {
                        console.log('buildRecipients error', e);
                        this.$bvModal.msgBoxOk('Build recipients ไม่สำเร็จ', {
                            title: 'ผิดพลาด',
                            size: 'sm',
                            okVariant: 'danger',
                            centered: true
                        });
                    } finally {
                        this.building = false;
                    }
                },

                async dispatchQueued() {
                    if (this.formmethod !== 'edit' || !this.code) return;

                    if (!this.canDispatch) {
                        this.$bvModal.msgBoxOk('ยังไม่มีผู้รับค้างส่ง กรุณา Build recipients ก่อน', {
                            title: 'แจ้งเตือน',
                            size: 'sm',
                            okVariant: 'warning',
                            centered: true
                        });
                        return;
                    }

                    this.dispatching = true;

                    try {
                        const limit = this.dispatchLimit || this.recommendedDispatch || 1000;

                        const res = await axios.post("{{ route('admin.'.$menu->currentRoute.'.dispatch') }}", {
                            id: this.code,
                            limit: limit
                        });

                        this.$bvModal.msgBoxOk(res.data.message || 'ดำเนินการเสร็จสิ้น', {
                            title: 'ผลการดำเนินการ',
                            size: 'sm',
                            okVariant: 'success',
                            centered: true
                        });

                        await this.refreshStatsOnce();

                    } catch (e) {
                        console.log('dispatchQueued error', e);
                        this.$bvModal.msgBoxOk('Dispatch ไม่สำเร็จ', {
                            title: 'ผิดพลาด',
                            size: 'sm',
                            okVariant: 'danger',
                            centered: true
                        });
                    } finally {
                        this.dispatching = false;
                    }
                },
            },
        });
    </script>
@endpush
