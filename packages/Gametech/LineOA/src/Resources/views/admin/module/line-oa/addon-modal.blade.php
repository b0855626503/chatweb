<!-- Modal เลือก Quick Reply -->
<b-modal
        id="quick-reply-modal"
        ref="quickReplyModal"
        title="เลือกข้อความตอบกลับ"
        size="md"
        centered
        :no-close-on-backdrop="true"
        @hidden="onQuickReplyModalHidden"
>
    <div v-if="quickRepliesLoading" class="text-center my-4">
        <b-spinner small></b-spinner>
        <span class="ml-2">กำลังโหลดข้อความตอบกลับ...</span>
    </div>

    <div v-else>
        <!-- แถบค้นหา + ปุ่มเพิ่มข้อความตอบกลับ -->
        <div class="mb-3 d-flex">
            <b-form-input
                    v-model="quickReplySearch"
                    placeholder="ค้นหาข้อความตอบกลับ..."
                    size="sm"
                    class="flex-grow-1 mr-2"
            ></b-form-input>

            <b-button
                    variant="outline-primary"
                    size="sm"
                    @click="openQuickReplyCreateModal"
            >
                <i class="fa fa-plus"></i> เพิ่มข้อความ
            </b-button>
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
            ไม่พบข้อความตอบกลับที่ใช้ได้
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
                        @click="sendQuickReplyToText"
                >
                    <span v-if="sendingQuickReply">
                        <b-spinner small class="mr-1"></b-spinner> กำลังส่ง...
                    </span>
                    <span v-else>
                        เลือก
                    </span>
                </b-button>
            </div>
        </div>
    </template>
</b-modal>

<!-- MODAL: เพิ่มข้อความตอบกลับใหม่จากหน้าแชต -->
<b-modal
        id="quick-reply-add-modal"
        ref="quickReplyAddModal"
        title="เพิ่มข้อความตอบกลับ"
        size="lg"
        centered
        hide-footer
        :no-close-on-backdrop="true"
        @hidden="resetQuickReplyForm"

