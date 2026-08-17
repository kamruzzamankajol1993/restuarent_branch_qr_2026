<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontController;




Route::get('/home_page/{table_id}', [FrontController::class, 'index'])->name('qr.home');


Route::post('/submit-review', [App\Http\Controllers\FrontController::class, 'submitReview'])->name('review.submit');
Route::get('/menu_page/{table_id}', [FrontController::class, 'menu'])->name('qr.menu');
Route::get('/cart_page/{table_id}', [FrontController::class, 'cart'])->name('qr.cart');
Route::get('/order_page/{table_id}', [FrontController::class, 'order'])->name('qr.orders');

Route::get('/home_page/feedback/{token}', [FrontController::class, 'feedbackForm'])->name('feedback.form');
Route::post('/feedback/{token}', [FrontController::class, 'submitFeedback'])->name('feedback.submit');
Route::get('/category_wise_products/{category_id}/{table_id}', [FrontController::class, 'categoryWiseProducts'])->name('qr.category');

Route::post('/add-to-cart', [FrontController::class, 'addToCart']);
Route::post('/update-cart-qty', [FrontController::class, 'updateCartQty']);
Route::post('/checkout-web', [FrontController::class, 'checkout']);
Route::post('/call-waiter', [FrontController::class, 'callWaiter']);

Route::get('/clear', function() {
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('config:cache');
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    return redirect()->back();
});
