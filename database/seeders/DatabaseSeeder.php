<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\HeadOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\ChartOfAccounts;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\MeasurementUnit;
use App\Models\ProductCategory;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BarcodeSequence;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();
        $userId = 1;
        // 🔑 Create Super Admin User
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com', // optional, keep if you want for notifications
                'password' => Hash::make('12345678'),
            ]
        );

        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->assignRole($superAdmin);

        // 📌 Functional Modules (CRUD-style permissions)
        $modules = [
            // User Management
            'user_roles',
            'users',

            // Accounts
            'coa',
            'shoa',

            // Products
            'products',
            'product_categories',
            'attributes',

            // Stock Management
            'locations',
            'stock_transfer',

            // Purchases
            'purchase_invoices',
            'purchase_return',

            // Sales
            'sale_invoices',
            'sale_return',

            // Vouchers
            'vouchers',

            // Production
            'production',
            'production_receiving',
            'production_return',

            // POS
            'pos_system',
        ];

        $actions = ['index', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "$module.$action",
                ]);
            }
        }

        // 📊 Report permissions (only view access, no CRUD)
        $reports = ['inventory', 'purchase', 'production', 'sales', 'accounts'];

        foreach ($reports as $report) {
            Permission::firstOrCreate([
                'name' => "reports.$report",
            ]);
        }

        // Assign all permissions to Superadmin
        $superAdmin->syncPermissions(Permission::all());

        // ---------------------
        // HEADS OF ACCOUNTS
        // ---------------------
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses',    'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // SUB HEADS
        // ---------------------
        SubHeadOfAccounts::insert([
            // Assets
            ['id' => 1,  'hoa_id' => 1, 'name' => 'Cash & Cash Equivalents', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2,  'hoa_id' => 1, 'name' => 'Bank Accounts',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 3,  'hoa_id' => 1, 'name' => 'Accounts Receivable',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 4,  'hoa_id' => 1, 'name' => 'Inventory',               'created_at' => $now, 'updated_at' => $now],
            ['id' => 5,  'hoa_id' => 1, 'name' => 'Other Current Assets',    'created_at' => $now, 'updated_at' => $now],

            // Liabilities
            ['id' => 6,  'hoa_id' => 2, 'name' => 'Accounts Payable',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 7,  'hoa_id' => 2, 'name' => 'Loans & Borrowings',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 8,  'hoa_id' => 2, 'name' => 'Other Liabilities',       'created_at' => $now, 'updated_at' => $now],

            // Equity
            ['id' => 9,  'hoa_id' => 3, 'name' => 'Owner Capital',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'hoa_id' => 3, 'name' => 'Retained Earnings',       'created_at' => $now, 'updated_at' => $now],

            // Revenue
            ['id' => 11, 'hoa_id' => 4, 'name' => 'Sales Revenue',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'hoa_id' => 4, 'name' => 'Other Income',            'created_at' => $now, 'updated_at' => $now],

            // Expenses
            ['id' => 13, 'hoa_id' => 5, 'name' => 'Cost of Goods Sold',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'hoa_id' => 5, 'name' => 'Operating Expenses',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 15, 'hoa_id' => 5, 'name' => 'Salaries & Wages',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 16, 'hoa_id' => 5, 'name' => 'Production Expenses',     'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // CHART OF ACCOUNTS
        // ---------------------
        $coaData = [

            // ── ASSETS ──────────────────────────────────────────────────────
            // Cash
            ['account_code' => '101001', 'shoa_id' => 1,  'name' => 'Shop Cash',              'account_type' => 'cash',     'receivables' => 0, 'payables' => 0],
            ['account_code' => '101002', 'shoa_id' => 1,  'name' => 'Petty Cash',             'account_type' => 'cash',     'receivables' => 0, 'payables' => 0],

            // Bank
            ['account_code' => '102001', 'shoa_id' => 2,  'name' => 'Meezan Bank',            'account_type' => 'bank',     'receivables' => 0, 'payables' => 0],
            ['account_code' => '102002', 'shoa_id' => 2,  'name' => 'HBL Account',            'account_type' => 'bank',     'receivables' => 0, 'payables' => 0],

            // Accounts Receivable (customers added here dynamically)
            ['account_code' => '103001', 'shoa_id' => 3,  'name' => 'Customer 01',            'account_type' => 'customer', 'receivables' => 0, 'payables' => 0],

            // Inventory
            ['account_code' => '104001', 'shoa_id' => 4,  'name' => 'Stock in Hand',          'account_type' => 'asset',    'receivables' => 0, 'payables' => 0],
            ['account_code' => '104002', 'shoa_id' => 4,  'name' => 'Raw Material Stock',     'account_type' => 'asset',    'receivables' => 0, 'payables' => 0],
            ['account_code' => '104003', 'shoa_id' => 4,  'name' => 'Work In Progress',       'account_type' => 'asset',    'receivables' => 0, 'payables' => 0],
            ['account_code' => '104004', 'shoa_id' => 4,  'name' => 'Finished Goods Stock',   'account_type' => 'asset',    'receivables' => 0, 'payables' => 0],

            // Other Current Assets
            ['account_code' => '105001', 'shoa_id' => 5,  'name' => 'Advance to Suppliers',   'account_type' => 'asset',    'receivables' => 0, 'payables' => 0],
            ['account_code' => '105002', 'shoa_id' => 5,  'name' => 'Prepaid Expenses',       'account_type' => 'asset',    'receivables' => 0, 'payables' => 0],
            ['account_code' => '105003', 'shoa_id' => 5,  'name' => 'Security Deposits',      'account_type' => 'asset',    'receivables' => 0, 'payables' => 0],

            // ── LIABILITIES ─────────────────────────────────────────────────
            // Accounts Payable (vendors added here dynamically)
            ['account_code' => '205001', 'shoa_id' => 6,  'name' => 'Vendor 01',              'account_type' => 'vendor',   'receivables' => 0, 'payables' => 0],

            // Loans
            ['account_code' => '206001', 'shoa_id' => 7,  'name' => 'Bank Loan',              'account_type' => 'liability','receivables' => 0, 'payables' => 0],

            // Other Liabilities
            ['account_code' => '207001', 'shoa_id' => 8,  'name' => 'Salaries Payable',       'account_type' => 'liability','receivables' => 0, 'payables' => 0],
            ['account_code' => '207002', 'shoa_id' => 8,  'name' => 'Tax Payable',            'account_type' => 'liability','receivables' => 0, 'payables' => 0],
            ['account_code' => '207003', 'shoa_id' => 8,  'name' => 'Advance from Customers', 'account_type' => 'liability','receivables' => 0, 'payables' => 0],

            // ── EQUITY ──────────────────────────────────────────────────────
            ['account_code' => '301001', 'shoa_id' => 9,  'name' => 'Owners Equity',          'account_type' => 'equity',   'receivables' => 0, 'payables' => 0],
            ['account_code' => '302001', 'shoa_id' => 10, 'name' => 'Retained Earnings',      'account_type' => 'equity',   'receivables' => 0, 'payables' => 0],

            // ── REVENUE ─────────────────────────────────────────────────────
            ['account_code' => '401001', 'shoa_id' => 11, 'name' => 'Sales Revenue',          'account_type' => 'revenue',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '401002', 'shoa_id' => 11, 'name' => 'Sales Return',           'account_type' => 'revenue',  'receivables' => 0, 'payables' => 0], // contra
            ['account_code' => '401003', 'shoa_id' => 11, 'name' => 'Sales Discount',         'account_type' => 'revenue',  'receivables' => 0, 'payables' => 0], // contra
            ['account_code' => '402001', 'shoa_id' => 12, 'name' => 'Purchase Discount',      'account_type' => 'revenue',  'receivables' => 0, 'payables' => 0], // discount received from vendor
            ['account_code' => '402002', 'shoa_id' => 12, 'name' => 'Other Income',           'account_type' => 'revenue',  'receivables' => 0, 'payables' => 0],

            // ── EXPENSES ────────────────────────────────────────────────────
            // COGS
            ['account_code' => '501001', 'shoa_id' => 13, 'name' => 'Cost of Goods Sold',     'account_type' => 'cogs',     'receivables' => 0, 'payables' => 0],
            ['account_code' => '501002', 'shoa_id' => 13, 'name' => 'Purchase Return',        'account_type' => 'cogs',     'receivables' => 0, 'payables' => 0], // contra

            // Operating Expenses
            ['account_code' => '502001', 'shoa_id' => 14, 'name' => 'Conveyance Expense',     'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '502002', 'shoa_id' => 14, 'name' => 'Labour Expense',         'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '502003', 'shoa_id' => 14, 'name' => 'Rent Expense',           'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '502004', 'shoa_id' => 14, 'name' => 'Utilities Expense',      'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '502005', 'shoa_id' => 14, 'name' => 'Repair & Maintenance',   'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '502006', 'shoa_id' => 14, 'name' => 'Miscellaneous Expense',  'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],

            // Salaries
            ['account_code' => '503001', 'shoa_id' => 15, 'name' => 'Salaries Expense',       'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],

            // Production Expenses
            ['account_code' => '504001', 'shoa_id' => 16, 'name' => 'Production Labour',      'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '504002', 'shoa_id' => 16, 'name' => 'Production Overhead',    'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
            ['account_code' => '504003', 'shoa_id' => 16, 'name' => 'Raw Material Consumed',  'account_type' => 'expense',  'receivables' => 0, 'payables' => 0],
        ];

        foreach ($coaData as $data) {
            ChartOfAccounts::create(array_merge($data, [
                'opening_date' => now()->toDateString(),
                'credit_limit' => 0.00,
                'remarks'      => null,
                'address'      => null,
                'contact_no'     => null,
                'created_by'   => $userId,
                'updated_by'   => $userId,
            ]));
        }

        Attribute::insert([
            ['id' => 1, 'name' => 'SIZE',           'slug' => 'SIZE',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'COLOR',          'slug' => 'COLOR',          'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Add Engraving?', 'slug' => 'add-engraving',  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Engraving',      'slug' => 'engraving',      'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Waist Size',     'slug' => 'waist-size',     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Shoe Size',      'slug' => 'shoe-size',      'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        AttributeValue::insert([
            // ── SIZE (attribute_id = 1) ───────────────────────────────────────
            ['id' => 1,   'attribute_id' => 1, 'value' => 'XX-SMALL',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2,   'attribute_id' => 1, 'value' => 'X-SMALL',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3,   'attribute_id' => 1, 'value' => 'SMALL',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4,   'attribute_id' => 1, 'value' => 'MEDIUM',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5,   'attribute_id' => 1, 'value' => 'LARGE',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6,   'attribute_id' => 1, 'value' => 'XL',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7,   'attribute_id' => 1, 'value' => 'X-LARGE',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8,   'attribute_id' => 1, 'value' => 'XX-LARGE',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9,   'attribute_id' => 1, 'value' => '3X-LARGE',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10,  'attribute_id' => 1, 'value' => '4X-LARGE',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 24,  'attribute_id' => 1, 'value' => '34',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 25,  'attribute_id' => 1, 'value' => '36',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 27,  'attribute_id' => 1, 'value' => '32',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 28,  'attribute_id' => 1, 'value' => 'Xs',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 29,  'attribute_id' => 1, 'value' => '38',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 30,  'attribute_id' => 1, 'value' => '40',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 31,  'attribute_id' => 1, 'value' => '42',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 32,  'attribute_id' => 1, 'value' => '44',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 33,  'attribute_id' => 1, 'value' => '46',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 35,  'attribute_id' => 1, 'value' => '42-49 mm',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 37,  'attribute_id' => 1, 'value' => '42-49',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 42,  'attribute_id' => 1, 'value' => '2xl',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 43,  'attribute_id' => 1, 'value' => 'S',                     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 44,  'attribute_id' => 1, 'value' => 'M',                     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 45,  'attribute_id' => 1, 'value' => 'L',                     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 46,  'attribute_id' => 1, 'value' => '3xl',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 47,  'attribute_id' => 1, 'value' => '48',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 49,  'attribute_id' => 1, 'value' => 'Custom size',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 53,  'attribute_id' => 1, 'value' => '13 inch',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 57,  'attribute_id' => 1, 'value' => 'Age 3-4',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 58,  'attribute_id' => 1, 'value' => '4xl',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 63,  'attribute_id' => 1, 'value' => 'Xxs',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 72,  'attribute_id' => 1, 'value' => 'Xxl',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 75,  'attribute_id' => 1, 'value' => 'Black diamond custom',  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 111, 'attribute_id' => 1, 'value' => 'Custom',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 119, 'attribute_id' => 1, 'value' => 'Age 4-5',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 120, 'attribute_id' => 1, 'value' => 'Age 5-6',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 121, 'attribute_id' => 1, 'value' => 'Age 6-7',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 122, 'attribute_id' => 1, 'value' => 'Age 7-8',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 123, 'attribute_id' => 1, 'value' => 'Age 8-9',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 127, 'attribute_id' => 1, 'value' => '46056',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 128, 'attribute_id' => 1, 'value' => '46085',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 129, 'attribute_id' => 1, 'value' => '46117',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 130, 'attribute_id' => 1, 'value' => '46148',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 131, 'attribute_id' => 1, 'value' => '46180',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 132, 'attribute_id' => 1, 'value' => '46211',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 133, 'attribute_id' => 1, 'value' => '46243',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 134, 'attribute_id' => 1, 'value' => '46275',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 137, 'attribute_id' => 1, 'value' => '28',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],

            // ── COLOR (attribute_id = 2) ──────────────────────────────────────
            ['id' => 11,  'attribute_id' => 2, 'value' => 'BLACK',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12,  'attribute_id' => 2, 'value' => 'RED',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 13,  'attribute_id' => 2, 'value' => 'BLUE',                  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 14,  'attribute_id' => 2, 'value' => 'BROWN',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 15,  'attribute_id' => 2, 'value' => 'BURGUNDY',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 16,  'attribute_id' => 2, 'value' => 'COGNAC',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 17,  'attribute_id' => 2, 'value' => 'GREEN',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 18,  'attribute_id' => 2, 'value' => 'PINK',                  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 19,  'attribute_id' => 2, 'value' => 'PURPLE',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 20,  'attribute_id' => 2, 'value' => 'RUB',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 21,  'attribute_id' => 2, 'value' => 'TAN',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 22,  'attribute_id' => 2, 'value' => 'WHITE',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 23,  'attribute_id' => 2, 'value' => 'YELLOW',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 36,  'attribute_id' => 2, 'value' => 'Vintage red',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 38,  'attribute_id' => 2, 'value' => 'Black-croco',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 39,  'attribute_id' => 2, 'value' => 'Dark brown',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 40,  'attribute_id' => 2, 'value' => 'Tan brown',             'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 41,  'attribute_id' => 2, 'value' => '3x-large',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 48,  'attribute_id' => 2, 'value' => 'Croco-black',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 54,  'attribute_id' => 2, 'value' => 'Skin/black',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 55,  'attribute_id' => 2, 'value' => 'Orange',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 56,  'attribute_id' => 2, 'value' => 'Vintage blue',          'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 60,  'attribute_id' => 2, 'value' => 'Beige',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 61,  'attribute_id' => 2, 'value' => 'Vintage tan',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 62,  'attribute_id' => 2, 'value' => 'Mocha mousse',          'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 64,  'attribute_id' => 2, 'value' => 'Olive green',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 65,  'attribute_id' => 2, 'value' => 'Cherry wax',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 66,  'attribute_id' => 2, 'value' => 'Forest',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 67,  'attribute_id' => 2, 'value' => 'Black rub',             'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 68,  'attribute_id' => 2, 'value' => 'Camel',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 69,  'attribute_id' => 2, 'value' => 'Black crocodile',       'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 70,  'attribute_id' => 2, 'value' => 'Brown crocodile',       'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 71,  'attribute_id' => 2, 'value' => 'Dark brown-croco',      'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 73,  'attribute_id' => 2, 'value' => 'Cherry red',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 74,  'attribute_id' => 2, 'value' => 'Gray',                  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 76,  'attribute_id' => 2, 'value' => 'Maroon',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 77,  'attribute_id' => 2, 'value' => '4x-large',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 78,  'attribute_id' => 2, 'value' => 'Large',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 79,  'attribute_id' => 2, 'value' => 'Medium',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 80,  'attribute_id' => 2, 'value' => 'Small',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 81,  'attribute_id' => 2, 'value' => 'X-large',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 82,  'attribute_id' => 2, 'value' => 'X-small',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 83,  'attribute_id' => 2, 'value' => 'Xx-large',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 84,  'attribute_id' => 2, 'value' => 'Xx-small',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 85,  'attribute_id' => 2, 'value' => 'Custom size',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 86,  'attribute_id' => 2, 'value' => 'Croco-dark brown',      'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 87,  'attribute_id' => 2, 'value' => 'Distressed brown',      'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 88,  'attribute_id' => 2, 'value' => 'Distressed black',      'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 89,  'attribute_id' => 2, 'value' => 'Cognac wax',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 90,  'attribute_id' => 2, 'value' => 'Burgandy',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 91,  'attribute_id' => 2, 'value' => 'Ash white',             'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 92,  'attribute_id' => 2, 'value' => 'Purpale',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 100, 'attribute_id' => 2, 'value' => 'Oxxblood',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 106, 'attribute_id' => 2, 'value' => 'Tan wax',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 107, 'attribute_id' => 2, 'value' => 'Blue/black',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 108, 'attribute_id' => 2, 'value' => 'Orange/black',          'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 109, 'attribute_id' => 2, 'value' => 'Red/black',             'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 110, 'attribute_id' => 2, 'value' => 'Distressed',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 112, 'attribute_id' => 2, 'value' => 'Gray wax',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 113, 'attribute_id' => 2, 'value' => 'Vintage brown',         'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 114, 'attribute_id' => 2, 'value' => 'Bordo',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 115, 'attribute_id' => 2, 'value' => 'Camel brown',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 116, 'attribute_id' => 2, 'value' => 'Ivory white',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 117, 'attribute_id' => 2, 'value' => 'Skin',                  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 118, 'attribute_id' => 2, 'value' => 'Choco',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 124, 'attribute_id' => 2, 'value' => 'Blush',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 125, 'attribute_id' => 2, 'value' => 'Honey tan',             'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 126, 'attribute_id' => 2, 'value' => 'Summer tan',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 135, 'attribute_id' => 2, 'value' => 'Charcoal black',        'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 136, 'attribute_id' => 2, 'value' => 'Vintage black',         'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 138, 'attribute_id' => 2, 'value' => 'Midnight blue',         'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 139, 'attribute_id' => 2, 'value' => 'Mulberry',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 140, 'attribute_id' => 2, 'value' => 'Ash blue',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 141, 'attribute_id' => 2, 'value' => 'Pearl white',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 142, 'attribute_id' => 2, 'value' => 'Teal green',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 143, 'attribute_id' => 2, 'value' => 'Viola purple',          'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],

            // ── Add Engraving? (attribute_id = 3) ────────────────────────────
            ['id' => 26,  'attribute_id' => 3, 'value' => 'No',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 34,  'attribute_id' => 3, 'value' => 'Yes',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],

            // ── Engraving (attribute_id = 4) ─────────────────────────────────
            ['id' => 50,  'attribute_id' => 4, 'value' => 'No',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 51,  'attribute_id' => 4, 'value' => 'Yes',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],

            // ── Waist Size (attribute_id = 5) ────────────────────────────────
            ['id' => 59,  'attribute_id' => 5, 'value' => '32',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 93,  'attribute_id' => 5, 'value' => '34',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 94,  'attribute_id' => 5, 'value' => '36',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 95,  'attribute_id' => 5, 'value' => '38',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 96,  'attribute_id' => 5, 'value' => '40',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 97,  'attribute_id' => 5, 'value' => '42',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 98,  'attribute_id' => 5, 'value' => '44',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 99,  'attribute_id' => 5, 'value' => '46',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],

            // ── Shoe Size (attribute_id = 6) ──────────────────────────────────
            ['id' => 52,  'attribute_id' => 6, 'value' => '36',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 101, 'attribute_id' => 6, 'value' => '37',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 102, 'attribute_id' => 6, 'value' => '38',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 103, 'attribute_id' => 6, 'value' => '39',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 104, 'attribute_id' => 6, 'value' => '40',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 105, 'attribute_id' => 6, 'value' => '41',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 📦 Product Categories
        ProductCategory::insert([
            ['id' => 1,  'name' => 'Raw Leather',                  'code' => 'raw-leather',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2,  'name' => 'Women Jackets',                'code' => 'women-jackets',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3,  'name' => 'Bags',                         'code' => 'bags',                     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4,  'name' => 'Skirts',                       'code' => 'skirts',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5,  'name' => 'Mens Jackets',                 'code' => 'men-jacket',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6,  'name' => 'Pant',                         'code' => 'pant',                     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7,  'name' => 'Wallets',                      'code' => 'wallets',                  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8,  'name' => 'Rugs',                         'code' => 'rugs',                     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9,  'name' => 'Belts',                        'code' => 'belts',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => 'Wallet',                       'code' => 'wallet',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Default',                      'code' => 'default',                  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'name' => 'Jackets',                      'code' => 'jackets',                  'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'name' => 'Jacket',                       'code' => 'jacket',                   'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'name' => 'Gift Sets',                    'code' => 'gift-sets',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 15, 'name' => 'Watch Straps',                 'code' => 'watch-straps',             'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 16, 'name' => 'Tote Bags',                    'code' => 'tote-bags',                'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 17, 'name' => 'Leather Blazers',              'code' => 'leather-blazers',          'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 18, 'name' => 'Laptop Bags',                  'code' => 'laptop-bags',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 19, 'name' => "Men's Leather Trench Coat",    'code' => 'mens-leather-trench-coat', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'name' => 'Shoes',                        'code' => 'shoes',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 21, 'name' => 'Eyewear Pouch',                'code' => 'eyewear-pouch',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 22, 'name' => 'Caps',                         'code' => 'caps',                     'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 23, 'name' => 'Backpack',                     'code' => 'backpack',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 24, 'name' => 'Duffle Bag',                   'code' => 'duffle-bag',               'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 25, 'name' => 'Services',                     'code' => 'services',                 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 26, 'name' => 'CROSSBODY BAG',                'code' => 'crossbody-bag',            'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 27, 'name' => 'Mules',                        'code' => 'mules',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 28, 'name' => 'Men Biker Jacket',             'code' => 'men-biker-jacket',         'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 29, 'name' => 'Card Holder',                  'code' => 'card-holder',              'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 30, 'name' => 'Bomber Jackets',               'code' => 'bomber-jackets',           'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 31, 'name' => 'OTHER',                        'code' => 'other',                    'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 📏 Measurement Units
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Piece', 'shortcode' => 'pcs'],
            ['id' => 2, 'name' => 'Meter', 'shortcode' => 'm'],
            ['id' => 3, 'name' => 'Square Feet', 'shortcode' => 'sq.ft'],
            ['id' => 4, 'name' => 'Yards', 'shortcode' => 'yrds'],
        ]);

        $sequences = [
            ['prefix' => 'GLOBAL', 'next_number' => 1],
            ['prefix' => 'FG', 'next_number' => 1],
            ['prefix' => 'RAW', 'next_number' => 1],
            ['prefix' => 'SRV', 'next_number' => 1],
            ['prefix' => 'PRD', 'next_number' => 1],
            ['prefix' => 'VAR', 'next_number' => 1],
        ];

        foreach ($sequences as $seq) {
            BarcodeSequence::firstOrCreate(
                ['prefix' => $seq['prefix']],
                ['next_number' => $seq['next_number']]
            );
        }
    }
}