>
    <b-form @submit.prevent="submitQuickReplyForm">
        <!-- หมวดหมู่ (fix เป็น quick_reply) -->
        <b-form-group
                label="หมวดหมู่ข้อความ:"
                label-for="qr-category"
        >
            <b-form-input
                    id="qr-category"
                    value="ข้อความตอบกลับ (quick_reply)"
                    size="sm"
                    disabled
            ></b-form-input>
        </b-form-group>

        <!-- DESCRIPTION -->
        <b-form-group
                label="ชื่อ:"
                label-for="qr-description"
        >
            <b-form-input
                    id="qr-description"
                    v-model="quickReplyForm.description"
                    size="sm"
                    autocomplete="off"
            ></b-form-input>
        </b-form-group>
        <div class="chat-reply-wrapper">
        <div
                v-if="showEmojiPickerModal"
                class="emoji-overlay-chat"
                ref="emojiPopupModal"
        >
            <emoji-picker
                    :data="emojiIndex"
                    :show-preview="false"
                    :show-skin-tones="false"
                    :emoji-size="20"
                    :per-line="8"
                    @select="onEmojiSelect"
            />
        </div>
        <!-- MESSAGE -->
        <b-form-group
                label="ข้อความ:"
                label-for="qr-message"
                description="ข้อความที่ส่งถึงลูกค้า (รองรับตัวแปร {display_name}, {username}, ...)"
        >
            <b-form-textarea
                    id="qr-message"
                    v-model="quickReplyForm.message"
                    ref="quickReplyMessageInput"
                    size="sm"
                    rows="3"
                    max-rows="6"
                    autocomplete="off"
                    required
                    class="no-resize"
            ></b-form-textarea>

            <!-- ปุ่มใส่ placeholder -->
            <div class="mt-2">
                <span class="text-muted mr-2">ตัวแปรที่ใช้ได้:</span>
                <b-button-group size="sm">
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{display_name}')"
                    >
                        {ชื่อแชตไลน์}
                    </b-button>
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{username}')"
                    >
                        {ไอดีเข้าเวบ}
                    </b-button>
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{phone}')"
                    >
                        {เบอร์โทร}
                    </b-button>
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{bank_name}')"
                    >
                        {ชื่อธนาคาร}
                    </b-button>
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{account_no}')"
                    >
                        {เลขบัญชี}
                    </b-button>
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{game_user}')"
                    >
                        {ไอดีเกม}
                    </b-button>
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{site_name}')"
                    >
                        {ชื่อเวบ}
                    </b-button>
                    <b-button
                            variant="outline-secondary"
                            @click.prevent="insertQuickReplyPlaceholder('{login_url}')"
                    >
                        {ทางเข้าเล่น}
                    </b-button>
                </b-button-group>
            </div>

            <!-- ปุ่มเปิด Emoji picker (ใช้ตัว teleport กลาง) -->

            <div class="mt-2">
        <span class="text-muted small">
            กด Emoji เพื่อแทรกลงในข้อความตอบกลับ
        </span>
                <b-button
                        ref="quickReplyEmojiBtn"
                        size="sm"
                        variant="outline-secondary"
                        @click.prevent="openEmojiPickerForQuickReply"
                >
                    😀 Emoji
                </b-button>
            </div>
        </b-form-group>
        </div>

        <!-- ENABLED -->
        <b-form-group label="สถานะการใช้งาน:">
            <b-form-checkbox
                    v-model="quickReplyForm.enabled"
                    switch
                    size="lg"
            >
                เปิดใช้งานข้อความนี้
            </b-form-checkbox>
        </b-form-group>

        <div v-if="quickReplySaveError" class="text-danger small mb-2">
            @{{ quickReplySaveError }}
        </div>

        <div class="text-right">
            <b-button
                    type="button"
                    variant="outline-secondary"
                    size="sm"
                    @click="$refs.quickReplyAddModal.hide()"
            >
                ยกเลิก
            </b-button>
            <b-button
                    type="submit"
                    variant="primary"
                    size="sm"
                    class="ml-2"
                    :disabled="quickReplySaving"
            >
                <span v-if="quickReplySaving">
                    <b-spinner small class="mr-1"></b-spinner> กำลังบันทึก...
                </span>
                <span v-else>
                    บันทึกข้อความตอบกลับ
                </span>
            </b-button>
        </div>
    </b-form>
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

        {{-- ถ้าเป็นโหมด username ให้มีช่อง username แยก --}}
        <b-form-group
                v-if="registerMode === 'username'"
                label="ยูสเซอร์เนม (ใช้ล็อกอิน)"
                label-for="reg_username"
        >
            <b-form-input
                    id="reg_username"
                    v-model="registerModal.username"
                    autocomplete="off"
                    minlength="5"
                    maxlength="10"
                    class="text-lowercase"
                    @input="onUsernameInput"
            ></b-form-input>
            <!-- กำลังตรวจสอบ Username -->
            <small v-if="registerModal.checkingUsername"
                   class="d-block mt-1 text-info">
                กำลังตรวจสอบ Username...
            </small>

            <!-- สถานะ Username: ถูกต้อง/ซ้ำ/ไม่ถูกต้อง -->
            <small v-else-if="registerModal.usernameStatusMessage"
                   class="d-block mt-1"
                   :class="usernameStatusClass">
                @{{ registerModal.usernameStatusMessage }}
            </small>
        </b-form-group>

        {{-- เบอร์โทร:
             - โหมด phone = ใช้เป็น username ด้วย → บังคับกรอกและ validate เข้ม
             - โหมด username = ใช้เป็นข้อมูลติดต่อ/ยืนยันตัวตน → จะบังคับหรือไม่ก็แล้วแต่ policy --}}
        <b-form-group label="เบอร์โทร" label-for="reg_phone">
            <b-form-input
                    id="reg_phone"
                    type="tel"
                    ref="registerPhoneInput"
                    pattern="[0-9]*" inputmode="numeric"
                    :minlength="phoneConfig.min_length"
                    :maxlength="phoneConfig.max_length"
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
    <div class="chat-reply-wrapper">
        <div
                v-if="showEmojiPickerNoteModal"
                class="emoji-overlay-chat"
                ref="emojiPopupNoteModal"
        >
            <emoji-picker
                    :data="emojiIndex"
                    :show-preview="false"
                    :show-skin-tones="false"
                    :emoji-size="20"
                    :per-line="8"
                    @select="onEmojiSelect"
            />
        </div>
    <b-form-textarea
            ref="noteMessageInput"
            v-model="noteModalText"
            rows="4"
            class="no-resize"
            max-rows="6"
            placeholder="พิมพ์โน้ตสำหรับเคสนี้..."
    ></b-form-textarea>
    </div>
    <!-- ปุ่มเปิด Emoji picker (ใช้ตัว teleport กลาง) -->

    <div class="mt-2">
        <span class="text-muted small">
            กด Emoji เพื่อแทรกลงในข้อความตอบกลับ
        </span>
        <b-button
                ref="noteEmojiBtn"
                size="sm"
                variant="outline-secondary"
                @click.prevent="openEmojiPickerForNote"
        >
            😀 Emoji
        </b-button>
    </div>

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

