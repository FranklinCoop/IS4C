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

use COREPOS\pos\lib\TransRecord;
use COREPOS\pos\plugins\Paycards\sql\PaycardRequest;
use COREPOS\pos\plugins\Paycards\sql\PaycardResponse;
use COREPOS\pos\plugins\Paycards\xml\BetterXmlData;
use COREPOS\pos\lib\Database;

if (!class_exists("BasicCCModule")) include_once(realpath(dirname(__FILE__)."/BasicCCModule.php"));
if (!class_exists("PaycardLib")) include_once(realpath(dirname(__FILE__)."/lib/PaycardLib.php"));

if (!class_exists("AutoLoader")) include_once(realpath(dirname(__FILE__).'/../../lib/AutoLoader.php'));

//define('FD_STORE_ID','');
//define('FD_PASSWD','');
//define('FD_CERT_PATH',realpath(dirname(__FILE__).'/lib').'/fd');
//define('FD_CERT_PASSWD','');
//define('FD_KEY_PASSWD','');

/* test credentials  */
/*

*/

class RapidConnect extends BasicCCModule
{

    private $pmod;
    public function __construct()
    {
        $this->pmod = new PaycardModule();
        $this->pmod->setDialogs(new PaycardDialogs());
        $this->conf = new PaycardConf();
    }

    function handlesType($type){
        if ($type == PaycardLib::PAYCARD_TYPE_ENCRYPTED || $type == PaycardLib::PAYCARD_TYPE_CREDIT) {
            return True;
        }
        else {return False;}
    }

    function handleResponse($authResult)
    {
        switch($this->conf->get("paycard_mode")){
        case PaycardLib::PAYCARD_MODE_AUTH:
            return $this->handleResponseAuth($authResult);
        case PaycardLib::PAYCARD_MODE_VOID:
            return $this->handleResponseVoid($authResult);
        }
    }

    function entered($validate,$json)
    {
        $this->trans_pan['pan'] = $this->conf->get("paycard_PAN");
        return $this->pmod->ccEntered($this->trans_pan['pan'], $validate, $json);
    }

    function paycardVoid($transID,$laneNo=-1,$transNo=-1,$json=array())
    {
        $this->voidTrans = "";
        $this->voidRef = "";
        return $this->pmod->ccVoid($transID, $laneNo, $transNo, $json);
    }

    private function statusToCode($statusMsg)
    {
        switch (strtoupper($statusMsg)) {
            case 'APPROVED':
                return 1;
            case 'DECLINED':
            case 'FRAUD':
                return 2;
            case 'FAILED':
            case 'DUPLICATE':
                return 0;
        }

        return 4;
    }

