<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\RoleMiddleware;

use App\Http\Controllers\{
    DashboardController,
    HomeController,
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
    POSController,
    SaleReturnController,
    InventoryReportController,
    ProductionReportController,
    PurchaseReportController,
    SalesReportController,
    AccountsReportController,
    BusinessReportController,
};

Auth::routes();

Route::middleware(['auth', RoleMiddleware::class . ':admin|superadmin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    
    Route::get('/reports/inventory-report', [InventoryReportController::class, 'index'])->name('reports.inventory_reports');
    Route::get('/reports/production-report', [ProductionReportController::class, 'index'])->name('reports.production_reports');
    Route::get('/reports/purchase-report', [PurchaseReportController::class, 'index'])->name('reports.purchase_reports');
    Route::get('/reports/sales-report', [SalesReportController::class, 'index'])->name('reports.sales_reports');
    Route::get('/reports/accounts-report', [AccountsReportController::class, 'index'])->name('reports.accounts_reports');
    Route::get('/reports/business-report', [BusinessReportController::class, 'index'])->name('reports.business_reports');
});

Route::middleware(['auth'])->group(function () {
    
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/products/details', [ProductController::class, 'details'])->name('products.receiving');
    Route::get('/product/{id}/variations', [ProductController::class, 'getVariations']);
    Route::get('/product/{id}/variations', [ProductionReceivingController::class, 'getVariations'])->name('production.receiving.getVariations');
    
    Route::get('/products/barcode-selection', [ProductController::class, 'barcodeSelection'])->name('products.barcode.selection');
    Route::post('/products/generate-multiple-barcodes', [ProductController::class, 'generateMultipleBarcodes'])->name('products.generateBarcodes');
    Route::get('/get-variation-by-code/{code}', [ProductController::class, 'getVariationByCode'])->name('variation.by.code');
    
    Route::prefix('production_receiving')->name('production.receiving.')->group(function () {
        Route::get('/', [ProductionReceivingController::class, 'index'])->name('index');
        Route::get('/create', [ProductionReceivingController::class, 'create'])->name('create');
        Route::post('/store', [ProductionReceivingController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [ProductionReceivingController::class, 'edit'])->name('edit');
        Route::put('/{id}/update', [ProductionReceivingController::class, 'update'])->name('update');
        Route::get('/{id}/print', [ProductionReceivingController::class, 'print'])->name('print');
    });
    
    Route::get('/item/{item}/invoices', [PurchaseInvoiceController::class, 'getInvoicesByItem']);
    Route::get('/invoice-item/{invoiceId}/item/{itemId}', [PurchaseInvoiceController::class, 'getItemDetails']);
    Route::get('/production-summary/{id}', [ProductionController::class, 'summary'])->name('production.summary');


    $modules = [
        'coa' => ['controller' => COAController::class, 'permission' => 'coa'],
        'shoa' => ['controller' => SubHeadOfAccController::class, 'permission' => 'shoa'],
        'products' => ['controller' => ProductController::class, 'permission' => 'products'],
        'purchase_invoices' => ['controller' => PurchaseInvoiceController::class, 'permission' => 'purchase_invoices'],
        'purchase_return' => ['controller' => PurchaseReturnController::class, 'permission' => 'purchase_return'],
        'production' => ['controller' => ProductionController::class, 'permission' => 'production'],
        'sale_invoices' => ['controller' => SaleInvoiceController::class, 'permission' => 'sale_invoices'],
        'sale_return' => ['controller' => SaleReturnController::class, 'permission' => 'sale_return'],
        'payment_vouchers' => ['controller' => PaymentVoucherController::class, 'permission' => 'payment_vouchers'],
        'pos_system' => ['controller' => POSController::class, 'permission' => 'pos_system'],
    ];

    foreach ($modules as $uri => $config) {
        $controller = $config['controller'];
        $permission = $config['permission'];

        Route::get("$uri", [$controller, 'index'])->middleware("check.permission:$permission.index")->name("$uri.index");
        Route::get("$uri/create", [$controller, 'create'])->middleware("check.permission:$permission.create")->name("$uri.create");
        Route::get("$uri/{id}/print", [$controller, 'print'])->middleware("check.permission:$permission.print")->name("$uri.print");
        Route::post("$uri", [$controller, 'store'])->middleware("check.permission:$permission.store")->name("$uri.store");
        Route::post("$uri/{id}/approve", [$controller, 'approve'])->middleware("check.permission:$permission.approve")->name("$uri.approve");
        Route::get("$uri/{id}", [$controller, 'show'])->middleware("check.permission:$permission.show")->name("$uri.show");
        Route::get("$uri/{id}/edit", [$controller, 'edit'])->middleware("check.permission:$permission.edit")->name("$uri.edit");
        Route::put("$uri/{id}", [$controller, 'update'])->middleware("check.permission:$permission.update")->name("$uri.update");
        Route::delete("$uri/{id}", [$controller, 'destroy'])->middleware("check.permission:$permission.delete")->name("$uri.destroy");
    }

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/purchase', [ReportController::class, 'purchase'])->middleware('check.permission:purchase_invoices.index')->name('purchase');
        Route::get('/purchase-return', [ReportController::class, 'purchaseReturn'])->middleware('check.permission:purchase_return.index')->name('purchase_return');
        Route::get('/production', [ReportController::class, 'production'])->middleware('check.permission:production.index')->name('production');
        Route::get('/production-receiving', [ReportController::class, 'productionReceiving'])->middleware('check.permission:production.index')->name('production_receiving');
        Route::get('/sales', [ReportController::class, 'sales'])->middleware('check.permission:sale_invoices.index')->name('sales');
        Route::get('/sale-return', [ReportController::class, 'saleReturn'])->middleware('check.permission:sale_return.index')->name('sale_return');
        Route::get('/payments', [ReportController::class, 'payments'])->middleware('check.permission:payment_vouchers.index')->name('payments');
    });

    Route::prefix('product-categories')->name('product-categories.')->group(function () {
        Route::get('/', [ProductCategoryController::class, 'index'])->name('index');
        Route::post('/', [ProductCategoryController::class, 'store'])->name('store');
        Route::put('/{productCategory}', [ProductCategoryController::class, 'update'])->name('update');
        Route::delete('/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('attributes')->name('attributes.')->group(function () {
        Route::get('/', [AttributeController::class, 'index'])->name('index');
        Route::post('/', [AttributeController::class, 'store'])->name('store');
        Route::put('/{attribute}', [AttributeController::class, 'update'])->name('update');
        Route::delete('/{attribute}', [AttributeController::class, 'destroy'])->name('destroy');
    });

});

// Route::resource('/products', ProductController::class);
// Route::resource('/purchases', PurchaseController::class);
// Route::get('/production/receiving', [ProductionController::class, 'receiving'])->name('production.receiving');
// Route::resource('/production', ProductionController::class);
// Route::resource('/sales', SaleController::class);
// Route::resource('/subhead-of-accounts', SubHeadOfAccController::class);
// Route::resource('/chart-of-accounts', COAController::class);


Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
