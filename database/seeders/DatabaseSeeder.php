<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\HeadOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\ChartOfAccounts;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Module;
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
        // User::factory(10)->create();

        $now = now();

        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'Admin', 'password' => Hash::make('12345678')]
        );

        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->assignRole($role);

        $modules = [
            ['name' => 'User Roles', 'shortcode' => 'user_roles'],
            ['name' => 'Users', 'shortcode' => 'users'],
            ['name' => 'Chart of Accounts', 'shortcode' => 'coa'],
            ['name' => 'Subhead of Accounts', 'shortcode' => 'shoa'],
            ['name' => 'Products', 'shortcode' => 'products'],
            ['name' => 'Purchase Invoices', 'shortcode' => 'purchase_invoices'],
            ['name' => 'Purchase Return', 'shortcode' => 'purchase_return'],
            ['name' => 'Production', 'shortcode' => 'production'],
            ['name' => 'Sale Invoices', 'shortcode' => 'sale_invoices'],
            ['name' => 'Sale Return', 'shortcode' => 'sale_return'],
            ['name' => 'Payment Vouchers', 'shortcode' => 'payment_vouchers'],
            ['name' => 'POS System', 'shortcode' => 'pos_system'],
        ];

        $actions = ['index', 'create', 'edit', 'delete', 'show', 'approval', 'print'];
        $allPermissions = [];

        foreach ($modules as $data) {
            $module = Module::firstOrCreate(
                ['shortcode' => $data['shortcode']],
                ['name' => $data['name'], 'status' => true]
            );

            foreach ($actions as $action) {
                $perm = Permission::firstOrCreate([
                    'name' => "{$data['shortcode']}.$action",
                    'guard_name' => 'web',
                ]);
                $allPermissions[] = $perm->id;
            }
        }

        $role->syncPermissions($allPermissions);

        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets'],
            ['id' => 2, 'name' => 'Liabilities'],
            ['id' => 3, 'name' => 'Expenses'],
            ['id' => 4, 'name' => 'Revenue'],
            ['id' => 5, 'name' => 'Equity'],
        ]);

        Attribute::insert([
            [
                'id' => 1,
                'name' => 'SIZE',
                'slug' => 'SIZE',
                'deleted_at' => null,
                'created_at' => '2025-07-11 18:22:55',
                'updated_at' => '2025-07-11 18:22:55',
            ],
            [
                'id' => 2,
                'name' => 'COLOR',
                'slug' => 'COLOR',
                'deleted_at' => null,
                'created_at' => '2025-07-11 18:23:46',
                'updated_at' => '2025-07-11 18:23:46',
            ]
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


        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1 , 'name' => "Current Assets"],
            ['id' => 2, 'hoa_id' => 1 , 'name' => "Inventory"],
            ['id' => 3, 'hoa_id' => 2 , 'name' => "Current Liabilities"],
            ['id' => 4, 'hoa_id' => 2 , 'name' => "Long-Term Liabilities"],
            ['id' => 5, 'hoa_id' => 4 , 'name' => "Sales"],
            ['id' => 6, 'hoa_id' => 3 , 'name' => "Expenses"],
            ['id' => 7, 'hoa_id' => 5 , 'name' => "Equity"],
        ]);

        ProductCategory::insert([
            ['id' => 1, 'name' => 'Raw Leather', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 18:04:58', 'updated_at' => '2025-07-11 18:04:58'],
            ['id' => 2, 'name' => 'Women Jackets', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:04:47', 'updated_at' => '2025-07-11 19:04:47'],
            ['id' => 3, 'name' => 'Bags', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:05:02', 'updated_at' => '2025-07-11 19:05:02'],
            ['id' => 4, 'name' => 'Skirts', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:05:16', 'updated_at' => '2025-07-11 19:05:16'],
            ['id' => 5, 'name' => 'Mens Jackets', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:05:32', 'updated_at' => '2025-07-11 19:05:32'],
            ['id' => 6, 'name' => 'Pant', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:10:57', 'updated_at' => '2025-07-11 19:10:57'],
            ['id' => 7, 'name' => 'Wallets', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:11:23', 'updated_at' => '2025-07-11 19:11:23'],
            ['id' => 8, 'name' => 'Rugs', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:11:33', 'updated_at' => '2025-07-11 19:11:33'],
        ]);
        
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Piece', 'shortcode' => 'pcs'],
            ['id' => 2, 'name' => 'Meter', 'shortcode' => 'm'],
            ['id' => 3, 'name' => 'Square Feet', 'shortcode' => 'sq.ft'],
        ]);

        ChartOfAccounts::insert([
            ['id' => 1, 'shoa_id' => 1, 'name' => "Cash", 'account_type'=> "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Asset", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'shoa_id' => 1, 'name' => "Bank", 'account_type'=> "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Asset", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'shoa_id' => 1, 'name' => "Accounts Receivable", 'account_type'=> "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Customer Accounts", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'shoa_id' => 3, 'name' => "Accounts Payable", 'account_type'=> "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Supplier Accounts", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'shoa_id' => 2, 'name' => "Raw Material Inventory", 'account_type'=> "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Inventory", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'shoa_id' => 6, 'name' => "Expense Account", 'account_type'=> "", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Expense", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'shoa_id' => 1, 'name' => "Test Customer", 'account_type'=> "customer", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Customer", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'shoa_id' => 3, 'name' => "Test Vendor", 'account_type'=> "vendor", 'receivables' => 0, 'payables' => 0, 'opening_date' => '2025-01-01', 'remarks' => "Vendor", 'address' => "", 'phone_no' => "", 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
