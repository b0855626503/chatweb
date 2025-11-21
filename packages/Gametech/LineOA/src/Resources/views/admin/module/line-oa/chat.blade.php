<div id="line-oa-chat-app">
    <line-oa-chat></line-oa-chat>
</div>

{{-- ================== TEMPLATE: LINE OA CHAT ================== --}}
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
                            <b-badge variant="primary" v-if="filters.status === 'open'">เปิดอยู่</b-badge>
                            <b-badge variant="secondary" v-else>ปิดแล้ว</b-badge>
                        </div>

                        <b-input-group size="sm" class="mt-2">
                            <b-form-input
                                    v-model="filters.q"
                                    placeholder="ค้นหา ชื่อลูกค้า / ยูส / เบอร์"
                                    @keyup.enter="fetchConversations(1)"
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
                                    @change="fetchConversations(1)"
                            ></b-form-select>

                            <b-form-select
                                    v-model="filters.account_id"
                                    :options="accountOptions"
                                    size="sm"
                                    @change="fetchConversations(1)"
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

                                        <div class="text-muted small text-truncate">
                                                <span v-if="conv.line_account && conv.line_account.name">
                                                    [@{{ conv.line_account.name }}]
                                                </span>
                                            @{{ conv.last_message || 'ยังไม่มีข้อความ' }}
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            <small class="text-muted">
                                                ยูส: @{{ conv.contact && conv.contact.member_username || '-' }}
                                            </small>
                                            <b-badge v-if="conv.unread_count > 0" variant="danger">
                                                @{{ conv.unread_count }}
                                            </b-badge>
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
                            <div class="mr-2">
                                <img
                                        v-if="selectedConversation.contact && selectedConversation.contact.picture_url"
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
                                <div class="d-flex justify-content-between">
                                    <h5 class="mb-0">
                                        @{{ (selectedConversation.contact &&
                                        (selectedConversation.contact.display_name ||
                                        selectedConversation.contact.member_username)) || 'ไม่ทราบชื่อ' }}
                                    </h5>
                                    <small class="text-muted" v-if="selectedConversation.line_account">
                                        OA: @{{ selectedConversation.line_account.name }}
                                    </small>
                                </div>
                                <div class="small text-muted">
                                    ยูส: @{{ selectedConversation.contact &&
                                    selectedConversation.contact.member_username || '-' }} /
                                    เบอร์: @{{ selectedConversation.contact &&
                                    selectedConversation.contact.member_mobile || '-' }}
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
                                            </div>
                                            <div class="small" v-else-if="msg.source === 'bot'">
                                                <strong>บอท</strong>
                                            </div>

                                            <div class="whitespace-pre-wrap">
                                                @{{ msg.text || '[' + msg.type + ']' }}
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
                            <b-form-textarea
                                    v-model="replyText"
                                    rows="1"
                                    max-rows="4"
                                    placeholder="พิมพ์ข้อความเพื่อตอบลูกค้า แล้วกด Enter หรือปุ่ม ส่ง"
                                    @keydown.enter.exact.prevent="sendReply"
                            ></b-form-textarea>
                            <b-input-group-append>
                                <b-button variant="primary" :disabled="sending || replyText.trim() === ''"
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
                    </div>
                </div>
            </b-col>
        </b-row>
    </b-container>
</script>



