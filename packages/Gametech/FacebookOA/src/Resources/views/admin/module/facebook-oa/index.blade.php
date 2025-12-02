@extends('admin::layouts.line-oa')

{{-- page title --}}
@section('title')
    {{ $menu->currentName }}
@endsection

@section('css')
    @include('admin::layouts.datatables_css')
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css"
          integrity="sha512-jU/7UFiaW5UBGODEopEqnbIAHOI8fO6T99m7Tsmqs2gkdujByJfkCbbfPSN4Wlqlb9TGnsuC0YgUgWkRBK7B9A=="
          crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <style>
        /* ====== รายการห้องแชต (ด้านซ้าย) ====== */
        .list-group-item.gt-conv-active {
            background-color: #e7f1ff; /* ฟ้าอ่อนกว่า primary */
            border-color: #b6d4fe;
            color: #0c63e4;
        }

        .list-group-item.gt-conv-active .text-muted,
        .list-group-item.gt-conv-active small {
            color: #0c63e4 !important;
        }

        .list-group-item.active .text-muted {
            color: #fff !important;
        }

        .list-group-item.gt-conv-active .badge {
            background-color: #0d6efd;
            color: #fff;
        }

        /* ====== bubble ฝั่งทีมงาน (outbound) ====== */
        .gt-msg-agent {
            background-color: #d1e7ff;
            color: #084298;
        }

        .gt-msg-agent .text-muted {
            color: #084298 !important;
        }

        #line-oa-chat-overlay {
            position: fixed;
            inset: 0;
            z-index: 9998;
        }

        .lineoa-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
        }

        .lineoa-popup {
            position: fixed;
            inset: 20px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            z-index: 9999;
            overflow: hidden;
        }

        .list-group-item.gt-conv-active .oa-reg-badge {
            background-color: #ffc107 !important; /* สี warning */
            color: #212529 !important; /* ดำ */
        }

        .chat-line-original {
            white-space: pre-wrap;
            font-size: 14px;
        }

        .chat-line-translated {
            white-space: pre-wrap;
            font-size: 13px;
            border-left: 3px solid #e0e0e0;
            padding-left: 4px;
        }

        .gt-conv-last-message {
            font-size: 12px;
            color: #666;
            white-space: nowrap; /* ไม่ตัดขึ้นบรรทัดใหม่ */
            overflow: hidden; /* ถ้ายาวเกิน ก็ตัดส่วนที่ล้นทิ้ง */
            text-overflow: ellipsis; /* แสดง ... ท้ายบรรทัด */
            max-width: 100%; /* หรือกำหนดเป็น px ก็ได้เช่น 220px */
        }
        /* ฝั่ง sidebar ทั้งคอลัมน์ – ไม่ให้เลื่อนซ้ายขวา */
        .line-oa-sidebar {
            overflow-x: hidden;
        }

        /* ข้อความพรีวิวในแต่ละห้อง */
        .line-oa-sidebar .conversation-last-message {
            display: block;
            white-space: nowrap;        /* บังคับบรรทัดเดียว */
            overflow: hidden;           /* ซ่อนส่วนเกิน */
            text-overflow: ellipsis;    /* ใส่ ... ท้ายประโยค */
        }
        /* ห้ามเลื่อนซ้าย-ขวา */
        .no-x-scroll {
            overflow-x: hidden !important;
        }

        /* บังคับ … ให้ทำงานเสมอ */
        .fixed-line {
            display: block;
            white-space: unset !important;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%; /* สำคัญมาก */
        }

    </style>
    <style>
        /* ให้คลิกทะลุข้อความได้แน่ ๆ */
        .dropzone .dz-message {
            pointer-events: auto;
        }

        /* กัน preview ทับพื้นที่คลิก */
        .dropzone .dz-preview {
            position: relative;
            z-index: 1;
        }

        .dropzone .dz-message {
            position: relative;
            z-index: 2;
        }

    </style>
@endpush

@section('content')
    <section class="content text-xs">
        <div class="card">
            <div class="card-body">
                <div id="line-oa-chat-app">
                    <line-oa-chat ref="lineOaChat"></line-oa-chat>
                </div>

                {{-- เอา block นี้ไปวางเพิ่มด้านล่างได้เลย --}}
                <div id="member-edit-app">
                    <b-modal
                            ref="memberEditModal"
                            id="line-oa-member-edit-modal"
                            centered
                            scrollable
                            size="md"
                            title="แก้ไขข้อมูลสมาชิก"
                            :no-close-on-backdrop="true"
                            :hide-footer="true"
                            :lazy="true"
                            @hidden="onMemberEditModalHidden"
                    >
                        <b-container class="bv-example-row">
                            <b-form
                                    @submit.prevent="memberEditSubmit"
                                    v-if="memberEditShow"
                                    id="member-edit-form"
                                    ref="memberEditFormRef"
                            >
                                <input
                                        type="hidden"
                                        id="memberEdit_upline_code"
                                        :value="memberEditForm.upline_code"
                                >

                                {{-- แถว: ผู้แนะนำ --}}
                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-af-group"
                                                label="เบอร์ ผู้แนะนำ:"
                                                label-for="memberEdit-af"
                                                description="ระบุ เบอร์ ผู้แนะนำ"
                                        >
                                            <b-form-input
                                                    id="memberEdit-af"
                                                    v-model="memberEditForm.af"
                                                    type="text"
                                                    size="sm"
                                                    placeholder="เบอร์ ผู้แนะนำ"
                                                    autocomplete="off"
                                                    @input="memberEditLoadAF($event)"
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-up_name-group"
                                                label="ชื่อผู้แนะนำ:"
                                                label-for="memberEdit-up_name"
                                        >
                                            <b-form-input
                                                    id="memberEdit-up_name"
                                                    v-model="memberEditForm.up_name"
                                                    type="text"
                                                    size="sm"
                                                    autocomplete="off"
                                                    readonly
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                {{-- แถว: ชื่อ / นามสกุล --}}
                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-firstname-group"
                                                label="ชื่อ:"
                                                label-for="memberEdit-firstname"
                                                description="ระบุ ชื่อ"
                                        >
                                            <b-form-input
                                                    id="memberEdit-firstname"
                                                    v-model="memberEditForm.firstname"
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
                                                id="memberEdit-lastname-group"
                                                label="นามสกุล:"
                                                label-for="memberEdit-lastname"
                                                description="ระบุ นามสกุล"
                                        >
                                            <b-form-input
                                                    id="memberEdit-lastname"
                                                    v-model="memberEditForm.lastname"
                                                    type="text"
                                                    size="sm"
                                                    placeholder="นามสกุล"
                                                    autocomplete="off"
                                                    required
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                {{-- แถว: User / Password --}}
                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-user_name-group"
                                                label="User ID:"
                                                label-for="memberEdit-user_name"
                                                description="เปลี่ยนได้"
                                        >
                                            <b-form-input
                                                    id="memberEdit-user_name"
                                                    v-model="memberEditForm.user_name"
                                                    type="text"
                                                    size="sm"
                                                    autocomplete="off"
                                                    required
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-user_pass-group"
                                                label="รหัสผ่าน:"
                                                label-for="memberEdit-user_pass"
                                                description="ระบุ รหัสผ่าน กรณีต้องการเปลี่ยนเท่านั้น"
                                        >
                                            <b-form-input
                                                    id="memberEdit-user_pass"
                                                    v-model="memberEditForm.user_pass"
                                                    type="text"
                                                    size="sm"
                                                    placeholder="รหัสผ่าน"
                                                    autocomplete="off"
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                {{-- แถว: ธนาคาร / เลขบัญชี --}}
                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-bank_code-group"
                                                label="ธนาคาร:"
                                                label-for="memberEdit-bank_code"
                                        >
                                            <b-form-select
                                                    id="memberEdit-bank_code"
                                                    v-model="memberEditForm.bank_code"
                                                    :options="memberEditOption.bank_code"
                                                    size="sm"
                                                    required
                                            ></b-form-select>
                                        </b-form-group>
                                    </b-col>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-acc_no-group"
                                                label="เลขที่บัญชี:"
                                                label-for="memberEdit-acc_no"
                                                description="ระบบไม่ได้เชคซ้ำให้นะ"
                                        >
                                            <b-form-input
                                                    id="memberEdit-acc_no"
                                                    v-model="memberEditForm.acc_no"
                                                    type="text"
                                                    size="sm"
                                                    autocomplete="off"
                                                    required
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                {{-- แถว: Line ID / เบอร์โทร --}}
                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-lineid-group"
                                                label="Line ID:"
                                                label-for="memberEdit-lineid"
                                        >
                                            <b-form-input
                                                    id="memberEdit-lineid"
                                                    v-model="memberEditForm.lineid"
                                                    type="text"
                                                    size="sm"
                                                    autocomplete="off"
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-tel-group"
                                                label="เบอร์โทร:"
                                                label-for="memberEdit-tel"
                                                description="ระบบไม่ได้เชคซ้ำให้นะ"
                                        >
                                            <b-form-input
                                                    id="memberEdit-tel"
                                                    v-model="memberEditForm.tel"
                                                    type="text"
                                                    size="sm"
                                                    autocomplete="off"
                                                    required
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                {{-- แถว: Max withdraw / Refer --}}
                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-maxwithdraw_day-group"
                                                label="ยอดถอนสูงสุด / วัน:"
                                                label-for="memberEdit-maxwithdraw_day"
                                                description="ถ้าค่าเป็น 0 = ใช้ค่าถอนสูงสุด / วัน จาก ตั้งค่าพื้นฐานเวบไซต์"
                                        >
                                            <b-form-input
                                                    id="memberEdit-maxwithdraw_day"
                                                    v-model="memberEditForm.maxwithdraw_day"
                                                    type="number"
                                                    size="sm"
                                                    autocomplete="off"
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                    <b-col>
                                        <b-form-group
                                                id="memberEdit-refer_code-group"
                                                label="Refer:"
                                                label-for="memberEdit-refer_code"
                                        >
                                            <b-form-select
                                                    id="memberEdit-refer_code"
                                                    v-model="memberEditForm.refer_code"
                                                    :options="memberEditOption.refer_code"
                                                    size="sm"
                                                    required
                                            ></b-form-select>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                {{-- แถว: Line ID (สำรอง) + รูป --}}
                                <b-form-row>
                                    <b-col>

                                    </b-col>
                                    <b-col>
                                        <div class="d-flex align-items-center">
                                            <img
                                                    v-if="memberEditPic && memberEditPic.url"
                                                    :src="memberEditPic.url"
                                                    style="max-width:120px"
                                            >
                                            <small v-else class="text-muted">ยังไม่มีรูป</small>
                                            <b-button
                                                    class="ml-3"
                                                    variant="primary"
                                                    @click="memberEditOpenUpload"
                                            >
                                                อัปโหลด/เปลี่ยนรูป
                                            </b-button>
                                        </div>
                                    </b-col>
                                </b-form-row>

                                <b-button type="submit" variant="primary">
                                    บันทึก
                                </b-button>
                            </b-form>
                        </b-container>
                    </b-modal>

                    <b-modal
                            ref="memberEditUploadModal"
                            title="อัปโหลดรูป"
                            @shown="memberEditOnUploadShown"
                            @hidden="memberEditOnUploadHidden"
                            hide-footer
                    >
                        <div
                                ref="memberEditDropzoneEl"
                                class="dropzone border rounded p-4 text-center"
                        >
                            <div class="dz-message">
                                ลากไฟล์มาวาง หรือคลิกเพื่อเลือกไฟล์
                            </div>
                            <div ref="memberEditDropzonePreviews" class="mt-3"></div>
                        </div>
                        <div class="text-center mt-2">
                            <b-button ref="memberEditPickBtn" size="sm" variant="secondary">
                                เลือกไฟล์
                            </b-button>
                        </div>
                    </b-modal>
                </div>

                <div id="member-refill-app">

                    {{-- ฟอร์มเลือกเป้าหมายที่จะเติมให้ (เดิม addedit) --}}
                    <b-modal
                            ref="assignTopupTargetModal"
                            id="assignTopupTargetModal"
                            centered
                            size="md"
                            title="เลือกระบุไอดีที่จะเติมให้"
                            :no-close-on-backdrop="true"
                            :hide-footer="true"
                            :lazy="true"
                            @shown="removeFocusFromTrigger"
                    >
                        <b-container class="bv-example-row">
                            <b-form
                                    v-if="showRefillUI"
                                    ref="assignTopupTargetFormRef"
                                    id="assignTopupTargetForm"
                                    @submit.prevent="submitAssignTopupTarget"
                            >
                                <input
                                        type="hidden"
                                        id="member_topup"
                                        :value="assignTopupTargetForm.member_topup"
                                        required
                                >

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-user_name"
                                                label="User ID / Game ID:"
                                                label-for="user_name"
                                                description="ระบุ User / Game ID ที่ต้องการเติมให้บิลนี้"
                                        >
                                            <b-input-group>
                                                <b-form-input
                                                        id="user_name"
                                                        v-model="assignTopupTargetForm.user_name"
                                                        type="text"
                                                        size="md"
                                                        placeholder="User / Game ID"
                                                        autocomplete="off"
                                                ></b-form-input>
                                                <b-input-group-append>
                                                    <b-button
                                                            variant="success"
                                                            @click="loadUserForAssignTarget"
                                                    >
                                                        ค้นหา
                                                    </b-button>
                                                </b-input-group-append>
                                            </b-input-group>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-name"
                                                label="ข้อมูลลูกค้า:"
                                                label-for="name"
                                        >
                                            <b-form-textarea
                                                    id="name"
                                                    v-model="assignTopupTargetForm.name"
                                                    size="sm"
                                                    rows="6"
                                                    max-rows="6"
                                                    required
                                                    plaintext
                                            ></b-form-textarea>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-remark_admin"
                                                label="หมายเหตุ:"
                                                label-for="remark_admin"
                                        >
                                            <b-form-input
                                                    id="remark_admin"
                                                    v-model="assignTopupTargetForm.remark_admin"
                                                    type="text"
                                                    size="sm"
                                                    autocomplete="off"
                                                    placeholder=""
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-button type="submit" variant="primary">
                                    บันทึก
                                </b-button>
                            </b-form>
                        </b-container>
                    </b-modal>

                    {{-- ฟอร์มเติมเงิน (เดิม refill) --}}
                    <b-modal
                            ref="refillModal"
                            id="refillModal"
                            centered
                            size="md"
                            title="เติมเงิน"
                            :no-close-on-backdrop="true"
                            :hide-footer="true"
                            :lazy="true"
                            @shown="removeFocusFromTrigger"
                            @hidden="onRefillModalHidden"
                    >
                        <b-container class="bv-example-row">
                            <b-form
                                    v-if="showRefillUI"
                                    ref="refillFormRef"
                                    id="refillForm"
                                    @submit.prevent="submitRefillForm"
                            >
                                <input
                                        type="hidden"
                                        id="id"
                                        :value="refillForm.id"
                                        required
                                >

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-refill-user"
                                                label="User ID / Game ID:"
                                                label-for="refill_user_name"
                                                description="ระบุ User / Game ID ที่ต้องการเติมเงินรายการนี้"
                                        >
                                            <b-input-group>
                                                <b-form-input
                                                        id="refill_user_name"
                                                        v-model="refillForm.user_name"
                                                        type="text"
                                                        size="md"
                                                        placeholder="User / Game ID"
                                                        autocomplete="off"
                                                ></b-form-input>
                                                <b-input-group-append>
                                                    <b-button
                                                            variant="success"
                                                            @click="loadUserForRefill"
                                                    >
                                                        ค้นหา
                                                    </b-button>
                                                </b-input-group-append>
                                            </b-input-group>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-refill-name"
                                                label="ข้อมูลลูกค้า:"
                                                label-for="refill_name"
                                        >
                                            <b-form-textarea
                                                    id="refill_name"
                                                    v-model="refillForm.name"
                                                    size="sm"
                                                    rows="6"
                                                    max-rows="6"
                                                    required
                                                    plaintext
                                            ></b-form-textarea>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-amount"
                                                label="จำนวนเงิน:"
                                                label-for="amount"
                                                description="ระบุจำนวนเงิน ระหว่าง 1 - 10,000"
                                        >
                                            <b-form-input
                                                    id="amount"
                                                    v-model="refillForm.amount"
                                                    type="number"
                                                    size="sm"
                                                    placeholder="จำนวนเงิน"
                                                    min="1"
                                                    max="10000"
                                                    autocomplete="off"
                                                    required
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-account_code"
                                                label="ช่องทางที่ฝาก:"
                                                label-for="account_code"
                                        >
                                            <b-form-select
                                                    id="account_code"
                                                    v-model="refillForm.account_code"
                                                    :options="banks"
                                                    size="sm"
                                                    required
                                            ></b-form-select>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-form-row>
                                    <b-col>
                                        <b-form-group
                                                id="input-group-refill-remark"
                                                label="หมายเหตุ:"
                                                label-for="refill_remark_admin"
                                        >
                                            <b-form-input
                                                    id="refill_remark_admin"
                                                    v-model="refillForm.remark_admin"
                                                    type="text"
                                                    size="sm"
                                                    autocomplete="off"
                                            ></b-form-input>
                                        </b-form-group>
                                    </b-col>
                                </b-form-row>

                                <b-button type="submit" variant="primary">
                                    บันทึก
                                </b-button>
                            </b-form>
                        </b-container>
                    </b-modal>

                    {{-- ฟอร์มหมายเหตุยกเลิกรายการ (เดิม clear) --}}
                    <b-modal
                            ref="clearRemarkModal"
                            id="clearRemarkModal"
                            centered
                            size="md"
                            title="โปรดระบุหมายเหตุ ในการทำรายการ"
                            :no-close-on-backdrop="true"
                            :hide-footer="true"
                            :lazy="true"
                            @shown="removeFocusFromTrigger"
                    >
                        <b-form
                                v-if="showRefillUI"
                                ref="clearRemarkFormRef"
                                id="clearRemarkForm"
                                @submit.stop.prevent="submitClearRemarkForm"
                        >
                            <b-form-group
                                    id="input-group-clear-remark"
                                    label="หมายเหตุ:"
                                    label-for="clear_remark"
                            >
                                <b-form-input
                                        id="clear_remark"
                                        v-model="clearRemarkForm.remark"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                ></b-form-input>
                            </b-form-group>

                            <b-button type="submit" variant="primary">
                                บันทึก
                            </b-button>
                        </b-form>
                    </b-modal>

                    {{-- NEW: ฟอร์มเพิ่ม - ลด ยอดเงิน --}}
                    <b-modal
                            ref="money"
                            id="money"
                            centered
                            size="sm"
                            title="เพิ่ม - ลด ยอดเงิน"
                            :no-stacking="true"
                            :no-close-on-backdrop="true"
                            :hide-footer="true"
                            @shown="removeFocusFromTrigger"
                    >
                        <b-form @submit.prevent.once="moneySubmit" v-if="showRefillUI">
                            <b-form-group
                                    id="input-group-money-type"
                                    label="ประเภทรายการ:"
                                    label-for="money_type"
                            >
                                <b-form-select
                                        id="money_type"
                                        v-model="formmoney.type"
                                        :options="typesmoney"
                                        size="sm"
                                        required
                                ></b-form-select>
                            </b-form-group>

                            <b-form-group
                                    id="input-group-money-amount"
                                    label="จำนวนเงิน:"
                                    label-for="money_amount"
                                    description="ระบุจำนวนเงิน ต่ำสุดคือ 1"
                            >
                                <b-form-input
                                        id="money_amount"
                                        v-model="formmoney.amount"
                                        type="number"
                                        size="sm"
                                        placeholder="จำนวนเงิน"
                                        min="1"
                                        step="0.01"
                                        autocomplete="off"
                                        required
                                ></b-form-input>
                            </b-form-group>

                            <b-form-group
                                    id="input-group-money-remark"
                                    label="หมายเหตุ:"
                                    label-for="money_remark"
                                    description="ระบุสาเหตุที่ทำรายการ"
                            >
                                <b-form-input
                                        id="money_remark"
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

                    {{-- NEW: ฟอร์มเพิ่ม - ลด Point --}}
                    <b-modal
                            ref="point"
                            id="point"
                            centered
                            size="sm"
                            title="เพิ่ม - ลด Point"
                            :no-stacking="true"
                            :no-close-on-backdrop="true"
                            :hide-footer="true"
                            @shown="removeFocusFromTrigger"
                    >
                        <b-form @submit.prevent.once="pointSubmit" v-if="showRefillUI">
                            <b-form-group
                                    id="input-group-point-type"
                                    label="ประเภทรายการ:"
                                    label-for="point_type"
                            >
                                <b-form-select
                                        id="point_type"
                                        v-model="formpoint.type"
                                        :options="typespoint"
                                        size="sm"
                                        required
                                ></b-form-select>
                            </b-form-group>

                            <b-form-group
                                    id="input-group-point-amount"
                                    label="จำนวน:"
                                    label-for="point_amount"
                                    description="ระบุจำนวน ระหว่าง 0 - 10,000"
                            >
                                <b-form-input
                                        id="point_amount"
                                        v-model="formpoint.amount"
                                        type="number"
                                        size="sm"
                                        placeholder="จำนวน"
                                        min="1"
                                        max="10000"
                                        autocomplete="off"
                                        required
                                ></b-form-input>
                            </b-form-group>

                            <b-form-group
                                    id="input-group-point-remark"
                                    label="หมายเหตุ:"
                                    label-for="point_remark"
                                    description="ระบุสาเหตุที่ทำรายการ"
                            >
                                <b-form-input
                                        id="point_remark"
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

                    {{-- NEW: ฟอร์มเพิ่ม - ลด Diamond --}}
                    <b-modal
                            ref="diamond"
                            id="diamond"
                            centered
                            size="sm"
                            title="เพิ่ม - ลด Diamond"
                            :no-stacking="true"
                            :no-close-on-backdrop="true"
                            :hide-footer="true"
                            @shown="removeFocusFromTrigger"
                    >
                        <b-form @submit.prevent.once="diamondSubmit" v-if="showRefillUI">
                            <b-form-group
                                    id="input-group-diamond-type"
                                    label="ประเภทรายการ:"
                                    label-for="diamond_type"
                            >
                                <b-form-select
                                        id="diamond_type"
                                        v-model="formdiamond.type"
                                        :options="typesdiamond"
                                        size="sm"
                                        required
                                ></b-form-select>
                            </b-form-group>

                            <b-form-group
                                    id="input-group-diamond-amount"
                                    label="จำนวน:"
                                    label-for="diamond_amount"
                                    description="ระบุจำนวนเงิน ระหว่าง 1 - 10,000"
                            >
                                <b-form-input
                                        id="diamond_amount"
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
                                    id="input-group-diamond-remark"
                                    label="หมายเหตุ:"
                                    label-for="diamond_remark"
                                    description="ระบุสาเหตุที่ทำรายการ"
                            >
                                <b-form-input
                                        id="diamond_remark"
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

                    {{-- NEW: ประวัติ ฝาก - ถอน --}}
                    <b-modal
                            ref="gamelog"
                            id="gamelog"
                            centered
                            size="lg"
                            :title="caption"
                            :no-stacking="false"
                            :no-close-on-backdrop="true"
                            :ok-only="true"
                            :lazy="true"
                            @shown="removeFocusFromTrigger"
                    >
                        <b-table
                                striped
                                hover
                                small
                                outlined
                                sticky-header
                                show-empty
                                :items="items"
                                :fields="fields"
                                :busy="isBusy"
                                ref="tbdatalog"
                                v-if="showRefillUI"
                        >
                            <template #table-busy>
                                <div class="text-center text-danger my-2">
                                    <b-spinner class="align-middle"></b-spinner>
                                    <strong>Loading...</strong>
                                </div>
                            </template>

                            <!-- รหัสรายการ -->
                            <template #cell(id)="{ item }">
                                <span v-text="item.id"></span>
                            </template>

                            <!-- เวลา -->
                            <template #cell(date_create)="{ item }">
                                <span v-text="item.date_create"></span>
                            </template>

                            <!-- ช่องทาง เช่น AUTO / แอดมิน / คิวอาร์ -->
                            <template #cell(method)="{ item }">
                                <span v-text="item.method"></span>
                            </template>

                            <!-- ยอดที่ลูกค้าแจ้ง (ฝาก/ถอน) -->
                            <template #cell(amount_request)="{ item }">
                                <span v-text="intToMoney(item.amount_request)"></span>
                            </template>

                            <!-- โบนัส / โปร (ถ้ามี) -->
                            <template #cell(credit_bonus)="{ item }">
                                <span v-if="item.credit_bonus" v-text="intToMoney(item.credit_bonus)"></span>
                                <span v-else class="text-muted">-</span>
                            </template>

                            <!-- ยอดสุทธิหลังดำเนินการ -->
                            <template #cell(amount)="{ item }">
                                <span v-text="intToMoney(item.amount)"></span>
                            </template>

                            <!-- เครดิตก่อน/หลัง -->
                            <template #cell(credit_before)="{ item }">
                                <span v-text="intToMoney(item.credit_before)"></span>
                            </template>

                            <template #cell(credit_after)="{ item }">
                                <span v-text="intToMoney(item.credit_after)"></span>
                            </template>

                            <!-- สถานะ -->
                            <template #cell(status)="{ item }">
            <span
                    class="badge"
                    :class="'bg-' + (item.status_color || 'secondary')"
                    v-text="item.status_display"
            ></span>
                            </template>
                        </b-table>
                    </b-modal>

                </div>


            </div>
        </div>
    </section>

