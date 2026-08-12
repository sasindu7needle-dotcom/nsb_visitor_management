<?php

use Illuminate\Support\Facades\Route;
use App\Models\VerifiedVisitor;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\VisitorCheckinController;
use App\Http\Controllers\ReturningVisitorController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminCapacityController;
use App\Http\Controllers\AdminVisitorController;
use App\Http\Controllers\GateTerminalController;
use App\Http\Controllers\AdminEventConfigurationController;
use App\Http\Controllers\AdminVisitorCategoryController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminDepartmentDirectoryController;
use App\Http\Controllers\AdminAppointmentController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/visitor/new', [VisitorController::class, 'startNew'])->name('visitor.start');
Route::get('/visitor/returning', [ReturningVisitorController::class, 'show'])->name('visitor.returning');
Route::post('/api/visitor/returning/find', [ReturningVisitorController::class, 'findByNic'])->middleware('throttle:20,1')->name('visitor.returning.find');
Route::post('/api/visitor/returning/compare', [ReturningVisitorController::class, 'captureAndCompare'])->middleware('throttle:10,1')->name('visitor.returning.compare');
Route::get('/visitor/create', [VisitorController::class, 'create'])->name('visitor.create');
Route::get('/visitor/appointments/{appointment}/{token}', [VisitorController::class, 'startAppointment'])->name('visitor.appointments.start');
Route::get('/visitor/upload-document', [VisitorController::class, 'showUploadDocument'])->name('visitor.upload_document');
Route::get('/visitor/live-face-check', [VisitorController::class, 'showLiveFaceCheck'])->name('visitor.live_face');
Route::get('/visitor/session-photo/{type?}', [VisitorController::class, 'sessionPhoto'])->name('visitor.session_photo');
Route::post('/visitor/confirm', [VisitorController::class, 'confirm'])->name('visitor.confirm');
Route::post('/visitor/payment-method', [VisitorController::class, 'selectPaymentMethod'])->name('visitor.payment-method');
Route::get('/visitor/payment/card', [VisitorController::class, 'cardGateway'])->name('visitor.payment.card');
Route::get('/visitor/payment/cash', [VisitorController::class, 'cashConfirmation'])->name('visitor.payment.cash');
Route::post('/visitor/payment/confirm', [VisitorController::class, 'confirmPayment'])->name('visitor.payment.confirm');
Route::get('/visitor/thank-you', [VisitorController::class, 'thankYou'])->name('visitor.thank-you');
Route::get('/visitor/list', fn () => redirect()->route('admin.visitors.index'))->name('visitor.list');
Route::post('/visitor', [VisitorController::class, 'store'])->name('visitor.store');
Route::delete('/visitor/{visitorId}', [VisitorController::class, 'checkout'])->name('visitor.checkout');

Route::post('/api/visitor/verify-vision', [VisitorCheckinController::class, 'verifyVision'])->name('visitor.verify_vision');
Route::post('/api/visitor/verify-live-face', [VisitorCheckinController::class, 'verifyLiveFace'])->middleware('throttle:10,1')->name('visitor.verify_live_face');
Route::post('/api/visitor/verify-session', [VisitorCheckinController::class, 'verifyVision'])->name('visitor.session');