    protected function handleResponseAuth($authResult)
    {
        //test if DataWire communication has worked.
        $rcResponse = $this->handleDatawireResponse($authResult);
        if ($rcResponse) {
            $rcXmlParse = new BetterXmlData($rcResponse);
            $rcXmlParse->xpath->registerNamespace('g', 'com/firstdata/Merchant/gmfV6.10');
            $responseCode = $rcXmlParse->query('/g:GMF/g:*/g:RespGrp/g:RespCode');
            
            $message = 'XML Parse Error';
            if ($responseCode)
                $message = $this->responseMsg($responseCode);

            $request = $this->last_request;

            $request = $this->last_request;
            $this->last_paycard_transaction_id = $request->last_paycard_transaction_id;
            $response = new PaycardResponse($request, $authResult, PaycardLib::paycard_db());

            $statusMsg = $rcXmlParse->query('/g:GMF/g:*/g:RespGrp/g:AddtlRespData');
            $responseCode = $this->statusToCode($statusMsg);
            $response->setResponseCode($responseCode);
            $resultCode = $responseCode;
            $response->setResultCode($resultCode);
            $resultMsg = $statusMsg; // already gathered above
            $response->setResultMsg($resultMsg);
            $xTransID = $rcXmlParse->query("/g:GMF/g:*Response/g:CommonGrp/g:RefNum");
            $response->setTransactionID($xTransID);
            $apprNumber = $rcXmlParse->query('/g:GMF/g:*/g:RespGrp/g:AuthID');
            $response->setApprovalNum($apprNumber);
            // valid credit transactions don't have an approval number
            $response->setValid(0);

            try {
                $response->saveResponse();
            } catch (Exception $ex) { }

            $comm = $this->pmod->commError($authResult);
            if ($comm !== false) {
                TransRecord::addcomment('');
                return $comm;
            }

            switch ($responseCode) {
                case 1: // APPROVED
                    return PaycardLib::PAYCARD_ERR_OK;
                case 2: // DECLINED
                    $this->conf->set("boxMsg",'Card Declined');
                    break;
                case 0: // ERROR
                    $texts = $rcXmlParse->query('/g:GMF/g:*/g:RespGrp/g:ErrorData');
                    $this->conf->set("boxMsg","Error: $texts");
                    break;
                case 4: //Schema Validation Error.
                    $texts = $rcXmlParse->query('/g:GMF/g:*/g:RespGrp/g:ErrorData');
                    $this->conf->set("boxMsg","Error: $texts");
                    break;
                default:
                    $this->conf->set("boxMsg","An unknown error occurred<br />at the gateway");
            }

        }
        /*
        $request = $this->last_request;
        $this->last_paycard_transaction_id = $request->last_paycard_transaction_id;
        $response = new PaycardResponse($request, $authResult, PaycardLib::paycard_db());

        $statusMsg = $xml->get("fdggwsapi:TransactionResult");
        $responseCode = $this->statusToCode($statusMsg);
        $response->setResponseCode($responseCode);
        // aren't two separate codes from goemerchant
        $resultCode = $responseCode;
        $response->setResultCode($resultCode);
        $resultMsg = $statusMsg; // already gathered above
        $response->setResultMsg($resultMsg);
        $xTransID = $xml->get("fdggwsapi:ProcessorReferenceNumber");
        $response->setTransactionID($xTransID);
        $apprNumber = $xml->get("fdggwsapi:ApprovalCode");
        $response->setApprovalNum($apprNumber);
        // valid credit transactions don't have an approval number
        $response->setValid(0);

        try {
            $response->saveResponse();
        } catch (Exception $ex) { }

        $comm = $this->pmod->commError($authResult);
        if ($comm !== false) {
            TransRecord::addcomment('');
            return $comm;
        }

        switch ($responseCode) {
            case 1: // APPROVED
                return PaycardLib::PAYCARD_ERR_OK;
            case 2: // DECLINED
                $this->conf->set("boxMsg",'Card Declined');
                break;
            case 0: // ERROR
                $texts = $xml->get_first("fdggwsapi:ProcessorResponseMessage");
                $this->conf->set("boxMsg","Error: $texts");
                break;
            default:
                $this->conf->set("boxMsg","An unknown error occurred<br />at the gateway");
        }

        */
        return PaycardLib::PAYCARD_ERR_PROC;
    }

    protected function handleResponseVoid($authResult){
        throw new Exception('Void not implemented');
    }

    function cleanup($json=array())
    {
        switch($this->conf->get("paycard_mode")){
        case PaycardLib::PAYCARD_MODE_AUTH:
            // cast to string. tender function expects string input
            // numeric input screws up parsing on negative values > $0.99
            $amt = "".(-1*($this->conf->get("paycard_amount")));
            $tType = 'CC';
            if ($this->conf->get('paycard_issuer') == 'American Express')
                $tType = 'AX';
            // if the transaction has a non-zero PaycardTransactionID,
            // include it in the tender line
            $recordID = $this->last_paycard_transaction_id;
            $charflag = ($recordID != 0) ? 'PT' : '';
            TransRecord::addFlaggedTender("Credit Card", $tType, $amt, $recordID, $charflag);
            $this->conf->set("boxMsg","<b>Approved</b><font size=-1><p>Please verify cardholder signature<p>[enter] to continue<br>\"rp\" to reprint slip<br>[void] to cancel and void</font>");
            if ($this->conf->get("paycard_amount") <= $this->conf->get("CCSigLimit") && $this->conf->get("paycard_amount") >= 0){
                $this->conf->set("boxMsg","<b>Approved</b><font size=-1><p>No signature required<p>[enter] to continue<br>[void] to cancel and void</font>");
            }
            break;
        case PaycardLib::PAYCARD_MODE_VOID:
            $void = new COREPOS\pos\parser\parse\Void();
            $void->voidid($this->conf->get("paycard_id"), array());
            $this->conf->set("boxMsg","<b>Voided</b><p><font size=-1>[enter] to continue<br>\"rp\" to reprint slip</font>");
            break;
        }
        if ($this->conf->get("paycard_amount") > $this->conf->get("CCSigLimit") || $this->conf->get("paycard_amount") < 0)
            $json['receipt'] = "ccSlip";
        return $json;
    }

