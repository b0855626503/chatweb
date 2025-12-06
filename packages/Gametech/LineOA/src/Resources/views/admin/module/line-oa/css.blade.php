@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css"
          integrity="sha512-jU/7UFiaW5UBGODEopEqnbIAHOI8fO6T99m7Tsmqs2gkdujByJfkCbbfPSN4Wlqlb9TGnsuC0YgUgWkRBK7B9A=="
          crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <style>
        .content-header {
            display:none !important;
        }
        .main-footer {
            display:block !important;
            margin-top: 15px;
        }

        .card-body {
            flex: 1 1 auto;
            min-height: 1px;
            padding: 0 !important;
        }
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
            /*font-size: 14px;*/
        }

        .chat-line-translated {
            white-space: pre-wrap;
            /*font-size: 13px;*/
            border-left: 3px solid #e0e0e0;
            padding-left: 4px;
        }

        .gt-conv-last-message {
            /*font-size: 12px;*/
            color: #666;
            white-space: nowrap; /* ไม่ตัดขึ้นบรรทัดใหม่ */
            overflow: hidden; /* ถ้ายาวเกิน ก็ตัดส่วนที่ล้นทิ้ง */
            text-overflow: ellipsis; /* แสดง ... ท้ายบรรทัด */
            max-width: 100%; /* หรือกำหนดเป็น px ก็ได้เช่น 220px */
        }

        /* ฝั่ง sidebar ทั้งคอลัมน์ – ไม่ให้เลื่อนซ้ายขวา */
        .line-oa-sidebar {
            overflow-x: hidden;
            font-size: 16px;
        }

        /* ข้อความพรีวิวในแต่ละห้อง */
        .line-oa-sidebar .conversation-last-message {
            display: block;
            white-space: nowrap; /* บังคับบรรทัดเดียว */
            overflow: hidden; /* ซ่อนส่วนเกิน */
            text-overflow: ellipsis; /* ใส่ ... ท้ายประโยค */

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

        .btn-app {
            background-color: transparent !important;
            color: inherit !important;
            padding: 15px 10px 15px !important
        }

        .btn-icon-group {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            gap: 3px;               /* ระยะห่างระหว่าง + และ - */
            margin-bottom: 4px;     /* ระยะห่างระหว่างไอคอนกับข้อความ */
        }

        .color-red {
            background-color: red !important;
            color: white !important;
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
    <style>
        /* wrapper ทั้งหน้าแชต */
        .line-chat-font {
            font-family: system-ui,
            -apple-system, /* iOS */ BlinkMacSystemFont, /* macOS */ "Segoe UI", /* Windows */ Roboto, /* Android */ "Helvetica Neue",
            Arial,
            "Noto Sans Thai",
            sans-serif;
            font-size: 18px; /* ขนาดใกล้เคียง LINE */
            line-height: 1.35; /* ระยะห่างบรรทัดแบบ LINE */
        }

        .line-oa-chat-page {
            height: calc(110vh - 0px); /* ปรับเลขนี้ตามความสูง header/footer ของ layout */
            display: flex;
            flex-direction: column;
        }

        /* ให้ container + row ขยายเต็ม และยืดลูกทุกคอลัมน์ */
        .line-oa-chat-page > .container-fluid,
        .line-oa-chat-page .row.h-100 {
            flex: 1 1 auto;
            min-height: 0;
        }

        .line-oa-chat-page .line-oa-col {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        /* คอลัมน์กลาง: ไม่ให้ทะลุกรอบ */
        .line-oa-chat-page .chat-middle-col {
            height: 100%;
            overflow: hidden;
        }

        /* ให้ list ข้อความเป็นตัว scroll เอง */
        .line-oa-chat-page .chat-middle-col .chat-message-list {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
        }

        .note-nav-btn {
            min-width: 24px !important;
            max-width: 24px !important;
            text-align: center;
        }

        .note-box {
            background: #f8f9fa;             /* เทาอ่อน อ่านง่าย */
            border: 1px solid #e3e6eb;       /* เส้นบางๆ แบบ modern */
            border-radius: 10px;             /* โค้งมน */
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);  /* เงานุ่มๆ */
            max-width: 100%;
            word-wrap: break-word;
        }

        .note-text {
            font-size: 14px;
            line-height: 1.45;
            color: #333;
        }

        .note-footer {
            border-top: 1px dashed #d0d0d0;  /* เส้นแบ่งสวย ๆ แบบโปร */
            padding-top: 6px;
        }

        .note-icon-btn.btn-icon {
            border: none !important;
            background: transparent none !important;
            color: #08c !important;
            padding: 0 !important;
            line-height: 20px !important;
            box-shadow: none !important;
            margin: 0 3px !important;
            min-width: 0 !important;
            width: auto !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;

        }

        .note-icon-btn i {
            font-size: 13px;
            pointer-events: none; /* กันไอคอนกินคลิก */
        }

        .no-resize {
            resize: none !important;
            overflow: auto !important; /* ป้องกันไม่ให้ browser ใส่ UI ของ resize */
        }

        /* Safari / iOS บางรุ่น */
        textarea.no-resize {
            -webkit-resize: none !important;
        }

        /* Firefox */
        textarea.no-resize {
            overflow: hidden !important; /* ถ้าต้องการซ่อน scrollbar แบบ UX ดีขึ้น */
        }

        .h-85 {
            height: 85% !important;
        }

        .h-90 {
            height: 90% !important;
        }

        .h-95 {
            height: 95% !important;
        }
        .chat-msg-footer {
            position: relative;
        }

        /* ปุ่ม … ให้ซ่อนอยู่ก่อน */
        .chat-msg-menu-toggle {
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s ease-in-out;
        }

        /* พอ hover แถวเวลา ให้ปุ่มโผล่ */
        .chat-msg-footer:hover .chat-msg-menu-toggle {
            opacity: 1;
            pointer-events: auto;
        }

        /* ไอคอน ... ให้เล็กหน่อย */
        .chat-msg-menu-toggle .fa {
            font-size: 11px;
        }
        .chat-msg-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 6px;
        }

        .chat-msg-in {
            justify-content: flex-start;
        }

        .chat-msg-out {
            justify-content: flex-end;
        }

        .chat-avatar {
            flex: 0 0 auto;
        }

        .chat-avatar-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* จำกัดความกว้าง bubble ไม่ให้ยาวเต็มจอ */
        .chat-msg-main {
            max-width: 70%;
        }

        /* เวลาใต้ bubble ให้ดูเตี้ย ๆ คล้าย LINE */
        .chat-msg-time {
            line-height: 1.1;
        }

        .chat-time-wrapper {
            position: relative;
        }

        /* ปุ่ม ... ซ่อนก่อน */
        .chat-msg-menu-toggle {
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s ease-in-out;
        }

        /* โผล่ตอน hover */
        .chat-time-wrapper:hover .chat-msg-menu-toggle {
            opacity: 1;
            pointer-events: auto;
        }

        /* ลดขนาด icon … */
        .chat-msg-menu-toggle .fa {
            font-size: 11px;
        }
        .no-resize {
            resize: none;
        }

        /* กล่อง textarea ให้ดูเป็นกรอบเดียวกับของ LINE */
        .chat-reply-textarea {
            border-radius: 4px;
            font-size: 14px;
        }

        /* ปุ่มไอคอนล่างซ้าย ให้ดูเป็นแค่ไอคอนบาง ๆ */
        .chat-tool-btn {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .chat-tool-btn i {
            font-size: 16px;
            color: #7a8699;
        }

        .chat-tool-btn:disabled i {
            opacity: .4;
        }

        /* ปุ่มส่งสีเขียว */
        .chat-send-btn {
            min-width: 64px;
            font-weight: 600;
            border-radius: 4px;
        }


    </style>
@endpush