Route::prefix('gate')->name('gate.')->group(function () {
    Route::get('/A/{direction}', [GateTerminalController::class, 'show'])->name('show');
    Route::post('/A/{direction}', [GateTerminalController::class, 'scan'])->middleware('throttle:120,1')->name('scan');
    Route::get('/visitor-photo/{visitor}', [GateTerminalController::class, 'photo'])->middleware('signed')->name('photo');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/counts', [AdminDashboardController::class, 'counts'])->name('dashboard.counts');
        Route::patch('/dashboard/visitor-requests/{visitor}', [AdminDashboardController::class, 'decideVisitorRequest'])->name('dashboard.visitor_requests.decide');
        Route::patch('/dashboard/visitor-passes/{visitor}/return', [AdminDashboardController::class, 'markVisitorPassReturned'])->name('dashboard.visitor_passes.return');
        Route::get('/configurations/event', [AdminEventConfigurationController::class, 'edit'])->name('configurations.event.edit');
        Route::put('/configurations/event', [AdminEventConfigurationController::class, 'update'])->name('configurations.event.update');
        Route::get('/configurations/capacity', [AdminCapacityController::class, 'edit'])->name('configurations.capacity.edit');
        Route::put('/configurations/capacity', [AdminCapacityController::class, 'update'])->name('configurations.capacity.update');

        Route::get('/configurations/categories', [AdminVisitorCategoryController::class, 'index'])->name('configurations.categories.index');
        Route::post('/configurations/categories', [AdminVisitorCategoryController::class, 'store'])->name('configurations.categories.store');
        Route::put('/configurations/categories/{category}', [AdminVisitorCategoryController::class, 'update'])->name('configurations.categories.update');
        Route::patch('/configurations/categories/{category}/toggle', [AdminVisitorCategoryController::class, 'toggleActive'])->name('configurations.categories.toggle');
        Route::delete('/configurations/categories/{category}', [AdminVisitorCategoryController::class, 'destroy'])->name('configurations.categories.destroy');

        Route::get('/configurations/users', [AdminUserController::class, 'index'])->name('configurations.users.index');
        Route::post('/configurations/users', [AdminUserController::class, 'store'])->name('configurations.users.store');
        Route::put('/configurations/users/{user}', [AdminUserController::class, 'update'])->name('configurations.users.update');
        Route::patch('/configurations/users/{user}/toggle', [AdminUserController::class, 'toggleStatus'])->name('configurations.users.toggle');
        Route::delete('/configurations/users/{user}', [AdminUserController::class, 'destroy'])->name('configurations.users.destroy');

        Route::get('/configurations/departments', [AdminDepartmentDirectoryController::class, 'index'])->name('configurations.departments.index');
        Route::post('/configurations/departments', [AdminDepartmentDirectoryController::class, 'storeDepartment'])->name('configurations.departments.store');
        Route::patch('/configurations/departments/{department}/toggle', [AdminDepartmentDirectoryController::class, 'toggleDepartment'])->name('configurations.departments.toggle');
        Route::post('/configurations/departments/people', [AdminDepartmentDirectoryController::class, 'storePerson'])->name('configurations.departments.people.store');
        Route::patch('/configurations/departments/people/{person}/toggle', [AdminDepartmentDirectoryController::class, 'togglePerson'])->name('configurations.departments.people.toggle');
        Route::delete('/configurations/departments/people/{person}', [AdminDepartmentDirectoryController::class, 'destroyPerson'])->name('configurations.departments.people.destroy');
        Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AdminAppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointment}/status', [AdminAppointmentController::class, 'updateStatus'])->name('appointments.status');
        Route::get('/visitors', [AdminVisitorController::class, 'index'])->name('visitors.index');
        Route::get('/visitors/{visitor}', fn (VerifiedVisitor $visitor) => redirect()->route('admin.visitors.index'))->name('visitors.show');
        Route::patch('/visitors/{visitor}/checkout', [AdminVisitorController::class, 'checkout'])->name('visitors.checkout');
        Route::patch('/visitors/{visitor}', [AdminVisitorController::class, 'update'])->name('visitors.update');
        Route::delete('/visitors/{visitor}', [AdminVisitorController::class, 'destroy'])->name('visitors.destroy');
        Route::get('/visitors/{visitor}/photo', [AdminVisitorController::class, 'photo'])->name('visitors.photo');
        Route::get('/visitors/{visitor}/badge', [AdminVisitorController::class, 'badge'])->name('visitors.badge');
        Route::get('/visitors/{visitor}/back-photo', [AdminVisitorController::class, 'backPhoto'])->name('visitors.back_photo');
        Route::get('/visitors/{visitor}/selfie', [AdminVisitorController::class, 'selfie'])->name('visitors.selfie');
        Route::get('/visitors/{visitor}/return-face-checks/{faceCheck}/photo', [AdminVisitorController::class, 'returnFacePhoto'])->name('visitors.return_face_photo');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
