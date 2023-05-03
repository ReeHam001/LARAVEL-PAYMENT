<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// client request
Route::post('pay',[App\Http\Controllers\FatoorahController::class,'payOrder']);  // post in postman get in web
Route::get('call_back', [App\Http\Controllers\FatoorahController::class, 'callBack']);  // endpoint - callback
// Route::get('call_back', [FatoorahController::class, 'callBack']);


// curl
Route::post('payTest',[App\Http\Controllers\testController::class,'payOrder']);  // get in web


Route::get('CallBackSuccess',function(){  // بعد الدفع وين بدو يرجعنا
     return 'Payment Success';
});


// for mobile: we call the endpoint -> return url
