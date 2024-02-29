<?php

/**
 * WhatsApp notification hook
 *
 * @package    WhatsApp
 * @author     Indra Hartawan <emailme@indrahartawan.com>
 * @modify     origrata       <origrata@ioscloud.co.id>
 * @copyright  Copyright (c) Indra Hartawan
 * @license    MIT License
 * @version    $Id$
 * @link       https://github.com/origrata/whmcs-hook-whatsapp
 */
if (!defined("WHMCS"))
   die("This file cannot be accessed directly");

function whatsapp_log($log_message)
{
   $today = date("M d, Y H:i:s");
   $txt = $today . " : " . $log_message ;
   $writelog = file_put_contents(dirname(__FILE__) ."/whatsapp.log", $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
}

function get_wa_config($configfile)
{
    $json = file_get_contents(dirname(__FILE__) ."/". $configfile);
    $config = json_decode($json, true);
    if ($config["debug"]) {
       whatsapp_log("Get configuration.");
    }
    return $config;
}

function get_client_details($clientid)
{
    //get configuration veriables
    $configvars = get_wa_config("whatsapp.json");
    $client = array();
    $command = "getclientsdetails";
    $adminuser = $configvars["adminuser"];
    $values["clientid"] = $clientid;
    $values["stats"] = false;
    $results = localAPI($command, $values, $adminuser);
    $parser = xml_parser_create();
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, $results, $values, $tags);
    xml_parser_free($parser);
    $client = $results;
    $client["token"] = $configvars["whatsapp_api_token"];
    $client["api_url"] = $configvars["whatsapp_api_url"];
    $client["debug"] = $configvars["debug"];
    if ($client["debug"]) {
       whatsapp_log("Get client details");
    }
    return $client;
}

function send_whatsapp($phone_no,$token,$message,$api_url,$debug){
    $domain = gethostname();
    $data = array(
        'Phone' => $phone_no,
        'Body' =>  $message);
    $bodys = json_encode($data);
    $curl = curl_init();
    curl_setopt_array($curl, array(
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $bodys,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Accept:  application/json",
                "token:".$token
            ),
        ));

        $response = curl_exec($curl);
        $err = json_decode(curl_error($curl), true);
        curl_close ($curl);
        if ($err) {
           whatsapp_log("cURL Error : " . $err);
        }
       // echo "RESPONSE SEND WA : ".$response."<br/>";
        return $response;
}

function whatsapp_new_order($vars) {
   if (isset($_SESSION['uid']) && intval($_SESSION['uid'])!==0){
      $clientdetails = get_client_details($_SESSION['uid']);
      if ($clientdetails['result'] == "success" ) {
      date_default_timezone_set("Asia/Jakarta");
      $today = date("M d, Y H:i:s");
      $firstname = $clientdetails['firstname'];
      $yr_phone_no = $clientdetails['phonenumber'];
      $yr_phone_no = preg_replace ("/^8/","08", $yr_phone_no);
      $phone_no = $yr_phone_no;
      $orderid = $vars['OrderID'];
      $ordernumber = $vars['OrderNumber'];
      $invoiceid = $vars['InvoiceID'];
      $amount = "Rp. ". number_format($vars['TotalDue'],2);
      //define payment method based on the database to make it friendly. Add yourself based on the value in the 'PaymentMethod'
      switch($vars['PaymentMethod']) {
          case "va_mandiri":
               $paymentmethod = "VA Bank Mandiri";
               break;
          default:
               $paymentmethod = $vars['PaymentMethod'];
               break;
      }
       $message = "Hi ". $firstname . ",

Ini adalah pesan otomatis. Terima kasih telah melakukan pemesanan di Ioscloud Indonesia pada ". $today .".

*Berikut detail pemesanan Anda :*
Nomor pemesanan : ". $ordernumber ."
Metode pembayaran : ". $paymentmethod ."
Total Pembelian : ". $amount ."
Lihat Invoice : https://my.ioscloud.co.id/viewinvoice.php?id=". $invoiceid ."
(harap login ke client area untuk melihat invoice dan melakukan pembayaran)

Pertanyaan soal pembayaran di ioscloud:
https://www.ioscloud.co.id/pembayaran

Promo terbaik ioscloud:
https://www.ioscloud.co.id/promo

Jika mengalami kendala bisa menghubungi kami via chat di nomor official berikut:
origrata    ---> 081268895343
henri ilham --->
syaflan     --->

_Silahkan abaikan pesan ini jika pembayaran Anda telah diselesaikan._
Terima kasih.";

        $phone_no = preg_replace( "/(\n)/", ",", $phone_no );
        $phone_no = preg_replace( "/(\r)/", "", $phone_no );
        $api_url = $clientdetails['api_url'];
        $token = $clientdetails['token'];
        if ($clientdetails['debug']){
           $msg = "phone_no -> " . $phone_no . " | token -> " . $token . "  | api_url -> " . $api_url;
           whatsapp_log($msg);
        }
        $result = send_whatsapp($phone_no,$token,$message,$api_url,$clientdetails['debug']);
        if ($clientdetails['debug']){ whatsapp_log("Message successfully sent to " . $phone_no . ""); }
     }
  }

}
add_hook("AfterShoppingCartCheckout",999,"whatsapp_new_order");
?>
