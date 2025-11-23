// นับเฉพาะ global block เท่านั้น (default = silent)
let pendingGlobal = 0

// กันกระพริบ: ดีเลย์เปิด/ปิด + บังคับแสดงขั้นต่ำ
const OPEN_DELAY  = 180   // ms กว่าจะโชว์
const CLOSE_DELAY = 120   // ms กว่าจะปิดหลังสุดท้ายจบ
const MIN_SHOW    = 300   // ms ระยะเวลาขั้นต่ำถ้าเปิดแล้ว

let openTimer  = null
let closeTimer = null
let lastOpenAt = 0

function openBlock() {
    clearTimeout(openTimer)
    clearTimeout(closeTimer)
    openTimer = setTimeout(() => {
        lastOpenAt = Date.now()
        window.app?.$blockUI?.()
    }, OPEN_DELAY)
}

function closeBlock() {
    const doClose = () => window.app?.$unblockUI?.()
    const elapsed = Date.now() - lastOpenAt
    const wait = Math.max(0, MIN_SHOW - elapsed)

    clearTimeout(openTimer)
    clearTimeout(closeTimer)
    closeTimer = setTimeout(doClose, CLOSE_DELAY + wait)
}

axios.interceptors.request.use(cfg => {
    const scope = cfg?.meta?.block ?? 'silent'
    if (scope === 'global') {
        if (++pendingGlobal === 1) openBlock()
    }
    return cfg
}, err => {
    const scope = err?.config?.meta?.block ?? 'silent'
    if (scope === 'global') {
        pendingGlobal = Math.max(0, pendingGlobal - 1)
        if (pendingGlobal === 0) closeBlock()
    }
    return Promise.reject(err)
})

axios.interceptors.response.use(resp => {
    const scope = resp?.config?.meta?.block ?? 'silent'
    if (scope === 'global') {
        pendingGlobal = Math.max(0, pendingGlobal - 1)
        if (pendingGlobal === 0) closeBlock()
    }
    return resp
}, err => {
    const scope = err?.config?.meta?.block ?? 'silent'
    if (scope === 'global') {
        pendingGlobal = Math.max(0, pendingGlobal - 1)
        if (pendingGlobal === 0) closeBlock()
    }
    return Promise.reject(err)
})
