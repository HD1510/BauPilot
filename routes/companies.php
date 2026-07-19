<?php

use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Companies\CompanyMemberController;
use App\Http\Controllers\Companies\CompanySwitchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::patch('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::patch('companies/{company}/archive', [CompanyController::class, 'archive'])->name('companies.archive');

    Route::post('companies/{company}/switch', CompanySwitchController::class)->name('companies.switch');

    Route::post('companies/{company}/members', [CompanyMemberController::class, 'store'])->name('companies.members.store');
    Route::post('companies/{company}/accounts', [CompanyMemberController::class, 'storeAccount'])->name('companies.accounts.store');
    Route::patch('companies/{company}/members/{user}', [CompanyMemberController::class, 'update'])->name('companies.members.update');
    Route::delete('companies/{company}/members/{user}', [CompanyMemberController::class, 'destroy'])->name('companies.members.destroy');
});