<b-modal
        id="assignee-modal"
        ref="assigneeModal"
        title="เลือกผู้รับผิดชอบเคสนี้"
        size="md"
        centered
        :no-close-on-backdrop="true"
>
    <!-- Loading -->
    <div v-if="assigneeLoading" class="text-center my-4">
        <b-spinner small></b-spinner>
        <span class="ml-2">กำลังโหลดรายชื่อพนักงาน...</span>
    </div>

    <!-- Content -->
    <div v-else>
        <!-- search -->
        <div class="mb-2">
            <b-form-input
                    v-model="assigneeSearch"
                    size="sm"
                    autocomplete="off"
                    placeholder="ค้นหาชื่อ / รหัสพนักงาน / user_name"
            ></b-form-input>
        </div>

        <!-- list -->
        <div
                class="list-group assignee-list"
                v-if="filteredAssignees.length"
        >
            <button
                    v-for="emp in filteredAssignees"
                    :key="emp.code"
                    type="button"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                    :class="{ active: selectedAssigneeId === emp.code }"
                    @click="selectedAssigneeId = emp.code"
            >
                <div>
                    <div class="font-weight-bold">
                        @{{ emp.display }}
                    </div>
                    <div class="small text-muted">
                        @{{ emp.sub || '' }}
                    </div>
                </div>

                <span v-if="selectedAssigneeId === emp.code" class="badge badge-light">
                    <i class="fa fa-check"></i>
                </span>
            </button>
        </div>

        <div v-else class="text-muted text-center my-4">
            ไม่พบพนักงานที่สามารถเลือกได้
        </div>
    </div>

    <template #modal-footer>
        <div class="w-100 d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                เลือกพนักงาน 1 คนเพื่อเป็นผู้รับผิดชอบเคสนี้
            </div>
            <div>
                <b-button
                        variant="outline-secondary"
                        size="sm"
                        @click="$refs.assigneeModal.hide()"
                >
                    ยกเลิก
                </b-button>
                <b-button
                        variant="primary"
                        size="sm"
                        class="ml-2"
                        :disabled="savingAssignee"
                        @click="saveAssignee"
                >
                    <span v-if="savingAssignee">
                        <b-spinner small class="mr-1"></b-spinner> กำลังบันทึก...
                    </span>
                    <span v-else>
                        บันทึกผู้รับผิดชอบ
                    </span>
                </b-button>
            </div>
        </div>
    </template>
</b-modal>

