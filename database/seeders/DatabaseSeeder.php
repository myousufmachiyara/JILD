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

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();

        // 🔑 Create Super Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'Admin', 'password' => Hash::make('12345678')]
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
            'payment_vouchers',

            // Production
            'production',
            'production_receiving',
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

        // ⚖️ Seed Accounts Heads
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets'],
            ['id' => 2, 'name' => 'Liabilities'],
            ['id' => 3, 'name' => 'Expenses'],
            ['id' => 4, 'name' => 'Revenue'],
            ['id' => 5, 'name' => 'Equity'],
        ]);

        // 🏷️ Seed Attributes
        Attribute::insert([
            ['id' => 1, 'name' => 'SIZE', 'slug' => 'SIZE', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'COLOR', 'slug' => 'COLOR', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        AttributeValue::insert([
            // SIZE
            ['id' => 1, 'attribute_id' => 1, 'value' => 'XX-SMALL', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'attribute_id' => 1, 'value' => 'X-SMALL', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'attribute_id' => 1, 'value' => 'SMALL', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'attribute_id' => 1, 'value' => 'MEDIUM', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'attribute_id' => 1, 'value' => 'LARGE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'attribute_id' => 1, 'value' => 'XL', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'attribute_id' => 1, 'value' => 'X-LARGE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'attribute_id' => 1, 'value' => 'XX-LARGE', 'created_at' => $now, 'updated_at' => $now],

            // COLOR
            ['id' => 9, 'attribute_id' => 2, 'value' => 'BLACK', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'attribute_id' => 2, 'value' => 'RED', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'attribute_id' => 2, 'value' => 'BLUE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'attribute_id' => 2, 'value' => 'WHITE', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 📊 Seed Subheads
        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1, 'name' => "Current Assets"],
            ['id' => 2, 'hoa_id' => 1, 'name' => "Inventory"],
            ['id' => 3, 'hoa_id' => 2, 'name' => "Current Liabilities"],
            ['id' => 4, 'hoa_id' => 2, 'name' => "Long-Term Liabilities"],
            ['id' => 5, 'hoa_id' => 4, 'name' => "Sales"],
            ['id' => 6, 'hoa_id' => 3, 'name' => "Expenses"],
            ['id' => 7, 'hoa_id' => 5, 'name' => "Equity"],
        ]);

        // 📦 Product Categories
        ProductCategory::insert([
            ['id' => 1, 'name' => 'Raw Leather', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Women Jackets', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Bags', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 📏 Measurement Units
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Piece', 'shortcode' => 'pcs'],
            ['id' => 2, 'name' => 'Meter', 'shortcode' => 'm'],
            ['id' => 3, 'name' => 'Square Feet', 'shortcode' => 'sq.ft'],
        ]);

        // 💰 Chart of Accounts
        ChartOfAccounts::insert([
            ['id' => 1, 'shoa_id' => 1, 'name' => "Cash", 'account_type' => "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Asset", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'shoa_id' => 1, 'name' => "Bank", 'account_type' => "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Asset", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'shoa_id' => 3, 'name' => "Accounts Payable", 'account_type' => "vendor", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Vendor", 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
