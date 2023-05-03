<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Srmklive\PayPal\Services\ExpressCheckout;

class PayPalController extends Controller
{

    public function goPayment(){

       return view('products.welcome');

    }
    public function payment()
    {
        $data = [];
        $data['items'] = [  // معلومات الشراء
            [
                'name' => 'Apple',
                'price' => 100,
                'desc'  => 'Macbook pro 14 inch',
                'qty' => 1
            ]
        ];

        $data['invoice_id'] = 1;
        $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
        $data['return_url'] = route('payment.success');
        $data['cancel_url'] = route('payment.cancel');
        $data['total'] = 100;

        $provider = new ExpressCheckout;

        $response = $provider->setExpressCheckout($data);

        $response = $provider->setExpressCheckout($data, true);

        return redirect($response['paypal_link']);
    }

    public function cancel()
    {
        dd('Your payment is canceled.');
    }

    public function success(Request $request) // التاكد من نجاح عملية الدفع
    {
        $provider = new ExpressCheckout;
        $response = $provider->getExpressCheckoutDetails($request->token); // الرد من البيبال

        if (in_array(strtoupper($response['ACK']), ['SUCCESS', 'SUCCESSWITHWARNING'])) { // ACK معلومة
            dd('Your payment was successfully.');
        }

        dd('Please try again later.');
    }
}
