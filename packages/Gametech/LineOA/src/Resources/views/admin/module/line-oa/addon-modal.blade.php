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

{{-- MODAL: ดูยอดเงิน --}}
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

{{-- MODAL: Note --}}
<b-modal
        id="note-modal"
        ref="noteModal"
        :title="noteModalMode === 'create' ? 'เพิ่มโน้ต' : 'แก้ไขโน้ต'"
        centered
        size="md"
        :no-close-on-backdrop="noteModalSaving"
        :hide-header-close="noteModalSaving"
>
    <b-form-textarea
            v-model="noteModalText"
            rows="4"
            max-rows="6"
            placeholder="พิมพ์โน้ตสำหรับเคสนี้..."
    ></b-form-textarea>

    <template #modal-footer="{ ok, cancel }">
        <b-button
                variant="secondary"
                @click="noteModalSaving ? null : cancel()"
                :disabled="noteModalSaving"
        >
            ยกเลิก
        </b-button>

        <b-button
                variant="primary"
                @click="saveNoteModal"
                :disabled="noteModalSaving || !noteModalText.trim()"
        >
            <b-spinner
                    v-if="noteModalSaving"
                    small
                    class="mr-1"
            ></b-spinner>
            บันทึก
        </b-button>
    </template>
</b-modal>
