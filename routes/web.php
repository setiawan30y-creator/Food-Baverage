<?php

/**
 * ALMARA Restaurant & Beverage OS — Routes
 * @2026-Alifiandra
 */

use App\Http\Controllers;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public;
use App\Http\Controllers\Settings;
use Illuminate\Support\Facades\Route;

// ---------- Publik (pelanggan, tanpa login) ----------
Route::get('/media/logo/{tenant}', [Controllers\MediaController::class, 'logo'])->name('media.logo');
Route::get('/media/menu/{menu}', [Controllers\MediaController::class, 'menu'])->name('media.menu');

Route::get('/t/{token}', [Public\QrMenuController::class, 'show'])->name('qr.show');
Route::post('/t/{token}', [Public\QrMenuController::class, 'store'])->middleware('throttle:public-order')->name('qr.order');

Route::get('/o/{slug}', [Public\OrderAheadController::class, 'index'])->name('oa.index');
Route::get('/o/{slug}/{branch}', [Public\OrderAheadController::class, 'menu'])->whereNumber('branch')->name('oa.menu');
Route::post('/o/{slug}/{branch}', [Public\OrderAheadController::class, 'store'])->whereNumber('branch')->middleware('throttle:public-order')->name('oa.store');

Route::get('/track/{token}', [Public\TrackController::class, 'show'])->name('track.show');
Route::get('/track/{token}/status', [Public\TrackController::class, 'status'])->name('track.status');
Route::get('/nota/{token}', [Public\TrackController::class, 'receipt'])->name('track.receipt');

