@extends('admin::layouts.line-oa')

{{-- page title --}}
@section('title')
    {{ $menu->currentName }}
@endsection

@section('css')
    @include('admin::layouts.datatables_css')
@endsection

@include('admin::module.line-oa.css')

@section('content')
    <section class="content text-xs">
        <div class="card">
            <div class="card-body">
                <div id="line-oa-chat-app" class="line-chat-font">
                    <line-oa-chat ref="lineOaChat"></line-oa-chat>
                </div>
                @include('admin::module.line-oa.member-edt-app')
                @include('admin::module.line-oa.member-refill-app')

            </div>
        </div>
    </section>

@endsection


@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"
            integrity="sha512-U2WE1ktpMTuRBPoCFDzomoIorbOyUv0sP8B+INA3EzNAhehbzED1rOJg6bCqPf/Tuposxb5ja/MAUnC8THSbLQ=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>


    <script>
        (function () {
            $('body').addClass('sidebar-collapse');

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
        <div class="line-oa-chat-page">
            <b-container fluid class="px-0 h-100">
                <b-row no-gutters class="h-100 align-items-stretch">
                    {{-- ====== LEFT: CONVERSATION LIST ====== --}}
                    <b-col cols="12"
                           md="3"
                           class="border-right line-oa-col line-oa-sidebar">
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
                                            <b-badge variant="primary" v-if="filters.status === 'open'">ทั้งหมด
                                            </b-badge>
                                            <b-badge variant="info" v-else-if="filters.status === 'assigned'">ดำเนินการ
                                            </b-badge>
                                            <b-badge variant="secondary" v-else>เสร็จสิ้น</b-badge>
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
                                        ที่รับผิดชอบ
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
{{--                                            @change="fetchConversations(1,{ silent : true})"--}}
                                    ></b-form-select>

                                    <b-form-select
                                            v-model="filters.account_id"
                                            :options="accountOptions"
                                            size="sm"
{{--                                            @change="fetchConversations(1,{ silent : true})"--}}
                                    >
                                        <template #first>
                                            <option :value="null">ทุก OA</option>
                                        </template>
                                    </b-form-select>
                                </div>
                            </div>

                            {{-- LIST --}}
                            <div
                                    class="flex-fill overflow-auto"
                                    ref="conversationList"
                                    @scroll="onConversationListScroll">

                                <!-- loading รอบแรก -->
                                <div v-if="loadingList && !conversations.length" class="text-center my-3 text-muted">
                                    <b-spinner small class="mr-1"></b-spinner> กำลังโหลดห้องแชต...
                                </div>

                                <!-- ไม่มีรายการ -->
                                <div v-else-if="!conversations.length" class="text-center text-muted my-3 small">
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
                                                        @{{ formatMessageDate(conv.last_message_at) }}
                                                    </small>
                                                </div>
                                                <!-- แสดงสถานะสมัครกับบอท -->
                                                <div v-if="conv.is_registering" class="mt-1">
                                                    <b-badge variant="warning" class="text-dark oa-reg-badge">
                                                        <i class="fa fa-robot"></i>
                                                        กำลังสมัครสมาชิกกับบอท
                                                    </b-badge>
                                                </div>
                                                <div class="text-muted no-x-scroll text-truncate fixed-line">
                                            <span v-if="conv.line_account && conv.line_account.name">
                                                [@{{ conv.line_account.name }}]
                                            </span>
                                                    @{{ conv.last_message || 'ยังไม่มีข้อความ' }}
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <div>
                                                        <p class="text-muted d-block mb-1">
                                                            ยูส: @{{ conv.contact && conv.contact.member_username || '-'
                                                            }}
                                                        </p>

                                                        {{-- แสดงชื่อคนปิด + เวลา ถ้าห้องปิดแล้ว --}}
                                                        {{--                                                        <div--}}
                                                        {{--                                                                v-if="conv.status === 'closed'"--}}
                                                        {{--                                                                class="text-muted small"--}}
                                                        {{--                                                        >--}}
                                                        {{--                                                            ปิดโดย @{{ conv.closed_by_employee_name || 'พนักงาน' }}--}}
                                                        {{--                                                            <span v-if="conv.closed_at">--}}
                                                        {{--                                                        เมื่อ @{{ formatDateTime(conv.closed_at) }}--}}
                                                        {{--                                                    </span>--}}
                                                        {{--                                                        </div>--}}
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <b-badge
                                                                v-if="conv.status === 'assigned'"
                                                                variant="info"
                                                                class="mr-1"
                                                        >
                                                            ดำเนินการ
                                                        </b-badge>
                                                        <b-badge
                                                                v-if="conv.status === 'closed'"
                                                                variant="success"
                                                                class="mr-1"
                                                        >
                                                            เสร็จสิ้น
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

                            <!-- loading เพิ่มตอนเลื่อนลง -->
                            <div v-if="loadingMore" class="text-center my-2 text-muted small">
                                <b-spinner small class="mr-1"></b-spinner> กำลังโหลดเพิ่ม...
                            </div>

                            <!-- ข้อความท้ายสุดเมื่อโหลดครบทุกหน้าแล้ว -->
                            <div
                                    v-if="!loadingMore && !loadingList && pagination.current_page >= pagination.last_page && conversations.length"
                                    class="text-center text-muted tiny my-2"
                            >
                                แสดงครบ @{{ conversations.length }} ห้องแล้ว
                            </div>
                        </div>
                    </b-col>

                    {{-- ====== MIDDLE: CHAT WINDOW ====== --}}
                    <b-col cols="12" md="6" class="chat-middle-col line-oa-col">

                        <div class="d-flex flex-column h-85">

                            {{-- HEADER (ย่อให้คล้าย LINE OA) --}}
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
                                            <h3 class="mb-0">
                                        <span
                                                class="text-primary"
                                                style="cursor: pointer; text-decoration: underline;"
                                                @click="openMemberModal"
                                        >
                                            @{{ (selectedConversation.contact &&
                                            (selectedConversation.contact.display_name ||
                                            selectedConversation.contact.member_username)) || 'ไม่ทราบชื่อ' }}
                                        </span>
                                            </h3>
                                        </div>
                                        <div class="text-muted small mt-1" v-if="selectedConversation.line_account">
                                            OA: @{{ selectedConversation.line_account.name }}
                                        </div>
                                    </div>
                                    <div class="text-right ml-3">

                                        {{--                                        <div class="mb-1">--}}
                                        {{--                                            <b-badge--}}
                                        {{--                                                    v-if="selectedConversation.status === 'closed'"--}}
                                        {{--                                                    variant="secondary"--}}
                                        {{--                                                    class="mr-1"--}}
                                        {{--                                            >--}}
                                        {{--                                                ปิดโดย @{{ selectedConversation.closed_by_employee_name || 'พนักงาน' }}--}}
                                        {{--                                            </b-badge>--}}
                                        {{--                                            <b-badge--}}
                                        {{--                                                    v-else-if="selectedConversation.assigned_employee_name"--}}
                                        {{--                                                    variant="info"--}}
                                        {{--                                                    class="mr-1"--}}
                                        {{--                                            >--}}
                                        {{--                                                รับเรื่องโดย @{{ selectedConversation.assigned_employee_name }}--}}
                                        {{--                                            </b-badge>--}}
                                        {{--                                        </div>--}}

                                        <div class="d-flex justify-content-end flex-wrap">
                                            <b-button
                                                    v-if="selectedConversation.status === 'open'"
                                                    size="sm"
                                                    variant="outline-primary"
                                                    class="mr-1 mb-1 btn-app"
                                                    @click="acceptConversation"
                                            ><i class="fa fa-list-check"></i>
                                                ต้องดำเนินการ
                                            </b-button>
                                            <b-button
                                                    v-if="selectedConversation.status !== 'closed'"
                                                    size="sm"
                                                    variant="outline-danger"
                                                    class="mr-1 mb-1 btn-app"
                                                    @click="closeConversation"
                                            >
                                                <i class="fa fa-check-circle"></i>
                                                ดำเนินการแล้ว
                                            </b-button>
{{--                                            <b-button--}}
{{--                                                    v-if="selectedConversation.status === 'closed'"--}}
{{--                                                    size="sm"--}}
{{--                                                    variant="outline-danger"--}}
{{--                                                    class="mr-1 mb-1"--}}
{{--                                                    @click="openConversation"--}}
{{--                                            >--}}
{{--                                                เปิดเคส--}}
{{--                                            </b-button>--}}
                                        </div>

                                        <div
                                                class="mt-1 small text-muted"
                                                v-if="selectedConversation.status === 'closed' && selectedConversation.closed_at"
                                        >
                                            ปิดเมื่อ @{{ formatDateTime(selectedConversation.closed_at) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-2 border-bottom bg-light text-muted text-center" v-else>
                                เลือกห้องแชตจากด้านซ้ายเพื่อเริ่มสนทนา
                            </div>

                            {{-- MESSAGE LIST --}}
                            <div class="flex-fill overflow-auto px-2 py-2 chat-message-list" ref="messageContainer">

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

                                        <div v-for="item in messagesWithSeparators"
                                             :key="item.kind === 'date' ? ('d-' + item.dateKey) : ('m-' + item.message.id)"
                                             class="mb-2">

                                            <!-- ========== หัวข้อวันที่คั่นกลาง (05 ธ.ค. / 06 ธ.ค. ฯลฯ) ========== -->
                                            <div v-if="item.kind === 'date'" class="chat-day-separator text-center my-2">
        <span class="badge badge-light px-3 py-1">
            @{{ formatChatDay(item.date) }}
        </span>
                                            </div>

                                            <!-- ========== บับเบิลข้อความ + avatar แบบ LINE ========== -->
                                            <div v-else
                                                 :class="[
            'chat-msg-row',
            item.message.direction === 'inbound' ? 'chat-msg-in' : 'chat-msg-out'
         ]">

                                                <!-- ================= INBOUND (ลูกค้า) ================= -->
                                                <template v-if="item.message.direction === 'inbound'">
                                                    <!-- avatar ลูกค้า -->
                                                    <div class="chat-avatar mr-2">
                                                        <img
                                                                :src="(selectedConversation.contact && selectedConversation.contact.picture_url) || '/images/default-avatar.png'"
                                                                class="chat-avatar-img"
                                                                alt="avatar"
                                                        >
                                                    </div>

                                                    <!-- ส่วนข้อความ -->
                                                    <div class="chat-msg-main">
                                                        <div :class="messageBubbleClass(item.message)">

                                                            <!-- ถ้าเป็นบอท -->
                                                            <div class="small" v-if="item.message.source === 'bot'">
                                                                <strong>บอท</strong>
                                                            </div>

                                                            <div class="whitespace-pre-wrap">
                                                                <!-- TEXT -->
                                                                <template v-if="item.message.type === 'text'">
                                                                    <div class="chat-line-original">
                                <span v-if="getMessageDisplay(item.message).lang"
                                      class="text-primary font-weight-bold mr-1">
                                    [@{{ getMessageDisplay(item.message).lang.toUpperCase() }}]
                                </span>
                                                                        <span>@{{ getMessageDisplay(item.message).original }}</span>
                                                                    </div>

                                                                    <div v-if="getMessageDisplay(item.message).translated"
                                                                         class="chat-line-translated text-muted mt-1">
                                <span v-if="getMessageDisplay(item.message).target"
                                      class="text-success font-weight-bold mr-1">
                                    [@{{ getMessageDisplay(item.message).target.toUpperCase() }}]
                                </span>
                                                                        <span>@{{ getMessageDisplay(item.message).translated }}</span>
                                                                    </div>
                                                                </template>

                                                                <!-- STICKER -->
                                                                <template v-else-if="item.message.type === 'sticker'">
                                                                    <img
                                                                            :src="stickerUrl(item.message)"
                                                                            class="img-fluid"
                                                                            style="max-width:130px;"
                                                                            alt="[Sticker]"
                                                                    >
                                                                </template>

                                                                <!-- IMAGE -->
                                                                <template v-else-if="item.message.type === 'image'">
                                                                    <img
                                                                            :src="item.message.payload?.message?.contentUrl || item.message.payload?.message?.previewUrl"
                                                                            class="img-fluid rounded"
                                                                            style="max-width:240px;"
                                                                            alt="[Image]"
                                                                    >
                                                                </template>

                                                                <!-- VIDEO -->
                                                                <template v-else-if="item.message.type === 'video'">
                                                                    <video
                                                                            controls
                                                                            class="img-fluid rounded"
                                                                            style="max-width:260px;"
                                                                            :poster="item.message.payload?.message?.previewUrl"
                                                                    >
                                                                        <source :src="item.message.payload?.message?.contentUrl">
                                                                    </video>
                                                                </template>

                                                                <!-- AUDIO -->
                                                                <template v-else-if="item.message.type === 'audio'">
                                                                    <audio controls :src="item.message.payload?.message?.contentUrl"></audio>
                                                                </template>

                                                                <!-- LOCATION -->
                                                                <template v-else-if="item.message.type === 'location'
                                             && item.message.payload
                                             && item.message.payload.message">
                                                                    <div>
                                                                        <strong>@{{ item.message.payload.message.title || 'ตำแหน่ง' }}</strong><br>
                                                                        @{{ item.message.payload.message.address }}<br>
                                                                        <a :href="'https://maps.google.com/?q='
                                          + item.message.payload.message.latitude
                                          + ',' + item.message.payload.message.longitude"
                                                                           target="_blank">
                                                                            เปิดแผนที่
                                                                        </a>
                                                                    </div>
                                                                </template>

                                                                <!-- UNSUPPORTED -->
                                                                <template v-else>
                                                                    [@{{ item.message.type }}]
                                                                </template>
                                                            </div>

                                                            <!-- เวลา + ปุ่ม ... -->
                                                            <div class="chat-time-wrapper d-flex align-items-center mt-1">
                        <span class="chat-msg-time text-muted small mr-1">
                            @{{ formatChatTime(item.message.sent_at) }}
                        </span>

                                                                <b-dropdown
                                                                        right
                                                                        size="sm"
                                                                        variant="link"
                                                                        no-caret
                                                                        toggle-class="p-0 chat-msg-menu-toggle"
                                                                >
                                                                    <template #button-content>
                                                                        <i class="fa fa-ellipsis-h"></i>
                                                                    </template>

                                                                    <b-dropdown-item @click="replyToMessage(item.message)">
                                                                        ตอบกลับ
                                                                    </b-dropdown-item>

                                                                    <b-dropdown-item @click="pinMessage(item.message)">
                                                                        ปักหมุด
                                                                    </b-dropdown-item>

                                                                    <b-dropdown-item v-if="item.message.is_pinned"
                                                                                     @click="unpinMessage(item.message)">
                                                                        เลิกปักหมุด
                                                                    </b-dropdown-item>
                                                                </b-dropdown>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- ================= OUTBOUND (พนักงาน) ================= -->
                                                <template v-else>
                                                    <div class="chat-msg-main">
                                                        <div :class="messageBubbleClass(item.message)">

                                                            <div class="small" v-if="item.message.meta && item.message.meta.employee_name">
                                                                <strong>@{{ item.message.meta.employee_name }}</strong>
                                                            </div>

                                                            <div class="whitespace-pre-wrap">
                                                                <!-- TEXT -->
                                                                <template v-if="item.message.type === 'text'">
                                                                    <div class="chat-line-original">
                                <span v-if="getMessageDisplay(item.message).lang"
                                      class="text-primary font-weight-bold mr-1">
                                    [@{{ getMessageDisplay(item.message).lang.toUpperCase() }}]
                                </span>
                                                                        <span>@{{ getMessageDisplay(item.message).original }}</span>
                                                                    </div>

                                                                    <div v-if="getMessageDisplay(item.message).translated"
                                                                         class="chat-line-translated text-muted mt-1">
                                <span v-if="getMessageDisplay(item.message).target"
                                      class="text-success font-weight-bold mr-1">
                                    [@{{ getMessageDisplay(item.message).target.toUpperCase() }}]
                                </span>
                                                                        <span>@{{ getMessageDisplay(item.message).translated }}</span>
                                                                    </div>
                                                                </template>

                                                                <!-- STICKER -->
                                                                <template v-else-if="item.message.type === 'sticker'">
                                                                    <img
                                                                            :src="stickerUrl(item.message)"
                                                                            class="img-fluid"
                                                                            style="max-width:130px;"
                                                                            alt="[Sticker]"
                                                                    >
                                                                </template>

                                                                <!-- IMAGE -->
                                                                <template v-else-if="item.message.type === 'image'">
                                                                    <img
                                                                            :src="item.message.payload?.message?.contentUrl || item.message.payload?.message?.previewUrl"
                                                                            class="img-fluid rounded"
                                                                            style="max-width:240px;"
                                                                            alt="[Image]"
                                                                    >
                                                                </template>

                                                                <!-- VIDEO -->
                                                                <template v-else-if="item.message.type === 'video'">
                                                                    <video
                                                                            controls
                                                                            class="img-fluid rounded"
                                                                            style="max-width:260px;"
                                                                            :poster="item.message.payload?.message?.previewUrl"
                                                                    >
                                                                        <source :src="item.message.payload?.message?.contentUrl">
                                                                    </video>
                                                                </template>

                                                                <!-- AUDIO -->
                                                                <template v-else-if="item.message.type === 'audio'">
                                                                    <audio controls :src="item.message.payload?.message?.contentUrl"></audio>
                                                                </template>

                                                                <!-- LOCATION -->
                                                                <template v-else-if="item.message.type === 'location'
                                             && item.message.payload
                                             && item.message.payload.message">
                                                                    <div>
                                                                        <strong>@{{ item.message.payload.message.title || 'ตำแหน่ง' }}</strong><br>
                                                                        @{{ item.message.payload.message.address }}<br>
                                                                        <a :href="'https://maps.google.com/?q='
                                          + item.message.payload.message.latitude
                                          + ',' + item.message.payload.message.longitude"
                                                                           target="_blank">
                                                                            เปิดแผนที่
                                                                        </a>
                                                                    </div>
                                                                </template>

                                                                <!-- UNSUPPORTED -->
                                                                <template v-else>
                                                                    [@{{ item.message.type }}]
                                                                </template>
                                                            </div>

                                                            <!-- เวลา + ปุ่ม ... -->
                                                            <div class="chat-time-wrapper d-flex align-items-center mt-1">
                        <span class="chat-msg-time text-muted small mr-1">
                            @{{ formatChatTime(item.message.sent_at) }}
                        </span>

                                                                <b-dropdown
                                                                        right
                                                                        size="sm"
                                                                        variant="link"
                                                                        no-caret
                                                                        toggle-class="p-0 chat-msg-menu-toggle"
                                                                >
                                                                    <template #button-content>
                                                                        <i class="fa fa-ellipsis-h"></i>
                                                                    </template>

                                                                    <b-dropdown-item @click="replyToMessage(item.message)">
                                                                        ตอบกลับ
                                                                    </b-dropdown-item>

                                                                    <b-dropdown-item @click="pinMessage(item.message)">
                                                                        ปักหมุด
                                                                    </b-dropdown-item>

                                                                    <b-dropdown-item v-if="item.message.is_pinned"
                                                                                     @click="unpinMessage(item.message)">
                                                                        เลิกปักหมุด
                                                                    </b-dropdown-item>
                                                                </b-dropdown>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                    </div>
                                </template>
                            </div>

                            {{-- REPLY BOX --}}
                            <div class="border-top p-2 bg-white" v-if="selectedConversation">


                                {{-- กล่องพิมพ์ข้อความ --}}
                                <b-form-textarea
                                        ref="replyBox"
                                        v-model="replyText"
                                        rows="1"
                                        max-rows="4"
                                        class="no-resize chat-reply-textarea"
                                        placeholder="Enter: ส่ง, Shift + Enter: ขึ้นบรรทัดใหม่..."
                                        :disabled="!canReply"
                                        @keydown.enter.exact.prevent="canReply && sendReply()"
                                ></b-form-textarea>

                                {{-- แถวล่าง: ไอคอน + ปุ่มส่ง --}}
                                <div class="d-flex justify-content-between align-items-center mt-2">

                                    {{-- ไอคอนด้านซ้าย --}}
                                    <div class="d-flex align-items-center">

                                        {{-- emoji (เผื่ออนาคตจะเปิด picker) --}}
                                        <b-button
                                                size="sm"
                                                variant="link"
                                                class="chat-tool-btn px-1"
                                                :disabled="!canReply"
                                                @click="openEmojiPicker && openEmojiPicker()"
                                        >
                                            <i class="far fa-smile"></i>
                                        </b-button>

                                        {{-- แนบรูป --}}
                                        <b-button
                                                size="sm"
                                                variant="link"
                                                class="chat-tool-btn px-1"
                                                :disabled="!canReply"
                                                @click="$refs.imageInput.click()"
                                        >
                                            <i class="fa fa-paperclip"></i>
                                        </b-button>

                                        {{-- ข้อความตอบกลับ --}}
                                        <b-button
                                                size="sm"
                                                variant="link"
                                                class="chat-tool-btn px-1"
                                                :disabled="!canReply"
                                                @click="openQuickReplyModal"
                                        >
                                            <i class="fas fa-comment-dots"></i>
                                        </b-button>
                                    </div>

                                    {{-- ปุ่มส่ง ด้านขวา --}}
                                    <div>
                                        <b-button
                                                variant="success"
                                                class="chat-send-btn"
                                                :disabled="sending || replyText.trim() === '' || !canReply"
                                                @click="sendReply"
                                        >
                <span v-if="sending">
                    <b-spinner small class="mr-1"></b-spinner> กำลังส่ง...
                </span>
                                            <span v-else>
                    ส่ง
                </span>
                                        </b-button>
                                    </div>
                                </div>

                                {{-- input file ซ่อน --}}
                                <input type="file"
                                       ref="imageInput"
                                       class="d-none"
                                       accept="image/*"
                                       @change="onSelectImage">
                            </div>

                        </div>
                    </b-col>

                    {{-- ====== RIGHT: PROFILE + ACTIONS + NOTES ====== --}}
                    <b-col cols="12" md="3" class="border-left line-oa-col">

                        <div class="d-flex flex-column h-100" v-if="selectedConversation">
                            {{-- PROFILE --}}
                            <div class="border-bottom p-3 text-center">
                                <div class="d-flex justify-content-center">
                                    <img
                                            v-if="selectedConversation.contact && selectedConversation.contact.picture_url"
                                            :src="selectedConversation.contact.picture_url"
                                            v-on:error="onProfileImageError"
                                            class="rounded-circle"
                                            style="width: 64px; height: 64px; object-fit: cover;"
                                    >
                                    <div v-else
                                         class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                                         style="width: 64px; height: 64px;">
                                        <i class="far fa-user fa-lg"></i>
                                    </div>
                                </div>
                                <div class="text-center d-flex align-items-center justify-content-center">
                                    <h4 class="mt-2 mb-1 mb-0 mr-1">
                                        @{{ (selectedConversation.contact &&
                                        (selectedConversation.contact.display_name ||
                                        selectedConversation.contact.member_username)) || 'ไม่ทราบชื่อ' }}
                                    </h4>

                                    <a @click="openMemberModal" class="icon-only" style="cursor: pointer;">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                </div>
                                <div class="small text-muted" v-if="selectedConversation.contact.member_username">
                                    ยูส: @{{ selectedConversation.contact &&
                                    selectedConversation.contact.member_username ||
                                    '-' }}
                                </div>
                                <div class="small text-muted" v-if="selectedConversation.contact.member_mobile">
                                    เบอร์: @{{ selectedConversation.contact &&
                                    selectedConversation.contact.member_mobile ||
                                    '-' }}
                                </div>
                            </div>

                            {{-- ACCOUNT DETAIL --}}
                            <div class="border-bottom p-2 small" v-if="selectedConversation.contact.member_id">
                                <div class="mb-1">
                                    <span class="text-muted">ชื่อจริง:</span>
                                    <span class="font-weight-bold">
                                @{{ selectedConversation.contact &&
                                selectedConversation.contact.member_name || '-' }}
                            </span>
                                </div>
                                <div class="mb-1">
                                    <span class="text-muted">ธนาคาร:</span>
                                    <span class="font-weight-bold">
                                @{{ selectedConversation.contact &&
                                selectedConversation.contact.member_bank_name || '-' }}
                            </span>
                                </div>
                                <div>
                                    <span class="text-muted">เลขบัญชี:</span>
                                    <span class="font-weight-bold">
                                @{{ selectedConversation.contact &&
                                selectedConversation.contact.member_acc_no || '-' }}
                            </span>
                                </div>
                            </div>

                            {{-- ACTION BUTTONS --}}
                            <div class="border-bottom p-2">
                                <div class="d-flex flex-wrap gap-2">   <!-- เพิ่ม flex-wrap ให้แตกบรรทัดได้ -->

                                    <div v-if="selectedConversation.is_registering && canControlRegister()">
                                        <b-button
                                                class="btn-app"
                                                variant="outline-danger"
                                                @click="cancelRegisterFlow"
                                        >
                                            <i class="fa fa-times"></i> ยกเลิกสมัคร (บอท)
                                        </b-button>
                                    </div>

                                    <div v-else-if="canControlRegister()">
                                        <b-button
                                                variant="success"
                                                @click="openRegisterModal"
                                                class="btn-app"
                                        >
                                            <i class="fa fa-user-plus"></i> สมัคร
                                        </b-button>
                                    </div>

                                    <div v-if="canControlRegister() && selectedConversation.contact.member_id">
                                        <b-button
                                                variant="outline-primary"
                                                @click="openMemberFromConversation"
                                                class="btn-app"
                                        >
                                            <i class="fa fa-user-edit"></i> แก้ไขข้อมูล
                                        </b-button>
                                    </div>

                                    <div v-if="canControlRegister() && selectedConversation.contact.member_id">
                                        <b-button
                                                variant="outline-dark"
                                                @click="openBalanceModal"
                                                class="btn-app"
                                        >
                                            <i class="fa fa-dollar"></i> ดูยอดเงิน
                                        </b-button>
                                    </div>

                                    <div v-if="canControlRegister()">
                                        <b-button
                                                variant="outline-success"
                                                @click="openRefillModal"
                                                class="btn-app"
                                        >
                                            <i class="fa fa-money-check"></i> เพิ่มฝาก
                                        </b-button>
                                    </div>

                                    <div v-if="canControlRegister()">
                                        <b-button
                                                variant="outline-success"
                                                @click="openRefillModal"
                                                class="btn-app"
                                        >
                                            <div class="btn-icon-group">
                                                <i class="fa fa-plus"></i>
                                                <i class="fa fa-minus"></i>
                                            </div>
                                            ยอดเงิน
                                        </b-button>
                                    </div>

                                    <div v-if="canControlRegister()">
                                        <b-button
                                                variant="outline-success"
                                                @click="openRefillModal"
                                                class="btn-app"
                                        >
                                            <i class="fa fa-history"></i> ประวัติ
                                        </b-button>
                                    </div>

                                </div>
                            </div>


                            {{-- STATUS / ASSIGNEE --}}
                            <div class="border-bottom p-2 small">
{{--                                <div class="mb-1">--}}
{{--                                    <span class="text-muted">สถานะเคส:</span>--}}
{{--                                    <span class="font-weight-bold text-uppercase ml-1">--}}
{{--                                @{{ selectedConversation.status || '-' }}--}}
{{--                            </span>--}}
{{--                                </div>--}}
                                <div class="mb-1">
                                    <span class="text-muted">ผู้รับผิดชอบ:</span>
                                    <span class="font-weight-bold ml-1">
                                @{{ selectedConversation.assigned_employee_name || 'ไม่มีผู้รับผิดชอบ' }}
                            </span>
                                    <a @click="openAssigneeModal" class="icon-only" style="cursor: pointer;">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                </div>
                                <div v-if="selectedConversation.closed_at">
                                    <span class="text-muted">ปิดเมื่อ:</span>
                                    <span class="ml-1">
                                @{{ formatDateTime(selectedConversation.closed_at) }}
                            </span>
                                </div>
                            </div>

                            {{-- NOTES --}}
                            <div class="flex-fill d-flex flex-column p-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center">
                                        <small class="mb-0 mr-2">โน้ต</small>
                                        <small v-if="notesCount">
                @{{ activeNotePosition }}
            </small>
                                    </div>

                                    <b-button
                                            size="sm"
                                            class="note-icon-btn btn-icon"
                                            variant="outline-success"
                                            @click="openNoteCreateModal"
                                    >
                                        <i class="fa fa-plus"></i>
                                    </b-button>
                                </div>
                                {{-- ส่วนแสดงโน้ต --}}
                                <div class="flex-fill d-flex flex-column">

                                    <div v-if="notesError" class="text-danger small mb-1">
                                        @{{ notesError }}
                                    </div>

                                    <div class="flex-fill overflow-auto">
                                        <div v-if="notesLoading" class="text-muted text-center my-2">
                                            <b-spinner small class="mr-1"></b-spinner>
                                            กำลังโหลดโน้ต...
                                        </div>

                                        <div v-else-if="!notes.length" class="text-muted small text-center">
                                            ยังไม่มีโน้ตสำหรับเคสนี้
                                        </div>

                                        <div v-else class="d-flex flex-column h-100">

                                            <!-- NOTE CONTENT BOX -->
                                            <div class="note-box mb-2 p-3">
                                                <div class="note-text">
                                                    @{{ activeNote.body || activeNote.text || '' }}
                                                </div>

                                                <!-- footer: writer + date + edit/delete -->
                                                <div class="note-footer d-flex justify-content-between align-items-center mt-2 small text-muted">

                                                    <!-- left: name -->
                                                    <span>
            @{{ activeNote.employee_name || activeNote.created_by_name || 'พนักงาน' }}
        </span>

                                                    <!-- right: date + buttons -->
                                                    <div class="d-flex align-items-center">

            <span v-if="activeNote.created_at" class="mr-2">
                @{{ formatDateTime(activeNote.created_at) }}
            </span>

                                                        <b-button
                                                                v-if="activeNote.id"
                                                                size="sm"
                                                                class="note-icon-btn btn-icon"
                                                                @click="openNoteEditModal(activeNote)"
                                                        >
                                                            <i class="fa fa-edit text-muted"></i>
                                                        </b-button>

                                                        <b-button
                                                                v-if="activeNote.id"
                                                                size="sm"
                                                                class="note-icon-btn btn-icon"
                                                                @click="confirmDeleteNote(activeNote)"
                                                        >
                                                            <i class="fa fa-trash text-muted"></i>
                                                        </b-button>
                                                    </div>

                                                </div>
                                            </div>
                                            <!-- ปุ่มสไลด์ซ้าย/ขวา แคบ ๆ ตรงกลางล่าง -->
                                            <div class="mt-2 d-flex justify-content-center align-items-center">
                                                <b-button
                                                        size="sm"
                                                        variant="outline-secondary"
                                                        class="px-2 py-1 mx-1"
                                                        @click="prevNote"
                                                        :disabled="activeNoteIndex <= 0"
                                                >
                                                    <i class="fa fa-chevron-left"></i>
                                                </b-button>

                                                <span class="small text-muted">
                    @{{ activeNotePosition }}
                </span>

                                                <b-button
                                                        size="sm"
                                                        variant="outline-secondary"
                                                        class="px-2 py-1 mx-1"
                                                        @click="nextNote"
                                                        :disabled="activeNoteIndex >= notesCount - 1"
                                                >
                                                    <i class="fa fa-chevron-right"></i>
                                                </b-button>
                                            </div>

                                        </div>
                                    </div>
                                </div>



                            </div>

                        </div>

                        <div v-else class="d-flex h-100 align-items-center justify-content-center text-muted small">
                            เลือกลูกค้าจากด้านซ้ายเพื่อดูรายละเอียด
                        </div>
                    </b-col>

                </b-row>
                {{-- MODAL: ผูก contact กับ member --}}
                @include('admin::module.line-oa.addon-modal')

            </b-container>
        </div>
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
                        per_page: 10,
                        total: 0,
                    },
                    filters: {
                        status: 'open',
                        q: '',
                        account_id: null,
                        scope: 'all', // 'all' | 'mine'
                    },
                    statusOptions: [
                        // {value: 'all', text: 'ทั้งหมด'},
                        {value: 'open', text: 'ทั้งหมด'},
                        {value: 'assigned', text: 'ดำเนินการ'},
                        {value: 'closed', text: 'เสร็จสิ้น'},
                    ],
                    accountOptions: [],
                    bankOptions: [],
                    depositTable: null,
                    currentActiveConversationId: null,
                    loadingList: false,
                    loadingMore: false,
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

                    // ===== Notes state =====
                    notes: [],
                    notesLoading: false,
                    notesError: '',
                    activeNoteIndex: 0,

                    // popup note
                    noteModalMode: 'create', // 'create' | 'edit'
                    noteModalText: '',
                    noteModalSaving: false,
                    noteEditingId: null,

                    quickReplyForm: {
                        message: '',
                        description: '',
                        enabled: true,
                    },
                    quickReplySaving: false,
                    quickReplySaveError: null,

                    // สำหรับ modal ผู้รับผิดชอบ
                    assigneeOptions: [],
                    assigneeLoading: false,
                    assigneeSearch: '',
                    selectedAssigneeId: null,
                    savingAssignee: false,
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
                notesCount() {
                    return this.notes ? this.notes.length : 0;
                },
                activeNote() {
                    if (!this.notesCount) {
                        return {};
                    }
                    const idx = Math.max(0, Math.min(this.activeNoteIndex, this.notesCount - 1));
                    return this.notes[idx] || {};
                },
                activeNotePosition() {
                    if (!this.notesCount) {
                        return '0/0';
                    }
                    return (this.activeNoteIndex + 1) + '/' + this.notesCount;
                },

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
                messagesWithSeparators() {
                    const out = [];
                    let lastDateKey = null;

                    (this.messages || []).forEach(msg => {
                        if (!msg || !msg.sent_at) return;

                        const key = this.dateKey(msg.sent_at);

                        if (key !== lastDateKey) {
                            out.push({
                                kind: 'date',
                                dateKey: key,
                                date: msg.sent_at,
                            });
                            lastDateKey = key;
                        }

                        out.push({
                            kind: 'msg',
                            message: msg,
                        });
                    });

                    return out;
                },
                filteredAssignees() {
                    const q = (this.assigneeSearch || '').toLowerCase();
                    if (!q) {
                        return this.assigneeOptions;
                    }
                    return this.assigneeOptions.filter(emp => {
                        return (
                            (emp.display && emp.display.toLowerCase().includes(q)) ||
                            (emp.sub && emp.sub.toLowerCase().includes(q)) ||
                            (emp.code && emp.code.toLowerCase().includes(q)) ||
                            (emp.user_name && emp.user_name.toLowerCase().includes(q))
                        );
                    });
                },
            },
            watch: {
                'filters.status': function () {
                    this.reloadConversations();
                },
                // 'filters.scope': function () {
                //     this.reloadConversations();
                // },
                'filters.account_id': function () {
                    this.reloadConversations();
                },
                // search ให้ debounce หน่อย
                // 'filters.q': _.debounce(function () {
                //     this.reloadConversations();
                // }, 400),
            },
            methods: {
                replyToMessage(msg) {
                    // ไว้ค่อยออกแบบต่อว่าจะ quote ยังไง
                    console.log('replyToMessage', msg.id);
                },
                pinMessage(msg) {
                    console.log('pinMessage', msg.id);
                },
                unpinMessage(msg) {
                    console.log('unpinMessage', msg.id);
                },
                dateKey(dateString) {
                    if (!dateString) return null;
                    const d = new Date(dateString);

                    const y = d.getFullYear();
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');

                    // ใช้เป็น key สำหรับเปรียบเทียบวันเดียวกัน
                    return `${y}-${m}-${day}`;
                },

                formatChatDay(dateString) {
                    if (!dateString) return '';

                    const d = new Date(dateString);

                    return d.toLocaleDateString('th-TH', {
                        day: '2-digit',
                        month: 'short',
                        // ถ้าอยากให้ขึ้นปีเมื่อข้ามปี:
                        // year: (d.getFullYear() !== new Date().getFullYear()) ? '2-digit' : undefined,
                    });
                },

                formatChatTime(dateString) {
                    if (!dateString) return '';
                    const d = new Date(dateString);

                    return d.toLocaleTimeString('th-TH', {
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                },
                // ===== Notes API (ต้องมี backend: GET/POST /line-oa/conversations/{id}/notes) =====
                async loadNotes() {
                    if (!this.selectedConversation || !this.selectedConversation.id) {
                        this.notes = [];
                        this.activeNoteIndex = 0;
                        return;
                    }

                    this.notesLoading = true;
                    this.notesError = '';

                    try {
                        const url = this.apiUrl(`conversations/${this.selectedConversation.id}/notes`);
                        const resp = await axios.get(url);

                        if (resp.data && resp.data.success) {
                            this.notes = resp.data.data || [];
                            this.activeNoteIndex = this.notes.length ? 0 : 0;
                        } else {
                            this.notes = [];
                            this.activeNoteIndex = 0;
                            this.notesError = resp.data.message || 'โหลดโน้ตไม่สำเร็จ';
                        }
                    } catch (e) {
                        this.notesError = 'โหลดโน้ตไม่สำเร็จ กรุณาลองใหม่';
                    } finally {
                        this.notesLoading = false;
                    }
                },
                openNoteCreateModal() {
                    if (!this.selectedConversation || !this.selectedConversation.id) {
                        this.notesError = 'กรุณาเลือกห้องก่อน';
                        return;
                    }
                    this.noteModalMode = 'create';
                    this.noteModalText = '';
                    this.noteEditingId = null;
                    this.notesError = '';
                    this.$refs.noteModal && this.$refs.noteModal.show();
                },

                openNoteEditModal(note) {
                    if (!note || !note.id) {
                        return;
                    }
                    this.noteModalMode = 'edit';
                    this.noteEditingId = note.id;
                    this.noteModalText = note.body || note.text || '';
                    this.notesError = '';
                    this.$refs.noteModal && this.$refs.noteModal.show();
                },
                async saveNoteModal() {
                    const body = (this.noteModalText || '').trim();
                    if (!body) {
                        this.notesError = 'ข้อความโน้ตห้ามเว้นว่าง';
                        return;
                    }

                    if (!this.selectedConversation || !this.selectedConversation.id) {
                        this.notesError = 'ไม่พบห้องสนทนา';
                        return;
                    }

                    this.noteModalSaving = true;
                    this.notesError = '';

                    try {
                        let resp;

                        if (this.noteModalMode === 'create') {
                            const url = this.apiUrl(`conversations/${this.selectedConversation.id}/notes`);
                            resp = await axios.post(url, { body });
                        } else {
                            const noteId = this.noteEditingId;
                            if (!noteId) {
                                throw new Error('ไม่พบโน้ตที่จะแก้ไข');
                            }
                            const url = this.apiUrl(`conversations/${this.selectedConversation.id}/notes/${noteId}`);
                            resp = await axios.patch(url, { body });
                        }

                        const ok = resp.data && resp.data.success;
                        if (!ok) {
                            this.notesError = resp.data.message || 'บันทึกโน้ตไม่สำเร็จ';
                            return;
                        }

                        const note = resp.data.data;

                        if (this.noteModalMode === 'create') {
                            // จะให้ล่าสุดไปอยู่หน้าสุดหรือท้ายสุดก็ได้
                            this.notes.unshift(note);
                            this.activeNoteIndex = 0;
                        } else {
                            const idx = this.notes.findIndex(n => n.id === note.id);
                            if (idx !== -1) {
                                this.$set(this.notes, idx, note);
                                this.activeNoteIndex = idx;
                            }
                        }

                        this.$refs.noteModal && this.$refs.noteModal.hide();
                    } catch (e) {
                        this.notesError = 'บันทึกโน้ตไม่สำเร็จ กรุณาลองใหม่';
                    } finally {
                        this.noteModalSaving = false;
                    }
                },
                async confirmDeleteNote(note) {
                    if (!note || !note.id) {
                        return;
                    }

                    if (!this.selectedConversation || !this.selectedConversation.id) {
                        this.notesError = 'ไม่พบห้องสนทนา';
                        return;
                    }

                    if (!window.confirm('ยืนยันการลบโน้ตนี้หรือไม่?')) {
                        return;
                    }

                    this.notesError = '';

                    try {
                        const url = this.apiUrl(`conversations/${this.selectedConversation.id}/notes/${note.id}`);
                        const resp = await axios.delete(url);

                        if (!resp.data || !resp.data.success) {
                            this.notesError = resp.data?.message || 'ลบโน้ตไม่สำเร็จ';
                            return;
                        }

                        const idx = this.notes.findIndex(n => n.id === note.id);
                        if (idx !== -1) {
                            this.notes.splice(idx, 1);

                            if (this.activeNoteIndex >= this.notes.length) {
                                this.activeNoteIndex = this.notes.length ? this.notes.length - 1 : 0;
                            }
                        }
                    } catch (e) {
                        this.notesError = 'ลบโน้ตไม่สำเร็จ กรุณาลองใหม่';
                    }
                },
                prevNote() {
                    if (this.activeNoteIndex > 0) {
                        this.activeNoteIndex--;
                    }
                },

                nextNote() {
                    if (this.activeNoteIndex < this.notesCount - 1) {
                        this.activeNoteIndex++;
                    }
                },

                async fetchNotes(conversationId) {
                    if (!conversationId) return;

                    this.notesLoading = true;
                    this.notesError = '';
                    this.notes = [];

                    try {
                        const res = await axios.get(this.apiUrl('conversations/' + conversationId + '/notes'));
                        const body = res.data || {};
                        const items = body.data || body.notes || [];

                        this.notes = items.map((n, idx) => ({
                            id: n.id || n.note_id || null,
                            _local_id: n.id ? null : ('local-' + idx),
                            body: n.body || n.text || '',
                            employee_name: n.employee_name || n.created_by_name || null,
                            created_at: n.created_at || null,
                        }));
                    } catch (e) {
                        console.error('[LineOA] fetchNotes error', e);
                        this.notesError = 'โหลดโน้ตไม่สำเร็จ';
                    } finally {
                        this.notesLoading = false;
                    }
                },


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
                    if (this.quickRepliesLoadedForConvId !== this.currentActiveConversationId) {
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
                            message: 'โหลดข้อความตอบกลับไม่สำเร็จ กรุณาลองใหม่'
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
                                message: 'ส่งข้อความตอบกลับไม่สำเร็จ (ไม่พบข้อมูลข้อความจากเซิร์ฟเวอร์)'
                            });
                        }
                    } catch (e) {
                        console.error('[LineOA] sendQuickReply error', e);

                        const msg =
                            e?.response?.data?.message ??
                            e?.response?.data?.msg ??
                            e?.response?.data?.error ??
                            'ส่งข้อความตอบกลับไม่สำเร็จ กรุณาลองใหม่';

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
                    const merge  = options.merge === true;   // อัปเดต list เดิมตาม id
                    const append = options.append === true;  // โหลดเพิ่มต่อท้าย list เดิม

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
                            per_page: this.pagination?.per_page || 20, // เผื่อกำหนด per_page เอง
                        }
                    }).then(res => {
                        const body    = res.data || {};
                        const newList = body.data || [];

                        // ===== อัปเดต pagination =====
                        this.pagination = Object.assign(this.pagination, body.meta || {});

                        // ===== จัดการ conversations =====
                        if (append && Array.isArray(this.conversations)) {
                            // โหมดโหลดเพิ่ม: เอาของเดิม + หน้าใหม่ ต่อท้ายกัน
                            const existing = this.conversations.slice();
                            const existingIds = new Set(
                                existing
                                    .filter(c => c && c.id != null)
                                    .map(c => c.id)
                            );

                            newList.forEach(item => {
                                if (!item || item.id == null) {
                                    return;
                                }
                                if (!existingIds.has(item.id)) {
                                    existing.push(item);
                                }
                            });

                            this.conversations = existing;

                        } else if (merge && Array.isArray(this.conversations) && this.conversations.length > 0) {
                            // โหมด merge เดิมของโบ๊ท: ใช้ id เดิม แล้วอัปเดตค่าใหม่ทับ
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
                            // โหมดปกติ: แทนที่ทั้ง list (เช่น เปลี่ยน filter / ค้นหาใหม่)
                            this.conversations = newList;
                        }

                        // ===== สร้าง accountOptions จาก list ปัจจุบัน (เฉพาะตอนยังไม่ได้เลือก OA) =====
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

                /**
                 * เวลาเปลี่ยน filter เช่น status / scope / account / ค้นหา
                 * ให้รีโหลดหน้า 1 ใหม่
                 */
                async reloadConversations() {
                    this.pagination.current_page = 1;
                    this.conversations = [];           // เคลียร์ list เดิม (กันรู้สึกว่าข้อมูลเก่าแปะค้าง)
                    await this.fetchConversations(1);  // โหลดหน้าแรกใหม่
                },

                /**
                 * handler เวลา scroll list ซ้าย
                 */
                onConversationListScroll(event) {
                    const el = event.target;

                    // ยังโหลดอยู่ → หยุดก่อนกัน double load
                    if (this.loadingList) return;

                    // ถ้ายังไม่ถึงหน้าสุดท้าย → เลื่อนโหลดเพิ่ม
                    if (this.pagination.current_page < this.pagination.last_page) {

                        // ถ้าเลื่อนถึงเกือบล่าง
                        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {

                            this.fetchConversations(this.pagination.current_page + 1, {
                                silent: true,
                                append: true,
                            });
                        }
                    }
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

                    // รีเซ็ต state ของโน้ตทุกครั้งที่เปลี่ยนห้อง
                    this.notes = [];
                    this.activeNote = {};
                    this.activeNoteIndex = 0;
                    this.notesError = null;

                    // ถ้าไม่ต้องโหลดข้อความใหม่ (เช่น แค่ refresh header)
                    if (!reloadMessages) {
                        this.loadNotes(); // ← ใช้เมทอดเดียวกับฝั่งอื่น
                        this.$nextTick(() => {
                            this.scrollToBottom();
                            this.autoFocusRef('replyBox');
                        });
                        return;
                    }

                    // โหลดข้อความ + แล้วค่อยโหลดโน้ต
                    this.fetchMessages(conv.id, { limit: 50, previous_id: previousId })
                        .then(() => {
                            this.loadNotes(); // ← ดึงโน้ตของห้องนี้
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

                formatMessageDate(dateString) {
                    if (!dateString) return '';

                    const date = new Date(dateString);
                    const now = new Date();

                    const isToday =
                        date.getDate() === now.getDate() &&
                        date.getMonth() === now.getMonth() &&
                        date.getFullYear() === now.getFullYear();

                    if (isToday) {
                        // HH:mm
                        return date.toLocaleTimeString('th-TH', {
                            hour: '2-digit',
                            minute: '2-digit',
                        });
                    }

                    // แสดง 05 ธ.ค
                    return date.toLocaleDateString('th-TH', {
                        day: '2-digit',
                        month: 'short'
                    });
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
                openQuickReplyCreateModal() {
                    // reset form ทุกครั้งก่อนเปิด
                    this.resetQuickReplyForm();
                    this.$nextTick(() => {
                        this.$refs.quickReplyAddModal && this.$refs.quickReplyAddModal.show();
                    });
                },

                resetQuickReplyForm() {
                    this.quickReplyForm = {
                        key: '',
                        message: '',
                        description: '',
                        enabled: true,
                    };
                    this.quickReplySaving = false;
                    this.quickReplySaveError = null;
                },

                /**
                 * ใส่ placeholder ลงใน textarea quick reply (ตำแหน่งเคอร์เซอร์)
                 */
                insertQuickReplyPlaceholder(token) {
                    const current = this.quickReplyForm.message || '';

                    let el = this.$refs.quickReplyMessageInput;
                    if (el && el.$el) {
                        el = el.$el;
                    }

                    if (!el || !el.tagName || el.tagName.toLowerCase() !== 'textarea') {
                        this.quickReplyForm.message = current + token;
                        return;
                    }

                    const start = el.selectionStart != null ? el.selectionStart : current.length;
                    const end   = el.selectionEnd   != null ? el.selectionEnd   : current.length;

                    const before = current.substring(0, start);
                    const after  = current.substring(end);

                    this.quickReplyForm.message = before + token + after;

                    this.$nextTick(() => {
                        el.focus();
                        const pos = start + token.length;
                        el.selectionStart = pos;
                        el.selectionEnd   = pos;
                    });
                },

                async submitQuickReplyForm() {
                    this.quickReplySaveError = null;

                    const description = (this.quickReplyForm.description || '').trim();
                    const message = (this.quickReplyForm.message || '').trim();

                    if (!description || !message) {
                        this.quickReplySaveError = 'กรุณากรอกคีย์และข้อความให้ครบถ้วน';
                        return;
                    }

                    // prefix key ด้วย quick_reply.
                    const category = 'quick_reply';

                    // ใช้ description หรือ message มาเป็น seed ทำ slug
                    const base = (this.quickReplyForm.description || this.quickReplyForm.message || '').trim();

                    let slug = base
                        .trim()
                        .replace(/\s+/g, '_')                 // แทนช่องว่างด้วย _
                        .replace(/[^ก-๙a-zA-Z0-9_]/g, '');    // อนุญาต: ไทย อังกฤษ ตัวเลข _

// fallback กัน slug ว่าง เช่น ข้อความเป็น emoji ล้วน
                    if (!slug) {
                        slug = 'ข้อความด่วน';
                    }

                    const ts = Date.now();
                    const rawKey = `quick_reply.${slug}_${ts}`;


                    const payload = {
                        category: category,
                        key: rawKey,
                        message: message,
                        description: this.quickReplyForm.description || '',
                        enabled: this.quickReplyForm.enabled ? 1 : 0,
                    };

                    this.quickReplySaving = true;

                    try {
                        // TODO: ปรับ route ให้ตรงกับ Controller ของข้อความตอบกลับที่โบ๊ทใช้จริง
                        const url = "{{ route('admin.line_quick_reply.create') }}"; // แก้ชื่อ route ตามของโบ๊ท
                        const resp = await axios.post(url, { data: payload });

                        // สมมติ response ส่ง success กลับมาเหมือนหน้าเมนูข้อความตอบกลับ
                        if (resp.data && resp.data.message) {
                            // ปิด modal
                            this.$refs.quickReplyAddModal.hide();

                            // refresh รายการ quick reply ใน popup เดิม
                            // ✅ โหลดลิสต์ข้อความตอบกลับใหม่สำหรับห้องนี้
                            await this.fetchQuickReplies();

                            // แถม: แจ้งเตือนเล็ก ๆ ถ้าอยากใช้ Toast
                            // this.$bvToast.toast(resp.data.message, { variant: 'success', solid: true, autoHideDelay: 2000 });
                        }
                    } catch (e) {
                        console.error('submitQuickReplyForm error', e);
                        this.quickReplySaveError = 'บันทึกข้อความตอบกลับไม่สำเร็จ กรุณาลองใหม่';
                    } finally {
                        this.quickReplySaving = false;
                    }
                },

                canAssignConversation() {
                    // ถ้ามี logic permission จริงให้มาเช็กที่นี่ เช่น เช็ก role / permission
                    // ตอนนี้เอาเบา ๆ: มีห้อง และ user login admin อยู่ก็ให้เปลี่ยนได้
                    return !!this.selectedConversation;
                },

                openAssigneeModal() {
                    if (!this.selectedConversation || !this.canAssignConversation()) {
                        return;
                    }

                    // preset ค่าเริ่มต้นเป็นคนเดิม (ถ้ามี)
                    this.selectedAssigneeId = this.selectedConversation.assigned_employee_id || null;

                    this.assigneeSearch = '';
                    this.assigneeLoading = true;

                    this.loadAssignees()
                        .then(() => {
                            this.$refs.assigneeModal.show();
                        })
                        .finally(() => {
                            this.assigneeLoading = false;
                        });
                },

                async loadAssignees() {
                    // แนะนำให้มี route backend ประมาณนี้:
                    // GET /line-oa/assignees   หรือ   /line-oa/conversations/{conversation}/assignees
                    // ให้คืน data: [{ id, code, name, user_name, role_name }, ...]
                    try {
                        const res = await axios.get(this.apiUrl('assignees'));
                        const body = res.data || {};
                        const items = body.data || body.employees || [];

                        this.assigneeOptions = items.map(e => {
                            const name = e.name || e.full_name || e.user_name || e.code || ('พนักงาน #' + e.id);

                            return {
                                id: e.id,
                                code: e.code || '',
                                user_name: e.user_name || '',
                                display: name,
                                sub: e.code
                                    ? (e.user_name ? `${e.code} • ${e.user_name}` : e.code)
                                    : (e.user_name || ''),
                                role: e.role_name || e.role || '',
                            };
                        });
                    } catch (e) {
                        console.error('[LineOA] loadAssignees error', e);
                        this.assigneeOptions = [];
                        this.showAlert && this.showAlert({
                            success: false,
                            message: 'โหลดรายชื่อผู้รับผิดชอบไม่สำเร็จ',
                        });
                    }
                },

                async saveAssignee() {
                    if (!this.selectedConversation || !this.selectedAssigneeId) {
                        return;
                    }

                    this.savingAssignee = true;

                    try {
                        const convId = this.selectedConversation.id;
                        // แนะนำ route backend:
                        // POST /line-oa/conversations/{conversation}/assign
                        const res = await axios.post(
                            this.apiUrl('conversations/' + convId + '/assign'),
                            { employee_id: this.selectedAssigneeId }
                        );

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


                        this.$refs.assigneeModal.hide();

                    } catch (e) {
                        console.error('[LineOA] saveAssignee error', e);
                        this.showAlert && this.showAlert({
                            success: false,
                            message: 'เกิดข้อผิดพลาดระหว่างบันทึกผู้รับผิดชอบ',
                        });
                    } finally {
                        this.savingAssignee = false;
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
                // this.autoCnt(false);
                // ถ้าหน้านี้ยังใช้ alertsound / autoCnt เดิมอยู่ สามารถเรียกจาก window ตัวอื่นได้
                // ไม่ผูกกับ memberRefillApp เพื่อลด side-effect
            },

            mounted() {
                this.loadBankAccount();
            },

            methods: {
                openGameLog(type, prefill = null) {
                    this.logType = type;

                    if (type === 'deposit') {
                        this.caption = 'ประวัติฝากเครดิต';
                        this.fields = [
                            {key: 'id', label: 'รหัส', sortable: false},
                            {key: 'date_create', label: 'เวลา', sortable: true},
                            {key: 'amount', label: 'ยอดฝาก', sortable: false},
                            {key: 'pro_name', label: 'โปรโมชั่น', sortable: true},
                            {key: 'credit_bonus', label: 'โบนัสที่ได้', sortable: false},
                            {key: 'credit_before', label: 'เครดิตก่อน', sortable: false},
                            {key: 'credit_after', label: 'เครดิตหลัง', sortable: false},
                            {key: 'status_display', label: 'สถานะ', sortable: true},
                        ];
                    } else if (type === 'withdraw') {
                        this.caption = 'ประวัติถอนเครดิต';
                        this.fields = [
                            {key: 'id', label: 'รหัส', sortable: false},
                            {key: 'date_create', label: 'เวลา', sortable: true},
                            {key: 'amount_request', label: 'ยอดแจ้ง', sortable: false},
                            {key: 'amount', label: 'ยอดถอนที่ได้รับ', sortable: false},
                            {key: 'credit_before', label: 'เครดิตก่อน', sortable: false},
                            {key: 'credit_after', label: 'เครดิตหลัง', sortable: false},
                            {key: 'status_display', label: 'สถานะ', sortable: true},
                        ];
                    } else {
                        this.caption = 'ประวัติรายการ';
                    }

                    this.showRefillUI = true;
                    if (prefill && prefill.member_id) {
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
