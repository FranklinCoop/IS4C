<?php
/*******************************************************************************

    Copyright 2016 Franklin Community Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

use COREPOS\pos\lib\DisplayLib;
use COREPOS\pos\plugins\Paycards\xml\BetterXmlData;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\CoreState;
use COREPOS\pos\lib\Database;

if (!class_exists('AutoLoader')) include(dirname(__FILE__).'/../../../lib/AutoLoader.php');
AutoLoader::loadMap();

$test = new DatawireOnBoarding();
$test->discoveryRequest();

class DatawireOnBoarding
{
  /*
    To use First Data RapitConnect over Datawire we need to complate the one
    time on boarding procidure and save our Datawire ID (DID) and the URL to
    send requests to.
  */
  protected $GATEWAY;
  protected $SOAPACTION = '';
  //protected $AuthKey1 = 'ERROR';
  //protected $AuthKey2 = 'ERROR';
  //protected $AppID = 'ERROR';
  //protected $ClientRef ='ERROR';
  //protected $ServiceID = 'ERROR';

  public function __construct()
  {
        $this->conf = new PaycardConf();
  }

  function discoveryRequest(){
    /*
    GET /sd/srsxml.rc HTTP/1.1
    User-Agent: IS4C v 2.2
    Host: stg.dw.us.fdcnet.biz
    Cache-Control: no-cache
    Connection: close
    */
    //$type ='GET';
    //$data="https://stg.dw.us.fdcnet.biz/sd/srsxml.rc";
    //$extraOpts = new array(
    //    "User-Agent" => "IS4C v 2.2",
    //    "Host" => "stg.dw.us.fdcnet.biz",
    //    "Cache-Control" => "no-cache"
    //);
    $AppID = $this->conf->get('DataWireAppID');
    $AuthKey1 = $this->conf->get('RapidConnectGID').$this->conf->get('RapidConnectMID');
    $AuthKey2 = $this->conf->get('RapidConnedTID');
    $ClientRef = "0000001V".$this->conf->get('RapidConnectID');
    $ServiceID = $this->conf->get('DataWireServiceID');

    $url = "https://stg.dw.us.fdcnet.biz/sd/srsxml.rc";

    $headers = array();
    $headers[] = "User-Agent: IS4C v 2.2";
    $headers[] = "Host: stg.dw.us.fdcnet.biz";
    $headers[] = "Cache-Control: no-cache";

    $funcReturn = $this->curl_send('xml', $url, $headers, '');
    //$funcReturn = $this->testDiscovery($url,$headers);
    $this->processDiscovery($funcReturn, $AppID, $AuthKey1, $AuthKey2, $ClientRef, $ServiceID);
  }

  function processDiscovery($data, $AppID, $AuthKey1, $AuthKey2, $ClientRef, $ServiceID) {
    /*
    <Response>
        <Status StatusCode="OK" />

        <ServiceDiscoveryResponse>
                <ServiceProvider>
                  <URL>https://stagingsupport.datawire.net/nocportal/SRS.do</URL>
                </ServiceProvider>
        </ServiceDiscoveryResponse>
    </Response>
    */
    //printf("Discovery Data: ".$data."\n");
    $response = $data['response'];
    printf("Discovery XML: ".$response."\n");

    $xmlParse = new BetterXmlData($response);
    $statusCode = $xmlParse->query("//@StatusCode");
    printf("Discovery Status:".$statusCode."\n");

    if ($statusCode=='OK') {
      $url = $xmlParse->query("/Response/ServiceDiscoveryResponse/ServiceProvider/URL");
      printf("Registration URL: ".$url."\n");
      $this->registrationRequest($url,$AppID, $AuthKey1, $AuthKey2, $ClientRef, $ServiceID);
    } else {
      printf("No Response\n");
    }
  }

  function registrationRequest($url, $AppID, $AuthKey1, $AuthKey2, $ClientRef, $ServiceID) {
    /*
     POST xyz HTTP/1.1
     Accept: text/xml, multipart/related
     Content-Type: text/xml
     User-Agent: JAX-WS RI 2.2.4-b01
     Host: provided by securetransport.integration@firstdata.com
     Connection: Keep-Alive
     Content-Length: actual_length_of_HTTP_messagebody
     <?xml version="1.0" encoding="UTF-8"?> <Request Version = "3">
      <ReqClientID>
        <DID></DID>
        <App>ApplicationID</App>
        <Auth>AuthKey1|AuthKey2</Auth>
        <ClientRef>the_ID</ClientRef>
        </ReqClientID>
      <Registration>
      <ServiceID>the_serviceID</ServiceID> </Registration>
    </Request>
    */

    $host = explode("/", $url)[2];
    printf("Registration Host: ".$host.'\n');

    $request = '<?xml version="1.0" encoding="UTF-8"?> <Request Version = "3">
          <ReqClientID>
            <DID></DID>
            <App>'.$AppID.'</App>
            <Auth>'.$AuthKey1.'|'.$AuthKey2.'</Auth>
            <ClientRef>'.$ClientRef.'</ClientRef>
            </ReqClientID>
          <Registration>
          <ServiceID>'.$ServiceID.'</ServiceID> </Registration>
        </Request>';
    $headers = array();
    $headers[] = "Accept: text/xml, multipart/related";
    $headers[] = "Content-Type: text/xml";
    $headers[] = "User-Agent: IS4C v 2.2";
    $headers[] = "Host: ".$host;//stg.dw.us.fdcnet.biz";
    $headers[] = "Connection: Keep-Alive";
    $headers[] = "Content-Length: ".strlen($request);
    //printf("Registration Request:\n");
    $this->printRequest('Registration Request:',$headers, $request);
    //printf($request."\n");
    //$data = $this->testReg($url, $headers);
    //
    //https://stagingsupport.datawire.net/nocportal/SRS.do
    //
    /*
      The Registration “Retry” Response XML Sample
      HTTP/1.1 200 OK
      Date: Thu, 05 Jun 2014 18:55:58 GMT
      Server: Apache-Coyote/1.1
      Content-Length: actual_length_of_HTTP_messagebody Content-Type: text/xml
      <?xml version="1.0" encoding="UTF-8"?> <Response Version = "3">
      <RespClientID>
       <DID />
       <ClientRef>the_ID</ClientRef>
    </RespClientID>
    <Status StatusCode = "Retry"/>
    <RegistrationResponse />
</Response>
    */
    $xmlParse = null;
    $statusCode = "Retry";
    $data = null;
    $response = '';
    $retryCount = 0;
    $statusCode ='';
    while ($statusCode != 'OK') {
      $data = $this->curl_send('POST', $url, $headers, $request);
      //$data = $this->testReg($url, $headers);
      $response = $data['response'];
      if ($response != '') {
        $xmlParse = new BetterXmlData($response);
        $statusCode = $xmlParse->query("//@StatusCode");
        if($statusCode == 'Failed')
          break;
      } else {
        printf("Error no response data.\n");
        printf('Curl Error #: '.$data['curlErr']."\n");
        printf('Curl Error Text: '.$data['curlErrText']."\n");
      }

      printf("In While, Status:".$statusCode."   Retry Count:".$retryCount."\n");
      if($retryCount == 20) {
        break; /*exit while loop*/
      } else {
        $retryCount++;
        sleep(3);
      }
    }

    //printf("Registration Data: ".$data."\n");
    printf("Registration XML: ".$response."\n");
    printf("Registration Status: ".$statusCode."\n");
    /*

The Registration Successful Response XML Sample
HTTP/1.1 200 OK
Date: Thu, 05 Jun 2014 18:55:58 GMT
Server: Apache-Coyote/1.1
Content-Length: actual_length_of_HTTP_messagebody Content-Type: text/xml
<?xml version="1.0" encoding="UTF-8"?>
<Response Version = "3">
  <RespClientID>
    <DID>Merchant_DID_forallsubsequentTransactions</DID>
    <ClientRef>the_ID</ClientRef>
  </RespClientID>
  <Status StatusCode = "OK"/>
    <RegistrationResponse>
      <DID>Merchant_DID_forallsubsequentTransactions</DID>
      <URL>tx_endpoint_url_1</URL>
      <URL>tx_endpoint_url_2</URL>
    </RegistrationResponse>
</Response>
    */

    if ($statusCode=='OK') {
      $did = $xmlParse->query("/Response/RegistrationResponse/DID");
      $urls = $xmlParse->query("/Response/RegistrationResponse/URL",true);
      $this->conf->set('DataWireDID', $did);
      printf("DID: ".$did."\n");
      $this->conf->set('DataWireURL1', $urls[0]);
      printf("URL1: ".$urls[0]."\n");
      $this->conf->set('DataWireURL2', $urls[1]);
      printf("URL2: ".$urls[1]."\n");
      //save the values to the papamter database.
      $dbc = Database::pDataConnect();
      $param_values = array($did, $urls[0], $urls[1]);
      $param_keys = array('DataWireDID', 'DataWireURL1', 'DataWireURL2');
      //$query = $dbc->prepare("UPDATE parameters SET param_value = ? WHERE param_key =?");
      for ($i=0 ; $i<=2 ; $i++) {
        $query = 'UPDATE parameters SET param_value ="'.$param_values[$i].'" WHERE param_key ="'.$param_keys[$i].'"';
        $response = $dbc->query($query);
        if ( $response === False )
          printf("\n Error: Could not save parameter data\n");
      }
      $this->activationRequest($did,$urls,$AppID, $AuthKey1, $AuthKey2, $ClientRef, $ServiceID, $host, $url);
                      //  $upQ = $dbc->prepare("INSERT INTO memContact (card_no, pref)
                    //VALUES (?, ?)");
                //$upR = $dbc->execute($upQ,array($memNum, $formPref));
         //DataWireDID = ?, DataWireURL1 = ?, DataWireURL2 =?";

    } else {
      printf("\n Error: Could not complate registration request\n");
    }
  }

  function activationRequest($did, $urls, $AppID, $AuthKey1, $AuthKey2, $ClientRef, $ServiceID, $host, $reg_url) {
    /*
HTTP/1.1 200 OK
Date: Thu, 05 Jun 2014 18:55:58 GMT
Server: Apache-Coyote/1.1
Content-Length: actual_length_of_HTTP_messagebody
Content-Type: text/xml
<?xml version="1.0" encoding="UTF-8"?> <Request Version = "3">
<ReqClientID> <DID>Merchant’s_DID_fromRegistrationResponse</DID>
            <App>ApplicationID</App>
            <Auth>AuthKey1|AuthKey2</Auth>
            <ClientRef>the_ID</ClientRef>
    </ReqClientID>
     <Activation>
<ServiceID>same_serviceIDasRegistration</ServiceID> </Activation>
</Request>
    
Date: Tue, 10 Jan 2017 18:46:26 GMT
Server: Apache-Coyote/1.1
Content-Length: 375
Content-Type: text/xml
<?xml version="1.0" encoding="UTF-8"?> <Request Version = "3">
    <ReqClientID> <DID>00011551602855605532</DID>
                <App>RAPIDCONNECTSRS</App>
                <Auth>20001RCTST0000000684|00000001</Auth>
                <ClientRef>0000001VRFR002</ClientRef>
        </ReqClientID>
         <Activation>
    <ServiceID>160</ServiceID> </Activation>
    </Request>
    */
    $request = '<?xml version="1.0" encoding="UTF-8"?> <Request Version = "3">
    <ReqClientID> <DID>'.$did.'</DID>
                <App>'.$AppID.'</App>
                <Auth>'.$AuthKey1.'|'.$AuthKey2.'</Auth>
                <ClientRef>'.$ClientRef.'</ClientRef>
        </ReqClientID>
         <Activation>
    <ServiceID>'.$ServiceID.'</ServiceID> </Activation>
    </Request>';
    $headers = array();
    $headers[] = "Date: ".gmdate("D, j M Y G:i:s T"); //Tue, 10 Jan 2017 13:44:56 EST
    $headers[] = "Server: Apache-Coyote/1.1";
    //$headers[] = "Accept: text/xml, multipart/related";
    //$headers[] = "Content-Type: text/xml";
    //$headers[] = "User-Agent: IS4C v 2.2";
    //$headers[] = "Host: stg.dw.us.fdcnet.biz";//.$host;//stg.dw.us.fdcnet.biz";
    //$headers[] = "Connection: Keep-Alive";
    $headers[] = "Content-Length: ".strlen($request);
    $headers[] = "Content-Type: text/xml";

    $data = $this->curl_send('POST', $reg_url, $headers, $request);
    //$data = $this->testActivation($urls, $headers);
    $this->printRequest('Activation Request:', $headers, $request);
    $response = $data['response'];
    printf("\nActivation Response:".$response."\n");
    $statusCode ='';

    if ($response != '') {
        $xmlParse = new BetterXmlData($response);
        $statusCode = $xmlParse->query("//@StatusCode");
      } else {
        printf("Error no activation data.\n");
        printf('Curl Error #: '.$data['curlErr']."\n");
        printf('Curl Error Text: '.$data['curlErrText']."\n");
      }

    if ($statusCode== 'OK') {
      printf("Activation Sucesseful!\n");
    } else {
      printf("Activation Falue.");
    }

    /*

The ActivationResponse XML Sample
HTTP/1.1 200 OK
Date: Thu, 05 Jun 2014 18:55:58 GMT
Server: Apache-Coyote/1.1
Content-Length: actual_length_of_HTTP_messagebody Content-Type: text/xml
<?xml version="1.0" encoding="UTF-8"?> <Response Version = "3">
    <RespClientID>
       <DID/>
       <ClientRef>the_ID</ClientRef>
    </RespClientID>
     <Status StatusCode = "OK"/>
     <ActivationResponse/>
</Response>
    */
  }

  function curl_send($type='POST',$url, $headers,$request) {
    $curlObj = curl_init($url);
    curl_setopt($curlObj, CURLOPT_HEADER, 0);
    curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curlObj, CURLOPT_CONNECTTIMEOUT,15);
    curl_setopt($curlObj, CURLOPT_FAILONERROR,false);
    curl_setopt($curlObj, CURLOPT_FOLLOWLOCATION,false);
    curl_setopt($curlObj, CURLOPT_FRESH_CONNECT,true);
    curl_setopt($curlObj, CURLOPT_TIMEOUT,30);
    curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
    //if (MiscLib::win32()) {
    //    curl_setopt($curlObj, CURLOPT_CAINFO, LOCAL_CERT_PATH);
    //}
    curl_setopt($curlObj, CURLOPT_HTTPHEADER, $headers);
    if($type=='POST') {
      curl_setopt($curlObj, CURLOPT_POSTFIELDS, $request);
      curl_setopt($curlObj, CURLOPT_POST, true);
    }
    $response = curl_exec($curlObj);

    $funcReturn = array(
        'curlErr' => curl_errno($curlObj),
        'curlErrText' => curl_error($curlObj),
        'curlTime' => curl_getinfo($curlObj,
                CURLINFO_TOTAL_TIME),
        'curlHTTP' => curl_getinfo($curlObj,
                CURLINFO_HTTP_CODE),
        'response' => $response
    );

    curl_close($curlObj);

    return $funcReturn;
  }

  function testReg ($url,$headers) {
    $testRegCount = 0;
    $ret=null;
    if ($testRegCount > 18) {
      $testRegCount++;
      printf("TestRegCount:".$testRegCount."\n");
      $ret = array(
          "OtherStuff" => "HTTP/1.1 200 OK
          Date: Thu, 05 Jun 2014 18:55:58 GMT
          Server: Apache-Coyote/1.1
          Content-Length: actual_length_of_HTTP_messagebody Content-Type: text/xml",
          "response" => "<Response Version = '3'>
            <RespClientID>
              <DID>Merchant_DID_forallsubsequentTransactions</DID>
              <ClientRef>the_ID</ClientRef>
            </RespClientID>
            <Status StatusCode = 'OK'/>
              <RegistrationResponse>
                <DID>Merchant_DID_forallsubsequentTransactions</DID>
                <URL>tx_endpoint_url_1</URL>
                <URL>tx_endpoint_url_2</URL>
              </RegistrationResponse>
          </Response>");
    } else {
      $testRegCount = 0;
      $ret = array(
          "OtherStuff" => "HTTP/1.1 200 OK
          Date: Thu, 05 Jun 2014 18:55:58 GMT
          Server: Apache-Coyote/1.1
          Content-Length: actual_length_of_HTTP_messagebody Content-Type: text/xml",
          "response" => "<Response Version = '3'>
                <RespClientID>
                  <DID>Merchant_DID_forallsubsequentTransactions</DID>
                  <ClientRef>the_ID</ClientRef>
                </RespClientID>
                <Status StatusCode = 'OK'/>
                  <RegistrationResponse>
                    <DID>Merchant_DID_forallsubsequentTransactions</DID>
                    <URL>tx_endpoint_url_1</URL>
                    <URL>tx_endpoint_url_2</URL>
                  </RegistrationResponse>
              </Response>");
    }
    return $ret;
  }

  function testActivation($url,$headers) {
    $ret = array(
        "OtherStuff" => "HTTP/1.1 200 OK
        Date: Thu, 05 Jun 2014 18:55:58 GMT
        Server: Apache-Coyote/1.1
        Content-Length: actual_length_of_HTTP_messagebody Content-Type: text/xml",
        "response" => "<Response Version = '3'>
            <RespClientID>
               <DID/>
               <ClientRef>the_ID</ClientRef>
            </RespClientID>
             <Status StatusCode = 'OK'/>
             <ActivationResponse/>
        </Response>");
    return $ret;
  }

  function testDiscovery($url,$header) {
    $ret = array(
        "OtherStuff" => "HTTP/1.1 200 OK
        Date: Thu, 05 Jun 2014 18:55:58 GMT
        Server: Apache-Coyote/1.1
        Content-Length: actual_length_of_HTTP_messagebody Content-Type: text/xml",
        "response" => "<Response>
                <Status StatusCode='OK' />

                <ServiceDiscoveryResponse>
                        <ServiceProvider>
                          <URL>https://stagingsupport.datawire.net/nocportal/SRS.do</URL>
                        </ServiceProvider>
                </ServiceDiscoveryResponse>
            </Response>");
    return $ret;
  }
  function printRequest($function_name, $headers, $request) {
    printf($function_name."\n");
    foreach ($headers as $header) {
      printf($header."\n");
    }
    printf($request."\n");
  }

  function output($data) {
    $outstr = "<body> test".$data."</body>";
    //printf($outstr."");
  }
}
