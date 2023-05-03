<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\services\FatoorahServices;

class FatoorahController extends Controller
{
    // inject : bring all thing in services to this class
    private $fatoorahServices1;

    public function __construct(FatoorahServices $fatoorahServices){
        $this->fatoorahServices1 = $fatoorahServices;
        // $fatoorahServices1 وخزنو ب  FatoorahServicesجاب كل شي بكلاس ال
    }

    // جبنا المعلومات تبع اليوزر من الداتا وبدنا نبعتها لبوابة الدفع
    public function payOrder()
    {
        // شركة الدفع بتعطينا لينك اليوزر بيدفع عليه
        // في طرق تانية للدفع

        // هذه البيانات من الداتا بيانات اليوزر وحسب شركة الدفع شو بتطلب
      $data = [
          'CustomerName' => 'Reeham',
          'CustomerEmail' => 'reeham@gmail.com',
          "NotificationOption" => "Lnk",   // شركة الدفع بتبعت اللينك لليوزر عالايميل مثلا او ويبسايت
          'Language' => 'en',
          "InvoiceValue" => 100,
          "DisplayCurrencyIso" => "kwd",
          'paymentMethodId' => 2,
          "MobileCountryCode" => "965",
          "CustomerMobile" => "12345678",
          "CallBackUrl" => env('CallBackUrl'),  // CallBackUrl اي بوابة ولازم ترجع برسالة تم الدفع او حدث خطأ
          "ErrorUrl" => env('ErrorUrl'),  // الرد من عنا عالسستم
        ];

      // بعتنا داتا اليوزر لشركة الدفع
      return $this->fatoorahServices1->sendPayment($data);

      // add to db table transactions : invoice id - user id : from auth

    }

    public function callBack(Request $request)
    {
        // البيانات يلي جاي من شركة الدفع paymentID
        // لو ما دفع بيرجع لل error_url
        // save the transaction to db

        dd($request);

        $data = [];
        $data['Key'] = $request->payementId;
        $data['KeyType'] = 'paymentId';

        return  $paymentData = $this->fatoorahServices1->getPaymentStatus($data);
        // search where in transaction : invoice id = $paymentData['Data]['InvoiceId]; and change the status

    }


}