    function doSend($type)
    {
        switch($type){
        case PaycardLib::PAYCARD_MODE_AUTH:
            return $this->sendAuth();
        case PaycardLib::PAYCARD_MODE_VOID:
            return $this->sendVoid();
        default:
            $this->conf->reset();
            return $this->setErrorMsg(0);
        }
    }

    protected function sendAuth()
    {
        $dbTrans = PaycardLib::paycard_db();
        if( !$dbTrans){
            $this->conf->reset();
            return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_NOSEND); // database error, nothing sent (ok to retry)
        }

        $request = new PaycardRequest($this->refnum($this->conf->get('paycard_id')), $dbTrans);
        $request->setProcessor('RapidConnect');
        $mode = 'sale';
        $this->trans_pan['pan'] = $this->conf->get("paycard_PAN");
        $cardPAN = $this->trans_pan['pan'];
        $cardExM = substr($this->conf->get("paycard_exp"),0,2);
        $cardExY = substr($this->conf->get("paycard_exp"),2,2);
        $cardTr1 = $this->conf->get("paycard_tr1");
        $cardTr2 = $this->conf->get("paycard_tr2");
        $request->setCardholder($this->conf->get("paycard_name"));
        $amount = $this->conf->get("paycard_amount")*100;

        if ($this->conf->get("training") == 1){
            $cardPAN = "4111111111111111";
            $cardTr1 = $cardTr2 = false;
            $request->setCardholder("Just Testing");
            $nextyear = mktime(0,0,0,date("m"),date("d"),date("Y")+1);
            $cardExM = date("m",$nextyear);
            $cardExY = date("y",$nextyear);
        }
        $request->setPAN($cardPAN);
        $request->setIssuer($this->conf->get("paycard_issuer"));

        $sendPAN = 0;
        $sendExp = 0;
        $sendTr1 = 0;
        $sendTr2 = 0;
        $magstripe = "";
        if (!$cardTr1 && !$cardTr2){
            $sendPAN = 1;
            $sendExp = 1;
        }
        if ($cardTr1) {
            $sendTr1 = 1;
            $magstripe .= "%".$cardTr1."?";
        }
        if ($cardTr2){
            $sendTr2 = 1;
            $magstripe .= ";".$cardTr2."?";
        }
        $request->setSent($sendPAN, $sendExp, $sendTr1, $sendTr2);

