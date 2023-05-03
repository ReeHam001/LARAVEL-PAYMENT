<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/home', 'HomeController@index')->name('home');

Route::post('comment', 'HomeController@saveComment')->name('comment.save');

################Begin paymentGateways Routes ########################


Route::get('offers', [App\Http\Controllers\OfferController::class,'index'])->name('offers.all');
Route::get('details/{offer_id}', [App\Http\Controllers\OfferController::class,'show'])->name('offers.show');
Route::get('get-checkout-id', [App\Http\Controllers\PaymentProviderController::class,'getCheckOutId'])->name('offers.checkout');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');


//paypal payment
Route::get('go-payment', [App\Http\Controllers\PayPalController::class, 'goPayment'])->name('payment.go'); //

Route::get('payment',[App\Http\Controllers\PayPalController::class, 'payment'])->name('payment'); // بودينا لصفحة الدفع
Route::get('cancel',[App\Http\Controllers\PayPalController::class, 'cancel'])->name('payment.cancel'); // الغاء الدفع
Route::get('payment/success', [App\Http\Controllers\PayPalController::class, 'success'])->name('payment.success'); // نجاح عملية الدفع
