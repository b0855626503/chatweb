<?php

namespace Gametech\Sms\Transformers;

use Illuminate\Support\Str;

class SmsCampaignTransformer
{
    public function transform($item): array
    {
        // กันกรณี schema บางเว็บยังใช้ description เป็นชื่อ
        $name = $item->name ?? $item->description ?? '-';

        return [
            'id' => (int) $item->id,

            'name' => e($name),

            'status' => e((string) ($item->status ?? 'draft')),

            'audience_mode' => e((string) ($item->audience_mode ?? '-')),

            'provider' => e((string) ($item->provider ?? 'vonage')),

            'message_short' => e(Str::limit((string) ($item->message ?? ''), 80)),

            // counters (ควรมีใน schema campaign)
            'recipients_total' => (int) ($item->total_recipients ?? 0),
            'delivered_count' => (int) ($item->delivered_count ?? 0),
            'failed_count' => (int) ($item->failed_count ?? 0),

            'scheduled_at' => $item->scheduled_at ? (string) $item->scheduled_at : '-',
            'updated_at' => $item->updated_at ? (string) $item->updated_at : '-',

            // action ปล่อยให้ฝั่ง blade/js สร้างปุ่มตามแพตเทิร์นเดิมของคุณ
            // (ถ้าคุณมี partial อยู่แล้วสามารถ render view ได้แทน)
            'action' => $this->actionButtons($item),
        ];
    }

    private function actionButtons($item): string
    {
        // คุณน่าจะมีปุ่ม edit/delete เรียก Vue method อยู่แล้ว
        // สร้างแบบ simple ให้ก่อน (เข้ากับ text-nowrap)
        $id = (int) $item->id;

        return '
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary" onclick="window.app && window.app.editModal && window.app.editModal(' . $id . ')">แก้ไข</button>
                <button type="button" class="btn btn-outline-danger" onclick="window.app && window.app.deleteItem && window.app.deleteItem(' . $id . ')">ลบ</button>
            </div>
        ';
    }
}
