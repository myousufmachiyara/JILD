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
            ['name' => 'Sale Invoices', 'shortcode' => 'sale_vouchers'],
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

        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1 , 'name' => "Current Assets"],
            ['id' => 2, 'hoa_id' => 1 , 'name' => "Inventory"],
            ['id' => 3, 'hoa_id' => 2 , 'name' => "Current Liabilities"],
            ['id' => 4, 'hoa_id' => 2 , 'name' => "Long-Term Liabilities"],
            ['id' => 5, 'hoa_id' => 4 , 'name' => "Sales"],
            ['id' => 6, 'hoa_id' => 3 , 'name' => "Expenses"],
            ['id' => 7, 'hoa_id' => 5 , 'name' => "Equity"],
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