@push('scripts')
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
                    },
                    statusOptions: [
                        {value: 'open', text: 'ห้องเปิดอยู่'},
                        {value: 'closed', text: 'ห้องปิดแล้ว'},
                    ],
                    accountOptions: [],

                    loadingList: false,
                    selectedConversation: null,
                    messages: [],
                    loadingMessages: false,

                    replyText: '',
                    sending: false,

                    autoRefreshTimer: null,
                };
            },
            created() {
                this.fetchConversations(1);
                this.startAutoRefresh();
            },
            beforeDestroy() {
                this.stopAutoRefresh();
            },
            methods: {
                apiUrl(path) {
                    return '/admin/line-oa/' + path.replace(/^\/+/, '');
                },

                fetchConversations(page = 1) {
                    this.loadingList = true;

                    axios.get(this.apiUrl('conversations'), {
                        params: {
                            page: page,
                            status: this.filters.status,
                            q: this.filters.q,
                            account_id: this.filters.account_id,
                        }
                    }).then(res => {
                        const body = res.data || {};

                        this.conversations = (body.data || []);
                        this.pagination = Object.assign(this.pagination, body.meta || {});

                        const accounts = {};
                        this.conversations.forEach(conv => {
                            if (conv.line_account && conv.line_account.id) {
                                accounts[conv.line_account.id] = conv.line_account.name || ('OA #' + conv.line_account.id);
                            }
                        });
                        this.accountOptions = Object.keys(accounts).map(id => ({
                            value: parseInt(id),
                            text: accounts[id],
                        }));

                        if (!this.selectedConversation && this.conversations.length > 0) {
                            this.selectConversation(this.conversations[0]);
                        }
                    }).catch(err => {
                        console.error('fetchConversations error', err);
                    }).finally(() => {
                        this.loadingList = false;
                    });
                },

                selectConversation(conv) {
                    this.selectedConversation = conv;
                    this.fetchMessages(conv.id);
                },

                fetchMessages(conversationId, options = {}) {
                    if (!conversationId) return;

                    this.loadingMessages = true;

                    const params = {
                        limit: options.limit || 50,
                    };

                    if (options.before_id) {
                        params.before_id = options.before_id;
                    }

                    axios.get(this.apiUrl('conversations/' + conversationId), {params})
                        .then(res => {
                            const body = res.data || {};
                            const messages = body.messages || [];

                            this.messages = messages;
                            this.selectedConversation = body.conversation || this.selectedConversation;

                            this.$nextTick(() => {
                                this.scrollToBottom();
                            });
                        })
                        .catch(err => {
                            console.error('fetchMessages error', err);
                        })
                        .finally(() => {
                            this.loadingMessages = false;
                        });
                },

                sendReply() {
                    if (!this.selectedConversation || this.sending) return;

                    const text = this.replyText.trim();
                    if (text === '') return;

                    this.sending = true;

                    axios.post(this.apiUrl('conversations/' + this.selectedConversation.id + '/reply'), {
                        text: text,
                    }).then(res => {
                        const msg = res.data && res.data.data ? res.data.data : null;

                        if (msg) {
                            this.messages.push(msg);
                        }

                        this.replyText = '';

                        this.$nextTick(() => {
                            this.scrollToBottom();
                        });

                        this.fetchConversations(this.pagination.current_page);
                    }).catch(err => {
                        console.error('sendReply error', err);
                        alert('ส่งข้อความไม่สำเร็จ กรุณาลองใหม่');
                    }).finally(() => {
                        this.sending = false;
                    });
                },

                scrollToBottom() {
                    const el = this.$refs.messageContainer;
                    if (!el) return;
                    el.scrollTop = el.scrollHeight;
                },

                formatDateTime(datetime) {
                    if (!datetime) return '';
                    return datetime;
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
                        return base + ' bg-primary text-white';
                    }
                    if (msg.source === 'bot') {
                        return base + ' bg-warning';
                    }
                    return base + ' bg-light';
                },

                conversationItemClass(conv) {
                    const classes = ['py-2'];
                    if (this.selectedConversation && this.selectedConversation.id === conv.id) {
                        classes.push('active');
                    }
                    return classes;
                },

                startAutoRefresh() {
                    this.stopAutoRefresh();
                    this.autoRefreshTimer = setInterval(() => {
                        this.fetchConversations(this.pagination.current_page || 1);
                        if (this.selectedConversation) {
                            this.fetchMessages(this.selectedConversation.id, {limit: 50});
                        }
                    }, 20000);
                },

                stopAutoRefresh() {
                    if (this.autoRefreshTimer) {
                        clearInterval(this.autoRefreshTimer);
                        this.autoRefreshTimer = null;
                    }
                },
            }
        });

        // window.app = new Vue({
        //     el: '#app',
        // });

    </script>
@endpush