{{-- ✅ STICKER MODAL --}}
<b-modal id="line-oa-sticker-modal"
         ref="stickerModal"
         title="เลือกสติกเกอร์"
         size="lg"
         hide-footer>

    <!-- เลือกชุดสติกเกอร์จาก config -->
    <div class="mb-3" v-if="stickerPackOptions.length">
        <b-form-select
                v-model="selectedStickerPackId"
                :options="stickerPackOptions">
        </b-form-select>
    </div>

    <div v-if="!activePack">
        <div class="text-muted text-center py-3">
            ยังไม่ได้กำหนดชุดสติกเกอร์ใน config/line_oa_stickers.php
        </div>
    </div>

    <div v-else>
        <b-row>
            <b-col cols="3"
                   v-for="stickerId in activeStickers"
                   :key="activePackageId + ':' + stickerId"
                   class="mb-3 text-center">
                <div class="gt-sticker-item"
                     @click="selectStickerFromPack(activePackageId, stickerId)"
                     style="cursor: pointer;">
                    <img
                            :src="buildStickerThumbnailUrl(stickerId)"
                            class="img-fluid mb-1"
                            style="max-height: 100px; object-fit: contain;">
                </div>
            </b-col>
        </b-row>

        <div v-if="!activeStickers.length" class="text-muted text-center py-3">
            ชุดสติกเกอร์นี้ยังไม่ได้กำหนดรายการ stickerId
        </div>
    </div>
</b-modal>

<b-modal
        id="member-adjust-modal"
        ref="memberAdjustModal"
        title="เพิ่ม / ลด ยอดคงเหลือ"
        centered
        size="md"
        :no-close-on-backdrop="true"
        :hide-footer="true"
>
    <div class="adjust-card-wrapper">

        <!-- ยอดเงิน -->
        <b-button
                class="adjust-card adjust-money"
                @click="openAdjust('money')"
        >
            <div class="adjust-card-icon">
                <i class="fa fa-coins"></i>
            </div>
            <div class="adjust-card-content">
                <div class="adjust-card-title">ยอดเงิน</div>
                <div class="adjust-card-sub">เพิ่ม/ลด เงินในกระเป๋าหลัก</div>
            </div>
        </b-button>

        <!-- Points -->
        <b-button
                class="adjust-card adjust-point"
                @click="openAdjust('point')"
        >
            <div class="adjust-card-icon">
                <i class="fa fa-star"></i>
            </div>
            <div class="adjust-card-content">
                <div class="adjust-card-title">Points</div>
                <div class="adjust-card-sub">จัดการคะแนนสะสม</div>
            </div>
        </b-button>

        <!-- Diamond -->
        <b-button
                class="adjust-card adjust-diamond"
                @click="openAdjust('diamond')"
        >
            <div class="adjust-card-icon">
                <i class="fa fa-gem"></i>
            </div>
            <div class="adjust-card-content">
                <div class="adjust-card-title">Diamond</div>
                <div class="adjust-card-sub">เพิ่ม/ลด เพชรพิเศษ</div>
            </div>
        </b-button>

    </div>
</b-modal>


<b-modal
        id="member-log-modal"
        ref="memberLogModal"
        title="ประวัติ"
        centered
        size="sm"
        :no-close-on-backdrop="true"
        :hide-footer="true"
>
    <div class="log-row">

        <b-button
                class="log-card log-card-deposit"
                @click="openLog('deposit')"
        >
            <div class="log-icon">
                <i class="fa fa-history"></i>
                <i class="fa fa-arrow-up"></i>
            </div>
            <div class="log-text">ฝาก</div>
        </b-button>

        <b-button
                class="log-card log-card-withdraw"
                @click="openLog('withdraw')"
        >
            <div class="log-icon">
                <i class="fa fa-history"></i>
                <i class="fa fa-arrow-down"></i>
            </div>
            <div class="log-text">ถอน</div>
        </b-button>

    </div>
</b-modal>







