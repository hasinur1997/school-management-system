<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\IncomeController;
use Illuminate\Support\Facades\Route;

// Categories (11.1): the shared income/expense category list. CRUD guarded
// by income.manage OR expense.manage (accountant work). Categories carry
// their own branch_id, so list/show are branch-isolated automatically and
// out-of-branch {category} bindings 404 via BranchScope. Deleting a category
// in use by income/expense rows → 409.
Route::middleware(['auth:sanctum', 'permission:income.manage|expense.manage'])->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

// Incomes (11.2): manual income ledger CRUD plus read access to the
// system-generated fee incomes Task 10.3 posts. Guarded by income.manage.
// Incomes carry their own branch_id, so list/show are branch-isolated and
// out-of-branch {income} bindings 404 via BranchScope. System-generated
// rows (payment_id set, is_system true) are immutable → update/delete 403.
Route::middleware(['auth:sanctum', 'permission:income.manage'])->group(function () {
    Route::get('incomes', [IncomeController::class, 'index'])->name('incomes.index');
    Route::post('incomes', [IncomeController::class, 'store'])->name('incomes.store');
    Route::put('incomes/{income}', [IncomeController::class, 'update'])->name('incomes.update');
    Route::delete('incomes/{income}', [IncomeController::class, 'destroy'])->name('incomes.destroy');
});

// Expenses (11.3): manual expense ledger CRUD mirroring incomes (same
// filters/sorts). Guarded by expense.manage; out-of-branch {expense}
// bindings 404 via BranchScope. category_id must be an expense-type
// category in the caller's branch (validated in the Form Requests).
Route::middleware(['auth:sanctum', 'permission:expense.manage'])->group(function () {
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
});
