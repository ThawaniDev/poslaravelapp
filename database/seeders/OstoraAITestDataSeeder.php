<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Comprehensive test data for Ostora production stores.
 * Inserts realistic data to test all 33 Wameed AI features.
 *
 * Run: php artisan db:seed --class=OstoraAITestDataSeeder
 */
class OstoraAITestDataSeeder extends Seeder
{
    // ── Production IDs ──────────────────────────────────────
    const ORG   = '019cdaab-f9f5-71c8-9ce9-155d0fea90c0';
    const STORE1 = '019cdaab-fc9c-72b7-8db6-0aa8fb02dbd4'; // Main Branch
    const STORE2 = 'b81d0024-a5f0-4a5a-9dbb-44e581098782'; // Branch 2

    // Users
    const OWNER     = '019cdaac-00a0-70fd-b4ae-e9ebe5d083c7'; // Mohammed - Store1
    const AHMED     = '019d43a7-0d7e-7086-b438-ed3124051822'; // Cashier - Store1
    const KAMAL     = '019d43bb-f4f4-7182-927c-a0446fd426cf'; // Cashier - Store2
    const BRANCH_MGR = '019d663c-6170-709d-ba93-bd3ca8a4c270'; // Manager - Store2

    // Registers
    const REG1_S1 = '6630903c-fb6f-496d-a1bb-83de921ff694';
    const REG2_S1 = '783602ae-8e29-4b48-9487-6ec27ad332e1';
    const REG1_S2 = '019d6a0e-5ea0-73aa-ae53-27518a7fd67c';
    const REG2_S2 = '019d6a0e-63ab-71b0-963c-a4b5c5cd8b40';

    // Existing categories
    const CAT_FRUITS    = '3d54c236-1d24-4509-9541-d60454f18168';
    const CAT_DAIRY     = 'ccc21198-1319-423e-a5a7-916e8d04cef3';
    const CAT_BEVERAGES = 'f74e9a97-25d0-49bf-9029-3aac5ae54fd7';
    const CAT_SNACKS    = 'da6255e7-5efa-4db2-8a2e-b23f4ae7c0de';
    const CAT_BAKERY    = 'daf08bd5-7aa9-494d-822a-c5fddf2ddc30';

    // New categories
    const CAT_CANNED   = 'cc000001-0000-4000-a000-000000000001';
    const CAT_PERSONAL = 'cc000001-0000-4000-a000-000000000002';
    const CAT_CLEANING = 'cc000001-0000-4000-a000-000000000003';
    const CAT_FROZEN   = 'cc000001-0000-4000-a000-000000000004';
    const CAT_GRAINS   = 'cc000001-0000-4000-a000-000000000005';
    const CAT_MEAT     = 'cc000001-0000-4000-a000-000000000006';
    const CAT_BABY     = 'cc000001-0000-4000-a000-000000000007';

    // Existing supplier
    const SUP_ALMARAI = '612d45de-f5a6-44e7-b45c-1d7c125fea2b';

    // New suppliers
    const SUP_FRESH  = '55000001-0000-4000-a000-000000000001';
    const SUP_GULF   = '55000001-0000-4000-a000-000000000002';
    const SUP_CLEAN  = '55000001-0000-4000-a000-000000000003';
    const SUP_FROZEN = '55000001-0000-4000-a000-000000000004';

    // Staff user IDs (for attendance_records FK)
    const STAFF_AHMED      = 'dd000001-0000-4000-a000-000000000001';
    const STAFF_OWNER      = 'dd000001-0000-4000-a000-000000000002';
    const STAFF_KAMAL      = 'dd000001-0000-4000-a000-000000000003';
    const STAFF_BRANCH_MGR = 'dd000001-0000-4000-a000-000000000004';

    // Existing customer groups
    const GRP_VIP       = '4644f3aa-70ee-49ec-a62f-8012bf789b81';
    const GRP_REGULAR   = 'fef29ffc-edfc-45c7-a0e9-6d93f2116edb';
    const GRP_WHOLESALE = '5d2af556-7eb6-438c-9b6d-547337d92c67';

    // Existing customer IDs
    const CUST_KHALID = '5c17502d-7bfe-46fa-8244-33670936d1a2';
    const CUST_NORA   = '74827866-c9d7-4053-ada0-af8f0cbb7360';
    const CUST_FATIMA = '23a1f942-d58b-4a04-af59-96f76891f576';

    private array $staffUserMap = []; // user_id => staff_user_id

    private array $allProducts = [];
    private array $popularProductIds = [];
    private array $mediumProductIds = [];
    private array $lowProductIds = [];
    private array $deadStockIds = [];
    private array $allCustomerIds = [];
    private array $newCustomerIds = [];
    private int $txnCounter = 50000;

