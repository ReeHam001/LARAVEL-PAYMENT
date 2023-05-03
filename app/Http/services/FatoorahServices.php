<?php

namespace App\Http\services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;

use Config;

// Guzzle or http or curl to talk to another server
class FatoorahServices {

    private $base_url;
    private $headers;
    private $request_client;


    // الاعدادات التي سنتكلم عن طريقها مع اي طرف ثالث
    // انشاء اتصال
    // نكلمها Client باي خدمة خارجية نحتاج
    public function __construct(Client $request_client)
    {
        $this->request_client = $request_client;
        $this->base_url = env('base_url');   //put in env من موقع مايفاتوره

        $this->headers = [
            'authorization' => 'Bearer ' . env('access_token'),  // لازم يكون في مسافة والا ما بيسشتغل
            'Content-Type' => 'application/json',

        ];

    }

    private function buildRequest($uri, $method, $data = []) // $uri : endpoint
    {
        // base_url لان متغير
        $request = new Request($method, $this->base_url . $uri, $this->headers); // Request : is Guzzle client

       if(!$data) // لو مافي داتا
       return false;

       $response = $this->request_client->send($request, [  //request_client  منقدر نبعت عن طريقها يعني جاهزة
        'json' => $data
       ]);

       // if($response->getStatusCode() != 200)
       // return false;

       $response = json_decode($response->getBody(), true);

       return $response;

    }


    // talk to payment company - we use Guzzle http
    public function sendPayment($data){
        return $response = $this->buildRequest('v2/SendPayment', 'POST', $data);
    }

    public function getPaymentStatus($data)  // webhook لانه ما عنا
    {
        return $response = $this->buildRequest('v2/getPaymentStatus', 'POST', $data);
    }

}

/*
{
    "IsSuccess": true,
    "Message": "Invoice Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 1373190,
        "InvoiceURL": "https://demo.MyFatoorah.com/KWT/ie/01072137319041",   هذا الرابط بدنا نبعتو للموبايل
        "CustomerReference": null,
        "UserDefinedField": null
    }
}

*/