@endsection


@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"
            integrity="sha512-U2WE1ktpMTuRBPoCFDzomoIorbOyUv0sP8B+INA3EzNAhehbzED1rOJg6bCqPf/Tuposxb5ja/MAUnC8THSbLQ=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    @include('admin::layouts.datatables_js')
    {{--    {!! $depositTable->scripts() !!}--}}
    <script>
        (function () {
            function findAnyVueRoot() {
                // 1) พยายามหา element ที่มี __vue__ โดย scanning ทั่วหน้า (เริ่มจาก body)
                var all = document.querySelectorAll('body, body *');
                for (var i = 0; i < all.length; i++) {
                    if (all[i].__vue__) {
                        return all[i].__vue__;
                    }
                }
                console.warn('ไม่พบ Vue root instance เลย');
                return null;
            }

            function findLineOaChatVm(vm) {
                if (!vm) return null;

                // ถ้าตัวนี้คือ line-oa-chat เอง
                var name = vm.$options && (vm.$options.name || vm.$options._componentTag);
                if (name === 'line-oa-chat') {
                    return vm;
                }

                // ลองไล่ children
                if (vm.$children && vm.$children.length) {
                    for (var i = 0; i < vm.$children.length; i++) {
                        var found = findLineOaChatVm(vm.$children[i]);
                        if (found) return found;
                    }
                }

                return null;
            }

            function getLineOaChatComponent() {
                var rootVm = findAnyVueRoot();
                if (!rootVm) {
                    console.warn('ยังหา Vue root ไม่เจอ');
                    return null;
                }

                // ถ้ามี ref แบบ lineOaChat ก็ลองก่อน
                if (rootVm.$refs && rootVm.$refs.lineOaChat) {
                    return rootVm.$refs.lineOaChat;
                }

                var comp = findLineOaChatVm(rootVm);
                if (!comp) {
                    console.warn('ไม่พบ component line-oa-chat จาก Vue tree');
                }
                return comp;
            }

            window.editModal = function (code) {
                if (!window.memberRefillApp) {
                    console.warn('memberRefillApp ยังไม่พร้อมใช้งาน');
                    return;
                }

                // 1) หา line-oa-chat เพื่อตรวจว่ามีห้องไหนถูกเลือกอยู่
                var comp = getLineOaChatComponent();
                var prefill = null;

                if (comp && comp.selectedConversation && comp.selectedConversation.contact) {
                    var c = comp.selectedConversation.contact;

                    // สมมติ structure: contact.member_id / contact.member_user
                    prefill = {
                        member_id: c.member_id || null,
                        member_username: c.member_username || null,
                    };
                }

                // 2) ส่ง topupId + prefill (ถ้ามี) เข้า memberRefillApp
                window.memberRefillApp.openAssignTopupTargetModal(code, prefill);
            };

            window.addModal = function () {
                if (!window.memberRefillApp) {
                    console.warn('memberRefillApp ยังไม่พร้อมใช้งาน');
                    return;
                }

                // addModal = เลือก target ใหม่ ไม่ผูกบิล (code = null)
                window.memberRefillApp.openAssignTopupTargetModal(null);
            };

            window.refill = function () {
                if (!window.memberRefillApp) {
                    console.warn('memberRefillApp ยังไม่พร้อมใช้งาน');
                    return;
                }

                // 1) พยายามหา line-oa-chat component
                var comp = getLineOaChatComponent();
                var prefill = null;

                if (comp && comp.selectedConversation && comp.selectedConversation.contact) {
                    var c = comp.selectedConversation.contact;

                    prefill = {
                        member_id: c.member_id || null,
                        member_username: c.member_username || null,
                    };
                }

                // 2) เรียก refillModal โดยส่ง prefill (ถ้าไม่มีจะเป็น null)
                window.memberRefillApp.openRefillModal(prefill);
            };

            window.clearModal = function (code) {
                if (!window.memberRefillApp) {
                    console.warn('memberRefillApp ยังไม่พร้อมใช้งาน');
                    return;
                }

                // clear = modal ระบุหมายเหตุ
                window.memberRefillApp.openClearRemarkModal(code);
            };

// เผื่อมีปุ่ม delete
            window.delModal = function (code) {
                if (!window.memberRefillApp) {
                    console.warn('memberRefillApp ยังไม่พร้อมใช้งาน');
                    return;
                }

                // ถ้าต้องสร้าง modal ลบ ให้ map จากตรงนี้ได้
                if (typeof window.memberRefillApp.openDeleteModal === 'function') {
                    window.memberRefillApp.openDeleteModal(code);
                } else {
                    console.warn('memberRefillApp ไม่มี method openDeleteModal');
                }
            };

            // สร้าง global helper สำหรับให้ DataTables เรียกใช้
            window.LineOaChatActions = {
                edit: function (code) {
                    var comp = getLineOaChatComponent();
                    if (!comp || typeof comp.editModal !== 'function') {
                        console.warn('editModal() ไม่พร้อมใช้งานบน line-oa-chat');
                        return;
                    }
                    comp.editModal(code);
                },
                approve: function (code) {
                    var comp = getLineOaChatComponent();
                    if (!comp || typeof comp.approveModal !== 'function') {
                        console.warn('approveModal() ไม่พร้อมใช้งานบน line-oa-chat');
                        return;
                    }
                    comp.approveModal(code);
                },
                cancel: function (code) {
                    var comp = getLineOaChatComponent();
                    if (!comp || typeof comp.clearModal !== 'function') {
                        console.warn('clearModal() ไม่พร้อมใช้งานบน line-oa-chat');
                        return;
                    }
                    comp.clearModal(code);
                },
                delete: function (code) {
                    var comp = getLineOaChatComponent();
                    if (!comp || typeof comp.delModal !== 'function') {
                        console.warn('delModal() ไม่พร้อมใช้งานบน line-oa-chat');
                        return;
                    }
                    comp.delModal(code);
                }
            };
        })();
    </script>
    <script>
        window.LineDefaultAvatar = "{{ asset('storage/img/'.$config->logo) }}";
        window.LineOAEventsChannel = "{{ config('app.name') }}_events";
        window.LineOAEmployee = {
            id: '{{ auth('admin')->user()->code ?? '' }}',
            name: '{{ auth('admin')->user()->user_name ?? '' }}',
        };

    </script>

    <script type="text/x-template" id="line-oa-chat-template">
        <b-container fluid class="px-0">
            <b-row no-gutters>
                {{-- ====== LEFT: CONVERSATION LIST ====== --}}
                <b-col cols="12" md="4" class="border-right" style="height: calc(100vh - 180px);">
                    <div class="d-flex flex-column h-100">

                        {{-- HEADER + FILTERS --}}
                        <div class="p-2 border-bottom bg-light">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="mb-0">
                                    <i class="far fa-comments"></i>
                                    แชตลูกค้า
                                </h5>
                                <div class="text-right">
                                    <div>
                                        <b-badge variant="primary" v-if="filters.status === 'open'">เปิดอยู่</b-badge>
                                        <b-badge variant="secondary" v-else>ปิดแล้ว</b-badge>
                                    </div>
                                </div>
                            </div>

                            {{-- Scope tab: ทั้งหมด / ที่รับเรื่อง --}}
                            <b-nav pills small class="mt-2">
                                <b-nav-item
                                        :active="filters.scope === 'all'"
                                        @click="changeScope('all')"
                                >
                                    ทั้งหมด
                                </b-nav-item>
                                <b-nav-item
                                        :active="filters.scope === 'mine'"
                                        @click="changeScope('mine')"
                                >
                                    ที่รับเรื่อง
                                </b-nav-item>
                            </b-nav>

                            <b-input-group size="sm" class="mt-2">
                                <b-form-input
                                        v-model="filters.q"
                                        placeholder="ค้นหา ชื่อลูกค้า / ยูส / เบอร์"
                                        @input="onSearchInput"
                                ></b-form-input>
                                <b-input-group-append>
                                    <b-button size="sm" variant="outline-secondary" @click="fetchConversations(1)">
                                        <i class="fa fa-search"></i>
                                    </b-button>
                                </b-input-group-append>
                            </b-input-group>

                            <div class="d-flex mt-2">
                                <b-form-select
                                        v-model="filters.status"
                                        :options="statusOptions"
                                        size="sm"
                                        class="mr-2"
                                        @change="fetchConversations(1,{ silent : true})"
                                ></b-form-select>

                                <b-form-select
                                        v-model="filters.account_id"
                                        :options="accountOptions"
                                        size="sm"
                                        @change="fetchConversations(1,{ silent : true})"
                                >
                                    <template #first>
                                        <option :value="null">ทุก OA</option>
                                    </template>
                                </b-form-select>
                            </div>
                        </div>

                        {{-- LIST --}}
                        <div class="flex-fill overflow-auto">
                            <div v-if="loadingList" class="text-center text-muted py-3">
                                <b-spinner small class="mr-2"></b-spinner>
                                กำลังโหลดรายการแชต...
                            </div>

                            <div v-else-if="conversations.length === 0" class="text-center text-muted py-3">
                                ไม่พบห้องแชต
                            </div>

                            <b-list-group flush v-else>
                                <b-list-group-item
                                        v-for="conv in conversations"
                                        :key="conv.id"
                                        button
                                        @click="selectConversation(conv)"
                                        :class="conversationItemClass(conv)"
                                >
                                    <div class="d-flex">
                                        <div class="mr-2">
                                            <img
                                                    v-if="conv.contact && conv.contact.picture_url"
                                                    :src="conv.contact.picture_url"
                                                    v-on:error="onProfileImageError"
                                                    class="rounded-circle"
                                                    style="width: 40px; height: 40px; object-fit: cover;"
                                            >
                                            <div v-else
                                                 class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                                                 style="width: 40px; height: 40px;">
                                                <i class="far fa-user"></i>
                                            </div>
                                        </div>

                                        <div class="flex-fill">
                                            <div class="d-flex justify-content-between">
                                                <strong>
                                                    @{{ (conv.contact && (conv.contact.display_name ||
                                                    conv.contact.member_username)) || 'ไม่ทราบชื่อ' }}
                                                </strong>
                                                <small class="text-muted" v-if="conv.last_message_at">
                                                    @{{ formatDateTime(conv.last_message_at) }}
                                                </small>
                                            </div>
                                            <!-- เพิ่มส่วนนี้ -->
                                            <div v-if="conv.is_registering" class="mt-1">
                                                <b-badge variant="warning" class="text-dark oa-reg-badge">
                                                    <i class="fa fa-robot"></i>
                                                    กำลังสมัครสมาชิกกับบอท
                                                </b-badge>
                                            </div>
                                            <!-- /เพิ่มส่วนนี้ -->
                                            <div class="text-muted no-x-scroll text-truncate fixed-line">
                                            <span v-if="conv.line_account && conv.line_account.name">
                                                [@{{ conv.line_account.name }}]
                                            </span>
                                                @{{ conv.last_message || 'ยังไม่มีข้อความ' }}
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <div>
                                                    <p class="text-muted d-block">
                                                        ยูส: @{{ conv.contact && conv.contact.member_username || '-' }}
                                                    </p>

                                                    {{-- แสดงชื่อคนปิด + เวลา ถ้าห้องปิดแล้ว --}}
                                                    <div
                                                            v-if="conv.status === 'closed'"
                                                            class="text-muted small"
                                                    >
                                                        ปิดโดย @{{ conv.closed_by_employee_name || 'พนักงาน' }}
                                                        <span v-if="conv.closed_at">
                                                        เมื่อ @{{ formatDateTime(conv.closed_at) }}
                                                    </span>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <b-badge
                                                            v-if="conv.assigned_employee_name && conv.status !== 'closed'"
                                                            variant="info"
                                                            class="mr-1"
                                                    >
                                                        รับเรื่องโดย @{{ conv.assigned_employee_name }}
                                                    </b-badge>
                                                    <b-badge v-if="conv.unread_count > 0" variant="danger">
                                                        @{{ conv.unread_count }}
                                                    </b-badge>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </b-list-group-item>
                            </b-list-group>
                        </div>

                        {{-- PAGINATION --}}
                        <div class="border-top p-1 d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                หน้า @{{ pagination.current_page }} / @{{ pagination.last_page }} (รวม @{{
                                pagination.total }} ห้อง)
                            </small>
                            <div>
                                <b-button size="sm" variant="outline-secondary"
                                          :disabled="pagination.current_page <= 1 || loadingList"
                                          @click="fetchConversations(pagination.current_page - 1)">
                                    <i class="fa fa-chevron-left"></i>
                                </b-button>
                                <b-button size="sm" variant="outline-secondary"
                                          :disabled="pagination.current_page >= pagination.last_page || loadingList"
                                          @click="fetchConversations(pagination.current_page + 1)">
                                    <i class="fa fa-chevron-right"></i>
                                </b-button>
                            </div>
                        </div>
                    </div>
                </b-col>

                {{-- ====== RIGHT: CHAT WINDOW ====== --}}
                <b-col cols="12" md="8" style="height: calc(100vh - 180px);">
                    <div class="d-flex flex-column h-100">

                        {{-- HEADER --}}
                        <div class="p-2 border-bottom bg-light" v-if="selectedConversation">
                            <div class="d-flex align-items-center">
                                <div class="mr-2"
                                     v-if="selectedConversation.contact"
                                     @click="openMemberModal"
                                     style="cursor: pointer;">
                                    <img
                                            v-if="selectedConversation.contact.picture_url"
                                            :src="selectedConversation.contact.picture_url"
                                            v-on:error="onProfileImageError"
                                            class="rounded-circle"
                                            style="width: 40px; height: 40px; object-fit: cover;"
                                    >
                                    <div v-else
                                         class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                                         style="width: 40px; height: 40px;">
                                        <i class="far fa-user"></i>
                                    </div>
                                </div>
                                <div class="flex-fill">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">

                                            {{-- ถ้ายังไม่มี member_id ให้คลิกชื่อเพื่อผูกสมาชิก --}}
                                            <template
                                                    v-if="selectedConversation.contact && !selectedConversation.contact.member_id">
                                            <span
                                                    class="text-primary"
                                                    style="cursor: pointer; text-decoration: underline;"
                                                    @click="openMemberModal"
                                            >
                                                @{{ (selectedConversation.contact &&
                                                (selectedConversation.contact.display_name ||
                                                selectedConversation.contact.member_username)) || 'ไม่ทราบชื่อ' }}
                                            </span>
                                            </template>
                                            <template v-else>
                                            <span
                                                    class="text-primary"
                                                    style="cursor: pointer; text-decoration: underline;"
                                                    @click="openMemberModal"
                                            >
                                                @{{ (selectedConversation.contact &&
                                                (selectedConversation.contact.display_name ||
                                                selectedConversation.contact.member_username)) || 'ไม่ทราบชื่อ' }}
                                            </span>
                                            </template>

                                        </h5>
                                        <div class="text-right">
                                            <p class="text-muted d-block" v-if="selectedConversation.line_account">
                                                OA: @{{ selectedConversation.line_account.name }}
                                            </p>

                                            <div class="mt-1">
                                                <div class="mb-1">
                                                    <b-badge
                                                            v-if="selectedConversation.status === 'closed'"
                                                            variant="secondary"
                                                            class="mr-1"
                                                    >
                                                        ปิดโดย @{{ selectedConversation.closed_by_employee_name ||
                                                        'พนักงาน' }}
                                                    </b-badge>
                                                    <b-badge
                                                            v-else-if="selectedConversation.assigned_employee_name"
                                                            variant="info"
                                                            class="mr-1"
                                                    >
                                                        รับเรื่องโดย @{{ selectedConversation.assigned_employee_name }}
                                                    </b-badge>
                                                </div>

                                                <div class="d-flex justify-content-end flex-wrap">


                                                    <b-button
                                                            v-if="selectedConversation.is_registering && canControlRegister()"
                                                            size="sm"
                                                            variant="outline-danger"
                                                            class="mr-1 mb-1"
                                                            @click="cancelRegisterFlow"
                                                    >
                                                        ยกเลิกสมัคร
                                                    </b-button>
                                                    <b-button
                                                            v-else-if="canControlRegister()"
                                                            size="sm"
                                                            variant="outline-success"
                                                            class="mr-1 mb-1"
                                                            @click="openRegisterModal"
                                                    >
                                                        สมัคร
                                                    </b-button>

                                                    <b-button
                                                            v-if="canControlRegister()"
                                                            size="sm"
                                                            variant="outline-success"
                                                            class="mb-1"
                                                            @click="openRefillModal"
                                                    >
                                                        เพิ่มรายการฝาก
                                                    </b-button>
                                                    <b-button-group
                                                            size="sm"
                                                            v-if="canControlRegister() && selectedConversation.contact.member_id"
                                                            class="mb-1"
                                                    >
                                                        <b-dropdown
                                                                size="sm"
                                                                right
                                                                text="เพิ่ม/ลด"
                                                                variant="outline-success"
                                                        >
                                                            <b-dropdown-item
                                                                    @click="window.memberRefillApp.money({ member_id: selectedConversation.contact.member_id })">
                                                                เพิ่ม/ลด ยอดเงิน
                                                            </b-dropdown-item>

                                                            <b-dropdown-item
                                                                    @click="window.memberRefillApp.point({ member_id: selectedConversation.contact.member_id })">
                                                                เพิ่ม/ลด Points
                                                            </b-dropdown-item>

                                                            <b-dropdown-item
                                                                    @click="window.memberRefillApp.diamond({ member_id: selectedConversation.contact.member_id })">
                                                                เพิ่ม/ลด Diamond
                                                            </b-dropdown-item>
                                                        </b-dropdown>
                                                    </b-button-group>

                                                    <b-button-group
                                                            size="sm"
                                                            v-if="canControlRegister() && selectedConversation.contact.member_id"
                                                            class="mb-1"
                                                    >
                                                    <b-dropdown
                                                            size="sm"
                                                            right
                                                            text="ประวัติ"
                                                            variant="outline-success"
                                                    >
                                                        <b-dropdown-item
                                                                @click="window.memberRefillApp.openGameLog('deposit',{ member_id: selectedConversation.contact.member_id })">
                                                            ฝาก
                                                        </b-dropdown-item>

                                                        <b-dropdown-item
                                                                @click="window.memberRefillApp.openGameLog('withdraw',{ member_id: selectedConversation.contact.member_id })">
                                                            ถอน
                                                        </b-dropdown-item>

                                                    </b-dropdown>
                                                    </b-button-group>

                                                    <b-button
                                                            v-if="canControlRegister() && selectedConversation.contact.member_id"
                                                            size="sm"
                                                            variant="outline-success"
                                                            class="mb-1"
                                                            @click="openMemberFromConversation"
                                                    >
                                                        แก้ไขข้อมูล
                                                    </b-button>

                                                    <b-button
                                                            v-if="canControlRegister() && selectedConversation.contact.member_id"
                                                            size="sm"
                                                            variant="outline-success"
                                                            class="mb-1 mr-3"
                                                            @click="openBalanceModal"
                                                    >
                                                        ดูยอดเงิน
                                                    </b-button>

                                                    <b-button
                                                            v-if="selectedConversation.status === 'open'"
                                                            size="sm"
                                                            variant="outline-primary"
                                                            class="mr-1 mb-1"
                                                            @click="acceptConversation"
                                                    >
                                                        รับเรื่อง
                                                    </b-button>
                                                    <b-button
                                                            v-if="selectedConversation.status !== 'closed'"
                                                            size="sm"
                                                            variant="outline-danger"
                                                            class="mr-1 mb-1"
                                                            @click="closeConversation"
                                                    >
                                                        ปิดเคส
                                                    </b-button>
                                                    <b-button
                                                            v-if="selectedConversation.status === 'closed'"
                                                            size="sm"
                                                            variant="outline-danger"
                                                            class="mr-1 mb-1"
                                                            @click="openConversation"
                                                    >
                                                        เปิดเคส
                                                    </b-button>
                                                </div>
                                            </div>

                                            <div
                                                    class="mt-1"
                                                    v-if="selectedConversation.status === 'closed' && selectedConversation.closed_at"
                                            >
                                                <small class="text-muted">
                                                    ปิดเมื่อ @{{ formatDateTime(selectedConversation.closed_at) }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <div class="text-muted">
                                            ยูส: @{{ selectedConversation.contact &&
                                            selectedConversation.contact.member_username || '-' }}
                                            /
                                            เบอร์: @{{ selectedConversation.contact &&
                                            selectedConversation.contact.member_mobile || '-' }}
                                            /
                                            ชื่อ: @{{ selectedConversation.contact &&
                                            selectedConversation.contact.member_name || '-' }}
                                            /
                                            ธนาคาร: @{{ selectedConversation.contact &&
                                            selectedConversation.contact.member_bank_name || '-' }}
                                            /
                                            เลขบัญชี: @{{ selectedConversation.contact &&
                                            selectedConversation.contact.member_acc_no || '-' }}
                                        </div>
                                    </div>

                                    <div class="mt-1" v-if="selectedConversation.is_registering">
                                        <p class="text-success">
                                            กำลังสมัครสมาชิกผ่านบอทอยู่ ทีมงานสามารถกด "ยกเลิกสมัคร" เพื่อดูแลต่อเอง
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-2 border-bottom bg-light text-muted text-center" v-else>
                            เลือกห้องแชตจากด้านซ้ายเพื่อเริ่มสนทนา
                        </div>

                        {{-- MESSAGE LIST --}}
                        <div class="flex-fill overflow-auto px-2 py-2" ref="messageContainer">
                            <div v-if="!selectedConversation"
                                 class="h-100 d-flex align-items-center justify-content-center text-muted">
                                ยังไม่ได้เลือกห้องแชต
                            </div>

                            <template v-else>
                                <div v-if="loadingMessages" class="text-center text-muted py-3">
                                    <b-spinner small class="mr-2"></b-spinner>
                                    กำลังโหลดข้อความ...
                                </div>

                                <div v-else-if="messages.length === 0" class="text-center text-muted py-3">
                                    ยังไม่มีประวัติการสนทนา
                                </div>

                                <div v-else>
                                    <div v-for="msg in messages" :key="msg.id" class="mb-2">
                                        <div :class="messageWrapperClass(msg)">
                                            <div :class="messageBubbleClass(msg)">
                                                <div class="small" v-if="msg.direction === 'outbound'">
                                                    <strong>พนักงาน</strong>
                                                    <strong v-if="msg.meta && msg.meta.employee_name">
                                                        - @{{ msg.meta.employee_name }}
                                                    </strong>
                                                </div>
                                                <div class="small" v-else-if="msg.source === 'bot'">
                                                    <strong>บอท</strong>
                                                </div>

                                                <div class="whitespace-pre-wrap">
                                                    <!-- TEXT -->
                                                    <template v-if="msg.type === 'text'">
                                                        <div class="chat-line-original">
                                                            <!-- แสดงภาษา (ถ้ามี) เช่น [EN] -->
                                                            <span v-if="getMessageDisplay(msg).lang"
                                                                  class="text-primary font-weight-bold mr-1">
            [@{{ getMessageDisplay(msg).lang.toUpperCase() }}]
        </span>

                                                            <!-- แสดงข้อความต้นฉบับ -->
                                                            <span>@{{ getMessageDisplay(msg).original }}</span>
                                                        </div>

                                                        <!-- บรรทัดแปล -->
                                                        <div v-if="getMessageDisplay(msg).translated"
                                                             class="chat-line-translated text-muted mt-1">
        <span v-if="getMessageDisplay(msg).target"
              class="text-success font-weight-bold mr-1">
            [@{{ getMessageDisplay(msg).target.toUpperCase() }}]
        </span>

                                                            <span>@{{ getMessageDisplay(msg).translated }}</span>
                                                        </div>
                                                    </template>

                                                    <!-- STICKER -->
                                                    <template v-else-if="msg.type === 'sticker'">
                                                        <img
                                                                :src="stickerUrl(msg)"
                                                                class="img-fluid"
                                                                style="max-width:130px;"
                                                                alt="[Sticker]"
                                                        >
                                                    </template>

                                                    <!-- IMAGE -->
                                                    <template v-else-if="msg.type === 'image'">
                                                        <img
                                                                :src="msg.payload?.message?.contentUrl || msg.payload?.message?.previewUrl"
                                                                class="img-fluid rounded"
                                                                style="max-width:240px;"
                                                                alt="[Image]"
                                                        >
                                                    </template>

                                                    <!-- VIDEO -->
                                                    <template v-else-if="msg.type === 'video'">
                                                        <video
                                                                controls
                                                                class="img-fluid rounded"
                                                                style="max-width:260px;"
                                                                :poster="msg.payload?.message?.previewUrl"
                                                        >
                                                            <source :src="msg.payload?.message?.contentUrl">
                                                        </video>
                                                    </template>

                                                    <!-- AUDIO -->
                                                    <template v-else-if="msg.type === 'audio'">
                                                        <audio controls :src="msg.payload?.message?.contentUrl"></audio>
                                                    </template>

                                                    <!-- LOCATION -->
                                                    <template
                                                            v-else-if="msg.type === 'location' && msg.payload && msg.payload.message">
                                                        <div>
                                                            <strong>@{{ msg.payload.message.title || 'ตำแหน่ง'
                                                                }}</strong><br>
                                                            @{{ msg.payload.message.address }}
                                                            <br>
                                                            <a
                                                                    :href="'https://maps.google.com/?q=' + msg.payload.message.latitude + ',' + msg.payload.message.longitude"
                                                                    target="_blank"
                                                            >
                                                                เปิดแผนที่
                                                            </a>
                                                        </div>
                                                    </template>

                                                    <!-- UNSUPPORTED -->
                                                    <template v-else>
                                                        [@{{ msg.type }}]
                                                    </template>
                                                </div>

                                                <div class="text-right text-muted small mt-1">
                                                    @{{ formatDateTime(msg.sent_at) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>


                        {{-- REPLY BOX --}}
                        <div class="border-top p-2 bg-white" v-if="selectedConversation">
                            <b-input-group>

                                {{-- ปุ่มแนบรูป --}}
                                <b-input-group-prepend>
                                    <b-button variant="outline-secondary" size="sm"
                                              @click="$refs.imageInput.click()"
                                              :disabled="!canReply">
                                        <i class="fa fa-paperclip"></i>
                                    </b-button>
                                </b-input-group-prepend>

                                {{-- ปุ่มข้อความด่่วน --}}
                                <b-input-group-prepend>
                                    <b-button variant="outline-secondary" size="sm"
                                              @click="openQuickReplyModal"
                                              :disabled="!canReply">
                                        <i class="fas fa-comment-dots"></i>
                                    </b-button>
                                </b-input-group-prepend>

                                <b-form-textarea
                                        ref="replyBox"
                                        v-model="replyText"
                                        rows="1"
                                        max-rows="2"
                                        :placeholder="selectedConversation && selectedConversation.status === 'closed'
                                    ? 'เคสนี้ถูกปิดแล้ว ไม่สามารถส่งข้อความได้'
                                    : 'พิมพ์ข้อความเพื่อตอบลูกค้า แล้วกด Enter หรือปุ่ม ส่ง'"
                                        :disabled="!canReply"
                                        @keydown.enter.exact.prevent="canReply && sendReply()"
                                ></b-form-textarea>

                                <b-input-group-append>
                                    <b-button variant="primary"
                                              :disabled="sending
                                              || replyText.trim() === ''
                                              || !canReply"
                                              @click="sendReply">
                                    <span v-if="sending">
                                        <b-spinner small class="mr-1"></b-spinner> กำลังส่ง...
                                    </span>
                                        <span v-else>
                                        <i class="fa fa-paper-plane"></i> ส่ง
                                    </span>
                                    </b-button>
                                </b-input-group-append>

                            </b-input-group>

                            {{-- input file ซ่อน --}}
                            <input type="file"
                                   ref="imageInput"
                                   class="d-none"
                                   accept="image/*"
                                   @change="onSelectImage">
                        </div>

                    </div>
                </b-col>
            </b-row>

            <!-- Modal เลือก Quick Reply -->
            <b-modal
                    id="quick-reply-modal"
                    ref="quickReplyModal"
                    title="เลือกข้อความด่วน"
                    size="lg"
                    centered
                    :no-close-on-backdrop="true"
                    @hidden="onQuickReplyModalHidden"
            >
                <div v-if="quickRepliesLoading" class="text-center my-4">
                    <b-spinner small></b-spinner>
                    <span class="ml-2">กำลังโหลดข้อความด่วน...</span>
                </div>

                <div v-else>
                    <!-- แถบค้นหา -->
                    <div class="mb-3 d-flex">
                        <b-form-input
                                v-model="quickReplySearch"
                                placeholder="ค้นหาข้อความด่วน..."
                                size="sm"
                        ></b-form-input>
                    </div>

                    <!-- รายการ Quick Reply -->
                    <div
                            v-if="filteredQuickReplies.length"
                            class="list-group"
                            style="max-height: 320px; overflow-y: auto;"
                    >
                        <button
                                v-for="item in filteredQuickReplies"
                                :key="item.id"
                                type="button"
                                class="list-group-item list-group-item-action"
                                :class="{ active: selectedQuickReply && selectedQuickReply.id === item.id }"
                                @click="selectQuickReply(item)"
                        >
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="font-weight-bold">
                                        @{{ item.label }}
                                    </div>
                                    <div class="text-muted">
                                        @{{ item.preview }}
                                    </div>
                                </div>
                                <span
                                        class="badge badge-light"
                                        v-if="item.category"
                                >
                        @{{ item.category }}
                    </span>
                            </div>
                        </button>
                    </div>

                    <div v-else class="text-muted text-center my-4">
                        ไม่พบข้อความด่วนที่ใช้ได้
                    </div>

                    <!-- พรีวิวข้อความที่จะส่ง -->
                    <div v-if="selectedQuickReply" class="mt-3">
                        <h6 class="font-weight-bold">ตัวอย่างข้อความที่จะส่ง</h6>
                        <pre
                                class="border rounded p-2 bg-light"
                                style="white-space: pre-wrap; font-size: 13px;"
                        >@{{ selectedQuickReply.body_preview }}</pre>
                        <small class="text-muted">
                            ข้อความจริงอาจมีรูป / ข้อความหลายบรรทัดตาม template (JSON)
                        </small>
                    </div>
                </div>

                <template #modal-footer>
                    <div class="w-100 d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            เลือกข้อความแล้วกด “ส่งข้อความนี้ให้ลูกค้า”
                        </div>
                        <div>
                            <b-button
                                    variant="outline-secondary"
                                    size="sm"
                                    @click="$refs.quickReplyModal.hide()"
                            >
                                ปิด
                            </b-button>
                            <b-button
                                    variant="success"
                                    size="sm"
                                    class="ml-2"
                                    :disabled="!selectedQuickReply || sendingQuickReply"
                                    @click="sendQuickReply"
                            >
                    <span v-if="sendingQuickReply">
                        <b-spinner small class="mr-1"></b-spinner> กำลังส่ง...
                    </span>
                                <span v-else>
                        ส่งข้อความนี้ให้ลูกค้า
                    </span>
                            </b-button>
                        </div>
                    </div>
                </template>
            </b-modal>


            {{-- MODAL: ผูก contact กับ member --}}
            <b-modal
                    id="line-oa-member-modal"
                    ref="memberModal"
                    title="เชื่อมลูกค้ากับสมาชิก"
                    size="sm"
                    centered
                    hide-footer
                    no-close-on-backdrop
                    lazy
                    body-class="pt-2 pb-2"
                    @hide="resetMemberModal"
                    @shown="onMemberModalShown"
                    @hidden="onMemberModalHidden"
            >
                <b-form @submit.prevent="saveMemberLink">

                    <b-form-group label="" label-for="display_name">
                        <b-input-group>
                            <b-form-input
                                    id="display_name"
                                    ref="displayNameInput"
                                    v-model="memberModal.display_name"
                                    placeholder=""
                                    maxlength="20"
                                    autocomplete="off"
                                    size="sm"
                            ></b-form-input>
                        </b-input-group>
                    </b-form-group>

                    <b-form-group label="" label-for="member_id">
                        <b-input-group>
                            <b-form-input
                                    id="member_id"
                                    ref="memberIdInput"
                                    v-model="memberModal.member_id"
                                    placeholder=""
                                    type="tel"
                                    maxlength="10"
                                    inputmode="number"
                                    autocomplete="off"
                                    size="sm"
                            ></b-form-input>
                            <b-input-group-append>
                                <b-button
                                        variant="secondary"
                                        size="sm"
                                        @click.prevent="searchMember"
                                        :disabled="memberModal.loading || !memberModal.member_id"
                                        class="px-3"
                                >
                                    <b-spinner v-if="memberModal.loading" small class="mr-1"></b-spinner>
                                    <span v-else>ค้นหา</span>
                                </b-button>
                            </b-input-group-append>
                        </b-input-group>
                    </b-form-group>

                    <b-alert
                            v-if="memberModal.error"
                            show
                            variant="danger"
                            class="py-1 mb-2"
                    >
                        @{{ memberModal.error }}
                    </b-alert>

                    <b-card
                            v-if="memberModal.member"
                            class="mb-2"
                            body-class="py-2 px-2"
                    >
                        <div>
                            <div><strong>ชื่อจริง:</strong> @{{ memberModal.member.name || '-' }}</div>
                            <div><strong>Username:</strong> @{{ memberModal.member.username || '-' }}</div>
                            <div><strong>เบอร์:</strong> @{{ memberModal.member.mobile || '-' }}</div>
                        </div>

                    </b-card>

                    <b-card
                            v-else
                            class="mb-2"
                            body-class="py-2 px-2"
                    >
                        <div>
                            <div class="text-center"><strong>ค้นหาข้อมูลสมาชิก ด้วยเบอร์โทร</strong></div>

                        </div>

                    </b-card>

                    <div class="d-flex justify-content-end mt-2">
                        <b-button
                                variant="secondary"
                                size="sm"
                                class="mr-2"
                                @click="$refs.memberModal.hide()"
                        >
                            ยกเลิก
                        </b-button>
                        <b-button
                                variant="primary"
                                size="sm"
                                type="submit"
                                :disabled="memberModal.saving || !memberModal.member"
                        >
                            <b-spinner v-if="memberModal.saving" small class="mr-1"></b-spinner>
                            <span v-else>บันทึก</span>
                        </b-button>
                    </div>
                </b-form>
            </b-modal>

            {{-- MODAL: สมัครสมาชิกแทนลูกค้า --}}
            <b-modal
                    id="line-oa-register-modal"
                    ref="registerModal"
                    title="สมัครสมาชิกแทนลูกค้า"
                    size="sm"
                    centered
                    hide-footer
                    no-close-on-backdrop
                    lazy
                    body-class="pt-2 pb-2"
                    @shown="onRegisterModalShown"
                    @hidden="onRegisterModalHidden"
            >
                <b-form @submit.prevent="submitRegisterByStaff">
                    <b-form-group label="เบอร์โทร" label-for="reg_phone">
                        <b-form-input
                                id="reg_phone"
                                type="tel"
                                ref="registerPhoneInput"
                                pattern="[0-9]*" inputmode="numeric"
                                maxlength="10"
                                v-model="registerModal.phone"
                                autocomplete="off"
                                @input="onPhoneInput"
                        ></b-form-input>
                        <!-- กำลังตรวจสอบเบอร์ -->
                        <small v-if="registerModal.checkingPhone"
                               class="d-block mt-1 text-info">
                            กำลังตรวจสอบเบอร์โทร...
                        </small>

                        <!-- สถานะเบอร์: ถูกต้อง/ซ้ำ/ไม่ถูกต้อง -->
                        <small v-else-if="registerModal.phoneStatusMessage"
                               class="d-block mt-1"
                               :class="phoneStatusClass">
                            @{{ registerModal.phoneStatusMessage }}
                        </small>
                    </b-form-group>


                    <b-form-group label="ธนาคาร" label-for="reg_bank">
                        <b-form-select
                                id="reg_bank"
                                v-model="registerModal.bank_code"
                                :options="bankOptions"
                                @change="onBankChange"
                        ></b-form-select>

                    </b-form-group>

                    <b-form-group label="เลขบัญชี" label-for="reg_account">
                        <b-form-input
                                id="reg_account"
                                pattern="[0-9]*" inputmode="numeric"
                                v-model="registerModal.account_no"
                                autocomplete="off"
                                maxlength="15"
                                @input="onAccountNoInput"
                        ></b-form-input>
                        <!-- กำลังตรวจสอบกับธนาคาร -->
                        <small v-if="registerModal.checkingAccount"
                               class="d-block mt-1 text-info">
                            กำลังตรวจสอบเลขบัญชีกับธนาคาร...
                        </small>

                        <!-- สถานะบัญชี: ใช้ได้/ไม่ถูกต้อง -->
                        <small v-else-if="registerModal.accountStatusMessage"
                               class="d-block mt-1"
                               :class="accountStatusClass">
                            @{{ registerModal.accountStatusMessage }}
                        </small>

                    </b-form-group>

                    <b-form-group label="ชื่อ" label-for="reg_name">
                        <b-form-input
                                id="reg_name"
                                v-model="registerModal.name"
                                autocomplete="off"
                                maxlength="20"
                        ></b-form-input>
                    </b-form-group>

                    <b-form-group label="นามสกุล" label-for="reg_surname">
                        <b-form-input
                                id="reg_surname"
                                v-model="registerModal.surname"
                                autocomplete="off"
                                maxlength="20"
                        ></b-form-input>
                    </b-form-group>

                    <b-alert
                            v-if="registerModal.error"
                            show
                            variant="danger"
                            class="py-1 mb-2"
                    >
                        @{{ registerModal.error }}
                    </b-alert>

                    <div class="text-right">
                        <b-button size="sm" variant="secondary" @click="$refs.registerModal.hide()">
                            ปิด
                        </b-button>
                        <b-button size="sm" variant="primary" class="ml-1" type="submit"
                                  :disabled="registerModal.loading || !canSubmitRegister">
                            <b-spinner v-if="registerModal.loading" small class="mr-1"></b-spinner>
                            <span v-else>สมัคร</span>
                        </b-button>
                    </div>
                </b-form>
            </b-modal>

            {{-- MODAL: เติมเงิน --}}
            <b-modal ref="topupModal" id="line-oa-topup-modal" centered size="xl" title="เพิ่ม รายการฝาก"
                     :no-close-on-backdrop="true" :hide-footer="true" @shown="onTopupModalShown"
                     @hidden="onTopupModalHidden">
                <b-container class="bv-example-row">
                    <b-form @submit.prevent="submitTopup">
                        <b-form-row>
                            <b-col>
                                <div class="row">
                                    <div class="col text-right">
                                        <button type="button" class="btn bg-gradient-primary btn-xs"
                                                @click="openRefillModal"><i
                                                    class="fa fa-plus"></i>
                                            เพิ่มรายการฝาก
                                        </button>
                                    </div>
                                </div>


                                {!! $depositTable->table([
    'id' => 'deposittable',
    'width' => '100%',
    'class' => 'table table-striped table-xs text-xs'
]) !!}
                            </b-col>

                        </b-form-row>


                    </b-form>
                </b-container>
            </b-modal>

            <b-modal
                    id="balance-modal"
                    ref="balanceModal"
                    title="ยอดเงินคงเหลือ"
                    hide-footer
                    centered
                    size="sm"
                    @shown="removeFocusFromTrigger"
                    @hidden="onBalanceModalHidden"
            >
                <div v-if="balanceLoading" class="text-center">
                    กำลังโหลดยอดเงิน...
                </div>

                <div v-else-if="balanceData" class="text-md text-center">
                    <p class="mb-1">
                        User ID :
                        <strong v-text="balanceData.member_username || '-'"></strong>
                    </p>
                    <p class="mb-1">
                        Game ID :
                        <strong v-text="balanceData.member_gameuser || '-'"></strong>
                    </p>
                    <p class="mb-1">
                        ยอดเงินคงเหลือ :
                        <strong v-text="balanceData.balance_text + ' บาท'"></strong>
                    </p>
                    <p class="mb-1">
                        โปรโมชั่นปัจจุบัน :
                        <strong v-text="balanceData.member_pro_name || '-'"></strong>
                    </p>
                    <p class="mb-1">
                        ยอดเทรินทั้งหมด :
                        <strong v-text="balanceData.member_turnover || '-'"></strong>
                    </p>
                    <p class="mb-0">
                        ยอดอั้นถอน :
                        <strong v-text="balanceData.member_limit || '-'"></strong>
                    </p>
                </div>

                <div v-else class="text-muted">
                    ยังไม่มีข้อมูลยอดเงิน
                </div>
            </b-modal>


        </b-container>
    </script>

    <script type="module">
        Vue.component('line-oa-chat', {
            template: '#line-oa-chat-template',
            data() {
                return {
                    conversations: [],
                    pagination: {
                        current_page: 1,
                        last_page: 1,
                        per_page: 20,
                        total: 0,
                    },
                    filters: {
                        status: 'open',
                        q: '',
                        account_id: null,
                        scope: 'all', // 'all' | 'mine'
                    },
                    statusOptions: [
                        {value: 'open', text: 'ทั้งหมด'},
                        // {value: 'open', text: 'ห้องเปิดอยู่'},
                        // {value: 'closed', text: 'ห้องปิดแล้ว'},
                    ],
                    accountOptions: [],
                    bankOptions: [],
                    depositTable: null,
                    currentActiveConversationId: null,
                    loadingList: false,
                    selectedConversation: null,
                    messages: [],
                    loadingMessages: false,
                    replyText: '',
                    sending: false,
                    uploadingImage: false,
                    autoRefreshTimer: null,
                    formatted: '',
                    selected: '',
                    fields: [
                        {key: 'time', label: 'วันที่รายการ'},
                        {key: 'bank', label: 'ช่องทางฝาก', class: 'text-center'},
                        {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                        {key: 'user_id', label: 'ผู้ทำรายการ', class: 'text-center'},
                        {key: 'status', label: 'สถานะ', class: 'text-center'},
                    ],
                    items: [],
                    caption: null,
                    isBusy: false,
                    show: false,
                    userFound: {addedit: false, deposit: false},
                    userTimer: null,

                    submittingSearch: false,
                    submittingAddEdit: false,
                    submittingDeposit: false,
                    submittingClear: false,

                    searchingDeposit: false,
                    searchedDeposit: false,
                    // debounce การค้นหา
                    searchDelayTimer: null,

                    // modal ผูก member
                    memberModal: {
                        member_id: '',
                        display_name: '',
                        member: null,
                        loading: false,
                        saving: false,
                        error: '',
                    },

                    // modal สมัครสมาชิกแทนลูกค้า
                    registerModal: {
                        phone: '',
                        bank_code: '',
                        account_no: '',
                        name: '',
                        surname: '',
                        loading: false,
                        error: '',
                        checkingDuplicate: false, // เช็คซ้ำเบอร์/บัญชี

                        checkingPhone: false,
                        phoneStatus: null,          // 'ok' | 'duplicate' | 'invalid' | null
                        phoneStatusMessage: '',

                        // สถานะการเช็คเลขบัญชี
                        checkingAccount: false,
                        accountStatus: null,        // 'ok' | 'invalid' | 'error' | null
                        accountStatusMessage: '',
                    },
                    balanceLoading: false,
                    balanceData: null,
                    bankAccountCheckTimer: null,
                    // modal เติมเงิน
                    topupModal: {
                        pendingItems: [],
                        selectedItem: null,
                        memberSearch: '',
                        member: null,
                        bank: '',
                        account_code: '',
                        date_bank: '',
                        time_bank: '',
                        amount: null,
                        loading: false,
                        error: '',
                    },
                    banks: [{value: '', text: '== ธนาคาร =='}],
                    // จะ set เป็น function ใน subscribeRealtime()
                    unsubscribeRealtime: null,

                    // ===== Quick Reply state =====
                    quickReplies: [],
                    quickRepliesLoading: false,
                    quickReplySearch: '',
                    quickRepliesLoadedForConvId: null,
                    selectedQuickReply: null,
                    sendingQuickReply: false,
                };
            },
            created() {
                this.fetchConversations(1);
                this.startAutoRefresh();
                this.subscribeRealtime();
                this.fetchBanks();

            },
            beforeDestroy() {
                this.stopAutoRefresh();

                if (this.selectedConversation) {
                    this.unlockConversation(this.selectedConversation);
                }

                if (typeof this.unsubscribeRealtime === 'function') {
                    this.unsubscribeRealtime();
                }
            },
            computed: {
                filteredQuickReplies() {
                    const term = (this.quickReplySearch || '').toLowerCase().trim();
                    if (!term) {
                        return this.quickReplies;
                    }

                    return this.quickReplies.filter(item => {
                        return (
                            (item.label && item.label.toLowerCase().includes(term)) ||
                            (item.preview && item.preview.toLowerCase().includes(term))
                        );
                    });
                },
                currentEmployeeId() {
                    const emp = window.LineOAEmployee || null;
                    if (!emp) return null;

                    if (emp.code) {
                        return String(emp.code);
                    }
                    if (emp.id) {
                        return String(emp.id);
                    }
                    return null;
                },
                canReply() {
                    const conv = this.selectedConversation;
                    if (!conv) return false;

                    // ปิดเคส → ห้ามตอบ
                    if (conv.status === 'closed') return false;

                    // ต้องมีคนรับเรื่องก่อน
                    if (!conv.assigned_employee_id) return true;

                    const me = this.currentEmployeeId;
                    if (!me) return false;

                    // ถ้ามีการล็อกห้อง → ให้เฉพาะคนล็อกตอบได้
                    if (conv.locked_by_employee_id) {
                        return true;
                        // return String(conv.locked_by_employee_id) === String(me);
                    }
                    //
                    // // ถ้าไม่มีการล็อก → ให้เฉพาะผู้รับเรื่องตอบได้
                    return true;
                    // return String(conv.assigned_employee_id) === String(me);
                },
                isTwBank() {
                    const code = String(this.registerModal.bank_code || '').toUpperCase();
                    return code === '18' || code === 'TW';
                },

                phoneStatusClass() {
                    const s = this.registerModal.phoneStatus;
                    if (s === 'ok') return 'text-success';
                    if (s === 'duplicate' || s === 'invalid') return 'text-danger';
                    return '';
                },

                accountStatusClass() {
                    const s = this.registerModal.accountStatus;
                    if (s === 'ok') return 'text-success';
                    if (s === 'invalid' || s === 'error') return 'text-danger';
                    return '';
                },

                // ของเดิมที่คุณมีอยู่แล้ว ปรับให้คิดสถานะด้วย
                canSubmitRegister() {
                    const m = this.registerModal;

                    const phoneDigits = (m.phone || '').replace(/\D/g, '');
                    const accDigits = (m.account_no || '').replace(/\D/g, '');

                    const phoneOk = phoneDigits.length === 10;
                    const bankOk = !!m.bank_code;

                    let accountOkLength = false;
                    if (this.isTwBank) {
                        accountOkLength = accDigits.length === 10;
                    } else {
                        accountOkLength = accDigits.length >= 10;
                    }

                    const nameOk = !!m.name;
                    const snameOk = !!m.surname;

                    const noPendingCheck = !m.checkingPhone && !m.checkingAccount;

                    // ห้ามสมัครถ้าเบอร์ "ซ้ำ" หรือ "ไม่ถูกต้อง"
                    const phoneStatusOk = !['duplicate', 'invalid'].includes(m.phoneStatus);

                    // ห้ามสมัครถ้าบัญชีสถานะ invalid/error
                    const accountStatusOk = !['invalid', 'error'].includes(m.accountStatus);

                    return phoneOk
                        && bankOk
                        && accountOkLength
                        && nameOk
                        && snameOk
                        && noPendingCheck
                        && phoneStatusOk
                        && accountStatusOk;
                },
                getMessageDisplay() {
                    return (msg) => {
                        const lines = {
                            original: msg.text || '',
                            translated: null,
                            lang: null,
                            target: null,
                        };

                        // === inbound (ลูกค้าพิมมา) ===
                        if (msg.direction === 'inbound' &&
                            msg.meta &&
                            msg.meta.translation_inbound
                        ) {
                            const t = msg.meta.translation_inbound;
                            lines.original = t.original_text || msg.text;
                            lines.translated = t.translated_text || null;
                            lines.lang = t.detected_source || t.source_language || null;  // เช่น 'ja'
                        }

                        // === outbound (พนักงานพิม) ===
                        if (msg.direction === 'outbound' &&
                            msg.meta &&
                            msg.meta.translation_outbound
                        ) {
                            const t = msg.meta.translation_outbound;
                            lines.original = t.original_text || msg.text;         // ไทย
                            lines.translated = t.translated_text || null;           // ภาษาเป้าหมาย
                            lines.target = t.target_language || null;           // เช่น 'en'
                        }

                        return lines;
                    };
                },
            },
            methods: {
// ===== Quick Reply =====
                openQuickReplyModal() {
                    if (!this.selectedConversation) {
                        this.showAlert({
                            success: false,
                            message: 'กรุณาเลือกห้องสนทนาก่อน'
                        });
                        return;
                    }

                    if (!this.canReply) {
                        this.showAlert({
                            success: false,
                            message: 'คุณไม่มีสิทธิ์ตอบในห้องสนทนานี้'
                        });
                        return;
                    }

                    // ถ้าเปลี่ยนห้องใหม่ หรือยังไม่เคยโหลดของห้องนี้ → โหลดใหม่
                    if (this.quickRepliesLoadedForConvId !== this.selectedConversation.id) {
                        this.fetchQuickReplies();
                    }

                    this.selectedQuickReply = null;
                    this.quickReplySearch = '';

                    if (this.$refs.quickReplyModal) {
                        this.$refs.quickReplyModal.show();
                    }
                },

                async fetchQuickReplies() {
                    if (!this.selectedConversation) return;

                    this.quickRepliesLoading = true;
                    this.quickReplies = [];
                    this.quickRepliesLoadedForConvId = this.selectedConversation.id;

                    try {
                        const convId = this.selectedConversation.id;

                        // ให้ backend ทำ route: GET /line-oa/conversations/{conversation}/quick-replies
                        const res = await axios.get(
                            this.apiUrl('conversations/' + convId + '/quick-replies')
                        );

                        const body = res.data || {};
                        const items = body.data || body.templates || [];

                        this.quickReplies = items.map(t => {
                            // ปรับ mapping ให้ทนต่อชื่อ field ต่าง ๆ ของ backend
                            const label =
                                t.label ||
                                t.title ||
                                t.name ||
                                t.key ||
                                ('Template #' + t.id);

                            const preview =
                                t.preview ||
                                t.preview_text ||
                                t.body_preview ||
                                t.text_preview ||
                                '';

                            const bodyPreview =
                                t.body_preview ||
                                t.body ||
                                t.text ||
                                preview ||
                                '';

                            return {
                                id: t.id,
                                label: label,
                                preview: preview,
                                body_preview: bodyPreview,
                                category: t.category || null,
                            };
                        });
                    } catch (e) {
                        console.error('[LineOA] fetchQuickReplies error', e);
                        this.showAlert({
                            success: false,
                            message: 'โหลดข้อความด่วนไม่สำเร็จ กรุณาลองใหม่'
                        });
                    } finally {
                        this.quickRepliesLoading = false;
                    }
                },

                selectQuickReply(item) {
                    this.selectedQuickReply = item || null;
                },

                async sendQuickReply() {
                    if (!this.selectedConversation || !this.selectedQuickReply) return;

                    if (!this.canReply) {
                        this.showAlert({
                            success: false,
                            message: 'คุณไม่มีสิทธิ์ตอบในห้องสนทนานี้'
                        });
                        return;
                    }

                    if (this.sendingQuickReply) return;
                    this.sendingQuickReply = true;

                    const convId = this.selectedConversation.id;

                    try {
                        // vars สำหรับแทน placeholder ใน template เช่น {display_name}, {username}
                        const vars = {
                            display_name:
                                (this.selectedConversation.contact &&
                                    this.selectedConversation.contact.display_name) ||
                                this.selectedConversation.contact_display_name ||
                                '',
                            username:
                                (this.selectedConversation.contact &&
                                    this.selectedConversation.contact.member_username) ||
                                this.selectedConversation.contact_member_username ||
                                '',
                        };

                        // ให้ backend ทำ route: POST /line-oa/conversations/{conversation}/reply-template
                        const res = await axios.post(
                            this.apiUrl('conversations/' + convId + '/reply-template'),
                            {
                                template_id: this.selectedQuickReply.id,
                                vars: vars,
                            }
                        );

                        const body = res.data || {};
                        const msg = body.data || body.message || null;

                        if (msg) {
                            // ใส่ message ลงในห้องแชต

                            this.messages.push(msg);

                            if (this.selectedConversation) {
                                this.selectedConversation.last_message = msg.text || this.selectedConversation.last_message;
                                this.selectedConversation.last_message_at = msg.sent_at || this.selectedConversation.last_message_at;
                                this.selectedConversation.unread_count = 0;
                            }

                            const idx = this.conversations.findIndex(c => c.id === this.selectedConversation.id);
                            if (idx !== -1) {
                                const conv = this.conversations[idx];
                                const updated = Object.assign({}, conv, {
                                    last_message: this.selectedConversation.last_message,
                                    last_message_at: this.selectedConversation.last_message_at,
                                    unread_count: 0,
                                });
                                this.$set(this.conversations, idx, updated);
                            }


                            if (this.$refs.quickReplyModal) {
                                this.$refs.quickReplyModal.hide();
                            }

                            this.$nextTick(() => {
                                this.scrollToBottom();
                            });
                        } else {
                            this.showAlert({
                                success: false,
                                message: 'ส่งข้อความด่วนไม่สำเร็จ (ไม่พบข้อมูลข้อความจากเซิร์ฟเวอร์)'
                            });
                        }
                    } catch (e) {
                        console.error('[LineOA] sendQuickReply error', e);

                        const msg =
                            e?.response?.data?.message ??
                            e?.response?.data?.msg ??
                            e?.response?.data?.error ??
                            'ส่งข้อความด่วนไม่สำเร็จ กรุณาลองใหม่';

                        this.showAlert({
                            success: false,
                            message: msg
                        });
                    } finally {
                        this.sendingQuickReply = false;
                    }
                },
                onProfileImageError(event) {
                    event.target.src = window.LineDefaultAvatar;
                    event.target.onerror = null; // กัน loop error
                },
                removeFocusFromTrigger() {
                    // ลอง blur องค์ประกอบที่กำลัง focus อยู่ตอนนี้
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }
                },
                onTopupModalShown() {
                    this.$nextTick(() => {
                        // ถ้า init แล้ว → แค่ reload
                        if (this.depositTable) {
                            if (this.depositTable.ajax && typeof this.depositTable.ajax.reload === 'function') {
                                this.depositTable.ajax.reload(null, false);
                            }
                            return;
                        }

                        window.LaravelDataTables = window.LaravelDataTables || {};
                        window.LaravelDataTables["deposittable"] = $("#deposittable").DataTable({
                            "serverSide": true,
                            "processing": true,
                            "ajax": {
                                "url": "https:\/\/demo.168csn.com\/bank_in",
                                "type": "GET",
                                "data": function (data) {
                                    for (var i = 0, len = data.columns.length; i < len; i++) {
                                        if (!data.columns[i].search.value) delete data.columns[i].search;
                                        if (data.columns[i].searchable === true) delete data.columns[i].searchable;
                                        if (data.columns[i].orderable === true) delete data.columns[i].orderable;
                                        if (data.columns[i].data === data.columns[i].name) delete data.columns[i].name;
                                    }
                                    delete data.search.regex;
                                }
                            },
                            "columns": [{
                                "name": "bank_payment.code",
                                "data": "code",
                                "title": "#",
                                "orderable": true,
                                "searchable": true,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "bankcode",
                                "data": "bankcode",
                                "title": "\u0e18\u0e19\u0e32\u0e04\u0e32\u0e23",
                                "orderable": false,
                                "searchable": false,
                                "className": "text-left text-nowrap"
                            }, {
                                "name": "bank_account.acc_no",
                                "data": "acc_no",
                                "title": "\u0e40\u0e25\u0e02\u0e1a\u0e31\u0e0d\u0e0a\u0e35",
                                "orderable": false,
                                "searchable": true,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "bank_payment.bank_time",
                                "data": "bank_time",
                                "title": "\u0e40\u0e27\u0e25\u0e32\u0e18\u0e19\u0e32\u0e04\u0e32\u0e23",
                                "orderable": false,
                                "searchable": true,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "bank_payment.channel",
                                "data": "channel",
                                "title": "\u0e0a\u0e48\u0e2d\u0e07\u0e17\u0e32\u0e07",
                                "orderable": false,
                                "searchable": true,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "bank_payment.detail",
                                "data": "detail",
                                "title": "\u0e23\u0e32\u0e22\u0e25\u0e30\u0e40\u0e2d\u0e35\u0e22\u0e14",
                                "orderable": false,
                                "searchable": true,
                                "className": "text-left text-nowrap"
                            }, {
                                "name": "bank_payment.value",
                                "data": "value",
                                "title": "\u0e08\u0e33\u0e19\u0e27\u0e19\u0e40\u0e07\u0e34\u0e19",
                                "orderable": false,
                                "searchable": true,
                                "className": "text-right text-nowrap"
                            }, {
                                "name": "bank_payment.user_name",
                                "data": "user_name",
                                "title": "User ID",
                                "orderable": false,
                                "searchable": true,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "bank_payment.date_update",
                                "data": "date",
                                "title": "\u0e40\u0e27\u0e25\u0e32\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a",
                                "orderable": false,
                                "searchable": true,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "confirm",
                                "data": "confirm",
                                "title": "\u0e40\u0e15\u0e34\u0e21\u0e40\u0e07\u0e34\u0e19",
                                "orderable": false,
                                "searchable": false,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "cancel",
                                "data": "cancel",
                                "title": "\u0e1b\u0e0f\u0e34\u0e40\u0e2a\u0e18",
                                "orderable": false,
                                "searchable": false,
                                "className": "text-center text-nowrap"
                            }, {
                                "name": "delete",
                                "data": "delete",
                                "title": "\u0e25\u0e1a",
                                "orderable": false,
                                "searchable": false,
                                "className": "text-center text-nowrap"
                            }],
                            "dom": "Bfrtip",
                            "responsive": false,
                            "stateSave": true,
                            "scrollX": true,
                            "paging": false,
                            "searching": false,
                            "deferRender": true,
                            "retrieve": true,
                            "ordering": true,
                            "pageLength": 50,
                            "order": [[0, "desc"]],
                            "lengthMenu": [[50, 100, 200, 500, 1000], ["50 rows", "100 rows", "200 rows", "500 rows", "1000 rows"]],
                            "buttons": [],
                            "columnDefs": [{"targets": "_all", "className": "text-center text-nowrap"}]
                        });

                        {{--const $table = $('#deposittable');--}}

                        {{--this.depositTable = $table.DataTable({--}}
                        {{--    processing: true,--}}
                        {{--    serverSide: true,--}}
                        {{--    ajax: {--}}
                        {{--        url: '{{ route('admin.bank_in.index') }}',--}}
                        {{--        type: 'GET'--}}
                        {{--    },--}}
                        {{--    paging: false,--}}
                        {{--    searching: false,--}}
                        {{--    ordering: true,--}}
                        {{--    scrollX: true,--}}
                        {{--    stateSave: true,--}}

                        {{--    // ✨ สำคัญ: map ให้ตรงกับ getColumns()--}}
                        {{--    columns: [--}}
                        {{--        { data: 'code',      name: 'bank_payment.code' },--}}
                        {{--        { data: 'bankcode',  name: 'bankcode' },--}}
                        {{--        { data: 'acc_no',    name: 'bank_account.acc_no' },--}}
                        {{--        { data: 'bank_time', name: 'bank_payment.bank_time' },--}}
                        {{--        { data: 'channel',   name: 'bank_payment.channel' },--}}
                        {{--        { data: 'detail',    name: 'bank_payment.detail' },--}}
                        {{--        { data: 'value',     name: 'bank_payment.value' },--}}
                        {{--        { data: 'user_name', name: 'bank_payment.user_name' },--}}
                        {{--        { data: 'date',      name: 'bank_payment.date_update' },--}}
                        {{--        { data: 'confirm',   name: 'confirm', searchable: false, orderable: false },--}}
                        {{--        { data: 'cancel',    name: 'cancel',  searchable: false, orderable: false },--}}
                        {{--        { data: 'delete',    name: 'delete',  searchable: false, orderable: false },--}}
                        {{--    ],--}}

                        {{--    // กัน column not found กลายเป็น error แดง ๆ--}}
                        {{--    columnDefs: [--}}
                        {{--        { targets: '_all', defaultContent: '' , className: 'text-nowrap' }--}}
                        {{--    ],--}}

                        {{--    order: [[0, 'desc']]--}}
                        {{--});--}}

                        {{--// ให้สอดคล้องกับ pattern เดิมของนาย--}}
                        {{--window.LaravelDataTables = window.LaravelDataTables || {};--}}
                        {{--window.LaravelDataTables['deposittable'] = this.depositTable;--}}
                    });
                },


                // ถ้าอยากมีปุ่ม reload เฉพาะ ก็เขียนแยกอีกเมธอดได้
                reloadDepositTable() {
                    if (this.depositTable && this.depositTable.ajax && typeof this.depositTable.ajax.reload === 'function') {
                        this.depositTable.ajax.reload(null, false);
                    }
                },
                onContext(ctx) {
                    this.formatted = ctx.selectedFormatted || '';
                    this.selected = ctx.selectedYMD || '';
                },
                apiUrl(path) {
                    return '/line-oa/' + path.replace(/^\/+/, '');
                },
                async fetchBanks() {
                    try {
                        const {data} = await axios.get(this.apiUrl('register/load-bank')); // route backend

                        this.bankOptions = data.bank;
                    } catch (e) {
                        console.error('โหลดรายการธนาคารไม่สำเร็จ', e);
                    }
                },
                onBankChange() {
                    // รีเซ็ตค่าที่เกี่ยวกับเลขบัญชี/การเช็ค
                    this.registerModal.account_no = '';
                    this.registerModal.checkingDuplicate = false;
                    this.registerModal.checkingAccount = false;
                    this.registerModal.error = null;

                    if (this.registerModal.bank_code == '18') {
                        this.registerModal.account_no = this.registerModal.phone;
                    }

                    if (this.bankAccountCheckTimer) {
                        clearTimeout(this.bankAccountCheckTimer);
                    }
                },
                onPhoneInput() {
                    // reset state ทุกครั้งที่พิมพ์
                    this.registerModal.error = null;
                    this.registerModal.phoneStatus = null;
                    this.registerModal.phoneStatusMessage = '';

                    let digits = (this.registerModal.phone || '').replace(/\D/g, '');
                    if (digits.length > 10) {
                        digits = digits.substring(0, 10);
                    }
                    this.registerModal.phone = digits; // บังคับให้เป็นตัวเลขล้วน

                    if (digits.length === 10) {
                        this.checkPhoneStatus(digits);
                    }
                },
                async checkPhoneStatus(phoneDigits) {
                    this.registerModal.checkingPhone = true;
                    this.registerModal.phoneStatus = null;
                    this.registerModal.phoneStatusMessage = '';

                    try {
                        // route นี้ให้ชี้ไปที่ ChatController::checkPhone
                        const {data} = await axios.post(this.apiUrl('register/check-phone'), {
                            phone: phoneDigits,
                        });

                        if (data.message !== 'success') {
                            this.registerModal.phoneStatus = 'invalid';
                            this.registerModal.phoneStatusMessage =
                                data.message || 'เบอร์โทรไม่ถูกต้อง';
                            return;
                        }

                        if (data.bank === true) {
                            this.registerModal.phoneStatus = 'duplicate';
                            this.registerModal.phoneStatusMessage = 'เบอร์นี้สมัครสมาชิกแล้วในระบบ';
                        } else {
                            this.registerModal.phoneStatus = 'ok';
                            this.registerModal.phoneStatusMessage = 'สามารถใช้เบอร์นี้สมัครสมาชิกได้';
                        }
                    } catch (e) {
                        console.error('checkPhoneStatus error', e);
                        this.registerModal.phoneStatus = 'error';
                        this.registerModal.phoneStatusMessage = 'ตรวจสอบเบอร์ไม่สำเร็จ กรุณาลองใหม่';
                        this.registerModal.error = 'ตรวจสอบเบอร์ไม่สำเร็จ กรุณาลองใหม่';
                    } finally {
                        this.registerModal.checkingPhone = false;
                    }
                },
                async checkPhoneDuplicate(phoneDigits) {
                    try {
                        this.registerModal.checkingDuplicate = true;

                        const {data} = await axios.post(this.apiUrl('register/check-phone'), {
                            phone: phoneDigits,
                        });

                        if (data.bank) {
                            this.registerModal.error = 'เบอร์นี้สมัครสมาชิกแล้ว';
                        }
                    } catch (e) {
                        console.error('เช็คเบอร์ซ้ำไม่สำเร็จ', e);
                        this.registerModal.error = 'ไม่สามารถตรวจสอบเบอร์ได้ กรุณาลองใหม่';
                    } finally {
                        this.registerModal.checkingDuplicate = false;
                    }
                },
                onAccountNoInput() {
                    this.registerModal.error = null;
                    this.registerModal.accountStatus = null;
                    this.registerModal.accountStatusMessage = '';

                    const accDigits = (this.registerModal.account_no || '').replace(/\D/g, '');
                    this.registerModal.account_no = accDigits;

                    if (this.bankAccountCheckTimer) {
                        clearTimeout(this.bankAccountCheckTimer);
                    }

                    if (accDigits.length >= 10) {
                        this.bankAccountCheckTimer = setTimeout(() => {
                            this.checkBankAccount(accDigits);
                        }, 400);
                    }
                },
                async checkBankAccount(accDigits) {
                    this.registerModal.checkingAccount = true;
                    this.registerModal.accountStatus = null;
                    this.registerModal.accountStatusMessage = '';

                    try {
                        const {data} = await axios.post(this.apiUrl('register/check-bank'), {
                            bank_code: this.registerModal.bank_code,
                            account_no: accDigits,
                        });

                        if (data.success) {
                            // autofill ชื่อ–นามสกุล ถ้ามี
                            if (data.firstname) {
                                this.registerModal.name = data.firstname;
                            }
                            if (data.lastname) {
                                this.registerModal.surname = data.lastname;
                            }

                            this.registerModal.accountStatus = 'ok';
                            this.registerModal.accountStatusMessage =
                                'ตรวจสอบเลขบัญชีกับธนาคารเรียบร้อย';
                        } else {
                            this.registerModal.accountStatus = 'invalid';
                            this.registerModal.accountStatusMessage =
                                data.message || 'ไม่พบข้อมูลบัญชี';
                        }
                    } catch (e) {
                        console.error('checkBankAccount error', e);
                        this.registerModal.accountStatus = 'error';
                        this.registerModal.accountStatusMessage =
                            'ไม่สามารถตรวจสอบเลขบัญชีได้';
                        this.registerModal.error = 'ไม่สามารถตรวจสอบเลขบัญชีได้';
                    } finally {
                        this.registerModal.checkingAccount = false;
                    }
                },
                canControlRegister() {
                    const conv = this.selectedConversation;
                    if (!conv) return false;

                    // ต้องเป็นห้องที่รับเรื่องแล้วเท่านั้น
                    // if (conv.status !== 'assigned') return false;

                    // ต้องมีคนรับเรื่อง (assigned_employee_id)
                    // if (!conv.assigned_employee_id) return false;

                    // if (!this.currentEmployeeId) return false;

                    // อนุญาตเฉพาะคนที่เป็นคนรับเรื่อง
                    return true;
                    // return String(conv.assigned_employee_id) === String(this.currentEmployeeId);
                },
                /**
                 * โหลดรายการห้องแชต
                 * options.silent = true จะไม่โชว์ spinner (ใช้กับ auto-refresh)
                 */

                fetchConversations(page = 1, options = {}) {
                    const silent = options.silent === true;
                    const merge = options.merge === true; // merge หรือ replace list

                    if (!silent) {
                        this.loadingList = true;
                    }

                    return axios.get(this.apiUrl('conversations'), {
                        params: {
                            page: page,
                            status: this.filters.status,
                            q: this.filters.q,
                            account_id: this.filters.account_id,
                            scope: this.filters.scope, // ให้ backend ใช้ filter ได้
                        }
                    }).then(res => {
                        const body = res.data || {};
                        const newList = body.data || [];

                        // อัปเดต pagination
                        this.pagination = Object.assign(this.pagination, body.meta || {});

                        // ===== จัดการ conversations =====
                        if (merge && Array.isArray(this.conversations) && this.conversations.length > 0) {
                            const oldById = {};
                            this.conversations.forEach(conv => {
                                if (conv && conv.id != null) {
                                    oldById[conv.id] = conv;
                                }
                            });

                            const mergedList = newList.map(item => {
                                if (!item || item.id == null) {
                                    return item;
                                }
                                const old = oldById[item.id];
                                return old
                                    ? Object.assign({}, old, item)
                                    : item;
                            });

                            this.conversations = mergedList;
                        } else {
                            this.conversations = newList;
                        }

                        // สร้าง accountOptions จาก list ปัจจุบัน
                        if (this.filters.account_id === null) {
                            const accounts = {};
                            this.conversations.forEach(conv => {
                                if (conv.line_account && conv.line_account.id) {
                                    accounts[conv.line_account.id] =
                                        conv.line_account.name || ('OA #' + conv.line_account.id);
                                }
                            });
                            this.accountOptions = Object.keys(accounts).map(id => ({
                                value: parseInt(id, 10),
                                text: accounts[id],
                            }));
                        }

                    }).catch(err => {
                        console.error('fetchConversations error', err);
                    }).finally(() => {
                        if (!silent) {
                            this.loadingList = false;
                        }
                    });
                },
                // ใช้สำหรับกรณี backend ต้องดึง content เอง (ตอนนี้ template ใช้ payload อยู่แล้ว)
                imageUrl(msg) {
                    const payloadMsg = msg.payload && msg.payload.message ? msg.payload.message : null;

                    if (payloadMsg) {
                        if (payloadMsg.contentUrl) {
                            return payloadMsg.contentUrl;
                        }
                        if (payloadMsg.previewUrl) {
                            return payloadMsg.previewUrl;
                        }
                    }

                    return this.apiUrl('messages/' + msg.id + '/content');
                },

                selectConversation(conv, options = {}) {
                    if (!conv) return;

                    const reloadMessages = options.reloadMessages !== false; // default = true
                    const previousId = this.currentActiveConversationId;

                    this.currentActiveConversationId = conv.id;
                    this.selectedConversation = conv;

                    if (!reloadMessages) {
                        this.$nextTick(() => {
                            this.scrollToBottom();
                            this.autoFocusRef('replyBox');
                        });
                        return;
                    }

                    this.fetchMessages(conv.id, {limit: 50, previous_id: previousId}).then(() => {
                        this.$nextTick(() => {
                            this.scrollToBottom();
                            this.autoFocusRef('replyBox');
                        });
                    });
                },
                autoFocusRef(refName) {
                    this.$nextTick(() => {
                        const r = this.$refs[refName];
                        if (!r) return;

                        if (typeof r.focus === 'function') {
                            try {
                                r.focus();
                                return;
                            } catch (_) {
                            }
                        }

                        const el =
                            r.$el?.querySelector?.('input,textarea') ||
                            (r instanceof HTMLElement ? r : null);

                        el?.focus?.();
                    });
                },

                fetchMessages(conversationId, options = {}) {
                    if (!conversationId) return Promise.resolve();

                    const silent = options.silent === true;
                    const isLoadOlder = !!options.before_id;

                    if (!silent) {
                        this.loadingMessages = true;
                    }

                    const params = {
                        limit: options.limit || 50,
                    };

                    if (options.before_id) {
                        params.before_id = options.before_id;
                    }

                    if (options.previous_id) {
                        params.previous_id = options.previous_id;
                    }

                    let prevScrollHeight = null;
                    let prevScrollTop = null;
                    const containerEl = this.$refs.messageContainer;

                    if (isLoadOlder && containerEl) {
                        prevScrollHeight = containerEl.scrollHeight;
                        prevScrollTop = containerEl.scrollTop;
                    }

                    return axios.get(this.apiUrl('conversations/' + conversationId), {params})
                        .then(res => {
                            const body = res.data || {};
                            const messages = body.messages || [];
                            const convFromServer = body.conversation || null;

                            if (isLoadOlder) {
                                this.messages = messages.concat(this.messages || []);
                            } else {
                                this.messages = messages;
                            }

                            if (convFromServer) {
                                if (this.selectedConversation && this.selectedConversation.id === convFromServer.id) {
                                    this.selectedConversation = Object.assign(
                                        {},
                                        this.selectedConversation,
                                        convFromServer
                                    );
                                } else if (!this.selectedConversation || this.selectedConversation.id === conversationId) {
                                    this.selectedConversation = convFromServer;
                                }
                            }

                            if (!isLoadOlder &&
                                this.selectedConversation &&
                                this.selectedConversation.id === conversationId
                            ) {
                                this.selectedConversation.unread_count = 0;

                                const idx = this.conversations.findIndex(c => c.id === conversationId);
                                if (idx !== -1) {
                                    const updated = Object.assign({}, this.conversations[idx], {
                                        unread_count: 0,
                                    });
                                    this.$set(this.conversations, idx, updated);
                                }
                            }

                            this.$nextTick(() => {
                                if (isLoadOlder && containerEl && prevScrollHeight !== null && prevScrollTop !== null) {
                                    const newScrollHeight = containerEl.scrollHeight;
                                    containerEl.scrollTop = newScrollHeight - prevScrollHeight + prevScrollTop;
                                    return;
                                }

                                if (!silent) {
                                    this.scrollToBottom();
                                }
                            });
                        })
                        .catch(err => {
                            console.error('fetchMessages error', err);
                        })
                        .finally(() => {
                            if (!silent) {
                                this.loadingMessages = false;
                            }
                        });
                },
                sendReply() {
                    if (!this.selectedConversation || this.sending) return;

                    if (!this.canReply) {
                        const msg = 'ห้องนี้ยังไม่ได้รับเรื่อง หรือคุณไม่ได้เป็นผู้รับเรื่อง ไม่สามารถตอบลูกค้าได้';
                        this.showAlert({
                            success: false,
                            message: msg
                        });

                        return;
                    }

                    const text = this.replyText.trim();
                    if (text === '') return;

                    this.sending = true;

                    axios.post(this.apiUrl('conversations/' + this.selectedConversation.id + '/reply'), {
                        text: text,
                    }).then(res => {
                        const msg = res.data && res.data.data ? res.data.data : null;

                        if (msg) {
                            this.messages.push(msg);

                            if (this.selectedConversation) {
                                this.selectedConversation.last_message = msg.text || this.selectedConversation.last_message;
                                this.selectedConversation.last_message_at = msg.sent_at || this.selectedConversation.last_message_at;
                                this.selectedConversation.unread_count = 0;
                            }

                            const idx = this.conversations.findIndex(c => c.id === this.selectedConversation.id);
                            if (idx !== -1) {
                                const conv = this.conversations[idx];
                                const updated = Object.assign({}, conv, {
                                    last_message: this.selectedConversation.last_message,
                                    last_message_at: this.selectedConversation.last_message_at,
                                    unread_count: 0,
                                });
                                this.$set(this.conversations, idx, updated);
                            }
                        }

                        this.replyText = '';

                        this.$nextTick(() => {
                            this.scrollToBottom();
                        });

                    }).catch(err => {
                        const status = err.response?.status;
                        const data = err.response?.data || {};

                        if (status === 403) {

                            // alert(data.message || 'ไม่สามารถตอบห้องนี้ได้ เนื่องจากถูกล็อกโดยพนักงานคนอื่น');
                            const msg = data.message || 'ไม่สามารถตอบห้องนี้ได้ เนื่องจากถูกล็อกโดยพนักงานคนอื่น';
                            this.showAlert({
                                success: false,
                                message: msg
                            });
                            return;
                        }
                        console.error('sendReply error', err);
                        const msg = 'ส่งข้อความไม่สำเร็จ กรุณาลองใหม่';
                        this.showAlert({
                            success: false,
                            message: msg
                        });
                        // alert('ส่งข้อความไม่สำเร็จ กรุณาลองใหม่');
                    }).finally(() => {
                        this.sending = false;
                    });
                },

                scrollToBottom() {
                    const el = this.$refs.messageContainer;
                    if (!el) return;
                    el.scrollTop = el.scrollHeight;
                },

                formatDateTime(dt) {
                    if (!dt) return '';
                    const d = new Date(dt);
                    if (isNaN(d.getTime())) {
                        return dt;
                    }
                    const pad = n => String(n).padStart(2, '0');
                    return d.getFullYear() + '-' +
                        pad(d.getMonth() + 1) + '-' +
                        pad(d.getDate()) + ' ' +
                        pad(d.getHours()) + ':' +
                        pad(d.getMinutes()) + ':' +
                        pad(d.getSeconds());
                },

                messageWrapperClass(msg) {
                    if (msg.direction === 'outbound') {
                        return 'd-flex justify-content-end';
                    }
                    return 'd-flex justify-content-start';
                },

                messageBubbleClass(msg) {
                    let base = 'p-2 rounded mb-1';
                    if (msg.direction === 'outbound') {
                        return base + ' gt-msg-agent';
                    }
                    if (msg.source === 'bot') {
                        return base + ' bg-warning';
                    }
                    return base + ' bg-light';
                },

                conversationItemClass(conv) {
                    const classes = ['py-2'];
                    if (this.selectedConversation && this.selectedConversation.id === conv.id) {
                        classes.push('gt-conv-active');
                    }
                    return classes;
                },

                startAutoRefresh() {
                    this.stopAutoRefresh();
                    this.autoRefreshTimer = setInterval(() => {
                        this.fetchConversations(this.pagination.current_page || 1, {silent: true, merge: true});
                        if (this.selectedConversation) {
                            this.fetchMessages(this.selectedConversation.id, {limit: 50, silent: true});
                        }
                    }, 600000); // ตอนนี้มี realtime แล้ว ใช้ sync ระยะยาว
                },

                stopAutoRefresh() {
                    if (this.autoRefreshTimer) {
                        clearInterval(this.autoRefreshTimer);
                        this.autoRefreshTimer = null;
                    }
                },
                updateOrInsertConversation(conv) {
                    if (!conv || !conv.id) return;

                    const id = conv.id;

                    const idx = this.conversations.findIndex(c => c.id === id);

                    if (idx !== -1) {
                        this.$set(this.conversations, idx, {
                            ...this.conversations[idx],
                            ...conv
                        });
                    } else {
                        this.conversations.unshift(conv);
                    }
                },
                // auto-search: debounce ตอนพิมพ์ค้นหา
                onSearchInput() {
                    if (this.searchDelayTimer) {
                        clearTimeout(this.searchDelayTimer);
                    }
                    this.searchDelayTimer = setTimeout(() => {
                        this.fetchConversations(1, {silent: true, merge: false});
                    }, 500);
                },

                onSelectImage(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    this.$refs.imageInput.value = '';

                    if (!file.type.startsWith('image/')) {

                        const msg = 'กรุณาเลือกไฟล์รูปภาพเท่านั้น';
                        this.showAlert({
                            success: false,
                            message: msg
                        });

                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        const msg = 'ไฟล์ใหญ่เกินไป สูงสุด 5MB';

                        this.showAlert({
                            success: false,
                            message: msg
                        });

                        return;
                    }

                    this.sendImage(file);
                },

                sendImage(file) {
                    if (!this.selectedConversation || this.uploadingImage) return;

                    if (!this.canReply) {
                        alert('ห้องนี้ยังไม่ได้รับเรื่อง หรือคุณไม่ได้เป็นผู้รับเรื่อง ไม่สามารถตอบลูกค้าได้');
                        return;
                    }

                    const convId = this.selectedConversation.id;
                    this.uploadingImage = true;

                    const form = new FormData();
                    form.append('image', file);

                    axios.post(this.apiUrl('conversations/' + convId + '/reply-image'), form, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }).then(res => {
                        const msg = res.data && res.data.data ? res.data.data : null;
                        if (msg) {
                            this.messages.push(msg);

                            if (this.selectedConversation) {
                                this.selectedConversation.last_message =
                                    this.buildPreviewFromMessage(msg) || this.selectedConversation.last_message;
                                this.selectedConversation.last_message_at = msg.sent_at || this.selectedConversation.last_message_at;
                                this.selectedConversation.unread_count = 0;
                            }

                            const idx = this.conversations.findIndex(c => c.id === convId);
                            if (idx !== -1) {
                                const conv = this.conversations[idx];
                                const updated = Object.assign({}, conv, {
                                    last_message: this.selectedConversation.last_message,
                                    last_message_at: this.selectedConversation.last_message_at,
                                    unread_count: 0,
                                });
                                this.$set(this.conversations, idx, updated);
                            }

                            this.$nextTick(() => this.scrollToBottom());
                        }
                    }).catch(err => {
                        console.error('sendImage error', err);

                        const msg =
                            err?.response?.data?.message ??
                            err?.response?.data?.msg ??
                            err?.response?.data?.error ??
                            'ส่งรูปไม่สำเร็จ กรุณาลองใหม่';

                        this.showAlert({
                            success: false,
                            message: msg
                        });
                    }).finally(() => {
                        this.uploadingImage = false;
                    });
                },

                // ====== สร้าง preview จาก message เวลา event ไม่ส่ง last_message มา ======
                buildPreviewFromMessage(msg) {
                    if (!msg) return '';
                    if (msg.type === 'text' && msg.text) {
                        const text = msg.text;
                        return text.length > 50 ? text.substr(0, 45) + '...' : text;
                    }
                    return '[' + (msg.type || 'message') + ']';
                },

                // ====== URL สติ๊กเกอร์ LINE ======
                stickerUrl(msg) {
                    if (!msg || !msg.payload || !msg.payload.message) return null;

                    const pkg = msg.payload.message.packageId;
                    const sid = msg.payload.message.stickerId;
                    const type = msg.payload.message.stickerResourceType || 'STATIC';

                    if (!pkg || !sid) return null;

                    if (type === 'STATIC') {
                        return `https://stickershop.line-scdn.net/stickershop/v1/sticker/${sid}/android/sticker.png`;
                    }

                    if (type === 'ANIMATION' || type === 'ANIMATION_SOUND') {
                        return `https://stickershop.line-scdn.net/stickershop/v1/sticker/${sid}/android/sticker_animation.png`;
                    }

                    if (type === 'POPUP') {
                        return `https://stickershop.line-scdn.net/stickershop/v1/sticker/${sid}/android/sticker_popup.png`;
                    }

                    return `https://stickershop.line-scdn.net/stickershop/v1/sticker/${sid}/android/sticker.png`;
                },
                playNewMessageSound() {
                    const audio = document.getElementById('line-noti-audio');
                    if (!audio) return;
                    audio.muted = false;
                    audio.currentTime = 0;

                    const playSound = () => {
                        audio.currentTime = 0;
                        audio.play().catch(() => {
                        });
                    };

                    playSound();

                },
                // ====== Realtime จาก Echo ======
                subscribeRealtime() {
                    if (!window.Echo || !window.LineOAEventsChannel) return;

                    const channelName = window.LineOAEventsChannel;
                    const vm = this;

                    console.log('[LineOA] subscribeRealtime to', channelName);

                    window.Echo.channel(channelName)
                        .listen('.LineOAChatMessageReceived', (e) => {
                            console.log('[LineOA] รับ event จาก websocket:', e);
                            vm.handleRealtimeIncoming(e);
                            if (e.message && e.message.direction === 'inbound') {
                                vm.playNewMessageSound();
                            }
                        })
                        .listen('.LineOAChatConversationUpdated', (e) => {
                            const conv = e.conversation || {};
                            if (!conv || !conv.id) {
                                return;
                            }

                            const isActive =
                                this.selectedConversation &&
                                this.selectedConversation.id === conv.id;

                            if (isActive) {
                                conv.unread_count = 0;
                            }

                            this.updateOrInsertConversation(conv);

                            if (isActive) {
                                this.selectedConversation = Object.assign(
                                    {},
                                    this.selectedConversation,
                                    conv
                                );
                            }
                        })
                        .listen('.LineOAConversationAssigned', (e) => {
                            vm.handleConversationAssigned(e);
                        })
                        .listen('.LineOAConversationClosed', (e) => {
                            vm.handleConversationClosed(e);
                        })
                        .listen('.LineOAConversationOpen', (e) => {
                            vm.handleConversationOpen(e);
                        })
                        .listen('.LineOAConversationLocked', (e) => {
                            vm.handleConversationLocked(e);
                        });

                    console.log('[LineOA] subscribeRealtime ตั้งค่าเรียบร้อย');

                    this.unsubscribeRealtime = () => {
                        try {
                            window.Echo.leaveChannel(channelName);
                        } catch (err) {
                            // เงียบไว้
                        }
                    };
                },

                handleRealtimeIncoming(e) {
                    if (!e || !e.conversation_id || !e.message) {
                        return;
                    }

                    const convId = e.conversation_id;
                    const newMsg = e.message;
                    const newConvRaw = e.conversation || {};

                    const idx = this.conversations.findIndex(c => c.id === convId);
                    const existing = idx !== -1 ? this.conversations[idx] : null;

                    const isActive = this.selectedConversation && this.selectedConversation.id === convId;

                    const lastMessage =
                        newConvRaw.last_message ??
                        newConvRaw.last_message_preview ??
                        this.buildPreviewFromMessage(newMsg) ??
                        (existing && existing.last_message) ??
                        null;

                    const lastMessageAt =
                        newConvRaw.last_message_at ??
                        newMsg.sent_at ??
                        (existing && existing.last_message_at) ??
                        null;

                    let unread;
                    if (isActive) {
                        unread = 0;
                    } else if (newConvRaw.unread_count != null) {
                        unread = newConvRaw.unread_count;
                    } else {
                        const oldUnread = existing && existing.unread_count ? existing.unread_count : 0;
                        unread = oldUnread + 1;
                    }

                    const mergedConv = Object.assign(
                        {},
                        existing || {},
                        newConvRaw,
                        {
                            last_message: lastMessage,
                            last_message_at: lastMessageAt,
                            unread_count: unread,
                        }
                    );

                    if (idx !== -1) {
                        this.$set(this.conversations, idx, mergedConv);
                    } else if (this.filters.status === 'open') {
                        this.conversations.unshift(mergedConv);
                        this.pagination.total += 1;
                    }

                    if (isActive) {
                        this.messages.push(newMsg);
                        this.selectedConversation = mergedConv;

                        this.$nextTick(() => {
                            this.scrollToBottom();
                        });
                    }
                },

                handleConversationAssigned(e) {
                    if (!e || !e.conversation) return;
                    const conv = e.conversation;

                    this.updateOrInsertConversation(conv);

                    if (this.selectedConversation && this.selectedConversation.id === conv.id) {
                        this.selectedConversation = Object.assign({}, this.selectedConversation, conv);
                    }
                },

                handleConversationClosed(e) {
                    if (!e || !e.conversation) return;
                    const conv = e.conversation;

                    this.updateOrInsertConversation(conv);

                    if (this.selectedConversation && this.selectedConversation.id === conv.id) {
                        this.selectedConversation = Object.assign({}, this.selectedConversation, conv);
                    }
                },
                handleConversationOpen(e) {
                    if (!e || !e.conversation) return;
                    const conv = e.conversation;

                    this.updateOrInsertConversation(conv);

                    if (this.selectedConversation && this.selectedConversation.id === conv.id) {
                        this.selectedConversation = Object.assign({}, this.selectedConversation, conv);
                    }
                },

                handleConversationLocked(e) {
                    if (!e || !e.conversation) return;
                    const conv = e.conversation;

                    this.updateOrInsertConversation(conv);

                    if (this.selectedConversation && this.selectedConversation.id === conv.id) {
                        this.selectedConversation = Object.assign({}, this.selectedConversation, conv);
                    }
                },

                // ====== scope tab: ทั้งหมด / ที่รับเรื่อง ======
                changeScope(scope) {
                    if (this.filters.scope === scope) return;
                    this.filters.scope = scope;
                    this.fetchConversations(1, {silent: true, merge: false});
                },
                onMemberModalShown() {
                    this.autoFocusRef('memberIdInput');
                },
                onMemberModalHidden() {
                    this.$nextTick(() => {
                        this.autoFocusRef('replyBox');
                    });
                },
                onQuickReplyModalHidden() {
                    this.$nextTick(() => {
                        this.autoFocusRef('replyBox');
                    });
                },
                onBalanceModalHidden() {
                    this.$nextTick(() => {
                        this.autoFocusRef('replyBox');
                    });
                },
                // ====== modal: ผูก contact กับ member ======
                openMemberModal() {
                    if (!this.selectedConversation || !this.selectedConversation.contact) {
                        return;
                    }
                    const conv = this.selectedConversation.contact;

                    this.memberModal.display_name = conv.display_name;
                    this.memberModal.error = '';
                    this.memberModal.member = null;
                    this.memberModal.member_id = conv.member_username || '';

                    this.$nextTick(() => {
                        if (this.$refs.memberModal) {
                            this.$refs.memberModal.show();
                        }
                    });
                },

                resetMemberModal() {
                    this.memberModal = {
                        display_name: '',
                        member_id: '',
                        member: null,
                        loading: false,
                        saving: false,
                        error: '',
                    };
                },

                searchMember() {
                    if (!this.memberModal.member_id) return;
                    this.memberModal.error = '';
                    this.memberModal.member = null;
                    this.memberModal.loading = true;

                    axios.get(this.apiUrl('members/find'), {
                        params: {
                            member_id: this.memberModal.member_id,
                        }
                    }).then(res => {
                        const data = res.data || {};
                        const member = data.data || data.member || null;

                        if (!member) {
                            this.memberModal.error = 'ไม่พบสมาชิกตาม Member ID ที่ระบุ';
                            return;
                        }

                        this.memberModal.member = {
                            name: member.name || member.full_name || '',
                            username: member.username || member.user || '',
                            mobile: member.mobile || member.tel || '',
                            id: member.id || member.code || this.memberModal.member_id,
                            display_name: this.memberModal.display_name,
                        };
                    }).catch(err => {
                        console.error('searchMember error', err);
                        this.memberModal.error = 'ค้นหาสมาชิกไม่สำเร็จ กรุณาลองใหม่';
                    }).finally(() => {
                        this.memberModal.loading = false;
                    });
                },

                saveMemberLink() {
                    if (!this.selectedConversation || !this.selectedConversation.contact) return;
                    if (!this.memberModal.member) return;

                    const contactId = this.selectedConversation.contact.id;
                    const member = this.memberModal.member;

                    this.memberModal.saving = true;

                    axios.post(this.apiUrl('contacts/' + contactId + '/attach-member'), {
                        member_id: member.id,
                        display_name: member.display_name,
                    }).then(res => {
                        const data = res.data || {};
                        const contact = data.data || data.contact || null;

                        if (contact) {
                            this.selectedConversation.contact = contact;
                        } else {
                            const c = this.selectedConversation.contact;
                            c.member_id = member.id;
                            c.member_username = member.username || c.member_username;
                            c.member_mobile = member.mobile || c.member_mobile;
                            c.display_name = member.display_name || c.display_name;
                            this.selectedConversation.contact = Object.assign({}, c);
                        }

                        const idx = this.conversations.findIndex(c => c.id === this.selectedConversation.id);
                        if (idx !== -1) {
                            const merged = Object.assign({}, this.conversations[idx], {
                                contact: this.selectedConversation.contact,
                            });
                            this.$set(this.conversations, idx, merged);
                        }

                        if (this.$refs.memberModal) {
                            this.$refs.memberModal.hide();
                        }
                    }).catch(err => {
                        console.error('saveMemberLink error', err);
                        this.memberModal.error = 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
                    }).finally(() => {
                        this.memberModal.saving = false;
                    });
                },

                async openBalanceModal() {
                    if (!this.selectedConversation) {
                        return;
                    }

                    this.balanceLoading = true;
                    this.balanceData = null;

                    try {
                        const res = await axios.get(this.apiUrl('get-balance'), {
                            params: {
                                conversation_id: this.selectedConversation.id,
                            },
                        });

                        if (!res.data || !res.data.ok) {
                            const msg = res.data && res.data.message
                                ? res.data.message
                                : 'ไม่สามารถดึงยอดเงินได้';
                            // this.showToastError && this.showToastError(msg);
                            this.showAlert({success: false, message: msg});
                            return;
                        }

                        this.balanceData = res.data.data || null;

                        // แสดง popup แบบง่าย ๆ: ใช้ b-modal
                        if (this.$refs.balanceModal) {
                            this.$refs.balanceModal.show();
                        } else {
                            // กันไว้ ถ้าไม่มี modal จริง ๆ ก็ alert ไปก่อน
                            // alert(
                            //     `ยอดเงินคงเหลือ: ${this.balanceData.balance_text} บาท`
                            // );

                            this.showAlert({
                                success: true,
                                message: `ยอดเงินคงเหลือ: ${this.balanceData.balance_text} บาท`
                            });
                        }
                    } catch (e) {
                        console.error(e);
                        this.showAlert({success: false, message: 'เกิดข้อผิดพลาดในการดึงยอดเงิน'});
                        // this.showToastError && this.showToastError('เกิดข้อผิดพลาดในการดึงยอดเงิน');
                    } finally {
                        this.balanceLoading = false;
                    }
                },

                // ====== รับเรื่อง / ปิดเคส ======
                updateConversationLocal(conv) {
                    if (!conv || !conv.id) return;

                    if (this.selectedConversation && this.selectedConversation.id === conv.id) {
                        this.selectedConversation = Object.assign({}, this.selectedConversation, conv);
                    }

                    const idx = this.conversations.findIndex(c => c.id === conv.id);
                    if (idx !== -1) {
                        const merged = Object.assign({}, this.conversations[idx], conv);
                        this.$set(this.conversations, idx, merged);
                    }
                },
                acceptConversation() {
                    if (!this.selectedConversation) return;

                    const id = this.selectedConversation.id;

                    axios.post(this.apiUrl('conversations/' + id + '/accept'))
                        .then(res => {
                            const conv = res.data.data || res.data.conversation || null;
                            if (!conv) return;

                            this.updateConversationLocal(conv);

                            this.fetchConversations(1, {silent: true, merge: true})
                                .then(() => {
                                    const idx = this.conversations.findIndex(c => c.id === conv.id);
                                    if (idx !== -1) {
                                        this.selectConversation(this.conversations[idx], {reloadMessages: false});
                                    }
                                });
                        })
                        .catch(err => {
                            console.error('acceptConversation error', err);
                            const msg =
                                err?.response?.data?.message ??
                                err?.response?.data?.msg ??
                                err?.response?.data?.error ??
                                'รับเรื่องไม่สำเร็จ';

                            this.showAlert({success: false, message: msg});
                        });
                },
                lockConversation(conv) {
                    if (!conv || !conv.id) return;

                    return axios.post(this.apiUrl('conversations/' + conv.id + '/lock'))
                        .then(res => {
                            const convNew = res.data.data || res.data.conversation || null;
                            if (convNew) {
                                this.updateConversationLocal(convNew);
                            }
                        })
                        .catch(err => {
                            console.error('lockConversation error', err);

                            const msg =
                                err?.response?.data?.message ??
                                err?.response?.data?.msg ??
                                err?.response?.data?.error ??
                                'ไม่สามารถล็อกห้องได้';

                            this.showAlert({success: false, message: msg});
                        });
                },

                unlockConversation(conv) {
                    if (!conv || !conv.id) return;

                    return axios.post(this.apiUrl('conversations/' + conv.id + '/unlock'))
                        .then(res => {
                            const convNew = res.data.data || res.data.conversation || null;
                            if (convNew) {
                                this.updateConversationLocal(convNew);
                            }
                        })
                        .catch(err => {
                            console.error('unlockConversation error', err);
                        });
                },
                async closeConversation() {
                    if (!this.selectedConversation) return;

                    const id = this.selectedConversation.id;

                    const ok = await this.showConfirm({message: 'ยืนยันปิดเคสนี้ ?'});
                    if (!ok) return;

                    try {
                        const {data} = await axios.post(this.apiUrl('conversations/' + id + '/close'));
                        const conv = data.data || null;
                        if (!conv) return;

                        // 1) อัปเดตห้องปัจจุบัน + list ซ้าย
                        this.updateConversationLocal(conv);

                        // 2) เปลี่ยน filter ไปแท็บปิดเคส
                        this.filters.status = 'closed';

                        // 3) โหลด list ใหม่แบบ merge แล้วเลือกห้องเดิม
                        await this.fetchConversations(1, {silent: true, merge: true});

                        const idx = this.conversations.findIndex(c => c.id === conv.id);
                        if (idx !== -1) {
                            this.selectConversation(this.conversations[idx], {reloadMessages: false});
                        }
                    } catch (err) {
                        const msg =
                            err?.response?.data?.message ??
                            err?.response?.data?.msg ??
                            err?.response?.data?.error ??
                            'ปิดเคสไม่สำเร็จ';

                        this.showAlert({success: false, message: msg});

                    } finally {
                        this.autoFocusRef('replyBox');
                    }
                },

                async openConversation() {
                    if (!this.selectedConversation) return;

                    const id = this.selectedConversation.id;

                    const ok = await this.showConfirm({message: 'ยืนยันเปิดเคสนี้ ?'});
                    if (!ok) return;

                    try {
                        const {data} = await axios.post(this.apiUrl('conversations/' + id + '/open'));
                        const conv = data.data || null;
                        if (!conv) return;

                        this.updateConversationLocal(conv);
                        this.filters.status = 'open';

                        await this.fetchConversations(1, {silent: true, merge: true});

                        const idx = this.conversations.findIndex(c => c.id === conv.id);
                        if (idx !== -1) {
                            this.selectConversation(this.conversations[idx], {reloadMessages: false});
                        }
                    } catch (err) {
                        const msg =
                            err?.response?.data?.message ??
                            err?.response?.data?.msg ??
                            err?.response?.data?.error ??
                            'เปิดเคสไม่สำเร็จ';

                        this.showAlert({success: false, message: msg});
                    } finally {
                        this.autoFocusRef('replyBox');
                    }
                },
                onRegisterModalShown() {
                    this.autoFocusRef('registerPhoneInput');
                },
                // ====== สมัครสมาชิก / ยกเลิกสมัคร / เติมเงิน ======
                openRegisterModal() {
                    if (!this.selectedConversation) return;

                    this.registerModal.error = '';
                    this.registerModal.loading = false;
                    this.registerModal.phone = '';
                    this.registerModal.bank_code = '';
                    this.registerModal.account_no = '';
                    this.registerModal.name = '';
                    this.registerModal.surname = '';

                    this.$nextTick(() => {
                        if (this.$refs.registerModal) {
                            this.$refs.registerModal.show();
                        }
                    });
                },

                async cancelRegisterFlow() {
                    if (!this.selectedConversation) return;

                    const ok = await this.showConfirm({
                        message: 'ยืนยันยกเลิกการสมัครกับบอทสำหรับห้องนี้ ?'
                    });
                    if (!ok) return;

                    try {
                        await axios.post(
                            this.apiUrl('conversations/' + this.selectedConversation.id + '/cancel-register')
                        );

                        this.selectedConversation.is_registering = false;
                        this.updateConversationLocal(this.selectedConversation);

                    } catch (err) {
                        const msg =
                            err?.response?.data?.message ??
                            err?.response?.data?.msg ??
                            err?.response?.data?.error ??
                            'ไม่สามารถยกเลิกการสมัครได้';

                        this.showAlert({success: false, message: msg});

                    } finally {
                        this.autoFocusRef('replyBox');
                    }
                },

                submitRegisterByStaff() {
                    if (this.registerModal.loading) {
                        return;
                    }

                    if (typeof this.canSubmitRegister !== 'undefined' && !this.canSubmitRegister) {
                        return;
                    }

                    this.registerModal.error = null;

                    const m = this.registerModal;

                    const payload = {
                        phone: m.phone,
                        bank_code: m.bank_code,
                        account_no: m.account_no,
                        name: m.name,
                        surname: m.surname,
                    };

                    const conv = this.selectedConversation || null;
                    if (conv) {
                        payload.conversation_id = conv.id || null;
                        payload.line_contact_id =
                            conv.line_contact_id ||
                            conv.contact_id ||
                            (conv.contact ? conv.contact.id : null) ||
                            null;

                        payload.line_account_id =
                            conv.line_account_id ||
                            conv.account_id ||
                            (conv.account ? conv.account.id : null) ||
                            null;
                    }

                    this.registerModal.loading = true;

                    axios.post(this.apiUrl('register/member'), payload)
                        .then((response) => {
                            const data = response.data || {};

                            if (!data.success) {
                                this.registerModal.error = data.message || 'สมัครสมาชิกไม่สำเร็จ';
                                this.showAlert(data);
                                return;
                            }
                            this.showAlert(data);

                            if (conv && data.member) {
                                // ที่นี่ถ้าอยาก sync กับ contact/conversation ต่อได้
                            }

                            if (this.$refs.registerModal) {
                                this.$refs.registerModal.hide();
                            }
                        })
                        .catch((error) => {
                            console.error('[LineOA] submitRegisterByStaff error', error);

                            this.registerModal.error = 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่';
                        })
                        .finally(() => {
                            this.registerModal.loading = false;

                        });
                },
                onRegisterModalHidden() {
                    // รอ 1 tick ให้ DOM stable
                    this.$nextTick(() => {
                        this.autoFocusRef('replyBox');
                    });
                },
                onTopupModalHidden() {
                    this.$nextTick(() => {
                        this.autoFocusRef('replyBox');
                    });
                },
                openMemberFromConversation() {
                    if (!this.selectedConversation) return;
                    const conv = this.selectedConversation.contact || {};

                    if (window.memberEditApp && typeof window.memberEditApp.memberEditOpen === 'function') {
                        window.memberEditApp.memberEditOpen(conv.member_id);
                    }
                },
                openTopupModal() {
                    if (!this.selectedConversation) return;

                    this.topupModal.error = '';
                    this.topupModal.loading = false;
                    this.topupModal.selectedItem = null;

                    const c = this.selectedConversation.contact || {};

                    this.topupModal.memberSearch = c.member_username || '';
                    this.topupModal.member = c.member_username ? {
                        username: c.member_username,
                        mobile: c.member_mobile,
                        name: c.member_name,
                        bank_name: c.member_bank_name,
                        acc_no: c.member_acc_no,
                    } : null;

                    this.topupModal.bank = '';
                    this.topupModal.amount = null;

                    this.$nextTick(() => {
                        if (this.$refs.topupModal) {
                            this.$refs.topupModal.show();
                        }
                    });
                },

                selectTopupItem(item) {
                    this.topupModal.selectedItem = item || null;
                },

                searchTopupMember() {
                    if (!this.topupModal.memberSearch) {
                        this.topupModal.error = 'กรุณากรอกไอดีสมาชิกก่อนค้นหา';
                        return;
                    }
                    this.topupModal.error = '';
                    console.log('[LineOA] searchTopupMember', this.topupModal.memberSearch);
                },

                submitTopup() {
                    if (this.topupModal.loading) return;

                    if (!this.topupModal.member && !this.topupModal.memberSearch) {
                        this.topupModal.error = 'กรุณาระบุไอดีสมาชิก';
                        return;
                    }
                    if (!this.topupModal.selectedItem) {
                        if (!this.topupModal.bank) {
                            this.topupModal.error = 'กรุณากรอกธนาคารที่เติม';
                            return;
                        }
                        if (!this.topupModal.amount || this.topupModal.amount <= 0) {
                            this.topupModal.error = 'กรุณากรอกจำนวนเงินที่ถูกต้อง';
                            return;
                        }
                    }

                    this.topupModal.error = '';
                    this.topupModal.loading = true;

                    console.log('[LineOA] submitTopup payload', this.topupModal);

                    setTimeout(() => {
                        this.topupModal.loading = false;
                        if (this.$refs.topupModal) {
                            this.$refs.topupModal.hide();
                        }
                    }, 500);
                },
                showAlert(data) {
                    const hasSuccess = typeof (data?.success) !== 'undefined';
                    const ok = hasSuccess && data.success === true;

                    const msg = data?.message
                        ?? data?.msg
                        ?? (hasSuccess
                            ? (ok ? 'ทำรายการสำเร็จ' : 'ทำรายการไม่สำเร็จ')
                            : 'แจ้งเตือนจากระบบ');

                    const variant = hasSuccess
                        ? (ok ? 'success' : 'danger')
                        : 'info';

                    this.$bvModal.msgBoxOk(msg, {
                        title: 'สถานะการทำรายการ',
                        okVariant: variant,
                        size: 'sm',
                        buttonSize: 'sm',
                        centered: true
                    });
                },
                async showConfirm(data) {
                    const hasSuccess = typeof (data?.success) !== 'undefined';
                    const ok = hasSuccess && data.success === true;

                    const msg = data?.message
                        ?? data?.msg
                        ?? (hasSuccess
                            ? (ok ? 'ทำรายการสำเร็จ' : 'ทำรายการไม่สำเร็จ')
                            : 'ยืนยันดำเนินการต่อหรือไม่');

                    const variant = hasSuccess
                        ? (ok ? 'success' : 'danger')
                        : 'info';

                    try {
                        const confirmed = await this.$bvModal.msgBoxConfirm(msg, {
                            title: 'ยืนยันการดำเนินการ',
                            size: 'sm',
                            buttonSize: 'sm',
                            okTitle: 'ยืนยัน',
                            cancelTitle: 'ยกเลิก',
                            okVariant: variant,
                            cancelVariant: 'danger',
                            centered: true,
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,
                            returnFocus: true,
                        });

                        return confirmed === true;
                    } catch (e) {
                        return false;
                    }
                },
                openRefillModal() {
                    if (!this.selectedConversation) return;
                    const conv = this.selectedConversation.contact || {};
                    var prefill = null;
                    prefill = {
                        member_id: conv.member_id || null,
                        member_username: conv.member_username || null,
                    };

                    if (window.memberRefillApp && typeof window.memberRefillApp.openRefillModal === 'function') {
                        window.memberRefillApp.openRefillModal(prefill);
                    }
                },

            }
        });

    </script>

    <script type="module">
        Dropzone.autoDiscover = false;

        window.memberEditApp = new Vue({
            el: '#member-edit-app',
            data() {
                return {
                    csrf: document.head.querySelector('meta[name="csrf-token"]').content,

                    // state หลักของ member edit
                    memberEditShow: false,
                    memberEditMode: 'edit',  // 'add' หรือ 'edit'
                    memberEditCode: null,    // member id ที่กำลังแก้ไข

                    memberEditForm: {
                        firstname: '',
                        lastname: '',
                        bank_code: '',
                        user_name: '',
                        user_pass: '',
                        acc_no: '',
                        wallet_id: '',
                        lineid: '',
                        pic_id: '',
                        tel: '',
                        one_time_password: '',
                        refer_code: 0,
                        maxwithdraw_day: 0,
                        af: '',
                        up_name: '',
                        upline_code: '',
                    },

                    // รูปปัจจุบัน
                    memberEditPic: null,

                    // Dropzone
                    memberEditDropzone: null,
                    memberEditSuppressDelete: false,

                    // options select ต่าง ๆ
                    memberEditOption: {
                        bank_code: [],
                        refer_code: [],
                    },
                };
            },
            mounted() {
                this.memberEditLoadBank();
                this.memberEditLoadRefer();
            },
            methods: {
                /* ============================
                 *  ส่วน Dropzone / Upload รูป
                 * ============================ */
                autoFocusOnLineOA(refName) {
                    this.$nextTick(() => {
                        // ===== helper หา Vue root / line-oa-chat ภายในฟังก์ชันนี้เอง =====
                        function findAnyVueRoot() {
                            var all = document.querySelectorAll('body, body *');
                            for (var i = 0; i < all.length; i++) {
                                if (all[i].__vue__) {
                                    return all[i].__vue__;
                                }
                            }
                            console.warn('[memberEditApp] ไม่พบ Vue root instance เลย');
                            return null;
                        }

                        function findLineOaChatVm(vm) {
                            if (!vm) return null;

                            var name = vm.$options && (vm.$options.name || vm.$options._componentTag);
                            if (name === 'line-oa-chat') {
                                return vm;
                            }

                            if (vm.$children && vm.$children.length) {
                                for (var i = 0; i < vm.$children.length; i++) {
                                    var found = findLineOaChatVm(vm.$children[i]);
                                    if (found) return found;
                                }
                            }
                            return null;
                        }

                        function getLineOaChatComponentLocal() {
                            var rootVm = findAnyVueRoot();
                            if (!rootVm) return null;

                            if (rootVm.$refs && rootVm.$refs.lineOaChat) {
                                return rootVm.$refs.lineOaChat;
                            }

                            var comp = findLineOaChatVm(rootVm);
                            if (!comp) {
                                console.warn('[memberEditApp] ไม่พบ component line-oa-chat จาก Vue tree');
                            }
                            return comp;
                        }

                        // ===== ใช้งานจริง =====
                        const comp = getLineOaChatComponentLocal();
                        if (!comp) {
                            return;
                        }

                        const target = comp.$refs && comp.$refs[refName];
                        if (!target) {
                            console.warn(`[memberEditApp] line-oa-chat ไม่มี $refs["${refName}"]`);
                            return;
                        }

                        // 1) ถ้า ref เป็น component ที่มี .focus()
                        if (typeof target.focus === 'function') {
                            try {
                                target.focus();
                                return;
                            } catch (e) {
                                console.warn('[memberEditApp] focus() บน component ล้มเหลว', e);
                            }
                        }

                        // 2) ถ้า ref เป็น element ตรง ๆ
                        if (target instanceof HTMLElement) {
                            target.focus?.();
                            return;
                        }

                        // 3) ref เป็น Vue component → หา input/textarea ข้างใน
                        const el =
                            target.$el?.querySelector?.('input,textarea,select,[tabindex]') ||
                            target.$el ||
                            null;

                        if (el && typeof el.focus === 'function') {
                            el.focus();
                        } else {
                            console.warn('[memberEditApp] ไม่พบ element ที่ focus ได้ใน ref', refName);
                        }
                    });
                },

                autoFocusRef(refName) {
                    this.$nextTick(() => {
                        const r = this.$refs[refName];
                        if (!r) return;

                        if (typeof r.focus === 'function') {
                            try {
                                r.focus();
                                return;
                            } catch (_) {
                            }
                        }

                        const el =
                            r.$el?.querySelector?.('input,textarea') ||
                            (r instanceof HTMLElement ? r : null);

                        el?.focus?.();
                    });
                },
                onMemberEditModalHidden() {
                    this.$nextTick(() => {
                        this.autoFocusOnLineOA('replyBox');
                    });
                },
                memberEditOpenUpload() {
                    this.$refs.memberEditUploadModal.show();
                },

                memberEditEnsureDropzone() {
                    if (this.memberEditDropzone) return;

                    this.memberEditDropzone = new Dropzone(this.$refs.memberEditDropzoneEl, {
                        // url: "",
                        url: "{{ route('admin.upload.pic') }}",
                        method: 'post',
                        maxFiles: 1,
                        acceptedFiles: 'image/*',
                        addRemoveLinks: true,
                        dictRemoveFile: 'ลบรูป',
                        previewsContainer: this.$refs.memberEditDropzonePreviews,
                        clickable: [this.$refs.memberEditDropzoneEl, this.$refs.memberEditPickBtn],
                        headers: {'X-CSRF-TOKEN': this.csrf},
                    });

                    this.memberEditDropzone.on('sending', (file, xhr, formData) => {
                        formData.append('id', this.memberEditCode || '');
                    });

                    this.memberEditDropzone.on('success', (file, resp) => {
                        file.serverId = resp.id;
                        // file.deleteUrl = resp.delete_url
                        //     || "".replace(':id', resp.id);
                        file.deleteUrl = resp.delete_url
                            || "{{ route('admin.delete.pic', ['id' => ':id']) }}".replace(':id', resp.id);

                        this.memberEditPic = {
                            id: resp.id,
                            name: file.name,
                            size: file.size,
                            url: resp.url,
                        };

                        // เก็บ path / url ไว้ในฟอร์มเพื่อนำไปใช้ด้านหลัง
                        this.memberEditForm.pic_id = resp.path || resp.url || '';
                    });

                    this.memberEditDropzone.on('maxfilesexceeded', file => {
                        this.memberEditSuppressDelete = true;
                        this.memberEditDropzone.removeAllFiles(true);
                        this.memberEditSuppressDelete = false;
                        this.memberEditDropzone.addFile(file);
                    });

                    const onRemovedFile = (file) => {
                        if (this.memberEditSuppressDelete) return;
                        if (!file.serverId) return;

                        // const url = file.deleteUrl
                        //     || "".replace(':id', file.serverId);
                        const url = file.deleteUrl
                            || "{{ route('admin.delete.pic', ['id' => ':id']) }}".replace(':id', file.serverId);

                        fetch(url, {
                            method: 'POST',
                            headers: {'X-CSRF-TOKEN': this.csrf},
                        }).then(() => {
                            if (this.memberEditPic && String(this.memberEditPic.id) === String(file.serverId)) {
                                this.memberEditPic = null;
                                this.memberEditForm.pic_id = '';
                            }
                        });
                    };

                    this.memberEditDropzone.on('removedfile', onRemovedFile);
                },

                memberEditOnUploadShown() {
                    this.memberEditEnsureDropzone();

                    this.memberEditSuppressDelete = true;
                    this.memberEditDropzone.removeAllFiles(true);
                    this.memberEditSuppressDelete = false;

                    const dzEl = this.$refs.memberEditDropzoneEl;
                    if (dzEl && dzEl.classList) dzEl.classList.remove('dz-started');
                    const msg = dzEl ? dzEl.querySelector('.dz-message') : null;
                    if (msg) msg.style.display = '';

                    if (this.memberEditDropzone.hiddenFileInput) {
                        this.memberEditDropzone.hiddenFileInput.disabled = false;
                    }
                    if (typeof this.memberEditDropzone.enable === 'function') {
                        this.memberEditDropzone.enable();
                    }

                    // preload รูปเดิม
                    if (this.memberEditPic && this.memberEditPic.url) {
                        const f = this.memberEditPic;
                        const mock = {
                            name: f.name || 'existing.jpg',
                            size: f.size || 12345,
                            serverId: f.id,
                            isExisting: true,
                            url: f.url,
                        };
                        this.memberEditDropzone.emit('addedfile', mock);
                        this.memberEditDropzone.emit('thumbnail', mock, f.url);
                        this.memberEditDropzone.emit('complete', mock);
                        this.memberEditDropzone.files.push(mock);
                    }
                },

                memberEditOnUploadHidden() {
                    if (this.memberEditDropzone) {
                        this.memberEditSuppressDelete = true;
                        this.memberEditDropzone.removeAllFiles(true);
                        this.memberEditSuppressDelete = false;
                    }
                    const dzEl = this.$refs.memberEditDropzoneEl;
                    if (dzEl && dzEl.classList) dzEl.classList.remove('dz-started');
                    const msg = dzEl ? dzEl.querySelector('.dz-message') : null;
                    if (msg) msg.style.display = '';
                },

                memberEditSetPicFromPath(path) {
                    if (!path) {
                        this.memberEditPic = null;
                        this.memberEditForm.pic_id = '';
                        return;
                    }
                    const fileName = path.split('/').pop();
                    const url = this.memberEditFileUrl(path);
                    this.memberEditPic = {
                        id: this.memberEditCode,
                        name: fileName,
                        url,
                        size: 12345,
                    };
                    this.memberEditForm.pic_id = path;
                },

                memberEditFileUrl(path) {
                    // ปรับตามที่เก็บไฟล์จริง ถ้าใช้ storage/public
                    return `{{ url('/storage') }}/${path}`;
                },

                /* ============================
                 *  ส่วนเปิด / โหลดข้อมูล member
                 * ============================ */

                // เรียกใช้จากภายนอก: window.memberEditApp.memberEditOpen(memberId)
                memberEditOpen(code) {
                    console.log('memberEditOpen', code);
                    // ตั้งค่า state เบื้องต้น
                    this.memberEditCode = code || null;
                    this.memberEditMode = 'edit';

                    // เคลียร์ฟอร์ม + รูป
                    this.memberEditResetForm();
                    this.memberEditPic = null;

                    // เปิด modal ทันที ไม่รอ axios
                    this.memberEditShow = true;
                    if (this.$refs.memberEditModal && typeof this.$refs.memberEditModal.show === 'function') {
                        this.$refs.memberEditModal.show();
                    } else {
                        console.error('memberEditModal ref not found');
                    }

                    // ถ้ามี code → ค่อยยิงโหลดข้อมูล async ตามหลัง
                    if (code) {
                        this.memberEditLoadData().catch(err => {
                            console.error('memberEditLoadData error:', err);
                        });
                    }
                },

                // alias สำหรับ “เพิ่มใหม่” (ใช้ id = null)
                memberEditNew() {
                    this.memberEditOpen(null);
                },

                memberEditResetForm() {
                    this.memberEditForm = {
                        firstname: '',
                        lastname: '',
                        bank_code: '',
                        user_name: '',
                        user_pass: '',
                        acc_no: '',
                        wallet_id: '',
                        lineid: '',
                        pic_id: '',
                        tel: '',
                        one_time_password: '',
                        refer_code: 0,
                        maxwithdraw_day: 0,
                        af: '',
                        up_name: '',
                        upline_code: '',
                    };
                },

                async memberEditLoadData() {
                    if (!this.memberEditCode) return;

                    const response = await axios.get("{{ route('admin.member.loaddata') }}", {
                        params: {id: this.memberEditCode},
                    });

                    const u = response.data.data;

                    this.memberEditForm = {
                        firstname: u.firstname,
                        lastname: u.lastname,
                        bank_code: u.bank_code,
                        user_name: u.user_name,
                        user_pass: '',
                        acc_no: u.acc_no,
                        wallet_id: u.wallet_id,
                        lineid: u.lineid,
                        pic_id: u.pic_id,
                        tel: u.tel,
                        one_time_password: '',
                        refer_code: u.refer_code,
                        maxwithdraw_day: u.maxwithdraw_day,
                        af: u.af || '',
                        up_name: u.up_name || '',
                        upline_code: u.upline_code || '',
                    };

                    if (u.pic_id) {
                        this.memberEditSetPicFromPath(u.pic_id);
                    } else {
                        this.memberEditPic = null;
                    }
                },

                async memberEditLoadBank() {
                    const response = await axios.get("{{ route('admin.member.loadbank') }}");
                    this.memberEditOption.bank_code = response.data.banks || [];
                },

                async memberEditLoadRefer() {
                    const response = await axios.get("{{ route('admin.member.loadrefer') }}");
                    this.memberEditOption.refer_code = response.data.refers || [];
                },

                async memberEditLoadAF(afValue) {
                    const response = await axios.get("{{ route('admin.member.loadaf') }}", {
                        params: {af: afValue},
                    });

                    if (response.data.success) {
                        this.memberEditForm.up_name = response.data.data.name;
                        this.memberEditForm.upline_code = response.data.data.code;
                    } else {
                        this.memberEditForm.up_name = '';
                        this.memberEditForm.upline_code = 0;
                    }
                },

                /* ============================
                 *  ส่วน submit / error handling
                 * ============================ */

                memberEditShowError(response) {
                    let message = response?.data?.message || 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';

                    if (typeof message === 'object') {
                        try {
                            message = Object.values(message).flat().join('\n');
                        } catch (e) {
                            message = [].concat(...Object.values(message)).join('\n');
                        }
                    }
                    if (Array.isArray(message)) {
                        message = message.join('\n');
                    }

                    this.$bvModal.msgBoxOk(message, {
                        title: 'ผลการดำเนินการ',
                        size: 'sm',
                        buttonSize: 'sm',
                        okVariant: 'danger',
                        headerClass: 'p-2 border-bottom-0',
                        footerClass: 'p-2 border-top-0',
                        centered: true,
                    });
                },

                memberEditSubmit(event) {
                    event.preventDefault();

                    let url;
                    if (this.memberEditMode === 'add') {
                        url = "{{ route('admin.member.create') }}";
                    } else {
                        url = "{{ route('admin.member.update') }}/" + this.memberEditCode;
                    }

                    const payload = {
                        firstname: this.memberEditForm.firstname,
                        lastname: this.memberEditForm.lastname,
                        bank_code: this.memberEditForm.bank_code,
                        user_name: this.memberEditForm.user_name,
                        user_pass: this.memberEditForm.user_pass,
                        acc_no: this.memberEditForm.acc_no,
                        wallet_id: this.memberEditForm.wallet_id,
                        lineid: this.memberEditForm.lineid,
                        pic_id: this.memberEditForm.pic_id,
                        tel: this.memberEditForm.tel,
                        one_time_password: this.memberEditForm.one_time_password,
                        maxwithdraw_day: this.memberEditForm.maxwithdraw_day,
                        refer_code: this.memberEditForm.refer_code,
                        upline_code: this.memberEditForm.upline_code,
                    };

                    const formData = new FormData();
                    formData.append('data', JSON.stringify(payload));

                    const config = {
                        headers: {'Content-Type': 'multipart/form-data'},
                    };

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
                                    centered: true,
                                });

                                this.$refs.memberEditModal.hide();
                            } else {
                                this.memberEditShowError(response);
                            }
                        })
                        .catch(error => {
                            this.memberEditShowError(error.response || {});
                        });
                },
            },
        });
    </script>

    <script type="module">
        window.memberRefillApp = new Vue({
            el: '#member-refill-app',

            data() {
                return {
                    showRefillUI: false,     // ใช้ control v-if ของฟอร์มใน modal
                    currentTopupId: null,    // code ของบิลแจ้งฝากที่กำลังจัดการ
                    currentClearId: null,    // code ของรายการที่จะ clear
                    currentMemberId: null,
                    // ฟอร์มสำหรับผูกบิลเติมเงินกับ Member/Game ID
                    assignTopupTargetForm: {
                        user_name: '',
                        name: '',
                        member_topup: '',
                        remark_admin: '',
                    },

                    // ฟอร์มเติมเงินตามปกติ
                    refillForm: {
                        id: '',
                        user_name: '',
                        name: '',
                        amount: 0,
                        account_code: '',
                        remark_admin: '',
                        one_time_password: '',
                    },

                    // ฟอร์มระบุหมายเหตุเวลา clear
                    clearRemarkForm: {
                        remark: '',
                    },

                    formmoney: {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: '',
                        one_time_password: '',
                    },
                    formpoint: {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: '',
                    },
                    formdiamond: {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: '',
                    },

                    // NEW: options ประเภทของรายการ
                    typesmoney: [
                        {value: 'D', text: 'เพิ่ม ยอดเงิน'},
                        {value: 'W', text: 'ลด ยอดเงิน'},
                    ],
                    typespoint: [
                        {value: 'D', text: 'เพิ่ม Point'},
                        {value: 'W', text: 'ลด Point'},
                    ],
                    typesdiamond: [
                        {value: 'D', text: 'เพิ่ม Diamond'},
                        {value: 'W', text: 'ลด Diamond'},
                    ],

                    // ธนาคารที่ใช้ใน select
                    banks: [{value: '', text: '== ธนาคาร =='}],

                    fields: [],
                    // modal log
                    logType: null,      // 'deposit' หรือ 'withdraw'
                    caption: '',
                    items: [],
                    isBusy: false,
                    show: false,
                };
            },

            created() {
                this.audio = document.getElementById('alertsound');
                this.autoCnt(false);
                // ถ้าหน้านี้ยังใช้ alertsound / autoCnt เดิมอยู่ สามารถเรียกจาก window ตัวอื่นได้
                // ไม่ผูกกับ memberRefillApp เพื่อลด side-effect
            },

            mounted() {
                this.loadBankAccount();
            },

            methods: {
                openGameLog(type,prefill = null) {
                    this.logType = type;

                    if (type === 'deposit') {
                        this.caption = 'ประวัติฝากเครดิต';
                        this.fields = [
                            { key: 'id',            label: 'รหัส',          sortable: false },
                            { key: 'date_create',   label: 'เวลา',          sortable: true },
                            { key: 'amount',        label: 'ยอดฝาก',      sortable: false },
                            { key: 'pro_name',        label: 'โปรโมชั่น',       sortable: true },
                            { key: 'credit_bonus',  label: 'โบนัสที่ได้',         sortable: false },
                            { key: 'credit_before', label: 'เครดิตก่อน',    sortable: false },
                            { key: 'credit_after',  label: 'เครดิตหลัง',    sortable: false },
                            { key: 'status_display',        label: 'สถานะ',         sortable: true },
                        ];
                    } else if (type === 'withdraw') {
                        this.caption = 'ประวัติถอนเครดิต';
                        this.fields = [
                            { key: 'id',            label: 'รหัส',          sortable: false },
                            { key: 'date_create',   label: 'เวลา',          sortable: true },
                            { key: 'amount_request',label: 'ยอดแจ้ง',       sortable: false },
                            { key: 'amount',        label: 'ยอดถอนที่ได้รับ',      sortable: false },
                            { key: 'credit_before', label: 'เครดิตก่อน',    sortable: false },
                            { key: 'credit_after',  label: 'เครดิตหลัง',    sortable: false },
                            { key: 'status_display',        label: 'สถานะ',         sortable: true },
                        ];
                    } else {
                        this.caption = 'ประวัติรายการ';
                    }

                    this.showRefillUI = true;
                    if (prefill &&  prefill.member_id) {
                        this.currentMemberId = prefill.member_id;
                    }
                    // เปิด modal
                    this.$nextTick(async () => {
                        this.$refs.gamelog.show();
                        await this.fetchGameLog();
                    });

                    // โหลดข้อมูล

                },
                async fetchGameLog() {
                    if (!this.logType) return;

                    this.isBusy = true;
                    this.items = [];

                    try {
                        const response = await axios.get('{{ route('admin.member.gamelog') }}', {
                            params: {
                                id: this.currentMemberId,
                                method: this.logType,

                                // อาจจะส่ง member_id ไปด้วย ถ้าต้องการจำกัด log ตามสมาชิก
                                // member_id: this.member.id
                            },
                        });

                        // สมมติ backend คืนเป็น { data: [...] }
                        this.items = response.data.list || [];
                    } catch (e) {
                        console.error('โหลด log ไม่สำเร็จ', e);
                        this.$bvToast && this.$bvToast.toast('ไม่สามารถโหลดประวัติได้', {
                            title: 'เกิดข้อผิดพลาด',
                            variant: 'danger',
                            solid: true,
                        });
                    } finally {
                        this.isBusy = false;
                    }
                },

                /**
                 * helper แปลงตัวเลขเป็น string เงิน (อาจมีอยู่แล้วใน app)
                 */
                intToMoney(value) {
                    if (value === null || value === undefined) return '0.00';
                    const n = Number(value) || 0;
                    return n.toLocaleString('th-TH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },
                autoFocusOnLineOA(refName) {
                    this.$nextTick(() => {
                        // ===== helper หา Vue root / line-oa-chat ภายในฟังก์ชันนี้เอง =====
                        function findAnyVueRoot() {
                            var all = document.querySelectorAll('body, body *');
                            for (var i = 0; i < all.length; i++) {
                                if (all[i].__vue__) {
                                    return all[i].__vue__;
                                }
                            }
                            console.warn('[memberEditApp] ไม่พบ Vue root instance เลย');
                            return null;
                        }

                        function findLineOaChatVm(vm) {
                            if (!vm) return null;

                            var name = vm.$options && (vm.$options.name || vm.$options._componentTag);
                            if (name === 'line-oa-chat') {
                                return vm;
                            }

                            if (vm.$children && vm.$children.length) {
                                for (var i = 0; i < vm.$children.length; i++) {
                                    var found = findLineOaChatVm(vm.$children[i]);
                                    if (found) return found;
                                }
                            }
                            return null;
                        }

                        function getLineOaChatComponentLocal() {
                            var rootVm = findAnyVueRoot();
                            if (!rootVm) return null;

                            if (rootVm.$refs && rootVm.$refs.lineOaChat) {
                                return rootVm.$refs.lineOaChat;
                            }

                            var comp = findLineOaChatVm(rootVm);
                            if (!comp) {
                                console.warn('[memberEditApp] ไม่พบ component line-oa-chat จาก Vue tree');
                            }
                            return comp;
                        }

                        // ===== ใช้งานจริง =====
                        const comp = getLineOaChatComponentLocal();
                        if (!comp) {
                            return;
                        }

                        const target = comp.$refs && comp.$refs[refName];
                        if (!target) {
                            console.warn(`[memberEditApp] line-oa-chat ไม่มี $refs["${refName}"]`);
                            return;
                        }

                        // 1) ถ้า ref เป็น component ที่มี .focus()
                        if (typeof target.focus === 'function') {
                            try {
                                target.focus();
                                return;
                            } catch (e) {
                                console.warn('[memberEditApp] focus() บน component ล้มเหลว', e);
                            }
                        }

                        // 2) ถ้า ref เป็น element ตรง ๆ
                        if (target instanceof HTMLElement) {
                            target.focus?.();
                            return;
                        }

                        // 3) ref เป็น Vue component → หา input/textarea ข้างใน
                        const el =
                            target.$el?.querySelector?.('input,textarea,select,[tabindex]') ||
                            target.$el ||
                            null;

                        if (el && typeof el.focus === 'function') {
                            el.focus();
                        } else {
                            console.warn('[memberEditApp] ไม่พบ element ที่ focus ได้ใน ref', refName);
                        }
                    });
                },

                autoFocusRef(refName) {
                    this.$nextTick(() => {
                        const r = this.$refs[refName];
                        if (!r) return;

                        if (typeof r.focus === 'function') {
                            try {
                                r.focus();
                                return;
                            } catch (_) {
                            }
                        }

                        const el =
                            r.$el?.querySelector?.('input,textarea') ||
                            (r instanceof HTMLElement ? r : null);

                        el?.focus?.();
                    });
                },
                onRefillModalHidden() {
                    this.$nextTick(() => {
                        this.autoFocusOnLineOA('replyBox');
                    });
                },
                /* -----------------------------------
                 * เปิด MODAL แบบชื่อใหม่
                 * ----------------------------------- */

                // เดิมคือ editModal() / addModal() แต่ความหมายคือเลือกเป้าหมายของบิลที่เลือก
                openAssignTopupTargetModal(topupId = null, prefill = null) {
                    this.currentTopupId = topupId || null;

                    // reset ฟอร์ม
                    this.assignTopupTargetForm = {
                        user_name: '',
                        name: '',
                        member_topup: '',
                        remark_admin: '',
                    };

                    this.showRefillUI = true;

                    // ถ้ามีข้อมูลจากห้องแชต (prefill)
                    if (prefill && (prefill.member_username || prefill.member_id)) {
                        // ให้เอา member_user มาใส่ช่องค้นหาไว้ก่อน
                        if (prefill.member_username) {
                            this.assignTopupTargetForm.user_name = prefill.member_username;
                        }

                        // เปิด modal แล้วค่อย auto ค้นหา
                        this.$nextTick(async () => {
                            if (this.$refs.assignTopupTargetModal) {
                                this.$refs.assignTopupTargetModal.show();
                            }

                            // ถ้ามี member_user → ให้ยิง loadUserForAssignTarget เลย
                            if (this.assignTopupTargetForm.user_name) {
                                try {
                                    await this.loadUserForAssignTarget();
                                } catch (e) {
                                    console.warn('auto loadUserForAssignTarget failed', e);
                                }
                            }
                        });
                    } else {
                        // กรณีไม่มี prefill → เปิด modal เฉย ๆ ให้แอดมินกรอกเอง
                        this.$nextTick(() => {
                            if (this.$refs.assignTopupTargetModal) {
                                this.$refs.assignTopupTargetModal.show();
                            }
                        });
                    }
                },


                // เดิม refill()
                openRefillModal(prefill = null) {
                    console.log('[memberRefillApp] openRefillModal(prefill =', prefill, ')');

                    this.currentTopupId = null;

                    // reset form ทุกครั้งก่อนเปิด
                    this.refillForm = {
                        id: '',
                        user_name: '',
                        name: '',
                        amount: 0,
                        account_code: '',
                        remark_admin: '.',
                        one_time_password: '',
                    };

                    this.showRefillUI = true;

                    // มีข้อมูลจากห้องแชต
                    if (prefill && prefill.member_username) {
                        this.refillForm.user_name = prefill.member_username;
                        console.warn('[memberRefillApp] refillForm user_name', prefill.member_username);
                        this.$nextTick(async () => {
                            if (this.$refs.refillModal) {
                                this.$refs.refillModal.show();
                            }
                            console.warn('[memberRefillApp] refillModal show');
                            // auto ค้นหา
                            if (this.refillForm.user_name) {
                                try {
                                    console.warn('[memberRefillApp] loadUserForRefill ', this.refillForm.user_name);
                                    await this.loadUserForRefill();
                                } catch (e) {
                                    console.warn('[memberRefillApp] auto loadUserForRefill failed', e);
                                }
                            }
                        });
                    } else {
                        // ไม่มี prefill → เปิดเปล่า ๆ ให้กรอกเอง
                        this.$nextTick(() => {
                            if (this.$refs.refillModal) {
                                this.$refs.refillModal.show();
                            }
                        });
                    }
                },


                // เดิม clearModal(code)
                openClearRemarkModal(code) {
                    this.currentClearId = code;
                    this.clearRemarkForm = {
                        remark: '',
                    };

                    this.showRefillUI = true;
                    if (this.$refs.clearRemarkModal) {
                        this.$refs.clearRemarkModal.show();
                    }
                },

                openMoneyModal(prefill = null) {
                    // reset form
                    this.formmoney = {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: '',
                        one_time_password: '',
                    };

                    // ถ้ามีข้อมูล member จาก chat ให้ prefill id ไว้
                    if (prefill && prefill.member_id) {
                        this.formmoney.id = prefill.member_id;
                    }

                    this.showRefillUI = true;
                    this.$nextTick(() => {
                        this.$refs.money && this.$refs.money.show();
                    });
                },

                openPointModal(prefill = null) {
                    this.formpoint = {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: '',
                    };

                    if (prefill && prefill.member_id) {
                        this.formpoint.id = prefill.member_id;
                    }

                    this.showRefillUI = true;
                    this.$nextTick(() => {
                        this.$refs.point && this.$refs.point.show();
                    });
                },

                openDiamondModal(prefill = null) {
                    this.formdiamond = {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: '',
                    };

                    if (prefill && prefill.member_id) {
                        this.formdiamond.id = prefill.member_id;
                    }

                    this.showRefillUI = true;
                    this.$nextTick(() => {
                        this.$refs.diamond && this.$refs.diamond.show();
                    });
                },


                /* -----------------------------------
                 * LOAD user / bank
                 * ----------------------------------- */

                async loadUserForAssignTarget() {
                    const response = await axios.post("{{ route('admin.bank_in.loaddata') }}", {
                        id: this.assignTopupTargetForm.user_name,
                    });

                    this.assignTopupTargetForm = {
                        ...this.assignTopupTargetForm,
                        name: response.data.data.name,
                        member_topup: response.data.data.code,
                    };
                },

                async loadUserForRefill() {
                    // ป้องกันกรณีไม่มีค่าอะไรเลย
                    if (!this.refillForm.user_name) {
                        console.warn('[memberRefillApp] loadUserForRefill(): ไม่มี user_name ให้ค้นหา');
                        return;
                    }

                    console.log('[memberRefillApp] loadUserForRefill(): search', this.refillForm.user_name);

                    try {
                        const response = await axios.post("{{ route('admin.bank_in.loaddata') }}", {
                            id: this.refillForm.user_name,
                        });

                        const data = response.data && response.data.data;

                        if (!data) {
                            console.warn('[memberRefillApp] loadUserForRefill(): response ไม่มี data', response.data);
                            return;
                        }

                        // เซ็ตข้อมูลกลับเข้าฟอร์ม
                        this.refillForm = {
                            ...this.refillForm,
                            name: data.name,
                            id: data.code,
                        };

                        console.log('[memberRefillApp] loadUserForRefill(): loaded', this.refillForm);
                    } catch (e) {
                        console.error('[memberRefillApp] loadUserForRefill(): error', e);
                    }
                },


                async loadBankAccount() {
                    const response = await axios.get("{{ route('admin.member.loadbankaccount') }}");
                    this.banks = response.data.banks;
                },

                /* -----------------------------------
                 * SUBMIT: ผูกบิลกับ Member (เดิม addEditSubmitNew)
                 * ----------------------------------- */

                submitAssignTopupTarget(event) {
                    event && event.preventDefault && event.preventDefault();

                    if (typeof this.toggleButtonDisable === 'function') {
                        this.toggleButtonDisable(true);
                    }

                    // logic เดิม: ถ้ามี code = update, ถ้าไม่มี = create
                    let url;
                    if (!this.currentTopupId) {
                        url = "{{ route('admin.bank_in.create') }}";
                    } else {
                        url = "{{ route('admin.bank_in.update') }}/" + this.currentTopupId;
                    }

                    const payload = {
                        member_topup: this.assignTopupTargetForm.member_topup,
                        remark_admin: this.assignTopupTargetForm.remark_admin,
                    };

                    const formData = new FormData();
                    formData.append('data', JSON.stringify(payload));

                    const config = {
                        headers: {'Content-Type': `multipart/form-data; boundary=${formData._boundary}`},
                    };

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
                                    centered: true,
                                });

                                if (window.LaravelDataTables && window.LaravelDataTables["deposittable"]) {
                                    window.LaravelDataTables["deposittable"].draw(false);
                                }

                                this.$refs.assignTopupTargetModal.hide();
                            } else {
                                // logic เดิม: mark invalid field ตาม key ใน response.data.message
                                $.each(response.data.message, function (index) {
                                    const el = document.getElementById(index);
                                    el && el.classList.add("is-invalid");
                                });
                                $('input').on('focus', (ev) => {
                                    ev.preventDefault();
                                    ev.stopPropagation();
                                    const id = $(ev.target).attr('id');
                                    const el = document.getElementById(id);
                                    el && el.classList.remove("is-invalid");
                                });
                            }
                        })
                        .catch(errors => {
                            console.log(errors);
                        });
                },

                /* -----------------------------------
                 * SUBMIT: เติมเงิน (เดิม refillSubmit)
                 * ----------------------------------- */

                submitRefillForm(event) {
                    event && event.preventDefault && event.preventDefault();

                    if (typeof this.toggleButtonDisable === 'function') {
                        this.toggleButtonDisable(true);
                    }

                    this.$http.post("{{ route('admin.member.refill') }}", this.refillForm)
                        .then(response => {
                            this.$bvModal.msgBoxOk(response.data.message, {
                                title: 'ผลการดำเนินการ',
                                size: 'sm',
                                buttonSize: 'sm',
                                okVariant: 'success',
                                headerClass: 'p-2 border-bottom-0',
                                footerClass: 'p-2 border-top-0',
                                centered: true,
                            });

                            if (window.LaravelDataTables && window.LaravelDataTables["deposittable"]) {
                                window.LaravelDataTables["deposittable"].draw(false);
                            }
                            this.$refs.refillModal.hide();

                        })
                        .catch(exception => {
                            console.log('error', exception);
                            if (typeof this.toggleButtonDisable === 'function') {
                                this.toggleButtonDisable(false);
                            }
                        });
                },

                /* -----------------------------------
                 * SUBMIT: clear (เดิม clearSubmit)
                 * ----------------------------------- */

                submitClearRemarkForm(event) {
                    event && event.preventDefault && event.preventDefault();

                    if (typeof this.toggleButtonDisable === 'function') {
                        this.toggleButtonDisable(true);
                    }

                    this.$http.post("{{ route('admin.bank_in.clear') }}", {
                        id: this.currentClearId,
                        remark: this.clearRemarkForm.remark,
                    })
                        .then(response => {
                            this.$bvModal.msgBoxOk(response.data.message, {
                                title: 'ผลการดำเนินการ',
                                size: 'sm',
                                buttonSize: 'sm',
                                okVariant: 'success',
                                headerClass: 'p-2 border-bottom-0',
                                footerClass: 'p-2 border-top-0',
                                centered: true,
                            });

                            if (window.LaravelDataTables && window.LaravelDataTables["deposittable"]) {
                                window.LaravelDataTables["deposittable"].draw(false);
                            }

                            this.$refs.clearRemarkModal.hide();
                        })
                        .catch(exception => {
                            console.log('error', exception);
                            if (typeof this.toggleButtonDisable === 'function') {
                                this.toggleButtonDisable(false);
                            }
                        });
                },

                moneySubmit(event) {
                    event && event.preventDefault && event.preventDefault();
                    if (typeof this.toggleButtonDisable === 'function') {
                        this.toggleButtonDisable(true);
                    }

                    this.$http.post("{{ route('admin.member.setwallet') }}", this.formmoney)
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

                            if (window.LaravelDataTables && window.LaravelDataTables["dataTableBuilder"]) {
                                window.LaravelDataTables["dataTableBuilder"].draw(false);
                            }

                            this.$refs.money && this.$refs.money.hide();
                        })
                        .catch(exception => {
                            console.log('error', exception);
                            if (typeof this.toggleButtonDisable === 'function') {
                                this.toggleButtonDisable(false);
                            }
                        });
                },

                pointSubmit(event) {
                    event && event.preventDefault && event.preventDefault();
                    if (typeof this.toggleButtonDisable === 'function') {
                        this.toggleButtonDisable(true);
                    }

                    this.$http.post("{{ route('admin.member.setpoint') }}", this.formpoint)
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

                            if (window.LaravelDataTables && window.LaravelDataTables["dataTableBuilder"]) {
                                window.LaravelDataTables["dataTableBuilder"].draw(false);
                            }

                            this.$refs.point && this.$refs.point.hide();
                        })
                        .catch(exception => {
                            console.log('error', exception);
                            if (typeof this.toggleButtonDisable === 'function') {
                                this.toggleButtonDisable(false);
                            }
                        });
                },

                diamondSubmit(event) {
                    event && event.preventDefault && event.preventDefault();
                    if (typeof this.toggleButtonDisable === 'function') {
                        this.toggleButtonDisable(true);
                    }

                    this.$http.post("{{ route('admin.member.setdiamond') }}", this.formdiamond)
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

                            if (window.LaravelDataTables && window.LaravelDataTables["dataTableBuilder"]) {
                                window.LaravelDataTables["dataTableBuilder"].draw(false);
                            }

                            this.$refs.diamond && this.$refs.diamond.hide();
                        })
                        .catch(exception => {
                            console.log('error', exception);
                            if (typeof this.toggleButtonDisable === 'function') {
                                this.toggleButtonDisable(false);
                            }
                        });
                },

                openDeleteModal(code) {
                    // popup confirm
                    this.$bvModal.msgBoxConfirm(
                        'คุณต้องการลบรายการนี้ใช่หรือไม่?',
                        {
                            title: 'โปรดยืนยันการทำรายการ',
                            size: 'sm',
                            okVariant: 'danger',
                            okTitle: 'ลบรายการ',
                            cancelTitle: 'ยกเลิก',
                            footerClass: 'p-2',
                            centered: true,
                        }
                    ).then(value => {
                        if (!value) {
                            return;
                        }

                        // ยิง API ลบรายการ
                        axios.post("{{ route('admin.bank_in.delete') }}", {
                            id: code
                        })
                            .then(response => {
                                this.$bvModal.msgBoxOk(response.data.message, {
                                    title: 'ผลการดำเนินการ',
                                    size: 'sm',
                                    buttonSize: 'sm',
                                    okVariant: 'success',
                                    headerClass: 'p-2 border-bottom-0',
                                    footerClass: 'p-2 border-top-0',
                                    centered: true,
                                });

                                // refresh datatable ถ้ามี
                                if (window.LaravelDataTables && window.LaravelDataTables["deposittable"]) {
                                    window.LaravelDataTables["deposittable"].draw(false);
                                }
                            })
                            .catch(error => {
                                this.$bvModal.msgBoxOk('เกิดข้อผิดพลาด ไม่สามารถลบรายการได้', {
                                    title: 'ข้อผิดพลาด',
                                    size: 'sm',
                                    buttonSize: 'sm',
                                    okVariant: 'danger',
                                    centered: true,
                                });
                            });
                    })
                        .catch(err => {
                            console.warn('Cancel delete', err);
                        });
                },

                /* -----------------------------------
                 * ALIAS ชื่อเดิม ให้ของเก่าไม่พัง
                 * ----------------------------------- */

                // clearModal(code) เดิม → ใช้ชื่อใหม่
                clearModal(code) {
                    this.openClearRemarkModal(code);
                },
                removeFocusFromTrigger() {
                    // ลอง blur องค์ประกอบที่กำลัง focus อยู่ตอนนี้
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }
                },
                // refill() เดิม
                refill() {
                    this.openRefillModal();
                },

                // addModal() เดิม → เปิด assign modal โดยไม่ผูกกับบิลเดิม
                addModal() {
                    this.openAssignTopupTargetModal(null);
                },

                // editModal(code) เดิม
                editModal(code) {
                    this.openAssignTopupTargetModal(code);
                },

                // refillSubmit() เดิม
                refillSubmit(event) {
                    this.submitRefillForm(event);
                },

                // addEditSubmitNew() เดิม
                addEditSubmitNew(event) {
                    this.submitAssignTopupTarget(event);
                },

                // loadUser() เดิม
                loadUser() {
                    this.loadUserForAssignTarget();
                },

                // loadUserRefill() เดิม
                loadUserRefill() {
                    this.loadUserForRefill();
                },

                // NEW: alias money/point/diamond แบบเดิม
                money(prefill = null) {
                    this.openMoneyModal(prefill);
                },

                point(prefill = null) {
                    this.openPointModal(prefill);
                },

                diamond(prefill = null) {
                    this.openDiamondModal(prefill);
                },
            },
        });
    </script>

@endpush