        try {
            $request->saveRequest();
        } catch (Exception $ex) {
            $this->conf->reset();
            return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_NOSEND); // internal error, nothing sent (ok to retry)
        }

        $this->last_request = $request;

        //get rapid connect xml packet.
        $msgXML = $this->authXML($request, $amount, $cardTr2);

        $this->GATEWAY = $this->conf->get('DataWireURL1');

        $rcXML = mb_convert_encoding($msgXML, 'utf-8', mb_detect_encoding($string));

        $rcXML = $this->xml_escape($rcXML);
        // if you have not escaped entities use
        //$rcXML = mb_convert_encoding($rcXML, 'HTML-ENTITIES', 'utf-8'); 

        $transArmorToken = $this->sendTransArmorTokenRequest($request);

        //XML request for datawire, contains the rappid connect request.
        $dwXML = $this->dataWireXML($rcXML);
        $headers = $this->dataWireHeaders($dwXML);

        $extraCurlSetup = array(
            //CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            //CURLOPT_USERPWD => "WS".FD_STORE_ID."._.1:".FD_PASSWD,
            //CURLOPT_SSLCERT => FD_CERT_PATH."/WS".FD_STORE_ID."._.1.pem",
            //CURLOPT_SSLKEY => FD_CERT_PATH."/WS".FD_STORE_ID."._.1.key",
            //CURLOPT_SSLKEYPASSWD => FD_KEY_PASSWD
        );

        //$soaptext = $this->soapify('', array('xml'=>$xml), '', False);
        
        $data = $this->curlSend($dwXML, 'POST', $this->GATEWAY, $extraCurlSetup, $dwXML, $headers);

        return $data;
    }

    protected function sendVoid()
    {
        throw new Exception('Void not implemented');
    }

    protected function sendTransArmorTokenRequest($request) {
        //request a Trans Armor Token from first data
        //takes the credit request and returns that token or an error.
        $tatXML = $this->transArmorTokenXML($request);
        $tatXML = $this->xml_escape($tatXML);

        //XML request for datawire, contains the rappid connect request.
        $dwXML = $this->dataWireXML($tatXML);
        $headers = $this->dataWireHeaders($dwXML);

        $data = $this->curlSend($dwXML, 'POST', $this->GATEWAY, array(), $dwXML, $headers);

        $tokenResponse = $this->handleDatawireResponse($data);
        if ($tokenResponse) {
            $tokenResponse = $xml->query('/r:Response/r:TransactionResponse/r:Payload');
            $tokenXML = new BetterXmlData($tokenResponse);
            $rcXmlParse->xpath->registerNamespace('g', 'com/firstdata/Merchant/gmfV6.10');
            $responseCode = $rcXmlParse->query('/g:GMF/g:*/g:RespGrp/g:RespCode');
        } else {return $data;}


        return 'Token Should Be Good';
    }

    protected function handleDatawireResponse($dwPacket) {
        $xml = new BetterXmlData($dwPacket['response']);
        $xml->xpath->registerNamespace('r', 'http://securetransport.dw/rcservice/xml');
        $statusCode = $xml->query("//@StatusCode");
        $dwRetCode = $xml->query('/r:Response/r:TransactionResponse/r:ReturnCode');

        //test if DataWire communication has worked.
        if ($statusCode == 'OK' && $dwRetCode == '000') {
            $rcResponse = $xml->query('/r:Response/r:TransactionResponse/r:Payload');
            return $rcResponse;
        } elseif ($dwRetCode) {
            $dwError = $this->datawireRetCode($dwRetCode);
            $this->conf->set("boxMsg",$dwError['errText']);
            return false;
        } else {
            $error = $dwPacket['curlErr'].': '.$dwPacket['curlErrText'].' : '.$dwPacket['curlHTTP']
                .'\n'.$authResult['response'];
            $this->conf->set("boxMsg",$error);
            return false;
        }

        return false;
    }

    public function refnum($transID)
    {
        //needs to be a 22 digit number for First Data/Rapid Connect
        $transNo   = (int)$this->conf->get("transno");
        $cashierNo = (int)$this->conf->get("CashierNo");
        $laneNo    = (int)$this->conf->get("laneno");

        // assemble string
        $ref = "";
        $ref .= date("ymdHi");
        //$ref .= "-";
        $ref .= str_pad($cashierNo, 3, "0", STR_PAD_LEFT);
        $ref .= str_pad($laneNo,    2, "0", STR_PAD_LEFT);
        $ref .= str_pad($transNo,   3, "0", STR_PAD_LEFT);
        $ref .= str_pad($transID,   3, "0", STR_PAD_LEFT);
        return $ref;
    }

    public function STAN() {
        //get the current STAN
        $STAN = $this->conf->get('RapidConnectSTAN');
        if ($STAN==999999) {
            $STAN = 1;
        } else {
            $STAN++;    
        }
        $this->conf->set('RapidConnectSTAN', $STAN);
        $dbc = Database::pDataConnect();

        $query = 'UPDATE parameters SET param_value = "RapidConnectSTAN" WHERE param_key ="'.$STAN.'"';
        $response = $dbc->query($query);

        return str_pad($STAN,6,"0", STR_PAD_LEFT);
    }

    protected function dataWireXML($xml_payload) {
        /*
        <?xml version="1.0" encoding="UTF-8"?>
        <Request Version="3" ClientTimeout="Sample_TimeoutValue" xmlns="http://securetransport.dw/rcservice/xml">
        <ReqClientID>
        <DID>Sample_DID</DID>
        <App>Sample_AppName</App>
        <Auth>Sample_AuthKey1|Sample_AuthKey2</Auth>
        <ClientRef>Sample_ClientRef</ClientRef>
        </ReqClientID>
        <Transaction>
        <ServiceID>Sample_ServiceID</ServiceID>
        <Payload Encoding="xml_escape">&lt;RC&gt;Hello, A &amp; B&lt;/RC&gt;</Payload> </Transaction>
        </Request>
        */
        //DataWire XML info.
        $AppID = $this->conf->get('DataWireAppID');
        $AuthKey1 = $this->conf->get('RapidConnectGID').$this->conf->get('RapidConnectMID');
        $AuthKey2 = $this->conf->get('RapidConnedTID');
        $ClientRef = "0000001V".$this->conf->get('RapidConnectID');
        $ServiceID = $this->conf->get('DataWireServiceID');
        $DataWireDID = $this->conf->get('DataWireDID');

        $retXML = '<?xml version="1.0" encoding="UTF-8"?>';
        $retXML .= '<Request Version="3" ClientTimeout="30" xmlns="http://securetransport.dw/rcservice/xml">';
            $retXML .= '<ReqClientID>';
                $retXML .= '<DID>'.$DataWireDID.'</DID>';
                $retXML .= '<App>'.$AppID.'</App>';
                $retXML .= '<Auth>'.$AuthKey1.'|'.$AuthKey2.'</Auth>';
                $retXML .= '<ClientRef>'.$ClientRef.'</ClientRef>';
            $retXML .= '</ReqClientID>';
            $retXML .= '<Transaction>';
                $retXML .= '<ServiceID>'.$ServiceID.'</ServiceID>';
                $retXML .= '<Payload Encoding="xml_escape">'.$xml_payload.'</Payload>';
            $retXML .= '</Transaction>';
        $retXML .= '</Request>';

        return $retXML;
    }

    protected function dataWireHeaders ($dwXML) {
        /*
        POST /xyz HTTP/1.1
        User-Agent: AppName_VersionNumber
        Host: Sample_URL (URL provided by securetransport.integration@firstdata.com)
        Connection: Keep-Alive
        Cache-Control: no-cache
        Content-Length: length_of_body (actual length of HTTP message body)
        Content-Type: text/xml
        */
        $DataWireURL = $this->conf->get('DataWireURL1');
        $host = explode("/", $DataWireURL)[2];

        $headers = array();
        $headers[] = 'User-Agent: IS4C v 2.2';
        $headers[] = 'Host: '.$host;
        $headers[] = 'Connection: Keep-Alive';
        $headers[] = 'Cache-Control: no-cache';
        $headers[] = 'Content-Length: '.strlen($dwXML);
        $headers[] = 'Content-Type: text/xml';
        return $headers;
    }
    
    protected function xml_escape($xmlString) {
        //xml escape encoding.
        $xmlString = str_replace('&', '&amp;', $xmlString);
        $xmlString = str_replace('<', '&lt;', $xmlString);
        $xmlString = str_replace('>', '&gt;', $xmlString);

        return $xmlString;
    }

    protected function xml_unescape($xmlString) {
        //xml escape decoding.
        $xmlString = str_replace('&lt;', '<', $xmlString);
        $xmlString = str_replace('&gt;', '>', $xmlString);
        $xmlString = str_replace('&amp;', '&', $xmlString);

        //$xmlString = substr_replace($xmlString, "", -1);
        //$xmlString = substr($xmlString, 0, -1);

        return $xmlString;
    }

    protected $SOAP_ENVELOPE_ATTRS = array(
        "xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\""
        );

    /** FirstData has a signficantly different SOAP format
        so the parent implementation is overriden
      @param $action top level tag in the soap body
      @param $objs keyed array of values
      @param $namespace include an xmlns attribute
      @return soap string
    */
    protected function soapify($action,$objs,$namespace="",$encode_tags=True){
        $ret = "<?xml version=\"1.0\"?>
            <SOAP-ENV:Envelope";
        foreach ($this->SOAP_ENVELOPE_ATTRS as $attr){
            $ret .= " ".$attr;
        }
        $ret .= ">
            <SOAP-ENV:Header />
            <SOAP-ENV:Body>";
        foreach($objs as $xml)
            $ret .= $xml;
        $ret .= "</SOAP-ENV:Body>
            </SOAP-ENV:Envelope>";

        return $ret;
    }

    protected function responseMsg($respCode) {
        switch ($respCode) {
            case '000': return "Approval"; break;
            case '001': return "Schema Validation Error"; break;
            case '002': return "Approve for partial amount";  break;
            case '003': return "Approve VIP";  break;
            case '004': return "Schema Validation Error"; break;
            case '100': return "Do not honor";  break;
            case '101': return "Expired card";  break;
            case '102': return "Suspected fraud";  break;
            case '104': return "Restricted card";  break;
            case '105': return "Call acquirer’s security department";  break;
            case '106': return "Allowable PIN tries exceeded";  break;
            case '107': return "Call for authorization";  break;
            case '108': return "Refer to issuer’s special conditions";  break;
            case '109': return "Invalid merchant. The merchant is not in the merchant database or the merchant is not permitted to use this particular card"; break;
            case '110': return "Invalid amount";  break;
            case '114': return "Invalid account type";  break;
            case '116': return "Not sufficient funds";  break;
            case '117': return "Incorrect PIN";  break;
            case '118': return "No card record"; break;
            case '119': return "Transaction not permitted to cardholder"; break;
            default: return "Error: Unknown response code from processor";
        }
    }

    protected function datawireRetCode ($retCode) {
        switch ($retCode) {
            case '006':
                return array('errText'=>'Invalid Session', 'retry'=>0); break;
            case '200':
                return array('errText'=>'Host Busy', 'retry'=>1); break;
            case '201':
                return array('errText'=>'Host Unavaiable', 'retry'=>1); break;
            case '202':
                return array('errText'=>'Host Connect Error', 'retry'=>1); break;
            case '203':
                return array('errText'=>'Host Drop', 'retry'=>1); break;
            case '204':
                return array('errText'=>'Host Comm Error', 'retry'=>0); break;
            case '205':
                return array('errText'=>'No Response', 'retry'=>1); break;
            case '206':
                return array('errText'=>'Host Send Error', 'retry'=>1); break;
            case '405':
                return array('errText'=>'Datawire Network Timeout', 'retry'=>1); break;
            case '505':
                return array('errText'=>'Network Error', 'retry'=>1); break;
            case '008':
                return array('errText'=>'Network Error', 'retry'=>1); break;
            default:
                return array('errText'=>'Error: No retCode', 'retry'=>0); break;
        }
    }

    protected function authXML($request, $amount, $cardTr2) {
                //xml request for rappid connect. needs to be wraped in the datawire xml.
        $msgXml = '<?xml version="1.0" encoding="UTF-8"?>';
        $msgXml .= '<GMF xmlns="com/firstdata/Merchant/gmfV6.10">';
        $msgXml .= "<".$request->type."Request>";
            $msgXml .= "<CommonGrp>";
                $msgXml .= "<PymtType>".$request->type."</PymtType>";
                $msgXml .= "<TxnType>Authorization</TxnType>";
                $msgXml .= "<LocalDateTime>".date("YmdHis")."</LocalDateTime>";
                $msgXml .= "<TrnmsnDateTime>".gmdate("YmdHis")."</TrnmsnDateTime>";
                $msgXml .= "<STAN>".$this->STAN()."</STAN>"; //needs value System Trace Audit Number (STAN) Assigned by us should be unique 6 long
                $msgXml .= "<RefNum>".mb_substr($request->refNum,0,12)."</RefNum>"; //22 length 12 bytes
                $msgXml .= "<OrderNum>".mb_substr($request->refNum,0,15)."</OrderNum>"; //length 15 or 8 bytes, numaric unique
                $msgXml .= "<TPPID>".$this->conf->get('RapidConnectID')."</TPPID>"; //TTPID
                $msgXml .= "<TermID>00000002</TermID>"; //TID from rapid connect (00000001 default)
                $msgXml .= "<MerchID>".$this->conf->get('RapidConnectMID')."</MerchID>"; //Merchant ID
                $msgXml .= "<MerchCatCode>5399</MerchCatCode>";
                $msgXml .= "<POSEntryMode>901</POSEntryMode>";
                $msgXml .= "<POSCondCode>00</POSCondCode>";
                $msgXml .= "<TermCatCode>01</TermCatCode>";
                $msgXml .= "<TermEntryCapablt>01</TermEntryCapablt>";
                $msgXml .= "<TxnAmt>".str_pad($amount, 10, '0', STR_PAD_LEFT)."</TxnAmt>"; //Amount of the transaction.
                $msgXml .= "<TxnCrncy>840</TxnCrncy>";
                $msgXml .= "<TermLocInd>0</TermLocInd>";
                $msgXml .= "<CardCaptCap>1</CardCaptCap>";
                $msgXml .= "<GroupID>".$this->conf->get('RapidConnectGID')."</GroupID>";
            $msgXml .= "</CommonGrp>";
            $msgXml .= "<CardGrp>";
                $msgXml .= "<Track2Data>".$cardTr2."</Track2Data>";
                $msgXml .= "<CardType>".$this->conf->get("paycard_issuer")."</CardType>"; //Card Type, looks like we store it as issuer (visa, mastercard, etc)
            $msgXml .= "</CardGrp>";
            $msgXml .= "<AddtlAmtGrp>";
                $msgXml .= "<PartAuthrztnApprvlCapablt>1</PartAuthrztnApprvlCapablt>";
            $msgXml .= "</AddtlAmtGrp>";
            //$msgXml .= "<VisaGrp>";
            //    $msgXml .= "<ACI>Y</ACI>";
            //    $msgXml .= "<VisaBID>56412</VisaBID>";
            //    $msgXml .= "<VisaAUAR>000000000000</VisaAUAR>"; //always 000000000000 unless assigned by VISA?
            //    $msgXml .= "<TaxAmtCapablt>1</TaxAmtCapablt>";
            //$msgXml .= "</VisaGrp>";
        $msgXml .= "</".$request->type."Request>";
        $msgXml .= "</GMF>";
        return $msgXml;
    }

    protected function transArmorTokenXML($request) {
        $msgXml = '<?xml version="1.0" encoding="UTF-8"?>';
        $msgXml .= '<GMF xmlns="com/firstdata/Merchant/gmfV6.10">';
            $msgXml .= '<TransArmorRequest>';
                $msgXml .= '<CommonGrp>';
                    $msgXml .= '<PymtType>'.$request->type.'</PymtType>';
                    $msgXml .= '<TxnType>TATokenRequest</TxnType>';
                    $msgXml .= '<LocalDateTime>'.date("YmdHis").'</LocalDateTime>';
                    $msgXml .= '<TrnmsnDateTime>'.gmdate("YmdHis").'</TrnmsnDateTime>';
                    $msgXml .= '<STAN>'.$this->STAN().'</STAN>';
                    $msgXml .= '<RefNum>'.mb_substr($request->refNum,0,12).'</RefNum>';
                    $msgXml .= '<TPPID>'.$this->conf->get('RapidConnectID').'</TPPID>';
                    $msgXml .= '<TermID>00000002</TermID>';
                    $msgXml .= '<MerchID>'.$this->conf->get('RapidConnectMID').'</MerchID>';
                    $msgXml .= '<GroupID>'.$this->conf->get('RapidConnectGID').'</GroupID>';
                $msgXml .= '</CommonGrp>';
                $msgXml .= '<TAGrp>';
                    $msgXml .= '<SctyLvl>Tknizatn</SctyLvl>';
                    //$msgXml .= '<EncrptBlock>'.'some value'.'</EncrptBlock>';
                    //$msgXml .= '<TknType>'.'some value'.'</TknType>';
                $msgXml .= '</TAGrp>';
            $msgXml .= '</TransArmorRequest>';
        $msgXml .= '</GMF>';
        return $msgXml;
    }
}
