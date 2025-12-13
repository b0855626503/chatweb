{{-- Modal Add/Edit SMS Campaign --}}
<b-modal ref="addedit" id="addedit" centered scrollable size="lg"
         title="เพิ่ม/แก้ไข SMS Campaign"
         :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true">
    <b-form @submit.prevent="addEditSubmit" v-if="show">

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
            v-if="formaddedit.audience_mode === 'upload_only' || formaddedit.audience_mode === 'mixed'"
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
                    (valid: @{{ importBatch.valid_phones }}, invalid: @{{ importBatch.invalid_phones }}, dup: @{{
                    importBatch.duplicate_phones }})
                </div>

                <b-button size="sm" variant="outline-success" class="mt-2"
                          @click="buildRecipients('upload_only')">
                    Build recipients จากไฟล์นี้
                </b-button>
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
        </b-form-group>

        {{-- Build recipients quick actions --}}
        <b-form-group
            id="input-group-build"
            label="สร้างรายชื่อผู้รับ (Build recipients):"
            description="แนะนำ: บันทึกแคมเปญก่อน แล้วค่อย Build/Dispatch เพื่อกันข้อมูลหลุด">
            <b-button size="sm" variant="outline-success" class="mr-1"
                      @click="buildRecipients('member_all')"
                      :disabled="formmethod !== 'edit' || building">
                @{{ building ? 'กำลังทำ...' : 'Build จากสมาชิกทั้งหมด' }}
            </b-button>

            <b-button size="sm" variant="outline-success"
                      v-if="formaddedit.audience_mode === 'mixed'"
                      @click="buildRecipients('mixed')"
                      :disabled="formmethod !== 'edit' || building || !importBatch.id">
                @{{ building ? 'กำลังทำ...' : 'Build แบบผสม (สมาชิก+ไฟล์)' }}
            </b-button>

            <small class="text-muted d-block mt-2">
                หมายเหตุ: ปุ่ม Build ต้องใช้ตอน “แก้ไข” เพราะต้องมี campaign id ก่อน
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
                              :disabled="formmethod !== 'edit' || dispatching">
                        @{{ dispatching ? 'กำลังปล่อยคิว...' : 'Dispatch' }}
                    </b-button>
                </b-input-group-append>
            </b-input-group>
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
                    show: false,
                    formmethod: 'add',
                    code: null,

                    building: false,
                    dispatching: false,

                    dispatchLimit: 1000,

                    // Upload import
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
                    this.dispatchLimit = 1000;
                    this.building = false;
                    this.dispatching = false;
                },

                async loadData() {
                    const response = await axios.post(
                        "{{ route('admin.'.$menu->currentRoute.'.loaddata') }}",
                        {id: this.code}
                    );

                    const data = response.data.data || {};

                    this.formaddedit.name = data.name || '';
                    this.formaddedit.sender_name = data.sender_name || '';
                    this.formaddedit.message = data.message || '';
                    this.formaddedit.audience_mode = data.audience_mode || 'member_all';
                    this.formaddedit.respect_opt_out = (data.respect_opt_out ?? 1) ? true : false;
                    this.formaddedit.require_consent = (data.require_consent ?? 0) ? true : false;
                    this.formaddedit.remark = data.remark || '';

                    // ถ้าเคยมี import ล่าสุดผูกไว้ใน meta ก็เอามาโชว์ได้ (optional)
                    // ปล่อยว่างไว้ก่อนเพื่อไม่ไปพึ่ง schema meta
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

                    this.$http.post(url, {id: this.code, data: this.formaddedit})
                        .then(response => {
                            // ถ้าเป็น add แล้ว backend สร้าง id ให้ แนะนำให้ส่งกลับ id ด้วย
                            // แต่เพื่อไม่เปลี่ยน interface เดิม เรารีเฟรช datatable อย่างเดียวก่อน
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

                        // เผื่ออยากผูก campaign_id ไว้เลย
                        if (this.formmethod === 'edit' && this.code) {
                            fd.append('campaign_id', this.code);
                        }

                        fd.append('country_code', this.importOptions.country_code || '66');
                        fd.append('has_header', this.importOptions.has_header ? 1 : 0);
                        if (this.importOptions.phone_column) fd.append('phone_column', this.importOptions.phone_column);

                        // route นี้ต้องมี: admin.sms_import.store (ถ้าคุณใช้ controller แบบที่ให้ไป)
                        // แต่ถ้าคุณตั้ง route เป็น sms_import หรือ sms_campaign/import/parse ให้แก้ตรงนี้ได้
                        const res = await axios.post("{{ route('admin.sms_import.parse') }}", fd, {
                            headers: {'Content-Type': 'multipart/form-data'}
                        });

                        // ถ้า controller ของคุณตอบแบบ sendResponse/sendSuccess อาจมีโครง data ต่างกัน
                        // ด้านล่างรองรับทั้ง 2 แบบ
                        const payload = res.data.data || res.data || {};
                        const batch = payload.batch || payload;

                        // ถ้าคุณใช้ controller ที่ผมให้ก่อนหน้า จะ redirect เป็นหน้า show
                        // แนะนำให้ทำ route parse แบบ json แยก (เหมือน LineOA style) เพื่อ UX ดีที่สุด
                        // แต่ตอนนี้ทำ best-effort แปลงให้ก่อน:
                        this.importBatch.id = batch.id || payload.batch_id || null;

                        // กรณี response เป็น JSON แบบเดิมที่ส่ง counters/preview
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

                    // สำหรับ upload_only/mixed ต้องมี importBatch id
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

                    this.dispatching = true;

                    try {
                        const res = await axios.post("{{ route('admin.'.$menu->currentRoute.'.dispatch') }}", {
                            id: this.code,
                            limit: this.dispatchLimit || 1000
                        });

                        this.$bvModal.msgBoxOk(res.data.message || 'ดำเนินการเสร็จสิ้น', {
                            title: 'ผลการดำเนินการ',
                            size: 'sm',
                            okVariant: 'success',
                            centered: true
                        });

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