    public function run(): void
    {
        DB::beginTransaction();
        try {
            $this->buildProductCatalog();
            echo "Starting Ostora AI Test Data Seeder...\n";

            $this->seedCategories();
            echo "✓ 7 new categories\n";

            $this->seedNewProducts();
            echo "✓ " . count(array_filter($this->allProducts, fn($p) => $p['new'])) . " new products\n";

            $this->seedSuppliers();
            echo "✓ 4 new suppliers\n";

            $this->seedProductSuppliers();
            echo "✓ Product-supplier links\n";

            $this->seedCustomers();
            echo "✓ " . count($this->newCustomerIds) . " new customers\n";

            $this->seedStockLevels();
            echo "✓ Stock levels for both stores\n";

            $this->seedStockBatches();
            echo "✓ Stock batches with varied expiry\n";

            $this->seedTransactions();
            echo "✓ Transactions over 90 days\n";

            $this->seedPurchaseOrders();
            echo "✓ Purchase orders\n";

            $this->seedGoodsReceipts();
            echo "✓ Goods receipts\n";

            $this->seedStockAdjustments();
            echo "✓ Stock adjustments\n";

            $this->seedStaffUsers();
            echo "✓ Staff users\n";

            $this->seedAttendanceRecords();
            echo "✓ Attendance records\n";

            $this->seedLoyaltyTransactions();
            echo "✓ Loyalty transactions\n";

            $this->updateCustomerStats();
            echo "✓ Customer stats updated\n";

            DB::commit();
            echo "\n🎉 All Ostora AI test data seeded successfully!\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error: " . $e->getMessage() . " at line " . $e->getLine() . "\n";
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRODUCT CATALOG (existing + new)
    // ─────────────────────────────────────────────────────────
    private function buildProductCatalog(): void
    {
        $products = [
            // Existing products
            ['id' => 'd3a139cc-3392-4b14-94cf-ce51cd7c5e00', 'name' => 'Banana', 'name_ar' => 'موز', 'sku' => 'FRU-001', 'barcode' => '6281000000101', 'sell' => 5.00, 'cost' => 2.50, 'cat' => self::CAT_FRUITS, 'w' => 'high', 'new' => false],
            ['id' => '705cbfe0-7620-4526-a547-3301102c79d8', 'name' => 'Apple Red', 'name_ar' => 'تفاح أحمر', 'sku' => 'FRU-002', 'barcode' => '6281000000102', 'sell' => 12.00, 'cost' => 7.00, 'cat' => self::CAT_FRUITS, 'w' => 'med', 'new' => false],
            ['id' => 'a65d5f7b-23dc-4145-9e92-b20991ccc8bc', 'name' => 'Fresh Milk 1L', 'name_ar' => 'حليب طازج 1 لتر', 'sku' => 'DAI-001', 'barcode' => '6281000000201', 'sell' => 6.50, 'cost' => 4.00, 'cat' => self::CAT_DAIRY, 'w' => 'high', 'new' => false],
            ['id' => 'c5250ab5-aaa4-445e-9942-d4fef2573de1', 'name' => 'Eggs Pack 30', 'name_ar' => 'بيض 30 حبة', 'sku' => 'DAI-002', 'barcode' => '6281000000202', 'sell' => 18.00, 'cost' => 12.00, 'cat' => self::CAT_DAIRY, 'w' => 'high', 'new' => false],
            ['id' => 'f3a47329-fe27-4d08-976e-f9c2df2652b4', 'name' => 'Water 500ml', 'name_ar' => 'ماء 500 مل', 'sku' => 'BEV-001', 'barcode' => '6281000000301', 'sell' => 1.00, 'cost' => 0.30, 'cat' => self::CAT_BEVERAGES, 'w' => 'high', 'new' => false],
            ['id' => 'cde2dfad-0faa-4ad5-a9a7-a5995b7fc73e', 'name' => 'Cola 330ml', 'name_ar' => 'كولا 330 مل', 'sku' => 'BEV-002', 'barcode' => '6281000000302', 'sell' => 2.50, 'cost' => 1.00, 'cat' => self::CAT_BEVERAGES, 'w' => 'high', 'new' => false],
            ['id' => '2daa540a-f34d-4b7c-8612-f5ed2d8774c3', 'name' => 'Chips Original', 'name_ar' => 'شيبس أصلي', 'sku' => 'SNK-001', 'barcode' => '6281000000401', 'sell' => 4.00, 'cost' => 2.00, 'cat' => self::CAT_SNACKS, 'w' => 'high', 'new' => false],
            ['id' => '4c4ee1d3-4240-4731-8755-05c57a6a898b', 'name' => 'Arabic Bread', 'name_ar' => 'خبز عربي', 'sku' => 'BAK-001', 'barcode' => '6281000000501', 'sell' => 2.00, 'cost' => 0.80, 'cat' => self::CAT_BAKERY, 'w' => 'high', 'new' => false],
            ['id' => '2a68447d-f616-4da6-9233-ec9548050bd2', 'name' => 'Croissant', 'name_ar' => 'كرواسون', 'sku' => 'BAK-002', 'barcode' => '6281000000502', 'sell' => 5.00, 'cost' => 2.00, 'cat' => self::CAT_BAKERY, 'w' => 'med', 'new' => false],
            ['id' => '46e732dd-1b41-45f4-babb-ac1f8c726c65', 'name' => 'Chocolate Bar', 'name_ar' => 'لوح شوكولاتة', 'sku' => 'SNK-002', 'barcode' => '12345678', 'sell' => 3.50, 'cost' => 1.50, 'cat' => self::CAT_SNACKS, 'w' => 'high', 'new' => false],

            // New – Fruits & Vegetables
            ['id' => 'aa000001-0000-4000-a000-000000000001', 'name' => 'Orange', 'name_ar' => 'برتقال', 'sku' => 'FRU-003', 'barcode' => '6281000000103', 'sell' => 8.00, 'cost' => 4.50, 'cat' => self::CAT_FRUITS, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000002', 'name' => 'Tomato 1kg', 'name_ar' => 'طماطم 1 كيلو', 'sku' => 'FRU-004', 'barcode' => '6281000000104', 'sell' => 4.00, 'cost' => 2.00, 'cat' => self::CAT_FRUITS, 'w' => 'high', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000003', 'name' => 'Cucumber', 'name_ar' => 'خيار', 'sku' => 'FRU-005', 'barcode' => '6281000000105', 'sell' => 3.50, 'cost' => 1.50, 'cat' => self::CAT_FRUITS, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000004', 'name' => 'Watermelon', 'name_ar' => 'بطيخ', 'sku' => 'FRU-006', 'barcode' => '6281000000106', 'sell' => 15.00, 'cost' => 8.00, 'cat' => self::CAT_FRUITS, 'w' => 'low', 'new' => true],

            // New – Dairy
            ['id' => 'aa000001-0000-4000-a000-000000000005', 'name' => 'Yogurt 1kg', 'name_ar' => 'زبادي 1 كيلو', 'sku' => 'DAI-003', 'barcode' => '6281000000203', 'sell' => 8.00, 'cost' => 5.00, 'cat' => self::CAT_DAIRY, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000006', 'name' => 'Cheese Slices', 'name_ar' => 'جبنة شرائح', 'sku' => 'DAI-004', 'barcode' => '6281000000204', 'sell' => 12.00, 'cost' => 7.50, 'cat' => self::CAT_DAIRY, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000007', 'name' => 'Butter 200g', 'name_ar' => 'زبدة 200 جرام', 'sku' => 'DAI-005', 'barcode' => '6281000000205', 'sell' => 15.00, 'cost' => 9.00, 'cat' => self::CAT_DAIRY, 'w' => 'low', 'new' => true],

            // New – Beverages
            ['id' => 'aa000001-0000-4000-a000-000000000008', 'name' => 'Orange Juice 1L', 'name_ar' => 'عصير برتقال 1 لتر', 'sku' => 'BEV-003', 'barcode' => '6281000000303', 'sell' => 9.00, 'cost' => 5.50, 'cat' => self::CAT_BEVERAGES, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000009', 'name' => 'Energy Drink', 'name_ar' => 'مشروب طاقة', 'sku' => 'BEV-004', 'barcode' => '6281000000304', 'sell' => 7.00, 'cost' => 3.00, 'cat' => self::CAT_BEVERAGES, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000010', 'name' => 'Tea Bags 100pk', 'name_ar' => 'شاي 100 كيس', 'sku' => 'BEV-005', 'barcode' => '6281000000305', 'sell' => 18.00, 'cost' => 10.00, 'cat' => self::CAT_BEVERAGES, 'w' => 'low', 'new' => true],

            // New – Snacks
            ['id' => 'aa000001-0000-4000-a000-000000000011', 'name' => 'Biscuits', 'name_ar' => 'بسكويت', 'sku' => 'SNK-003', 'barcode' => '6281000000402', 'sell' => 3.00, 'cost' => 1.50, 'cat' => self::CAT_SNACKS, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000012', 'name' => 'Mixed Nuts 500g', 'name_ar' => 'مكسرات مشكلة 500 جرام', 'sku' => 'SNK-004', 'barcode' => '6281000000403', 'sell' => 25.00, 'cost' => 15.00, 'cat' => self::CAT_SNACKS, 'w' => 'low', 'new' => true],

            // New – Bakery
            ['id' => 'aa000001-0000-4000-a000-000000000013', 'name' => 'Cake Slice', 'name_ar' => 'قطعة كيك', 'sku' => 'BAK-003', 'barcode' => '6281000000503', 'sell' => 8.00, 'cost' => 3.50, 'cat' => self::CAT_BAKERY, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000014', 'name' => 'Samosa 6pk', 'name_ar' => 'سمبوسة 6 حبات', 'sku' => 'BAK-004', 'barcode' => '6281000000504', 'sell' => 6.00, 'cost' => 2.50, 'cat' => self::CAT_BAKERY, 'w' => 'med', 'new' => true],

            // New – Canned Food
            ['id' => 'aa000001-0000-4000-a000-000000000015', 'name' => 'Tuna Can', 'name_ar' => 'تونا معلبة', 'sku' => 'CAN-001', 'barcode' => '6281000000601', 'sell' => 8.00, 'cost' => 5.00, 'cat' => self::CAT_CANNED, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000016', 'name' => 'Baked Beans', 'name_ar' => 'فاصوليا معلبة', 'sku' => 'CAN-002', 'barcode' => '6281000000602', 'sell' => 5.00, 'cost' => 2.50, 'cat' => self::CAT_CANNED, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000017', 'name' => 'Tomato Paste', 'name_ar' => 'معجون طماطم', 'sku' => 'CAN-003', 'barcode' => '6281000000603', 'sell' => 4.00, 'cost' => 2.00, 'cat' => self::CAT_CANNED, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000018', 'name' => 'Chickpeas Can', 'name_ar' => 'حمص معلب', 'sku' => 'CAN-004', 'barcode' => '6281000000604', 'sell' => 4.50, 'cost' => 2.00, 'cat' => self::CAT_CANNED, 'w' => 'low', 'new' => true],

            // New – Personal Care
            ['id' => 'aa000001-0000-4000-a000-000000000019', 'name' => 'Shampoo', 'name_ar' => 'شامبو', 'sku' => 'PC-001', 'barcode' => '6281000000701', 'sell' => 22.00, 'cost' => 12.00, 'cat' => self::CAT_PERSONAL, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000020', 'name' => 'Soap Bar', 'name_ar' => 'صابون', 'sku' => 'PC-002', 'barcode' => '6281000000702', 'sell' => 5.00, 'cost' => 2.50, 'cat' => self::CAT_PERSONAL, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000021', 'name' => 'Toothpaste', 'name_ar' => 'معجون أسنان', 'sku' => 'PC-003', 'barcode' => '6281000000703', 'sell' => 12.00, 'cost' => 6.00, 'cat' => self::CAT_PERSONAL, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000022', 'name' => 'Tissue Box', 'name_ar' => 'مناديل', 'sku' => 'PC-004', 'barcode' => '6281000000704', 'sell' => 8.00, 'cost' => 4.00, 'cat' => self::CAT_PERSONAL, 'w' => 'med', 'new' => true],

            // New – Cleaning
            ['id' => 'aa000001-0000-4000-a000-000000000023', 'name' => 'Dish Soap', 'name_ar' => 'صابون أطباق', 'sku' => 'CLN-001', 'barcode' => '6281000000801', 'sell' => 10.00, 'cost' => 5.00, 'cat' => self::CAT_CLEANING, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000024', 'name' => 'Floor Cleaner', 'name_ar' => 'منظف أرضيات', 'sku' => 'CLN-002', 'barcode' => '6281000000802', 'sell' => 15.00, 'cost' => 8.00, 'cat' => self::CAT_CLEANING, 'w' => 'dead', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000025', 'name' => 'Laundry Detergent 3kg', 'name_ar' => 'مسحوق غسيل 3 كيلو', 'sku' => 'CLN-003', 'barcode' => '6281000000803', 'sell' => 28.00, 'cost' => 16.00, 'cat' => self::CAT_CLEANING, 'w' => 'low', 'new' => true],

            // New – Frozen
            ['id' => 'aa000001-0000-4000-a000-000000000026', 'name' => 'Frozen Chicken 1kg', 'name_ar' => 'دجاج مجمد 1 كيلو', 'sku' => 'FRZ-001', 'barcode' => '6281000000901', 'sell' => 25.00, 'cost' => 16.00, 'cat' => self::CAT_FROZEN, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000027', 'name' => 'Frozen Vegetables', 'name_ar' => 'خضار مجمدة', 'sku' => 'FRZ-002', 'barcode' => '6281000000902', 'sell' => 12.00, 'cost' => 7.00, 'cat' => self::CAT_FROZEN, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000028', 'name' => 'Ice Cream 1L', 'name_ar' => 'آيس كريم 1 لتر', 'sku' => 'FRZ-003', 'barcode' => '6281000000903', 'sell' => 18.00, 'cost' => 10.00, 'cat' => self::CAT_FROZEN, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000029', 'name' => 'Frozen Fries 1kg', 'name_ar' => 'بطاطس مجمدة 1 كيلو', 'sku' => 'FRZ-004', 'barcode' => '6281000000904', 'sell' => 10.00, 'cost' => 5.50, 'cat' => self::CAT_FROZEN, 'w' => 'low', 'new' => true],

            // New – Rice & Grains
            ['id' => 'aa000001-0000-4000-a000-000000000030', 'name' => 'Basmati Rice 5kg', 'name_ar' => 'أرز بسمتي 5 كيلو', 'sku' => 'RG-001', 'barcode' => '6281000001001', 'sell' => 35.00, 'cost' => 22.00, 'cat' => self::CAT_GRAINS, 'w' => 'high', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000031', 'name' => 'White Sugar 1kg', 'name_ar' => 'سكر أبيض 1 كيلو', 'sku' => 'RG-002', 'barcode' => '6281000001002', 'sell' => 8.00, 'cost' => 5.00, 'cat' => self::CAT_GRAINS, 'w' => 'high', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000032', 'name' => 'Flour 2kg', 'name_ar' => 'طحين 2 كيلو', 'sku' => 'RG-003', 'barcode' => '6281000001003', 'sell' => 10.00, 'cost' => 6.00, 'cat' => self::CAT_GRAINS, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000033', 'name' => 'Spaghetti 500g', 'name_ar' => 'معكرونة 500 جرام', 'sku' => 'RG-004', 'barcode' => '6281000001004', 'sell' => 5.00, 'cost' => 2.50, 'cat' => self::CAT_GRAINS, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000034', 'name' => 'Lentils 1kg', 'name_ar' => 'عدس 1 كيلو', 'sku' => 'RG-005', 'barcode' => '6281000001005', 'sell' => 7.00, 'cost' => 3.50, 'cat' => self::CAT_GRAINS, 'w' => 'low', 'new' => true],

            // New – Meat & Poultry
            ['id' => 'aa000001-0000-4000-a000-000000000035', 'name' => 'Fresh Chicken Whole', 'name_ar' => 'دجاج كامل طازج', 'sku' => 'MT-001', 'barcode' => '6281000001101', 'sell' => 22.00, 'cost' => 14.00, 'cat' => self::CAT_MEAT, 'w' => 'med', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000036', 'name' => 'Beef Mince 500g', 'name_ar' => 'لحم بقر مفروم 500 جرام', 'sku' => 'MT-002', 'barcode' => '6281000001102', 'sell' => 30.00, 'cost' => 20.00, 'cat' => self::CAT_MEAT, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000037', 'name' => 'Lamb Cuts 1kg', 'name_ar' => 'قطع لحم ضأن 1 كيلو', 'sku' => 'MT-003', 'barcode' => '6281000001103', 'sell' => 55.00, 'cost' => 38.00, 'cat' => self::CAT_MEAT, 'w' => 'low', 'new' => true],

            // New – Baby Products
            ['id' => 'aa000001-0000-4000-a000-000000000038', 'name' => 'Baby Diapers Pack', 'name_ar' => 'حفاضات أطفال', 'sku' => 'BB-001', 'barcode' => '6281000001201', 'sell' => 45.00, 'cost' => 28.00, 'cat' => self::CAT_BABY, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000039', 'name' => 'Baby Formula', 'name_ar' => 'حليب أطفال', 'sku' => 'BB-002', 'barcode' => '6281000001202', 'sell' => 65.00, 'cost' => 42.00, 'cat' => self::CAT_BABY, 'w' => 'dead', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000040', 'name' => 'Baby Wipes', 'name_ar' => 'مناديل أطفال مبللة', 'sku' => 'BB-003', 'barcode' => '6281000001203', 'sell' => 15.00, 'cost' => 8.00, 'cat' => self::CAT_BABY, 'w' => 'low', 'new' => true],

            // New – Uncategorized (for AI categorization feature)
            ['id' => 'aa000001-0000-4000-a000-000000000041', 'name' => 'Imported Snack Mix', 'name_ar' => 'خليط وجبات مستوردة', 'sku' => 'UNC-001', 'barcode' => '6281000001301', 'sell' => 15.00, 'cost' => 8.00, 'cat' => null, 'w' => 'low', 'new' => true],
            ['id' => 'aa000001-0000-4000-a000-000000000042', 'name' => 'Herbal Drink', 'name_ar' => 'مشروب أعشاب', 'sku' => 'UNC-002', 'barcode' => '6281000001302', 'sell' => 9.00, 'cost' => 5.00, 'cat' => null, 'w' => 'low', 'new' => true],
        ];

        $this->allProducts = $products;

        foreach ($products as $p) {
            match ($p['w']) {
                'high' => $this->popularProductIds[] = $p['id'],
                'med'  => $this->mediumProductIds[] = $p['id'],
                'dead' => $this->deadStockIds[] = $p['id'],
                default => $this->lowProductIds[] = $p['id'],
            };
        }
    }

    private function getProductById(string $id): ?array
    {
        foreach ($this->allProducts as $p) {
            if ($p['id'] === $id) return $p;
        }
        return null;
    }

    private function pickRandomProducts(int $count, bool $allowDeadStock = false): array
    {
        // Build weighted pool: high=10, med=5, low=2, dead=0
        $pool = [];
        foreach ($this->popularProductIds as $id) {
            for ($i = 0; $i < 10; $i++) $pool[] = $id;
        }
        foreach ($this->mediumProductIds as $id) {
            for ($i = 0; $i < 5; $i++) $pool[] = $id;
        }
        foreach ($this->lowProductIds as $id) {
            for ($i = 0; $i < 2; $i++) $pool[] = $id;
        }
        if ($allowDeadStock) {
            foreach ($this->deadStockIds as $id) {
                $pool[] = $id;
            }
        }

        $selected = [];
        $usedIds = [];
        $attempts = 0;
        while (count($selected) < $count && $attempts < 50) {
            $id = $pool[array_rand($pool)];
            if (!in_array($id, $usedIds)) {
                $usedIds[] = $id;
                $selected[] = $this->getProductById($id);
            }
            $attempts++;
        }
        return $selected;
    }

    // ─────────────────────────────────────────────────────────
    // CATEGORIES
    // ─────────────────────────────────────────────────────────
    private function seedCategories(): void
    {
        $cats = [
            ['id' => self::CAT_CANNED,   'name' => 'Canned Food',    'name_ar' => 'معلبات'],
            ['id' => self::CAT_PERSONAL, 'name' => 'Personal Care',  'name_ar' => 'عناية شخصية'],
            ['id' => self::CAT_CLEANING, 'name' => 'Cleaning',       'name_ar' => 'تنظيف'],
            ['id' => self::CAT_FROZEN,   'name' => 'Frozen Food',    'name_ar' => 'أطعمة مجمدة'],
            ['id' => self::CAT_GRAINS,   'name' => 'Rice & Grains',  'name_ar' => 'أرز وحبوب'],
            ['id' => self::CAT_MEAT,     'name' => 'Meat & Poultry', 'name_ar' => 'لحوم ودواجن'],
            ['id' => self::CAT_BABY,     'name' => 'Baby Products',  'name_ar' => 'مستلزمات أطفال'],
        ];

        foreach ($cats as $i => $c) {
            DB::table('categories')->updateOrInsert(
                ['id' => $c['id']],
                [
                    'organization_id' => self::ORG,
                    'name' => $c['name'],
                    'name_ar' => $c['name_ar'],
                    'sort_order' => 6 + $i,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRODUCTS
    // ─────────────────────────────────────────────────────────
    private function seedNewProducts(): void
    {
        $rows = [];
        foreach ($this->allProducts as $p) {
            if (!$p['new']) continue;
            $rows[] = [
                'id' => $p['id'],
                'organization_id' => self::ORG,
                'category_id' => $p['cat'],
                'name' => $p['name'],
                'name_ar' => $p['name_ar'],
                'sku' => $p['sku'],
                'barcode' => $p['barcode'],
                'sell_price' => $p['sell'],
                'cost_price' => $p['cost'],
                'tax_rate' => 15.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('products')->insert($chunk);
        }
    }

    // ─────────────────────────────────────────────────────────
    // SUPPLIERS
    // ─────────────────────────────────────────────────────────
    private function seedSuppliers(): void
    {
        $suppliers = [
            ['id' => self::SUP_FRESH, 'name' => 'Saudi Fresh Farms', 'phone' => '+966501234001', 'email' => 'orders@saudifresh.sa', 'contact_person' => 'Ahmad Al-Farsi', 'city' => 'Riyadh', 'country' => 'SA', 'payment_terms' => 'NET30', 'rating' => 4],
            ['id' => self::SUP_GULF, 'name' => 'Gulf Distribution Co.', 'phone' => '+966501234002', 'email' => 'sales@gulfdist.sa', 'contact_person' => 'Saeed Bin Omar', 'city' => 'Jeddah', 'country' => 'SA', 'payment_terms' => 'NET15', 'rating' => 5],
            ['id' => self::SUP_CLEAN, 'name' => 'Clean & Care Supplies', 'phone' => '+966501234003', 'email' => 'info@cleancare.sa', 'contact_person' => 'Maryam Hassan', 'city' => 'Dammam', 'country' => 'SA', 'payment_terms' => 'NET30', 'rating' => 3],
            ['id' => self::SUP_FROZEN, 'name' => 'Frozen Kingdom LLC', 'phone' => '+966501234004', 'email' => 'orders@frozenkingdom.sa', 'contact_person' => 'Khalid Al-Rashid', 'city' => 'Riyadh', 'country' => 'SA', 'payment_terms' => 'COD', 'rating' => 4],
        ];

        foreach ($suppliers as $s) {
            DB::table('suppliers')->updateOrInsert(
                ['id' => $s['id']],
                array_merge($s, [
                    'organization_id' => self::ORG,
                    'is_active' => true,
                    'outstanding_balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRODUCT-SUPPLIER LINKS
    // ─────────────────────────────────────────────────────────
    private function seedProductSuppliers(): void
    {
        $links = [
            // Al Marai → Dairy products
            [self::SUP_ALMARAI, 'a65d5f7b-23dc-4145-9e92-b20991ccc8bc', 4.00, 3],  // Milk
            [self::SUP_ALMARAI, 'c5250ab5-aaa4-445e-9942-d4fef2573de1', 12.00, 3], // Eggs
            [self::SUP_ALMARAI, 'aa000001-0000-4000-a000-000000000005', 5.00, 3],  // Yogurt
            [self::SUP_ALMARAI, 'aa000001-0000-4000-a000-000000000006', 7.50, 3],  // Cheese
            [self::SUP_ALMARAI, 'aa000001-0000-4000-a000-000000000007', 9.00, 5],  // Butter

            // Fresh Farms → Fruits, Vegetables, Meat
            [self::SUP_FRESH, 'd3a139cc-3392-4b14-94cf-ce51cd7c5e00', 2.50, 2], // Banana
            [self::SUP_FRESH, '705cbfe0-7620-4526-a547-3301102c79d8', 7.00, 2], // Apple
            [self::SUP_FRESH, 'aa000001-0000-4000-a000-000000000001', 4.50, 2], // Orange
            [self::SUP_FRESH, 'aa000001-0000-4000-a000-000000000002', 2.00, 1], // Tomato
            [self::SUP_FRESH, 'aa000001-0000-4000-a000-000000000003', 1.50, 1], // Cucumber
            [self::SUP_FRESH, 'aa000001-0000-4000-a000-000000000035', 14.00, 2], // Chicken
            [self::SUP_FRESH, 'aa000001-0000-4000-a000-000000000036', 20.00, 3], // Beef
            [self::SUP_FRESH, 'aa000001-0000-4000-a000-000000000037', 38.00, 3], // Lamb

            // Gulf Distribution → Beverages, Snacks, Canned, Grains
            [self::SUP_GULF, 'f3a47329-fe27-4d08-976e-f9c2df2652b4', 0.30, 5], // Water
            [self::SUP_GULF, 'cde2dfad-0faa-4ad5-a9a7-a5995b7fc73e', 1.00, 5], // Cola
            [self::SUP_GULF, '2daa540a-f34d-4b7c-8612-f5ed2d8774c3', 2.00, 7], // Chips
            [self::SUP_GULF, 'aa000001-0000-4000-a000-000000000008', 5.50, 5], // OJ
            [self::SUP_GULF, 'aa000001-0000-4000-a000-000000000009', 3.00, 7], // Energy
            [self::SUP_GULF, 'aa000001-0000-4000-a000-000000000010', 10.00, 14], // Tea
            [self::SUP_GULF, 'aa000001-0000-4000-a000-000000000015', 5.00, 10], // Tuna
            [self::SUP_GULF, 'aa000001-0000-4000-a000-000000000030', 22.00, 7], // Rice
            [self::SUP_GULF, 'aa000001-0000-4000-a000-000000000031', 5.00, 7],  // Sugar

            // Clean & Care → Personal Care, Cleaning, Baby
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000019', 12.00, 14], // Shampoo
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000020', 2.50, 14],  // Soap
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000021', 6.00, 14],  // Toothpaste
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000022', 4.00, 7],   // Tissue
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000023', 5.00, 14],  // Dish soap
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000025', 16.00, 14], // Detergent
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000038', 28.00, 14], // Diapers
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000039', 42.00, 21], // Formula
            [self::SUP_CLEAN, 'aa000001-0000-4000-a000-000000000040', 8.00, 14],  // Baby wipes

            // Frozen Kingdom → Frozen food
            [self::SUP_FROZEN, 'aa000001-0000-4000-a000-000000000026', 16.00, 3], // Frozen Chicken
            [self::SUP_FROZEN, 'aa000001-0000-4000-a000-000000000027', 7.00, 5],  // Frozen Veg
            [self::SUP_FROZEN, 'aa000001-0000-4000-a000-000000000028', 10.00, 5], // Ice Cream
            [self::SUP_FROZEN, 'aa000001-0000-4000-a000-000000000029', 5.50, 5],  // Fries
        ];

        $rows = [];
        foreach ($links as [$supplierId, $productId, $cost, $lead]) {
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'product_id' => $productId,
                'supplier_id' => $supplierId,
                'cost_price' => $cost,
                'lead_time_days' => $lead,
                'created_at' => now(),
            ];
        }

        // Remove existing links for these products first
        $productIds = array_column($rows, 'product_id');
        DB::table('product_suppliers')->whereIn('product_id', $productIds)->delete();
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('product_suppliers')->insert($chunk);
        }
    }

    // ─────────────────────────────────────────────────────────
    // CUSTOMERS
    // ─────────────────────────────────────────────────────────
    private function seedCustomers(): void
    {
        $this->allCustomerIds = [self::CUST_KHALID, self::CUST_NORA, self::CUST_FATIMA];

        $customers = [
            // VIP customers (high spend)
            ['name' => 'Abdullah Al-Otaibi', 'phone' => '+966550100001', 'group' => self::GRP_VIP],
            ['name' => 'Hanan Al-Dosari', 'phone' => '+966550100002', 'group' => self::GRP_VIP],
            ['name' => 'Turki Al-Shamari', 'phone' => '+966550100003', 'group' => self::GRP_VIP],
            ['name' => 'Reem Al-Harbi', 'phone' => '+966550100004', 'group' => self::GRP_VIP],
            ['name' => 'Faisal Al-Qahtani', 'phone' => '+966550100005', 'group' => self::GRP_VIP],

            // Regular customers
            ['name' => 'Saleh Al-Ghamdi', 'phone' => '+966550200001', 'group' => self::GRP_REGULAR],
            ['name' => 'Amal Bin Saeed', 'phone' => '+966550200002', 'group' => self::GRP_REGULAR],
            ['name' => 'Yousef Al-Zahrani', 'phone' => '+966550200003', 'group' => self::GRP_REGULAR],
            ['name' => 'Maha Al-Mutairi', 'phone' => '+966550200004', 'group' => self::GRP_REGULAR],
            ['name' => 'Nawaf Al-Shehri', 'phone' => '+966550200005', 'group' => self::GRP_REGULAR],
            ['name' => 'Sara Al-Enazi', 'phone' => '+966550200006', 'group' => self::GRP_REGULAR],
            ['name' => 'Bader Al-Tamimi', 'phone' => '+966550200007', 'group' => self::GRP_REGULAR],
            ['name' => 'Noura Al-Subaie', 'phone' => '+966550200008', 'group' => self::GRP_REGULAR],
            ['name' => 'Hamad Al-Dossary', 'phone' => '+966550200009', 'group' => self::GRP_REGULAR],
            ['name' => 'Lina Al-Rasheed', 'phone' => '+966550200010', 'group' => self::GRP_REGULAR],

            // Occasional / Low frequency
            ['name' => 'Majed Al-Anazi', 'phone' => '+966550300001', 'group' => self::GRP_REGULAR],
            ['name' => 'Dalal Al-Khaldi', 'phone' => '+966550300002', 'group' => self::GRP_REGULAR],
            ['name' => 'Sultan Al-Harthy', 'phone' => '+966550300003', 'group' => self::GRP_REGULAR],
            ['name' => 'Asma Al-Johani', 'phone' => '+966550300004', 'group' => self::GRP_REGULAR],
            ['name' => 'Rakan Al-Mutlaq', 'phone' => '+966550300005', 'group' => null],
            ['name' => 'Haifa Al-Dawsari', 'phone' => '+966550300006', 'group' => null],
            ['name' => 'Mishaal Al-Otaibi', 'phone' => '+966550300007', 'group' => null],
            ['name' => 'Ghada Al-Yami', 'phone' => '+966550300008', 'group' => null],

            // New customers (very recent)
            ['name' => 'Fahad Al-Ruwaili', 'phone' => '+966550400001', 'group' => null],
            ['name' => 'Mona Al-Shahrani', 'phone' => '+966550400002', 'group' => null],
            ['name' => 'Abdulrahman Al-Harbi', 'phone' => '+966550400003', 'group' => null],
            ['name' => 'Lamia Al-Qahtani', 'phone' => '+966550400004', 'group' => null],
            ['name' => 'Nasser Al-Balawi', 'phone' => '+966550400005', 'group' => null],

            // Wholesale
            ['name' => 'Al-Baraka Trading Est.', 'phone' => '+966550500001', 'group' => self::GRP_WHOLESALE],
            ['name' => 'Riyadh Mini Market', 'phone' => '+966550500002', 'group' => self::GRP_WHOLESALE],
        ];

        $rows = [];
        foreach ($customers as $i => $c) {
            $id = sprintf('c0000001-0000-4000-a000-0000000000%02d', $i + 1);
            $this->allCustomerIds[] = $id;
            $this->newCustomerIds[] = $id;

            $rows[] = [
                'id' => $id,
                'organization_id' => self::ORG,
                'name' => $c['name'],
                'phone' => $c['phone'],
                'loyalty_code' => 'SEED-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'loyalty_points' => 0,
                'total_spend' => 0,
                'visit_count' => 0,
                'group_id' => $c['group'],
                'created_at' => now()->subDays(rand(5, 180)),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('customers')->insert($chunk);
        }
    }

    // ─────────────────────────────────────────────────────────
    // STOCK LEVELS
    // ─────────────────────────────────────────────────────────
    private function seedStockLevels(): void
    {
        // Remove existing stock levels for our stores
        DB::table('stock_levels')->whereIn('store_id', [self::STORE1, self::STORE2])->delete();

        $rows = [];
        foreach ($this->allProducts as $p) {
            foreach ([self::STORE1, self::STORE2] as $storeId) {
                $isDead = in_array($p['id'], $this->deadStockIds);
                $isPopular = in_array($p['id'], $this->popularProductIds);

                // Dead stock: high quantity, low reorder (it sits there)
                // Popular: moderate quantity, higher reorder
                // Others: varied
                if ($isDead) {
                    $qty = rand(40, 80);
                    $reorder = 5;
                } elseif ($isPopular) {
                    // Some popular items below reorder point (for smart reorder)
                    $qty = rand(1, 100) <= 30 ? rand(2, 8) : rand(20, 60);
                    $reorder = 15;
                } else {
                    $qty = rand(5, 50);
                    $reorder = 10;
                }

                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'store_id' => $storeId,
                    'product_id' => $p['id'],
                    'quantity' => $qty,
                    'reserved_quantity' => 0,
                    'reorder_point' => $reorder,
                    'max_stock_level' => $reorder * 5,
                    'average_cost' => $p['cost'],
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('stock_levels')->insert($chunk);
        }
    }

    // ─────────────────────────────────────────────────────────
    // STOCK BATCHES (expiry data)
    // ─────────────────────────────────────────────────────────
    private function seedStockBatches(): void
    {
        // Delete existing seed batches
        DB::table('stock_batches')->whereIn('store_id', [self::STORE1, self::STORE2])
            ->where('batch_number', 'like', 'SEED-%')->delete();

        // Perishable products that need batches
        $perishables = [
            // Dairy
            'a65d5f7b-23dc-4145-9e92-b20991ccc8bc', // Milk
            'c5250ab5-aaa4-445e-9942-d4fef2573de1', // Eggs
            'aa000001-0000-4000-a000-000000000005',  // Yogurt
            'aa000001-0000-4000-a000-000000000006',  // Cheese
            'aa000001-0000-4000-a000-000000000007',  // Butter
            // Bakery
            '4c4ee1d3-4240-4731-8755-05c57a6a898b',  // Bread
            '2a68447d-f616-4da6-9233-ec9548050bd2',  // Croissant
            'aa000001-0000-4000-a000-000000000013',   // Cake
            'aa000001-0000-4000-a000-000000000014',   // Samosa
            // Meat
            'aa000001-0000-4000-a000-000000000035',   // Chicken
            'aa000001-0000-4000-a000-000000000036',   // Beef
            'aa000001-0000-4000-a000-000000000037',   // Lamb
            // Fruits
            'd3a139cc-3392-4b14-94cf-ce51cd7c5e00',  // Banana
            '705cbfe0-7620-4526-a547-3301102c79d8',   // Apple
            'aa000001-0000-4000-a000-000000000002',   // Tomato
            'aa000001-0000-4000-a000-000000000003',   // Cucumber
            // Frozen
            'aa000001-0000-4000-a000-000000000026',   // Frozen Chicken
            'aa000001-0000-4000-a000-000000000028',   // Ice Cream
        ];

        // Expiry offsets in days from now (negative = expired)
        $expiryProfiles = [
            [-5, -2, 2, 7, 30],     // very mixed including expired
            [1, 3, 14, 45],          // mostly upcoming
            [3, 7, 21, 60],          // moderate
            [7, 14, 30, 90],         // mostly safe
            [-3, 5, 10, 20],         // some expired, mostly ok
        ];

        $rows = [];
        $batchNum = 0;
        foreach ($perishables as $productId) {
            $product = $this->getProductById($productId);
            if (!$product) continue;

            $profile = $expiryProfiles[array_rand($expiryProfiles)];

            foreach ([self::STORE1, self::STORE2] as $storeId) {
                foreach ($profile as $daysOffset) {
                    $batchNum++;
                    $rows[] = [
                        'id' => Str::uuid()->toString(),
                        'store_id' => $storeId,
                        'product_id' => $productId,
                        'batch_number' => 'SEED-B' . str_pad($batchNum, 4, '0', STR_PAD_LEFT),
                        'expiry_date' => now()->addDays($daysOffset)->toDateString(),
                        'quantity' => rand(3, 20),
                        'unit_cost' => $product['cost'],
                        'created_at' => now()->subDays(max(0, 30 - $daysOffset)),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('stock_batches')->insert($chunk);
        }
        echo "  → " . count($rows) . " stock batches\n";
    }

    // ─────────────────────────────────────────────────────────
    // TRANSACTIONS (90 days of data)
    // ─────────────────────────────────────────────────────────
    private function seedTransactions(): void
    {
        $stores = [
            [
                'id' => self::STORE1,
                'cashiers' => [
                    ['id' => self::AHMED, 'reg' => self::REG1_S1],
                    ['id' => self::OWNER, 'reg' => self::REG2_S1],
                ],
            ],
            [
                'id' => self::STORE2,
                'cashiers' => [
                    ['id' => self::KAMAL, 'reg' => self::REG1_S2],
                    ['id' => self::BRANCH_MGR, 'reg' => self::REG2_S2],
                ],
            ],
        ];

        $sessionRows = [];
        $txnRows = [];
        $itemRows = [];
        $paymentRows = [];
        $now = Carbon::now();

        // Peak hours (weighted)
        $hours = [8,9,9,10,10,10,11,11,11,12,12,12,12,13,13,13,14,14,15,15,16,16,16,17,17,17,18,18,18,19,19,20,20,21];

        for ($dayOffset = 0; $dayOffset < 90; $dayOffset++) {
            $date = $now->copy()->subDays($dayOffset)->startOfDay();

            // More transactions for recent days
            $baseTxnCount = match (true) {
                $dayOffset < 7  => rand(8, 14),
                $dayOffset < 30 => rand(5, 10),
                $dayOffset < 60 => rand(3, 7),
                default         => rand(2, 5),
            };

            foreach ($stores as $store) {
                $storeId = $store['id'];
                $txnCount = $baseTxnCount + rand(-1, 2); // Vary per store
                if ($txnCount < 1) $txnCount = 1;

                // Create 1-2 POS sessions for this day
                $useBothCashiers = rand(1, 100) <= 70;
                $cashiersToday = $useBothCashiers
                    ? $store['cashiers']
                    : [$store['cashiers'][0]];

                $sessions = [];
                foreach ($cashiersToday as $cashier) {
                    $sessionId = Str::uuid()->toString();
                    $openedAt = $date->copy()->setHour(7)->setMinute(30);
                    $closedAt = $date->copy()->setHour(22)->setMinute(0);

                    $sessions[] = ['id' => $sessionId, 'cashier_id' => $cashier['id'], 'register_id' => $cashier['reg']];

                    $sessionRows[] = [
                        'id' => $sessionId,
                        'store_id' => $storeId,
                        'register_id' => $cashier['reg'],
                        'cashier_id' => $cashier['id'],
                        'status' => $dayOffset === 0 ? 'open' : 'closed',
                        'opening_cash' => 500.00,
                        'closing_cash' => $dayOffset === 0 ? null : rand(800, 2500),
                        'expected_cash' => $dayOffset === 0 ? null : rand(800, 2500),
                        'cash_difference' => $dayOffset === 0 ? null : rand(-20, 20),
                        'transaction_count' => 0, // Will be approximate
                        'opened_at' => $openedAt,
                        'closed_at' => $dayOffset === 0 ? null : $closedAt,
                        'z_report_printed' => $dayOffset !== 0,
                        'created_at' => $openedAt,
                        'updated_at' => $closedAt,
                    ];
                }

                for ($t = 0; $t < $txnCount; $t++) {
                    $this->txnCounter++;
                    $session = $sessions[array_rand($sessions)];
                    $hour = $hours[array_rand($hours)];
                    $txnDate = $date->copy()->setHour($hour)->setMinute(rand(0, 59))->setSecond(rand(0, 59));

                    // 5% returns, 3% voids
                    $roll = rand(1, 100);
                    $isReturn = $roll <= 5;
                    $isVoid = !$isReturn && $roll <= 8;
                    $type = $isReturn ? 'return' : 'sale';
                    $status = $isVoid ? 'voided' : 'completed';

                    // Pick products (allow dead stock only for very old transactions)
                    $itemCount = rand(2, 6);
                    $selectedProducts = $this->pickRandomProducts($itemCount, $dayOffset > 60);

                    if (empty($selectedProducts)) continue;

                    $txnId = Str::uuid()->toString();
                    $subtotal = 0;
                    $totalTax = 0;
                    $totalDiscount = 0;

                    foreach ($selectedProducts as $product) {
                        $qty = rand(1, 4);
                        $unitPrice = $product['sell'];
                        $rawTotal = round($qty * $unitPrice, 2);

                        // 8% chance of item discount
                        $discount = 0;
                        if (rand(1, 100) <= 8) {
                            $discount = round($rawTotal * rand(5, 25) / 100, 2);
                        }

                        $afterDiscount = $rawTotal - $discount;
                        $taxAmount = round($afterDiscount * 15 / 115, 2);

                        $itemRows[] = [
                            'id' => Str::uuid()->toString(),
                            'transaction_id' => $txnId,
                            'product_id' => $product['id'],
                            'product_name' => $product['name'],
                            'product_name_ar' => $product['name_ar'],
                            'quantity' => $isReturn ? -$qty : $qty,
                            'unit_price' => $unitPrice,
                            'cost_price' => $product['cost'],
                            'discount_amount' => $discount,
                            'discount_type' => $discount > 0 ? 'percentage' : null,
                            'discount_value' => $discount > 0 ? rand(5, 25) : null,
                            'tax_rate' => 15.00,
                            'tax_amount' => $taxAmount,
                            'line_total' => $afterDiscount,
                            'is_return_item' => $isReturn,
                            'created_at' => $txnDate,
                        ];

                        $subtotal += ($afterDiscount - $taxAmount);
                        $totalTax += $taxAmount;
                        $totalDiscount += $discount;
                    }

                    $totalAmount = round($subtotal + $totalTax, 2);
                    if ($isReturn) $totalAmount = -abs($totalAmount);

                    // 55% of transactions have a customer
                    $customerId = null;
                    if (rand(1, 100) <= 55 && !empty($this->allCustomerIds)) {
                        $customerId = $this->allCustomerIds[array_rand($this->allCustomerIds)];
                    }

                    $txnRows[] = [
                        'id' => $txnId,
                        'organization_id' => self::ORG,
                        'store_id' => $storeId,
                        'register_id' => $session['register_id'],
                        'pos_session_id' => $session['id'],
                        'cashier_id' => $session['cashier_id'],
                        'customer_id' => $customerId,
                        'transaction_number' => 'SEED-' . $this->txnCounter,
                        'type' => $type,
                        'status' => $status,
                        'subtotal' => round(abs($subtotal), 2),
                        'discount_amount' => round($totalDiscount, 2),
                        'tax_amount' => round(abs($totalTax), 2),
                        'total_amount' => round(abs($totalAmount), 2),
                        'created_at' => $txnDate,
                        'updated_at' => $txnDate,
                    ];

                    // Payment
                    $method = rand(1, 100) <= 55 ? 'cash' : 'card';
                    $amt = round(abs($totalAmount), 2);
                    $paymentRows[] = [
                        'id' => Str::uuid()->toString(),
                        'transaction_id' => $txnId,
                        'method' => $method,
                        'amount' => $amt,
                        'cash_tendered' => $method === 'cash' ? (ceil($amt / 5) * 5) : null,
                        'change_given' => $method === 'cash' ? round((ceil($amt / 5) * 5) - $amt, 2) : null,
                        'tip_amount' => 0,
                        'created_at' => $txnDate,
                    ];
                }
            }
        }

        // Batch insert
        echo "  → " . count($sessionRows) . " POS sessions\n";
        foreach (array_chunk($sessionRows, 200) as $chunk) {
            DB::table('pos_sessions')->insert($chunk);
        }

        echo "  → " . count($txnRows) . " transactions\n";
        foreach (array_chunk($txnRows, 200) as $chunk) {
            DB::table('transactions')->insert($chunk);
        }

        echo "  → " . count($itemRows) . " transaction items\n";
        foreach (array_chunk($itemRows, 500) as $chunk) {
            DB::table('transaction_items')->insert($chunk);
        }

        echo "  → " . count($paymentRows) . " payments\n";
        foreach (array_chunk($paymentRows, 200) as $chunk) {
            DB::table('payments')->insert($chunk);
        }
    }

    // ─────────────────────────────────────────────────────────
    // PURCHASE ORDERS
    // ─────────────────────────────────────────────────────────
    private function seedPurchaseOrders(): void
    {
        $suppliers = [
            self::SUP_ALMARAI => ['a65d5f7b-23dc-4145-9e92-b20991ccc8bc', 'c5250ab5-aaa4-445e-9942-d4fef2573de1', 'aa000001-0000-4000-a000-000000000005'],
            self::SUP_FRESH   => ['d3a139cc-3392-4b14-94cf-ce51cd7c5e00', 'aa000001-0000-4000-a000-000000000002', 'aa000001-0000-4000-a000-000000000035'],
            self::SUP_GULF    => ['f3a47329-fe27-4d08-976e-f9c2df2652b4', 'cde2dfad-0faa-4ad5-a9a7-a5995b7fc73e', 'aa000001-0000-4000-a000-000000000030'],
            self::SUP_CLEAN   => ['aa000001-0000-4000-a000-000000000022', 'aa000001-0000-4000-a000-000000000038', 'aa000001-0000-4000-a000-000000000019'],
            self::SUP_FROZEN  => ['aa000001-0000-4000-a000-000000000026', 'aa000001-0000-4000-a000-000000000028', 'aa000001-0000-4000-a000-000000000029'],
        ];

        $statuses = ['draft', 'sent', 'partially_received', 'received', 'received', 'received'];
        $poRows = [];
        $poItemRows = [];
        $poNum = 0;

        foreach ([self::STORE1, self::STORE2] as $storeId) {
            foreach ($suppliers as $supplierId => $productIds) {
                // 2-3 POs per supplier over 90 days
                $poCount = rand(2, 3);
                for ($i = 0; $i < $poCount; $i++) {
                    $poNum++;
                    $poId = Str::uuid()->toString();
                    $daysAgo = rand(5, 85);
                    $createdAt = now()->subDays($daysAgo);
                    $status = $statuses[array_rand($statuses)];

                    $totalCost = 0;
                    $items = [];
                    $selectedProducts = array_rand(array_flip($productIds), min(rand(2, 3), count($productIds)));
                    if (!is_array($selectedProducts)) $selectedProducts = [$selectedProducts];

                    foreach ($selectedProducts as $productId) {
                        $product = $this->getProductById($productId);
                        if (!$product) continue;
                        $qty = rand(20, 100);
                        $cost = $product['cost'];
                        $received = $status === 'received' ? $qty : ($status === 'partially_received' ? intval($qty * 0.6) : 0);

                        $items[] = [
                            'id' => Str::uuid()->toString(),
                            'purchase_order_id' => $poId,
                            'product_id' => $productId,
                            'quantity_ordered' => $qty,
                            'unit_cost' => $cost,
                            'quantity_received' => $received,
                        ];
                        $totalCost += $qty * $cost;
                    }

                    $poRows[] = [
                        'id' => $poId,
                        'organization_id' => self::ORG,
                        'store_id' => $storeId,
                        'supplier_id' => $supplierId,
                        'reference_number' => 'PO-SEED-' . str_pad($poNum, 4, '0', STR_PAD_LEFT),
                        'status' => $status,
                        'expected_date' => $createdAt->copy()->addDays(rand(3, 14))->toDateString(),
                        'total_cost' => round($totalCost, 2),
                        'created_by' => self::OWNER,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                    $poItemRows = array_merge($poItemRows, $items);
                }
            }
        }

        foreach (array_chunk($poRows, 50) as $chunk) {
            DB::table('purchase_orders')->insert($chunk);
        }
        foreach (array_chunk($poItemRows, 100) as $chunk) {
            DB::table('purchase_order_items')->insert($chunk);
        }
        echo "  → " . count($poRows) . " purchase orders, " . count($poItemRows) . " items\n";
    }

    // ─────────────────────────────────────────────────────────
    // GOODS RECEIPTS
    // ─────────────────────────────────────────────────────────
    private function seedGoodsReceipts(): void
    {
        // Get received/partially_received POs
        $pos = DB::table('purchase_orders')
            ->where('organization_id', self::ORG)
            ->whereIn('status', ['received', 'partially_received'])
            ->where('reference_number', 'like', 'PO-SEED-%')
            ->get();

        $grRows = [];
        $grItemRows = [];
        $grNum = 0;

        foreach ($pos as $po) {
            $grNum++;
            $grId = Str::uuid()->toString();
            $receivedAt = Carbon::parse($po->created_at)->addDays(rand(2, 7));

            $poItems = DB::table('purchase_order_items')
                ->where('purchase_order_id', $po->id)
                ->get();

            $totalCost = 0;
            foreach ($poItems as $item) {
                $qty = $item->quantity_received > 0 ? $item->quantity_received : $item->quantity_ordered;
                $grItemRows[] = [
                    'id' => Str::uuid()->toString(),
                    'goods_receipt_id' => $grId,
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'unit_cost' => $item->unit_cost,
                    'batch_number' => 'GR-SEED-' . str_pad($grNum, 3, '0', STR_PAD_LEFT),
                    'expiry_date' => now()->addDays(rand(14, 180))->toDateString(),
                ];
                $totalCost += $qty * $item->unit_cost;
            }

            $grRows[] = [
                'id' => $grId,
                'store_id' => $po->store_id,
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'reference_number' => 'GR-SEED-' . str_pad($grNum, 4, '0', STR_PAD_LEFT),
                'status' => 'confirmed',
                'total_cost' => round($totalCost, 2),
                'received_by' => self::OWNER,
                'received_at' => $receivedAt,
                'confirmed_at' => $receivedAt->copy()->addHours(rand(1, 4)),
            ];
        }

        foreach (array_chunk($grRows, 50) as $chunk) {
            DB::table('goods_receipts')->insert($chunk);
        }
        foreach (array_chunk($grItemRows, 100) as $chunk) {
            DB::table('goods_receipt_items')->insert($chunk);
        }
        echo "  → " . count($grRows) . " goods receipts, " . count($grItemRows) . " items\n";
    }

    // ─────────────────────────────────────────────────────────
    // STOCK ADJUSTMENTS (shrinkage, damage, count)
    // ─────────────────────────────────────────────────────────
    private function seedStockAdjustments(): void
    {
        $reasons = [
            ['type' => 'decrease', 'reason_code' => 'shrinkage'],
            ['type' => 'decrease', 'reason_code' => 'damage'],
            ['type' => 'decrease', 'reason_code' => 'expired'],
            ['type' => 'increase', 'reason_code' => 'found'],
            ['type' => 'decrease', 'reason_code' => 'shrinkage'],
            ['type' => 'decrease', 'reason_code' => 'shrinkage'],
        ];

        $saRows = [];
        $saItemRows = [];

        foreach ([self::STORE1, self::STORE2] as $storeId) {
            // 8-12 adjustments per store over past 60 days
            $count = rand(8, 12);
            for ($i = 0; $i < $count; $i++) {
                $reason = $reasons[array_rand($reasons)];
                $saId = Str::uuid()->toString();
                $createdAt = now()->subDays(rand(1, 60));
                $cashier = $storeId === self::STORE1 ? self::AHMED : self::KAMAL;

                $saRows[] = [
                    'id' => $saId,
                    'store_id' => $storeId,
                    'type' => $reason['type'],
                    'reason_code' => $reason['reason_code'],
                    'notes' => match ($reason['reason_code']) {
                        'shrinkage' => 'Inventory count discrepancy',
                        'damage' => 'Product damaged during handling',
                        'expired' => 'Removed expired stock',
                        'found' => 'Found additional stock during count',
                    },
                    'adjusted_by' => $cashier,
                    'created_at' => $createdAt,
                ];

                // 1-3 items per adjustment
                $itemCount = rand(1, 3);
                $products = $this->pickRandomProducts($itemCount);
                foreach ($products as $product) {
                    $qty = $reason['type'] === 'decrease' ? -rand(1, 5) : rand(1, 3);
                    $saItemRows[] = [
                        'id' => Str::uuid()->toString(),
                        'stock_adjustment_id' => $saId,
                        'product_id' => $product['id'],
                        'quantity' => $qty,
                        'unit_cost' => $product['cost'],
                    ];
                }
            }
        }

        foreach (array_chunk($saRows, 50) as $chunk) {
            DB::table('stock_adjustments')->insert($chunk);
        }
        foreach (array_chunk($saItemRows, 100) as $chunk) {
            DB::table('stock_adjustment_items')->insert($chunk);
        }
        echo "  → " . count($saRows) . " adjustments, " . count($saItemRows) . " items\n";
    }

    // ─────────────────────────────────────────────────────────
    // STAFF USERS (bridge table for attendance_records FK)
    // ─────────────────────────────────────────────────────────
    private function seedStaffUsers(): void
    {
        $staff = [
            ['user_id' => self::AHMED, 'store_id' => self::STORE1, 'first_name' => 'Ahmed', 'last_name' => 'Cashier'],
            ['user_id' => self::OWNER, 'store_id' => self::STORE1, 'first_name' => 'Mohammed', 'last_name' => 'Al-Ostora'],
            ['user_id' => self::KAMAL, 'store_id' => self::STORE2, 'first_name' => 'Kamal', 'last_name' => 'Hamid'],
            ['user_id' => self::BRANCH_MGR, 'store_id' => self::STORE2, 'first_name' => 'Branch', 'last_name' => 'Manager'],
        ];

        foreach ($staff as $s) {
            // Check if staff_user already exists for this user
            $existing = DB::table('staff_users')->where('user_id', $s['user_id'])->value('id');
            if ($existing) {
                $this->staffUserMap[$s['user_id']] = $existing;
            } else {
                $id = Str::uuid()->toString();
                DB::table('staff_users')->insert(array_merge($s, [
                    'id' => $id,
                    'pin_hash' => bcrypt('1234'),
                    'status' => 'active',
                    'employment_type' => 'full_time',
                    'hire_date' => now()->subMonths(6)->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $this->staffUserMap[$s['user_id']] = $id;
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // ATTENDANCE RECORDS
    // ─────────────────────────────────────────────────────────
    private function seedAttendanceRecords(): void
    {
        $staff = [
            ['id' => $this->staffUserMap[self::AHMED] ?? null, 'store' => self::STORE1],
            ['id' => $this->staffUserMap[self::OWNER] ?? null, 'store' => self::STORE1],
            ['id' => $this->staffUserMap[self::KAMAL] ?? null, 'store' => self::STORE2],
            ['id' => $this->staffUserMap[self::BRANCH_MGR] ?? null, 'store' => self::STORE2],
        ];

        // Filter out any staff without a mapping
        $staff = array_filter($staff, fn($s) => $s['id'] !== null);

        $rows = [];
        for ($dayOffset = 0; $dayOffset < 45; $dayOffset++) {
            $date = now()->subDays($dayOffset)->startOfDay();

            foreach ($staff as $s) {
                // 85% attendance rate
                if (rand(1, 100) > 85) continue;

                $clockIn = $date->copy()->setHour(rand(7, 9))->setMinute(rand(0, 30));
                $shiftHours = rand(7, 10);
                $clockOut = $dayOffset === 0 ? null : $clockIn->copy()->addHours($shiftHours)->addMinutes(rand(-15, 30));

                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'staff_user_id' => $s['id'],
                    'store_id' => $s['store'],
                    'clock_in_at' => $clockIn,
                    'clock_out_at' => $clockOut,
                    'break_minutes' => rand(30, 60),
                    'overtime_minutes' => $shiftHours > 8 ? ($shiftHours - 8) * 60 : 0,
                    'auth_method' => 'pin',
                    'created_at' => $clockIn,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('attendance_records')->insert($chunk);
        }
        echo "  → " . count($rows) . " attendance records\n";
    }

    // ─────────────────────────────────────────────────────────
    // LOYALTY TRANSACTIONS
    // ─────────────────────────────────────────────────────────
    private function seedLoyaltyTransactions(): void
    {
        $rows = [];
        // Give loyalty points to VIP and regular customers based on their transactions
        $activeCustomers = array_slice($this->allCustomerIds, 0, 20);

        foreach ($activeCustomers as $custId) {
            // 3-8 loyalty events per customer
            $eventCount = rand(3, 8);
            $balance = 0;

            for ($i = 0; $i < $eventCount; $i++) {
                $isEarn = rand(1, 100) <= 80;
                $points = $isEarn ? rand(10, 100) : -rand(5, 30);
                $balance = max(0, $balance + $points);

                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'customer_id' => $custId,
                    'type' => $isEarn ? 'earn' : 'redeem',
                    'points' => $points,
                    'balance_after' => $balance,
                    'notes' => $isEarn ? 'Points earned from purchase' : 'Points redeemed',
                    'performed_by' => self::AHMED,
                    'created_at' => now()->subDays(rand(1, 60)),
                ];
            }

            // Update customer loyalty points
            DB::table('customers')
                ->where('id', $custId)
                ->update(['loyalty_points' => $balance]);
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('loyalty_transactions')->insert($chunk);
        }
        echo "  → " . count($rows) . " loyalty transactions\n";
    }

    // ─────────────────────────────────────────────────────────
    // UPDATE CUSTOMER STATISTICS
    // ─────────────────────────────────────────────────────────
    private function updateCustomerStats(): void
    {
        DB::statement("
            UPDATE customers
            SET
                total_spend = COALESCE(sub.total, 0),
                visit_count = COALESCE(sub.visits, 0),
                last_visit_at = sub.last_visit
            FROM (
                SELECT
                    customer_id,
                    SUM(total_amount) as total,
                    COUNT(*) as visits,
                    MAX(created_at) as last_visit
                FROM transactions
                WHERE customer_id IS NOT NULL
                  AND status = 'completed'
                  AND type = 'sale'
                  AND organization_id = '" . self::ORG . "'
                GROUP BY customer_id
            ) sub
            WHERE customers.id = sub.customer_id
              AND customers.organization_id = '" . self::ORG . "'
        ");
    }
}
