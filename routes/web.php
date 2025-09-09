<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,
    SubHeadOfAccController,
    COAController,
    SaleInvoiceController,
    ProductionController,
    PurchaseInvoiceController,
    PurchaseReturnController,
    ProductController,
    UserController,
    RoleController,
    AttributeController,
    ProductCategoryController,
    ProductionReceivingController,
    PaymentVoucherController,
    ReportController,
    InventoryReportController,
    POSController,
    SaleReturnController,
    PermissionController,
    LocationController,
    StockTransferController
};

Auth::routes();

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Product Helpers
    Route::get('/products/details', [ProductController::class, 'details'])->name('products.receiving');
    Route::get('/products/barcode-selection', [ProductController::class, 'barcodeSelection'])->name('products.barcode.selection');
    Route::post('/products/generate-multiple-barcodes', [ProductController::class, 'generateMultipleBarcodes'])->name('products.generateBarcodes');
    Route::get('/get-product-by-code/{barcode}', [ProductController::class, 'getByBarcode'])->name('product.byBarcode');
    Route::get('/product/{product}/variations', [ProductController::class, 'getVariations'])->name('product.variations');
    
    //Purchase Helper
    Route::get('/product/{product}/invoices', [PurchaseInvoiceController::class, 'getProductInvoices']);

    // Production Receiving
    Route::prefix('production_receiving')->name('production.receiving.')->group(function () {
        Route::get('/', [ProductionReceivingController::class, 'index'])->middleware('check.permission:production_receiving.index')->name('index');
        Route::get('/create', [ProductionReceivingController::class, 'create'])->middleware('check.permission:production_receiving.create')->name('create');
        Route::post('/store', [ProductionReceivingController::class, 'store'])->middleware('check.permission:production_receiving.create')->name('store');
        Route::get('/{id}/edit', [ProductionReceivingController::class, 'edit'])->middleware('check.permission:production_receiving.edit')->name('edit');
        Route::put('/{id}/update', [ProductionReceivingController::class, 'update'])->middleware('check.permission:production_receiving.edit')->name('update');
        Route::get('/{id}/print', [ProductionReceivingController::class, 'print'])->middleware('check.permission:production_receiving.print')->name('print');
    });

    // Production Summary
    Route::get('/production-summary/{id}', [ProductionController::class, 'summary'])->name('production.summary');

    // Common Modules
    $modules = [
        // User Management
        'roles' => ['controller' => RoleController::class, 'permission' => 'user_roles'],
        'permissions' => ['controller' => PermissionController::class, 'permission' => 'role_permissions'],
        'users' => ['controller' => UserController::class, 'permission' => 'users'],

        // Accounts
        'coa' => ['controller' => COAController::class, 'permission' => 'coa'],
        'shoa' => ['controller' => SubHeadOfAccController::class, 'permission' => 'shoa'],

        // Products
        'products' => ['controller' => ProductController::class, 'permission' => 'products'],
        'product_categories' => ['controller' => ProductCategoryController::class, 'permission' => 'product_categories'],
        'attributes' => ['controller' => AttributeController::class, 'permission' => 'attributes'],

        // Stock Management
        'locations' => ['controller' => LocationController::class, 'permission' => 'locations'],
        'stock_transfer' => ['controller' => StockTransferController::class, 'permission' => 'stock_transfer'],

        // Purchases
        'purchase_invoices' => ['controller' => PurchaseInvoiceController::class, 'permission' => 'purchase_invoices'],
        'purchase_return' => ['controller' => PurchaseReturnController::class, 'permission' => 'purchase_return'],

        // Sales
        'sale_invoices' => ['controller' => SaleInvoiceController::class, 'permission' => 'sale_invoices'],
        'sale_return' => ['controller' => SaleReturnController::class, 'permission' => 'sale_return'],

        // Vouchers
        'payment_vouchers' => ['controller' => PaymentVoucherController::class, 'permission' => 'payment_vouchers'],

        // Production
        'production' => ['controller' => ProductionController::class, 'permission' => 'production'],
        'production_receiving' => ['controller' => ProductionReceivingController::class, 'permission' => 'production_receiving'],

        // POS (optional)
        'pos_system' => ['controller' => POSController::class, 'permission' => 'pos_system'],
    ];

    foreach ($modules as $uri => $config) {
        if($uri === 'roles') continue; // skip roles for now

        $controller = $config['controller'];
        $permission = $config['permission'];

        Route::get("$uri", [$controller, 'index'])->middleware("check.permission:$permission.index")->name("$uri.index");
        Route::get("$uri/create", [$controller, 'create'])->middleware("check.permission:$permission.create")->name("$uri.create");
        Route::post("$uri", [$controller, 'store'])->middleware("check.permission:$permission.create")->name("$uri.store");
        Route::get("$uri/{id}", [$controller, 'show'])->middleware("check.permission:$permission.index")->name("$uri.show");
        Route::get("$uri/{id}/edit", [$controller, 'edit'])->middleware("check.permission:$permission.edit")->name("$uri.edit");
        Route::put("$uri/{id}", [$controller, 'update'])->middleware("check.permission:$permission.edit")->name("$uri.update");
        Route::delete("$uri/{id}", [$controller, 'destroy'])->middleware("check.permission:$permission.delete")->name("$uri.destroy");
        Route::get("$uri/{id}/print", [$controller, 'print'])->middleware("check.permission:$permission.print")->name("$uri.print");
    }

    // Roles module (model binding: Role $role)
    Route::get("roles", [RoleController::class, 'index'])->middleware("check.permission:user_roles.index")->name("roles.index");
    Route::get("roles/create", [RoleController::class, 'create'])->middleware("check.permission:user_roles.create")->name("roles.create");
    Route::post("roles", [RoleController::class, 'store'])->middleware("check.permission:user_roles.create")->name("roles.store");
    Route::get("roles/{role}/edit", [RoleController::class, 'edit'])->middleware("check.permission:user_roles.edit")->name("roles.edit");
    Route::put("roles/{role}", [RoleController::class, 'update'])->middleware("check.permission:user_roles.edit")->name("roles.update");
    Route::delete("roles/{role}", [RoleController::class, 'destroy'])->middleware("check.permission:user_roles.delete")->name("roles.destroy");

    // Reports (readonly)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('inventory', [InventoryReportController::class, 'inventoryReports'])->name('inventory');
    });
});