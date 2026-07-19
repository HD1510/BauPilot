<?php

use App\Http\Controllers\MasterData\CostTypeController;
use App\Http\Controllers\MasterData\CustomerContactController;
use App\Http\Controllers\MasterData\CustomerController;
use App\Http\Controllers\MasterData\EmployeeAccountController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\MaterialController;
use App\Http\Controllers\MasterData\MaterialScanController;
use App\Http\Controllers\MasterData\OvertimeController;
use App\Http\Controllers\MasterData\SupplierController;
use App\Http\Controllers\MasterData\VehicleController;
use App\Http\Controllers\MasterData\VehicleDateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Kunden mit Ansprechpartnern
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::patch('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
    Route::patch('customers/{customerId}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
    Route::post('customers/{customer}/contacts', [CustomerContactController::class, 'store'])->name('customers.contacts.store');
    Route::delete('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])->name('customers.contacts.destroy');

    // Lieferanten
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::patch('suppliers/{supplier}/archive', [SupplierController::class, 'archive'])->name('suppliers.archive');

    // Kostenarten (Liste mit Inline-Formularen)
    Route::get('cost-types', [CostTypeController::class, 'index'])->name('cost-types.index');
    Route::post('cost-types', [CostTypeController::class, 'store'])->name('cost-types.store');
    Route::patch('cost-types/{cost_type}', [CostTypeController::class, 'update'])->name('cost-types.update');
    Route::patch('cost-types/{cost_type}/archive', [CostTypeController::class, 'archive'])->name('cost-types.archive');

    // Mitarbeiter
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('employees/{employee}/archive', [EmployeeController::class, 'archive'])->name('employees.archive');
    Route::post('employees/{employee}/account', [EmployeeAccountController::class, 'store'])->name('employees.account.store');
    Route::patch('employees/{employee}/account/password', [EmployeeAccountController::class, 'updatePassword'])->name('employees.account.password');

    // Überstunden je Monat und Auszahlungen (M8; Lohndaten, admin/büro)
    Route::post('employees/{employee}/overtime-entries', [OvertimeController::class, 'storeEntry'])->name('employees.overtime-entries.store');
    Route::delete('employees/{employee}/overtime-entries/{entry}', [OvertimeController::class, 'destroyEntry'])->name('employees.overtime-entries.destroy');
    Route::post('employees/{employee}/overtime-payouts', [OvertimeController::class, 'storePayout'])->name('employees.overtime-payouts.store');
    Route::delete('employees/{employee}/overtime-payouts/{payout}', [OvertimeController::class, 'destroyPayout'])->name('employees.overtime-payouts.destroy');

    // Fahrzeuge mit Zusatzterminen
    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
    Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::get('vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
    Route::patch('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::patch('vehicles/{vehicle}/archive', [VehicleController::class, 'archive'])->name('vehicles.archive');
    Route::post('vehicles/{vehicle}/dates', [VehicleDateController::class, 'store'])->name('vehicles.dates.store');
    Route::delete('vehicles/{vehicle}/dates/{date}', [VehicleDateController::class, 'destroy'])->name('vehicles.dates.destroy');

    // Material (Artikel-Preisliste) — inkl. Einlesen per PDF/Foto
    Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::post('materials/scan', [MaterialScanController::class, 'scan'])->name('materials.scan');
    Route::post('materials/import', [MaterialScanController::class, 'import'])->name('materials.import');
    Route::get('materials/create', [MaterialController::class, 'create'])->name('materials.create');
    Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::get('materials/{material}/edit', [MaterialController::class, 'edit'])->name('materials.edit');
    Route::patch('materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
    Route::patch('materials/{material}/archive', [MaterialController::class, 'archive'])->name('materials.archive');
});
