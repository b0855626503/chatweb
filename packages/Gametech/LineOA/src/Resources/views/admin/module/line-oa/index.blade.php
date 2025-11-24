@extends('admin::layouts.line-oa')

{{-- page title --}}
@section('title')
    {{ $menu->currentName }}
@endsection

@section('content')
    <section class="content text-xs">
        <div class="card">
            <div class="card-body">
                <div id="line-oa-chat-app">
                    <line-oa-chat></line-oa-chat>
                </div>
            </div>
        </div>
    </section>

    <div id="line-oa-chat-overlay" v-if="showLineChat">
        <div class="lineoa-backdrop" @click="closeLineChat"></div>

        <div class="lineoa-popup">
            <line-oa-chat :is-overlay="true"></line-oa-chat>
        </div>
    </div>
@endsection

@push('styles')
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
    </style>
@endpush

@push('scripts')

    {{-- ชื่อ channel เดียวกับที่ใช้ใน Echo.channel('{{ config('app.name') }}_events') --}}
    <script>
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
                                            <div class="text-muted small text-truncate">
                                            <span v-if="conv.line_account && conv.line_account.name">
                                                [@{{ conv.line_account.name }}]
                                            </span>
                                                @{{ conv.last_message || 'ยังไม่มีข้อความ' }}
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <div>
                                                    <small class="text-muted d-block">
                                                        ยูส: @{{ conv.contact && conv.contact.member_username || '-' }}
                                                    </small>

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
                                            <small class="text-muted d-block" v-if="selectedConversation.line_account">
                                                OA: @{{ selectedConversation.line_account.name }}
                                            </small>

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
                                                            variant="outline-warning"
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
                                                            class="mb-1 mr-3"
                                                            @click="openTopupModal"
                                                    >
                                                        เติมเงิน
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

                                <b-form-textarea
                                        ref="replyBox"
                                        v-model="replyText"
                                        rows="1"
                                        max-rows="4"
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

            {{-- MODAL: ผูก contact กับ member --}}
            <b-modal
                    id="line-oa-member-modal"
                    ref="memberModal"
                    title="เชื่อมลูกค้ากับสมาชิก"
                    size="md"
                    centered
                    hide-footer
                    body-class="pt-2 pb-2"
                    @hide="resetMemberModal"
                    @shown="onMemberModalShown"
            >
                <b-form @submit.prevent="saveMemberLink">
                    <b-form-group label="Username:" label-for="member_id" label-cols="4" label-class="pt-1">
                        <b-input-group>
                            <b-form-input
                                    id="member_id"
                                    ref="memberIdInput"
                                    v-model="memberModal.member_id"
                                    placeholder=""
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
                        <div class="small">
                            <div><strong>ชื่อจริง:</strong> @{{ memberModal.member.name || '-' }}</div>
                            <div><strong>Username:</strong> @{{ memberModal.member.username || '-' }}</div>
                            <div><strong>เบอร์:</strong> @{{ memberModal.member.mobile || '-' }}</div>
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
                    size="md"
                    centered
                    hide-footer
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
            <b-modal
                    id="line-oa-topup-modal"
                    ref="topupModal"
                    title="เติมเงิน"
                    size="xl"
                    centered
                    hide-footer
                    body-class="pt-2 pb-2"
            >
                <b-row>
                    <b-col cols="12" md="4" class="border-right">
                        <h6 class="mb-2">รายการรอเติม</h6>
                        <div class="small text-muted mb-2">
                            เลือกรายการที่ต้องการเติม หรือสร้างรายการใหม่ทางฝั่งขวา
                        </div>

                        <div v-if="!topupModal.pendingItems.length" class="text-muted small">
                            ยังไม่มีรายการรอเติม
                        </div>
                        <b-list-group v-else flush>
                            <b-list-group-item
                                    v-for="item in topupModal.pendingItems"
                                    :key="item.id"
                                    href="#"
                                    class="py-1"
                                    :active="topupModal.selectedItem && topupModal.selectedItem.id === item.id"
                                    @click.prevent="selectTopupItem(item)"
                            >
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="font-weight-bold">
                                            @{{ item.amount }} ฿
                                        </div>
                                        <small class="text-muted">
                                            @{{ item.reference || 'ไม่ระบุอ้างอิง' }}
                                        </small>
                                    </div>
                                    <div class="text-right">
                                        <small class="d-block text-muted">@{{ item.created_at }}</small>
                                    </div>
                                </div>
                            </b-list-group-item>
                        </b-list-group>
                    </b-col>
                    <b-col cols="12" md="8">
                        <h6 class="mb-2">ข้อมูลการเติมเงิน</h6>

                        <b-form-group label="ไอดีสมาชิก" label-for="topup_member" label-cols="4" label-class="pt-1">
                            <b-input-group>
                                <b-form-input
                                        id="topup_member"
                                        v-model="topupModal.memberSearch"
                                        autocomplete="off"
                                ></b-form-input>
                                <b-input-group-append>
                                    <b-button size="sm" variant="outline-primary" @click="searchTopupMember">
                                        ค้นหา
                                    </b-button>
                                </b-input-group-append>
                            </b-input-group>
                            <small class="form-text text-muted">
                                กรอกไอดีแล้วกดค้นหาเพื่อโหลดข้อมูลสมาชิก
                            </small>
                        </b-form-group>

                        <b-card v-if="topupModal.member" class="mb-2" body-class="py-2 px-2">
                            <div class="small">
                                <div>ยูส: @{{ topupModal.member.username }}</div>
                                <div>เบอร์: @{{ topupModal.member.mobile }}</div>
                                <div>ชื่อ: @{{ topupModal.member.name }}</div>
                                <div>ธนาคาร: @{{ topupModal.member.bank_name }}</div>
                                <div>เลขบัญชี: @{{ topupModal.member.acc_no }}</div>
                            </div>
                        </b-card>

                        <b-alert
                                v-if="topupModal.error"
                                show
                                variant="danger"
                                class="py-1 mb-2"
                        >
                            @{{ topupModal.error }}
                        </b-alert>

                        <template v-if="topupModal.selectedItem">
                            <b-alert show variant="info" class="py-1 mb-2">
                                กำลังเติมจากรายการที่เลือก:
                                ยอด @{{ topupModal.selectedItem.amount }} ฿
                            </b-alert>
                        </template>

                        <template v-else>
                            <b-form-group label="ธนาคารที่เติม" label-for="topup_bank" label-cols="4"
                                          label-class="pt-1">
                                <b-form-input
                                        id="topup_bank"
                                        v-model="topupModal.bank"
                                        autocomplete="off"
                                ></b-form-input>
                            </b-form-group>

                            <b-form-group label="จำนวนเงิน" label-for="topup_amount" label-cols="4" label-class="pt-1">
                                <b-form-input
                                        id="topup_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        v-model="topupModal.amount"
                                ></b-form-input>
                            </b-form-group>
                        </template>

                        <div class="text-right mt-3">
                            <b-button size="sm" variant="secondary" @click="$refs.topupModal.hide()">
                                ปิด
                            </b-button>
                            <b-button size="sm" variant="primary" class="ml-1" type="button" @click="submitTopup"
                                      :disabled="topupModal.loading">
                                <b-spinner v-if="topupModal.loading" small class="mr-1"></b-spinner>
                                <span v-else>เติมเงิน</span>
                            </b-button>
                        </div>
                    </b-col>
                </b-row>
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
                        {value: 'open', text: 'ห้องเปิดอยู่'},
                        {value: 'closed', text: 'ห้องปิดแล้ว'},
                    ],
                    accountOptions: [],
                    bankOptions: [],
                    currentActiveConversationId: null,
                    loadingList: false,
                    selectedConversation: null,
                    messages: [],
                    loadingMessages: false,
                    replyText: '',
                    sending: false,
                    uploadingImage: false,
                    autoRefreshTimer: null,

                    // debounce การค้นหา
                    searchDelayTimer: null,

                    // modal ผูก member
                    memberModal: {
                        member_id: '',
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

                    bankAccountCheckTimer: null,
                    // modal เติมเงิน
                    topupModal: {
                        pendingItems: [],
                        selectedItem: null,
                        memberSearch: '',
                        member: null,
                        bank: '',
                        amount: null,
                        loading: false,
                        error: '',
                    },

                    // จะ set เป็น function ใน subscribeRealtime()
                    unsubscribeRealtime: null,
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
                    if (!conv.assigned_employee_id) return false;

                    const me = this.currentEmployeeId;
                    if (!me) return false;

                    // ถ้ามีการล็อกห้อง → ให้เฉพาะคนล็อกตอบได้
                    if (conv.locked_by_employee_id) {
                        return String(conv.locked_by_employee_id) === String(me);
                    }

                    // ถ้าไม่มีการล็อก → ให้เฉพาะผู้รับเรื่องตอบได้
                    return String(conv.assigned_employee_id) === String(me);
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
                apiUrl(path) {
                    return '/admin/line-oa/' + path.replace(/^\/+/, '');
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
                    if (conv.status !== 'assigned') return false;

                    // ต้องมีคนรับเรื่อง (assigned_employee_id)
                    if (!conv.assigned_employee_id) return false;

                    if (!this.currentEmployeeId) return false;

                    // อนุญาตเฉพาะคนที่เป็นคนรับเรื่อง
                    return String(conv.assigned_employee_id) === String(this.currentEmployeeId);
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
                        return text.length > 100 ? text.substr(0, 97) + '...' : text;
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
                // ====== modal: ผูก contact กับ member ======
                openMemberModal() {
                    if (!this.selectedConversation || !this.selectedConversation.contact) {
                        return;
                    }

                    this.memberModal.error = '';
                    this.memberModal.member = null;
                    this.memberModal.member_id = '';

                    this.$nextTick(() => {
                        if (this.$refs.memberModal) {
                            this.$refs.memberModal.show();
                        }
                    });
                },

                resetMemberModal() {
                    this.memberModal = {
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
            }
        });

    </script>

    <script type="module">

        Vue.mixin({
            data() {
                return {
                    showLineChat: false,
                    lineChatActiveConversationId: null,
                };
            },

            methods: {
                openLineChat(id = null) {
                    console.log('openLineChat', id);
                    this.showLineChat = true;
                    this.lineChatActiveConversationId = id;
                },
                closeLineChat() {
                    this.showLineChat = false;
                    this.lineChatActiveConversationId = null;
                },
            },
        });

    </script>
@endpush
