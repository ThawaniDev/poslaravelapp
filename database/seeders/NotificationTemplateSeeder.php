<?php

namespace Database\Seeders;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationProvider;
use App\Domain\Notification\Models\NotificationProviderStatus;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\NotificationTemplateService;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTemplates();
        $this->seedProviderStatuses();
    }

    private function seedTemplates(): void
    {
        $catalog = NotificationTemplateService::allEvents();

        // The channels each event should have templates for.
        // We seed InApp and Push for all; SMS/Email for critical events.
        $defaultChannels = [NotificationChannel::InApp, NotificationChannel::Push];
        $criticalChannels = [
            NotificationChannel::InApp,
            NotificationChannel::Push,
            NotificationChannel::Sms,
            NotificationChannel::Email,
        ];

        // Template content per event (EN/AR)
        $content = $this->getTemplateContent();

        foreach ($catalog as $eventKey => $meta) {
            $channels = $meta['is_critical'] ? $criticalChannels : $defaultChannels;

            foreach ($channels as $channel) {
                $key = $eventKey;
                $en = $content[$key] ?? null;
                if (!$en) {
                    continue;
                }

                NotificationTemplate::updateOrCreate(
                    ['event_key' => $key, 'channel' => $channel],
                    [
                        'title' => $en['title'],
                        'title_ar' => $en['title_ar'],
                        'body' => $en['body'],
                        'body_ar' => $en['body_ar'],
                        'available_variables' => $meta['variables'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function seedProviderStatuses(): void
    {
        $providerChannels = [
            // SMS providers
            ['provider' => NotificationProvider::Unifonic, 'channel' => NotificationChannel::Sms, 'priority' => 1],
            ['provider' => NotificationProvider::Taqnyat, 'channel' => NotificationChannel::Sms, 'priority' => 2],
            ['provider' => NotificationProvider::Msegat, 'channel' => NotificationChannel::Sms, 'priority' => 3],
            // Email providers
            ['provider' => NotificationProvider::Mailgun, 'channel' => NotificationChannel::Email, 'priority' => 1],
            ['provider' => NotificationProvider::Ses, 'channel' => NotificationChannel::Email, 'priority' => 2],
            ['provider' => NotificationProvider::Smtp, 'channel' => NotificationChannel::Email, 'priority' => 3],
            // WhatsApp
            ['provider' => NotificationProvider::Unifonic, 'channel' => NotificationChannel::Whatsapp, 'priority' => 1],
        ];

        foreach ($providerChannels as $pc) {
            NotificationProviderStatus::updateOrCreate(
                ['provider' => $pc['provider'], 'channel' => $pc['channel']],
                [
                    'priority' => $pc['priority'],
                    'is_enabled' => true,
                    'is_healthy' => true,
                    'failure_count_24h' => 0,
                    'success_count_24h' => 0,
                ],
            );
        }
    }

    private function getTemplateContent(): array
    {
        return [
            // ── Order Events ─────────────────────────────────
            'order.new' => [
                'title' => 'New Order #{{order_id}}',
                'title_ar' => 'طلب جديد #{{order_id}}',
                'body' => 'A new order has been placed at {{store_name}} - {{branch_name}} for {{total}}.',
                'body_ar' => 'تم استلام طلب جديد في {{store_name}} - {{branch_name}} بقيمة {{total}}.',
            ],
            'order.new_external' => [
                'title' => 'External Order #{{order_id}} from {{platform}}',
                'title_ar' => 'طلب خارجي #{{order_id}} من {{platform}}',
                'body' => '{{customer_name}} placed an order with {{item_count}} items ({{total}}) via {{platform}} at {{store_name}}.',
                'body_ar' => 'قام {{customer_name}} بطلب {{item_count}} أصناف ({{total}}) عبر {{platform}} في {{store_name}}.',
            ],
            'order.status_changed' => [
                'title' => 'Order #{{order_id}} Status Changed',
                'title_ar' => 'تغيير حالة الطلب #{{order_id}}',
                'body' => 'Order #{{order_id}} at {{store_name}} changed from {{old_status}} to {{new_status}}.',
                'body_ar' => 'تم تغيير حالة الطلب #{{order_id}} في {{store_name}} من {{old_status}} إلى {{new_status}}.',
            ],
            'order.completed' => [
                'title' => 'Order #{{order_id}} Completed',
                'title_ar' => 'تم إكمال الطلب #{{order_id}}',
                'body' => 'Order #{{order_id}} has been fulfilled at {{store_name}} ({{total}}).',
                'body_ar' => 'تم إكمال الطلب #{{order_id}} في {{store_name}} ({{total}}).',
            ],
            'order.cancelled' => [
                'title' => 'Order #{{order_id}} Cancelled',
                'title_ar' => 'تم إلغاء الطلب #{{order_id}}',
                'body' => 'Order #{{order_id}} ({{total}}) was cancelled at {{store_name}}. Reason: {{reason}}.',
                'body_ar' => 'تم إلغاء الطلب #{{order_id}} ({{total}}) في {{store_name}}. السبب: {{reason}}.',
            ],
            'order.refund_requested' => [
                'title' => 'Refund Requested for Order #{{order_id}}',
                'title_ar' => 'طلب استرداد للطلب #{{order_id}}',
                'body' => 'A refund of {{amount}} has been requested for order #{{order_id}} at {{store_name}}.',
                'body_ar' => 'تم طلب استرداد بقيمة {{amount}} للطلب #{{order_id}} في {{store_name}}.',
            ],
            'order.refund_approved' => [
                'title' => 'Refund Approved for Order #{{order_id}}',
                'title_ar' => 'تمت الموافقة على الاسترداد للطلب #{{order_id}}',
                'body' => 'A refund of {{amount}} for order #{{order_id}} has been processed at {{store_name}}.',
                'body_ar' => 'تم معالجة استرداد بقيمة {{amount}} للطلب #{{order_id}} في {{store_name}}.',
            ],
            'order.payment_failed' => [
                'title' => '⚠ Payment Failed — Order #{{order_id}}',
                'title_ar' => '⚠ فشل الدفع — الطلب #{{order_id}}',
                'body' => 'Payment of {{total}} failed for order #{{order_id}} at {{store_name}}. Please check.',
                'body_ar' => 'فشل دفع {{total}} للطلب #{{order_id}} في {{store_name}}. يرجى المراجعة.',
            ],

            // ── Inventory Events ─────────────────────────────
            'inventory.low_stock' => [
                'title' => 'Low Stock: {{product_name}}',
                'title_ar' => 'مخزون منخفض: {{product_name}}',
                'body' => '{{product_name}} is below reorder point at {{store_name}}. Current: {{current_qty}}, Reorder: {{reorder_point}}.',
                'body_ar' => '{{product_name}} أقل من نقطة إعادة الطلب في {{store_name}}. الحالي: {{current_qty}}، إعادة الطلب: {{reorder_point}}.',
            ],
            'inventory.out_of_stock' => [
                'title' => 'Out of Stock: {{product_name}}',
                'title_ar' => 'نفاد المخزون: {{product_name}}',
                'body' => '{{product_name}} is out of stock at {{store_name}}.',
                'body_ar' => 'نفد مخزون {{product_name}} في {{store_name}}.',
            ],
            'inventory.expiry_warning' => [
                'title' => 'Expiry Warning: {{product_name}}',
                'title_ar' => 'تحذير انتهاء صلاحية: {{product_name}}',
                'body' => '{{product_name}} at {{store_name}} expires on {{expiry_date}} ({{days_remaining}} days remaining).',
                'body_ar' => '{{product_name}} في {{store_name}} ينتهي في {{expiry_date}} (متبقي {{days_remaining}} يوم).',
            ],
            'inventory.excess_stock' => [
                'title' => 'Excess Stock: {{product_name}}',
                'title_ar' => 'فائض مخزون: {{product_name}}',
                'body' => '{{product_name}} at {{store_name}} exceeds max threshold. Current: {{current_qty}}, Max: {{max_threshold}}.',
                'body_ar' => '{{product_name}} في {{store_name}} يتجاوز الحد الأقصى. الحالي: {{current_qty}}، الحد: {{max_threshold}}.',
            ],
            'inventory.adjustment' => [
                'title' => 'Stock Adjusted: {{product_name}}',
                'title_ar' => 'تعديل مخزون: {{product_name}}',
                'body' => '{{product_name}} at {{store_name}} adjusted by {{adjusted_by}}: {{old_qty}} → {{new_qty}}.',
                'body_ar' => '{{product_name}} في {{store_name}} تم تعديله بواسطة {{adjusted_by}}: {{old_qty}} → {{new_qty}}.',
            ],

            // ── Finance Events ───────────────────────────────
            'finance.daily_summary' => [
                'title' => 'Daily Summary — {{date}}',
                'title_ar' => 'ملخص يومي — {{date}}',
                'body' => '{{store_name}}: {{total_transactions}} transactions totaling {{total_sales}} on {{date}}.',
                'body_ar' => '{{store_name}}: {{total_transactions}} معاملة بإجمالي {{total_sales}} في {{date}}.',
            ],
            'finance.shift_closed' => [
                'title' => 'Shift Closed — {{cashier_name}}',
                'title_ar' => 'تم إغلاق الوردية — {{cashier_name}}',
                'body' => '{{cashier_name}} closed their shift at {{store_name}}. Sales: {{total_sales}}. Cash expected: {{cash_expected}}, actual: {{cash_actual}}.',
                'body_ar' => '{{cashier_name}} أغلق ورديته في {{store_name}}. المبيعات: {{total_sales}}. النقد المتوقع: {{cash_expected}}، الفعلي: {{cash_actual}}.',
            ],
            'finance.cash_discrepancy' => [
                'title' => '⚠ Cash Discrepancy — {{cashier_name}}',
                'title_ar' => '⚠ اختلاف في النقد — {{cashier_name}}',
                'body' => '{{cashier_name}} at {{store_name}}: expected {{expected}}, actual {{actual}}, difference {{difference}}.',
                'body_ar' => '{{cashier_name}} في {{store_name}}: المتوقع {{expected}}، الفعلي {{actual}}، الفرق {{difference}}.',
            ],
            'finance.large_transaction' => [
                'title' => 'Large Transaction Alert',
                'title_ar' => 'تنبيه معاملة كبيرة',
                'body' => 'Transaction {{transaction_id}} of {{amount}} processed by {{cashier_name}} at {{store_name}}.',
                'body_ar' => 'المعاملة {{transaction_id}} بقيمة {{amount}} تمت بواسطة {{cashier_name}} في {{store_name}}.',
            ],

            // ── System Events ────────────────────────────────
            'system.offline_mode' => [
                'title' => '⚠ POS Offline — {{device_name}}',
                'title_ar' => '⚠ نظام نقاط البيع غير متصل — {{device_name}}',
                'body' => '{{device_name}} at {{store_name}} has gone offline. Local transactions will sync when reconnected.',
                'body_ar' => '{{device_name}} في {{store_name}} أصبح غير متصل. ستتم مزامنة المعاملات المحلية عند إعادة الاتصال.',
            ],
            'system.sync_failed' => [
                'title' => '⚠ Sync Failed — {{platform}}',
                'title_ar' => '⚠ فشل المزامنة — {{platform}}',
                'body' => 'Synchronization with {{platform}} failed at {{store_name}}: {{error_message}}.',
                'body_ar' => 'فشلت المزامنة مع {{platform}} في {{store_name}}: {{error_message}}.',
            ],
            'system.printer_error' => [
                'title' => 'Printer Error: {{printer_name}}',
                'title_ar' => 'خطأ في الطابعة: {{printer_name}}',
                'body' => '{{printer_name}} at {{store_name}} is offline or has an error.',
                'body_ar' => '{{printer_name}} في {{store_name}} غير متصلة أو بها خطأ.',
            ],
            'system.update_available' => [
                'title' => 'Update Available — {{version}}',
                'title_ar' => 'تحديث متاح — {{version}}',
                'body' => 'POS version {{version}} is available. {{release_notes_summary}}',
                'body_ar' => 'إصدار نقاط البيع {{version}} متاح. {{release_notes_summary}}',
            ],
            'system.license_expiring' => [
                'title' => 'License Expiring — {{plan_name}}',
                'title_ar' => 'انتهاء الترخيص — {{plan_name}}',
                'body' => 'Your {{plan_name}} subscription expires on {{expiry_date}} ({{days_remaining}} days remaining).',
                'body_ar' => 'اشتراكك في {{plan_name}} ينتهي في {{expiry_date}} (متبقي {{days_remaining}} يوم).',
            ],

            // ── Staff Events ─────────────────────────────────
            'staff.login' => [
                'title' => 'Employee Login: {{user_name}}',
                'title_ar' => 'تسجيل دخول موظف: {{user_name}}',
                'body' => '{{user_name}} logged in on {{device_name}} at {{store_name}}.',
                'body_ar' => '{{user_name}} سجل الدخول على {{device_name}} في {{store_name}}.',
            ],
            'staff.unauthorized_access' => [
                'title' => '⚠ Unauthorized Access — {{user_name}}',
                'title_ar' => '⚠ وصول غير مصرح — {{user_name}}',
                'body' => '{{user_name}} attempted "{{attempted_action}}" without permission at {{store_name}}.',
                'body_ar' => '{{user_name}} حاول تنفيذ "{{attempted_action}}" بدون إذن في {{store_name}}.',
            ],
            'staff.discount_applied' => [
                'title' => 'High Discount Applied — {{user_name}}',
                'title_ar' => 'خصم عالي مُطبق — {{user_name}}',
                'body' => '{{user_name}} applied {{discount_pct}} discount on order #{{order_id}} at {{store_name}}.',
                'body_ar' => '{{user_name}} طبق خصم {{discount_pct}} على الطلب #{{order_id}} في {{store_name}}.',
            ],
            'staff.void_transaction' => [
                'title' => 'Transaction Voided — {{user_name}}',
                'title_ar' => 'إلغاء معاملة — {{user_name}}',
                'body' => '{{user_name}} voided transaction {{transaction_id}} ({{amount}}) at {{store_name}}.',
                'body_ar' => '{{user_name}} ألغى المعاملة {{transaction_id}} ({{amount}}) في {{store_name}}.',
            ],
        ];
    }
}