// ---------- Autentikasi ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ---------- Aplikasi (login + konteks tenant) ----------
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/', [Controllers\DashboardController::class, 'index'])->middleware('ability:dashboard')->name('dashboard');
    Route::post('/branch/switch', [Controllers\DashboardController::class, 'switchBranch'])->name('branch.switch');
    Route::post('/me/theme', [Settings\ThemeController::class, 'userMode'])->name('me.theme');

    // POS & pesanan
    Route::middleware('ability:pos')->group(function () {
        Route::get('/pos', [Controllers\PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/orders', [Controllers\PosController::class, 'store'])->name('pos.store');
        Route::post('/orders/{order}/pay', [Controllers\PosController::class, 'pay'])->name('orders.pay');
    });
    Route::middleware('ability:orders')->group(function () {
        Route::get('/orders', [Controllers\OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders/pickup/verify', [Controllers\OrderController::class, 'verifyPickup'])->name('orders.pickup');
        Route::get('/orders/{order}', [Controllers\OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [Controllers\OrderController::class, 'status'])->name('orders.status');
        Route::post('/orders/{order}/cancel', [Controllers\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('/orders/{order}/receipt', [Controllers\OrderController::class, 'receipt'])->name('orders.receipt');
        Route::get('/customers', [Controllers\CustomerController::class, 'index'])->name('customers.index');
    });

    // Meja & QR
    Route::middleware('ability:tables')->group(function () {
        Route::get('/tables', [Controllers\TableController::class, 'index'])->name('tables.index');
        Route::get('/tables/print', [Controllers\TableController::class, 'printQr'])->name('tables.print');
        Route::post('/tables', [Controllers\TableController::class, 'store'])->name('tables.store');
        Route::put('/tables/{table}', [Controllers\TableController::class, 'update'])->name('tables.update');
        Route::post('/tables/{table}/regenerate', [Controllers\TableController::class, 'regenerate'])->name('tables.regenerate');
        Route::post('/tables/{table}/close', [Controllers\TableController::class, 'close'])->name('tables.close');
        Route::post('/tables/{table}/move', [Controllers\TableController::class, 'move'])->name('tables.move');
    });

    // Dapur / KDS
    Route::middleware('ability:kitchen')->group(function () {
        Route::get('/kitchen', [Controllers\KitchenController::class, 'index'])->name('kitchen.index');
        Route::get('/kitchen/feed', [Controllers\KitchenController::class, 'feed'])->name('kitchen.feed');
        Route::post('/kitchen/items/{item}', [Controllers\KitchenController::class, 'item'])->name('kitchen.item');
        Route::post('/kitchen/orders/{order}/ready', [Controllers\KitchenController::class, 'ready'])->name('kitchen.ready');
        Route::post('/kitchen/orders/{order}/served', [Controllers\KitchenController::class, 'served'])->name('kitchen.served');
    });

    // Menu
    Route::middleware('ability:menu')->group(function () {
        Route::get('/menus', [Controllers\MenuController::class, 'index'])->name('menus.index');
        Route::get('/menus/create', [Controllers\MenuController::class, 'create'])->name('menus.create');
        Route::post('/menus', [Controllers\MenuController::class, 'store'])->name('menus.store');
        Route::get('/menus/{menu}/edit', [Controllers\MenuController::class, 'edit'])->name('menus.edit');
        Route::put('/menus/{menu}', [Controllers\MenuController::class, 'update'])->name('menus.update');
        Route::post('/menus/{menu}/branch', [Controllers\MenuController::class, 'branchSetting'])->name('menus.branch');
        Route::post('/menus/{menu}/modifiers', [Controllers\MenuController::class, 'storeModifier'])->name('menus.modifiers.store');
        Route::delete('/modifiers/{modifier}', [Controllers\MenuController::class, 'destroyModifier'])->name('menus.modifiers.destroy');
        Route::post('/menu-categories', [Controllers\MenuController::class, 'storeCategory'])->name('categories.store');
        Route::put('/menu-categories/{category}', [Controllers\MenuController::class, 'updateCategory'])->name('categories.update');
    });

    // Persediaan
    Route::middleware('ability:inventory')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/items', [Controllers\InventoryItemController::class, 'index'])->name('items.index');
        Route::get('/items/create', [Controllers\InventoryItemController::class, 'create'])->name('items.create');
        Route::post('/items', [Controllers\InventoryItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit', [Controllers\InventoryItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [Controllers\InventoryItemController::class, 'update'])->name('items.update');

        Route::get('/stock', [Controllers\StockController::class, 'index'])->name('stock');
        Route::get('/stock/{item}', [Controllers\StockController::class, 'ledger'])->name('ledger');
        Route::post('/adjust', [Controllers\StockController::class, 'adjust'])->middleware('ability:inventory.approve')->name('adjust');
        Route::get('/opening', [Controllers\StockController::class, 'openingForm'])->name('opening');
        Route::post('/opening', [Controllers\StockController::class, 'openingStore'])->middleware('ability:inventory.approve')->name('opening.store');

        Route::get('/waste', [Controllers\WasteController::class, 'index'])->name('waste.index');
        Route::post('/waste', [Controllers\WasteController::class, 'store'])->name('waste.store');
        Route::post('/waste/{waste}/decide', [Controllers\WasteController::class, 'decide'])->middleware('ability:inventory.approve')->name('waste.decide');

        Route::get('/counts', [Controllers\StockCountController::class, 'index'])->name('counts.index');
        Route::post('/counts', [Controllers\StockCountController::class, 'store'])->name('counts.store');
        Route::get('/counts/{count}', [Controllers\StockCountController::class, 'show'])->name('counts.show');
        Route::put('/counts/{count}', [Controllers\StockCountController::class, 'update'])->name('counts.update');
    });

    // Pembelian
    Route::middleware('ability:purchasing')->group(function () {
        Route::get('/suppliers', [Controllers\SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [Controllers\SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [Controllers\SupplierController::class, 'update'])->name('suppliers.update');
        Route::get('/purchases', [Controllers\PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [Controllers\PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [Controllers\PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [Controllers\PurchaseController::class, 'show'])->name('purchases.show');
        Route::post('/purchases/{purchase}/receive', [Controllers\PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::post('/purchases/{purchase}/pay', [Controllers\PurchaseController::class, 'pay'])->name('purchases.pay');
        Route::post('/purchases/{purchase}/cancel', [Controllers\PurchaseController::class, 'cancel'])->name('purchases.cancel');
    });

    // Resep & produksi
    Route::middleware('ability:recipes')->group(function () {
        Route::get('/recipes', [Controllers\RecipeController::class, 'index'])->name('recipes.index');
        Route::get('/recipes/create', [Controllers\RecipeController::class, 'create'])->name('recipes.create');
        Route::post('/recipes', [Controllers\RecipeController::class, 'store'])->name('recipes.store');
        Route::get('/recipes/{recipe}/edit', [Controllers\RecipeController::class, 'edit'])->name('recipes.edit');
        Route::put('/recipes/{recipe}', [Controllers\RecipeController::class, 'update'])->name('recipes.update');
    });
    Route::middleware('ability:production')->group(function () {
        Route::get('/production', [Controllers\ProductionController::class, 'index'])->name('production.index');
        Route::get('/production/create', [Controllers\ProductionController::class, 'create'])->name('production.create');
        Route::post('/production', [Controllers\ProductionController::class, 'store'])->name('production.store');
        Route::get('/production/{batch}', [Controllers\ProductionController::class, 'show'])->name('production.show');
    });

    // Kasir & closing
    Route::middleware('ability:closing')->group(function () {
        Route::get('/cashier', [Controllers\CashierController::class, 'index'])->name('cashier.index');
        Route::post('/cashier/open', [Controllers\CashierController::class, 'open'])->name('cashier.open');
        Route::post('/cashier/movements', [Controllers\CashierController::class, 'movement'])->name('cashier.movement');
        Route::get('/cashier/close', [Controllers\CashierController::class, 'closeForm'])->name('cashier.close');
        Route::post('/cashier/close', [Controllers\CashierController::class, 'close'])->name('cashier.close.store');

        Route::get('/closings', [Controllers\ClosingController::class, 'index'])->name('closings.index');
        Route::get('/closings/export', [Controllers\ClosingController::class, 'export'])->name('closings.export');
        Route::get('/closings/{closing}', [Controllers\ClosingController::class, 'show'])->name('closings.show');
        Route::get('/closings/{closing}/print', [Controllers\ClosingController::class, 'print'])->name('closings.print');
        Route::post('/closings/{closing}/approve', [Controllers\ClosingController::class, 'approve'])->middleware('ability:closing.approve')->name('closings.approve');
        Route::post('/closings/{closing}/reopen', [Controllers\ClosingController::class, 'reopen'])->middleware('ability:closing.approve')->name('closings.reopen');
    });

    // Karyawan, jadwal, absensi, payroll
    Route::middleware('ability:employees')->group(function () {
        Route::get('/employees', [Controllers\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/create', [Controllers\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [Controllers\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [Controllers\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [Controllers\EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/employees/{employee}/advances', [Controllers\EmployeeController::class, 'storeAdvance'])->middleware('ability:payroll')->name('employees.advances');
    });
    Route::middleware('ability:schedule')->group(function () {
        Route::get('/schedule', [Controllers\ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('/schedule', [Controllers\ScheduleController::class, 'save'])->name('schedule.save');
        Route::get('/schedule/print', [Controllers\ScheduleController::class, 'print'])->name('schedule.print');
        Route::post('/schedule/templates', [Controllers\ScheduleController::class, 'saveTemplate'])->name('schedule.templates');
    });
    Route::middleware('ability:attendance.self')->group(function () {
        Route::get('/attendance/me', [Controllers\AttendanceController::class, 'me'])->name('attendance.me');
        Route::post('/attendance/punch', [Controllers\AttendanceController::class, 'punch'])->middleware('throttle:attendance')->name('attendance.punch');
        Route::get('/leaves', [Controllers\LeaveController::class, 'index'])->name('leaves.index');
        Route::post('/leaves', [Controllers\LeaveController::class, 'store'])->name('leaves.store');
        Route::post('/leaves/{leave}/cancel', [Controllers\LeaveController::class, 'cancel'])->name('leaves.cancel');
        Route::get('/my/payslips', [Controllers\PayrollController::class, 'mine'])->name('payroll.mine');
        Route::get('/payslips/{item}', [Controllers\PayrollController::class, 'payslip'])->name('payroll.payslip');
    });
    Route::middleware('ability:attendance.manage')->group(function () {
        Route::get('/attendance', [Controllers\AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/manual', [Controllers\AttendanceController::class, 'manual'])->name('attendance.manual');
        Route::get('/attendance/export', [Controllers\AttendanceController::class, 'export'])->name('attendance.export');
        Route::post('/leaves/{leave}/decide', [Controllers\LeaveController::class, 'decide'])->name('leaves.decide');
    });
    Route::middleware('ability:payroll')->prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [Controllers\PayrollController::class, 'index'])->name('index');
        Route::post('/', [Controllers\PayrollController::class, 'store'])->name('store');
        Route::get('/{run}', [Controllers\PayrollController::class, 'show'])->name('show');
        Route::post('/{run}/calculate', [Controllers\PayrollController::class, 'calculate'])->name('calculate');
        Route::put('/items/{item}', [Controllers\PayrollController::class, 'updateItem'])->name('items.update');
        Route::post('/{run}/approve', [Controllers\PayrollController::class, 'approve'])->middleware('ability:payroll.approve')->name('approve');
        Route::post('/{run}/pay', [Controllers\PayrollController::class, 'pay'])->middleware('ability:payroll.approve')->name('pay');
    });

    // Laporan & audit
    Route::middleware('ability:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/sales', [Controllers\ReportController::class, 'sales'])->name('sales');
        Route::get('/menu', [Controllers\ReportController::class, 'menu'])->name('menu');
        Route::get('/stock', [Controllers\ReportController::class, 'stock'])->name('stock');
        Route::get('/waste', [Controllers\ReportController::class, 'waste'])->name('waste');
    });
    Route::get('/audit', [Controllers\AuditController::class, 'index'])->middleware('ability:audit')->name('audit.index');

    // Pengaturan (pemilik)
    Route::middleware('ability:settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/company', [Settings\CompanyController::class, 'edit'])->name('company');
        Route::post('/company', [Settings\CompanyController::class, 'update'])->name('company.update');
        Route::delete('/company/logo', [Settings\CompanyController::class, 'deleteLogo'])->name('company.logo.delete');
        Route::get('/theme', [Settings\ThemeController::class, 'edit'])->name('theme');
        Route::post('/theme', [Settings\ThemeController::class, 'update'])->name('theme.update');
        Route::post('/theme/reset', [Settings\ThemeController::class, 'reset'])->name('theme.reset');
        Route::get('/branches', [Settings\BranchController::class, 'index'])->name('branches');
        Route::post('/branches', [Settings\BranchController::class, 'store'])->name('branches.store');
        Route::put('/branches/{branch}', [Settings\BranchController::class, 'update'])->name('branches.update');
        Route::post('/branches/{branch}/stations', [Settings\BranchController::class, 'storeStation'])->name('stations.store');
        Route::delete('/stations/{station}', [Settings\BranchController::class, 'destroyStation'])->name('stations.destroy');
        Route::get('/users', [Settings\UserController::class, 'index'])->name('users');
        Route::post('/users', [Settings\UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [Settings\UserController::class, 'update'])->name('users.update');
    });
});
