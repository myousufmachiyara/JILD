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

        // 🏷️ Seed Attributes
        Attribute::insert([
            ['id' => 1, 'name' => 'SIZE', 'slug' => 'SIZE', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'COLOR', 'slug' => 'COLOR', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        AttributeValue::insert([
            // SIZE attribute_id = 1
            ['id' => 1, 'attribute_id' => 1, 'value' => 'XX-SMALL', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 2, 'attribute_id' => 1, 'value' => 'X-SMALL',  'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 3, 'attribute_id' => 1, 'value' => 'SMALL',    'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 4, 'attribute_id' => 1, 'value' => 'MEDIUM',   'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 5, 'attribute_id' => 1, 'value' => 'LARGE',    'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 6, 'attribute_id' => 1, 'value' => 'XL',       'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 7, 'attribute_id' => 1, 'value' => 'X-LARGE',  'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 8, 'attribute_id' => 1, 'value' => 'XX-LARGE', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 9, 'attribute_id' => 1, 'value' => '3X-LARGE', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 10,'attribute_id' => 1, 'value' => '4X-LARGE', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],

            // COLOR attribute_id = 2
            ['id' => 11, 'attribute_id' => 2, 'value' => 'BLACK',    'created_at' => '2025-07-11 18:23:46', 'updated_at' => '2025-07-11 18:23:46'],
            ['id' => 12, 'attribute_id' => 2, 'value' => 'RED',      'created_at' => '2025-07-11 18:23:46', 'updated_at' => '2025-07-11 18:23:46'],
            ['id' => 13, 'attribute_id' => 2, 'value' => 'BLUE',     'created_at' => '2025-07-11 18:23:46', 'updated_at' => '2025-07-11 18:23:46'],
            ['id' => 14, 'attribute_id' => 2, 'value' => 'BROWN',    'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 15, 'attribute_id' => 2, 'value' => 'BURGUNDY', 'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 16, 'attribute_id' => 2, 'value' => 'COGNAC',   'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 17, 'attribute_id' => 2, 'value' => 'GREEN',    'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 18, 'attribute_id' => 2, 'value' => 'PINK',     'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 19, 'attribute_id' => 2, 'value' => 'PURPLE',   'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 20, 'attribute_id' => 2, 'value' => 'RUB',      'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 21, 'attribute_id' => 2, 'value' => 'TAN',      'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 22, 'attribute_id' => 2, 'value' => 'WHITE',    'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 23, 'attribute_id' => 2, 'value' => 'YELLOW',   'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
        ]);

        // 📦 Product Categories
        ProductCategory::insert([
            ['id' => 1, 'name' => 'Raw Leather', 'code'=> 'raw-leather', 'created_at' => '2025-07-11 18:04:58', 'updated_at' => '2025-07-11 18:04:58'],
            ['id' => 2, 'name' => 'Women Jackets', 'code'=> 'women-jackets', 'created_at' => '2025-07-11 19:04:47', 'updated_at' => '2025-07-11 19:04:47'],
            ['id' => 3, 'name' => 'Bags', 'code'=> 'bags', 'created_at' => '2025-07-11 19:05:02', 'updated_at' => '2025-07-11 19:05:02'],
            ['id' => 4, 'name' => 'Skirts', 'code'=> 'skirts', 'created_at' => '2025-07-11 19:05:16', 'updated_at' => '2025-07-11 19:05:16'],
            ['id' => 5, 'name' => 'Mens Jackets', 'code'=> 'men-jacket', 'created_at' => '2025-07-11 19:05:32', 'updated_at' => '2025-07-11 19:05:32'],
            ['id' => 6, 'name' => 'Pant',  'code'=> 'pant', 'created_at' => '2025-07-11 19:10:57', 'updated_at' => '2025-07-11 19:10:57'],
            ['id' => 7, 'name' => 'Wallets',  'code'=> 'wallets', 'created_at' => '2025-07-11 19:11:23', 'updated_at' => '2025-07-11 19:11:23'],
            ['id' => 8, 'name' => 'Rugs',  'code'=> 'rugs', 'created_at' => '2025-07-11 19:11:33', 'updated_at' => '2025-07-11 19:11:33'],
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
