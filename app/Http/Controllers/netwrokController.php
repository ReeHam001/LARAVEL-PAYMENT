<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class netwrokController extends Controller
{
    protected function cURL($url, $json)
    {
        // Create curl resource
        $ch = curl_init($url);

        // Request headers
        $headers = array();
        $headers[] = 'Content-Type: application/json';

        // Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // $output contains the output string
        $output = curl_exec($ch);

        // Close curl resource to free up system resources
        curl_close($ch);
        return json_decode($output);
    }

    protected function GETcURL($url)
    {
        // Create curl resource
        $ch = curl_init($url);

        // Request headers
        $headers = array();
        $headers[] = 'Content-Type: application/json';

        // Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // $output contains the output string
        $output = curl_exec($ch);

        // Close curl resource to free up system resources
        curl_close($ch);
        return json_decode($output);
    }

    public function credit()
    {
        $currency_code = Currency::where(['code' => 'EGP'])->first();
        if (isset($currency_code) == false) {
            Toastr::error(translate('paymob_supports_EGP_currency'));
            return back();
        }

        $config = Helpers::get_business_settings('paymob_accept');
        try {
            $token = $this->getToken();
            $order = $this->createOrder($token);
            $paymentToken = $this->getPaymentToken($order, $token);
        }catch (\Exception $exception){
            Toastr::error(translate('country_permission_denied_or_misconfiguration'));
            return back();
        }
        return \Redirect::away('https://portal.weaccept.co/api/acceptance/iframes/' . $config['iframe_id'] . '?payment_token=' . $paymentToken);
    }

    public function getToken()
    {
        $config = Helpers::get_business_settings('paymob_accept');
        $response = $this->cURL(
            'https://accept.paymobsolutions.com/api/auth/tokens',
            ['api_key' => $config['api_key']]
        );

        return $response->token;
    }

    public function createOrder($token)
    {
        $discount = session()->has('coupon_discount') ? session('coupon_discount') : 0;
        $value = CartManager::cart_grand_total() - $discount;
        $value = Convert::usdToegp($value);

        $items = [];
        foreach (CartManager::get_cart() as $detail) {
            array_push($items, [
                'name' => $detail->product['name'],
                'amount_cents' => round(Convert::usdToegp($detail['price']),2) * 100,
                'description' => $detail->product['name'],
                'quantity' => $detail['quantity']
            ]);
        }

        $data = [
            "auth_token" => $token,
            "delivery_needed" => "false",
            "amount_cents" => round($value,2) * 100,
            "currency" => "EGP",
            "items" => $items,

        ];
        $response = $this->cURL(
            'https://accept.paymob.com/api/ecommerce/orders',
            $data
        );

        return $response;
    }

    public function getPaymentToken($order, $token)
    {
        $discount = session()->has('coupon_discount') ? session('coupon_discount') : 0;
        $value = CartManager::cart_grand_total() - $discount;
        $value = Convert::usdToegp($value);
        $user = Helpers::get_customer();

        $config = Helpers::get_business_settings('paymob_accept');
        $billingData = [
            "apartment" => "NA",
            "email" => $user['email'],
            "floor" => "NA",
            "first_name" => $user['f_name'],
            "street" => "NA",
            "building" => "NA",
            "phone_number" => $user['phone'],
            "shipping_method" => "PKG",
            "postal_code" => "NA",
            "city" => "NA",
            "country" => "NA",
            "last_name" => $user['l_name'],
            "state" => "NA",
        ];
        $data = [
            "auth_token" => $token,
            "amount_cents" => round($value,2) * 100,
            "expiration" => 3600,
            "order_id" => $order->id,
            "billing_data" => $billingData,
            "currency" => "EGP",
            "integration_id" => $config['integration_id']
        ];

        $response = $this->cURL(
            'https://accept.paymob.com/api/acceptance/payment_keys',
            $data
        );

        return $response->token;
    }

    public function callback(Request $request)
    {
        $config = Helpers::get_business_settings('paymob_accept');
        $data = $request->all();
        ksort($data);
        $hmac = $data['hmac'];
        $array = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order',
            'owner',
            'pending',
            'source_data_pan',
            'source_data_sub_type',
            'source_data_type',
            'success',
        ];
        $connectedString = '';
        foreach ($data as $key => $element) {
            if (in_array($key, $array)) {
                $connectedString .= $element;
            }
        }
        $secret = $config['hmac'];
        $hased = hash_hmac('sha512', $connectedString, $secret);
        if ($hased == $hmac) {
            $unique_id = OrderManager::gen_unique_id();
            $order_ids = [];
            foreach (CartManager::get_cart_group_ids() as $group_id) {
                $data = [
                    'payment_method' => 'paymob_accept',
                    'order_status' => 'confirmed',
                    'payment_status' => 'paid',
                    'transaction_ref' => 'tran-' . $unique_id,
                    'order_group_id' => $unique_id,
                    'cart_group_id' => $group_id
                ];
                $order_id = OrderManager::generate_order($data);
                array_push($order_ids, $order_id);
            }
            CartManager::cart_clean();
            if (auth('customer')->check()) {
                Toastr::success('Payment success.');
                return view('web-views.checkout-complete');
            }
            return response()->json(['message' => 'Payment succeeded'], 200);
        }

        if (auth('customer')->check()) {
            Toastr::error('Payment failed.');
            return redirect('/account-order');
        }
        return response()->json(['message' => 'Payment failed'], 403);
    }



















    public function payOrder()
    {

    /* ------------------------ Configurations ---------------------------------- */
    //Test
    $apiURL = 'https://apitest.myfatoorah.com';
    $apiKey = 'rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL';
    //Test token value to be placed here: https://myfatoorah.readme.io/docs/test-token



    /* ------------------------ Functions --------------------------------------- */
    /*
    * Initiate Payment Endpoint Function
    */

    function initiatePayment($apiURL, $apiKey, $postFields) {

        $json = callAPI("$apiURL/v2/InitiatePayment", $apiKey, $postFields);
        return $json->Data->PaymentMethods;
    }

    //------------------------------------------------------------------------------
    /*
    * Execute Payment Endpoint Function
    */

    function executePayment($apiURL, $apiKey, $postFields) {

        $json = callAPI("$apiURL/v2/ExecutePayment", $apiKey, $postFields);
        return $json->Data;
    }

    //------------------------------------------------------------------------------
    /*
    * Call API Endpoint Function
    */

    function callAPI($endpointURL, $apiKey, $postFields = [], $requestType = 'POST') {

        $curl = curl_init($endpointURL);
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api-gateway.sandbox.ngenius-payments.com/identity/auth/access-token",
            CURLOPT_CUSTOMREQUEST  => $requestType,
            CURLOPT_POSTFIELDS     => json_encode($postFields),
            CURLOPT_HTTPHEADER     => array(
                "accept: application/vnd.ni-identity.v1+json",
                "authorization: Basic ".$apikey,
                "content-type: application/vnd.ni-identity.v1+json"
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 1 ,
            CURLOPT_POSTFIELDS =>  "{\"realmName\":\"ni\"}"
        ));

        $response = curl_exec($curl);
        $curlErr  = curl_error($curl);

        curl_close($curl);

        if ($curlErr) {
            //Curl is not working in your server
            die("Curl Error: $curlErr");
        }

        $error = handleError($response);
        if ($error) {
            die("Error: $error");
        }

        $access_token = $response->access_token;

        return json_decode($response);
    }

    //------------------------------------------------------------------------------
    /*
    * Handle Endpoint Errors Function
    */

    function handleError($response) {

        $json = json_decode($response);
        if (isset($json->IsSuccess) && $json->IsSuccess == true) {
            return null;
        }

        //Check for the errors
        if (isset($json->ValidationErrors) || isset($json->FieldsErrors)) {
            $errorsObj = isset($json->ValidationErrors) ? $json->ValidationErrors : $json->FieldsErrors;
            $blogDatas = array_column($errorsObj, 'Error', 'Name');

            $error = implode(', ', array_map(function ($k, $v) {
                        return "$k: $v";
                    }, array_keys($blogDatas), array_values($blogDatas)));
        } else if (isset($json->Data->ErrorMessage)) {
            $error = $json->Data->ErrorMessage;
        }

        if (empty($error)) {
            $error = (isset($json->Message)) ? $json->Message : (!empty($response) ? $response : 'API key or API URL is not correct');
        }

        return $error;
        }


    /* ------------------------ Call InitiatePayment Endpoint ------------------- */
    //Fill POST fields array
    $ipPostFields = ['InvoiceAmount' => 100, 'CurrencyIso' => 'KWD'];

    //Call endpoint
    $paymentMethods = initiatePayment($apiURL, $apiKey, $ipPostFields);

    //You can save $paymentMethods information in database to be used later
    $paymentMethodId = 2;
    /*foreach ($paymentMethods as $pm) {
        if ($pm->PaymentMethodEn == 'VISA/MASTER') {
            $paymentMethodId = $pm->PaymentMethodId;
            break;
        }
    }*/

    /* ------------------------ Call ExecutePayment Endpoint -------------------- */
    //Fill customer address array
    /* $customerAddress = array(
    'Block'               => 'Blk #', //optional
    'Street'              => 'Str', //optional
    'HouseBuildingNo'     => 'Bldng #', //optional
    'Address'             => 'Addr', //optional
    'AddressInstructions' => 'More Address Instructions', //optional
    ); */

    //Fill invoice item array
    /* $invoiceItems[] = [
    'ItemName'  => 'Item Name', //ISBAN, or SKU
    'Quantity'  => '2', //Item's quantity
    'UnitPrice' => '25', //Price per item
    ]; */

    //Fill POST fields array
    $postFields = [
        //Fill required data
        'paymentMethodId' => $paymentMethodId,
        'InvoiceValue'    => '50',
        'CallBackUrl'     => 'https://example.com/callback.php',
        'ErrorUrl'        => 'https://example.com/callback.php', //or 'https://example.com/error.php'
            //Fill optional data
            //'CustomerName'       => 'fname lname',
            //'DisplayCurrencyIso' => 'KWD',
            //'MobileCountryCode'  => '+965',
            //'CustomerMobile'     => '1234567890',
            //'CustomerEmail'      => 'email@example.com',
            //'Language'           => 'en', //or 'ar'
            //'CustomerReference'  => 'orderId',
            //'CustomerCivilId'    => 'CivilId',
            //'UserDefinedField'   => 'This could be string, number, or array',
            //'ExpiryDate'         => '', //The Invoice expires after 3 days by default. Use 'Y-m-d\TH:i:s' format in the 'Asia/Kuwait' time zone.
            //'SourceInfo'         => 'Pure PHP', //For example: (Laravel/Yii API Ver2.0 integration)
            //'CustomerAddress'    => $customerAddress,
            //'InvoiceItems'       => $invoiceItems,
        ];

        //Call endpoint
        $data = executePayment($apiURL, $apiKey, $postFields);

        //You can save payment data in database as per your needs
        $invoiceId   = $data->InvoiceId;
        $paymentLink = $data->PaymentURL;

        //Redirect your customer to the payment page to complete the payment process
        //Display the payment link to your customer
        echo "Click on <a href='$paymentLink' target='_blank'>$paymentLink</a> to pay with invoiceID $invoiceId.";
        die;

    }


}
