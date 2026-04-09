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
            ['id' => 1, 'name' => 'Assets', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // SUB HEADS
        // ---------------------
        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1, 'name' => 'Cash', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'hoa_id' => 1, 'name' => 'Bank', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'hoa_id' => 1, 'name' => 'Accounts Receivable', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'hoa_id' => 1, 'name' => 'Inventory', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'hoa_id' => 2, 'name' => 'Accounts Payable', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'hoa_id' => 2, 'name' => 'Loans', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'hoa_id' => 3, 'name' => 'Owner Capital', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'hoa_id' => 4, 'name' => 'Sales', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'hoa_id' => 5, 'name' => 'Purchases', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10,'hoa_id' => 5, 'name' => 'Salaries', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11,'hoa_id' => 5, 'name' => 'Rent', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12,'hoa_id' => 5, 'name' => 'Utilities', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // CHART OF ACCOUNTS
        // ---------------------
        $coaData = [
            ['account_code' => '104001', 'shoa_id' => 4, 'name' => 'Stock in Hand', 'account_type' => 'asset', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '103001', 'shoa_id' => 3, 'name' => 'Customer 01', 'account_type' => 'customer', 'receivables' => 12000.00, 'payables' => 0.00],
            ['account_code' => '205001', 'shoa_id' => 5, 'name' => 'Vendor 01', 'account_type' => 'vendor', 'receivables' => 0.00, 'payables' => 7500.00],
            ['account_code' => '101001', 'shoa_id' => 1, 'name' => 'Shop Cash', 'account_type' => 'cash', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '102001', 'shoa_id' => 2, 'name' => 'Meezan Yousuf', 'account_type' => 'bank', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '307001', 'shoa_id' => 7, 'name' => 'Owners Equity', 'account_type' => 'equity', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '408001', 'shoa_id' => 8, 'name' => 'Sales Revenue', 'account_type' => 'revenue', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '509001', 'shoa_id' => 9, 'name' => 'Cost of Goods Sold', 'account_type' => 'cogs', 'receivables' => 0.00, 'payables' => 0.00],
        ];

        foreach ($coaData as $data) {
            ChartOfAccounts::create(array_merge($data, [
                'opening_date' => '2026-01-19',
                'credit_limit' => 0.00,
                'remarks'      => null,
                'address'      => null,
                'phone_no'     => null,
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
