/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

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

using System;
using System.IO;
using System.Threading;
using System.Net;
using System.Net.Sockets;
using System.Xml;
using System.Drawing;
using System.Linq;
using System.Collections;
using System.Collections.Generic;
using CustomForms;
using BitmapBPP;
using DSIEMVXLib;
using AxDSIEMVXLib;
using DSIPDCXLib;
using AxDSIPDCXLib;
using ComPort;

namespace SPH {

public class SPH_Datacap_EMVX : SerialPortHandler 
{
    private DsiEMVX emv_ax_control = null;
    private DsiPDCX pdc_ax_control = null;
    private string device_identifier = null;
    private string com_port = "0";
    private string device_ip = "";
    protected string server_list = "x1.mercurypay.com;x2.backuppay.com";
    protected int LISTEN_PORT = 8999; // acting as a Datacap stand-in
    protected short CONNECT_TIMEOUT = 60;
    protected string sequence_no = null;
    private RBA_Stub rba = null;
    private string xml_log = null;
    private bool enable_xml_log = true;
    private bool pdc_active;
    private Object pdcLock = new Object();
    private bool emv_reset;
    private bool always_reset = false;
    private bool emv_active;
    private bool reverse_servers = false;
    private Object emvLock = new Object();
    private string terminalID = "";

    public SPH_Datacap_EMVX(string p) : base(p)
    { 
        device_identifier=p;
        if (p.Contains(":")) {
            string[] parts = p.Split(new char[]{':'}, 2);
            device_identifier = parts[0];
            com_port = parts[1];
            Console.WriteLine(com_port);
            if (System.Text.RegularExpressions.Regex.IsMatch(parts[1], @"\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}")) {
                Console.WriteLine("Is an IP Address");
                com_port = "1";
                this.device_ip = parts[1];
            }
        }

        string my_location = AppDomain.CurrentDomain.BaseDirectory;
        char sep = Path.DirectorySeparatorChar;
        xml_log = my_location + sep + "log.xml";
        pdc_active = false;
        emv_active = false;
        emv_reset = true;

        if (device_identifier.Substring(0, 8) == "INGENICO") {
            rba = new RBA_Stub("COM"+com_port);
            rba.SetEMV(RbaButtons.EMV);
        }
    }

    /**
      Initialize EMVX control with servers
      and response timeout
    */
    protected bool ReInitDevice()
    {
        if (pdc_ax_control == null) {
            pdc_ax_control = new DsiPDCX();
            pdc_ax_control.ServerIPConfig(server_list, 0);
            pdc_ax_control.SetResponseTimeout(CONNECT_TIMEOUT);
            InitPDCX();
        }
        lock (pdcLock) {
            if (pdc_active) {
                Console.WriteLine("Reset PDC");
                pdc_ax_control.CancelRequest();
                pdc_active = false;
            }
        }
        lock (emvLock) {
            if (emv_active) {
                Console.WriteLine("Reset EMV");
                var type = emv_ax_control.GetType();
                if (type.GetMethod("CancelRequest") != null) {
                    emv_ax_control.CancelRequest();
                    emv_active = false;
                }
            }
        }

        if (emv_ax_control == null) {
            emv_ax_control = new DsiEMVX();
        }
        FlaggedReset();

        if (rba != null) {
            rba.SetParent(this.parent);
            rba.SetVerbose(this.verbose_mode);
            try {
                rba.stubStart();
            } catch (Exception) {}
        }

        return true;
    }

    private TcpListener GetHTTP()
    {
        TcpListener http = null;
        while (true) {
            try {
                http = new TcpListener(IPAddress.Any, LISTEN_PORT);
                http.Start();
                break;
            } catch (System.Net.Sockets.SocketException ex) {
                //this.LISTEN_PORT += 2;
                throw ex;
            }
        }

        if (http != null && this.LISTEN_PORT > 8999) {
            parent.MsgSend("SETTCP" + this.LISTEN_PORT);
        }

        return http;
    }

    /**
      Driver listens over TCP for incoming HTTP data. Driver
      is providing a web-service style endpoint so POS behavior
      does not have to change. Rather than POSTing information to
      a remote processor it POSTs information to the driver.

      Driver strips off headers, feeds XML into the dsiEMVX control,
      then sends the response back to the client.
    */
    public override void Read()
    { 
        ReInitDevice();
        RBA_Stub emailRBA = null;
        if (device_identifier.Contains("INGENICO")) {
            emailRBA = new RBA_Stub("COM"+com_port);
            emailRBA.SetParent(this.parent);
            emailRBA.SetVerbose(this.verbose_mode);
        }
        TcpListener http = this.GetHTTP();
        byte[] buffer = new byte[10];
        while (SPH_Running) {
            string result = "";
            string keyVal = "key";
            bool saveResult = false;
            try {
                using (TcpClient client = http.AcceptTcpClient()) {
                    client.ReceiveTimeout = 100;
                    using (NetworkStream stream = client.GetStream()) {
                        string message = "";
                        int bytes_read = 0;
                        do {
                            bytes_read = stream.Read(buffer, 0, buffer.Length);
                            message += System.Text.Encoding.ASCII.GetString(buffer, 0, bytes_read);
                        } while (stream.DataAvailable);

                        if (rba != null) {
                            rba.stubStop();
                        }

                        message = GetHttpBody(message);
                        message = message.Trim(new char[]{'"'});
                        try {
                            XmlDocument request = new XmlDocument();
                            request.LoadXml(message);
                            keyVal = request.SelectSingleNode("TStream/Transaction/InvoiceNo").InnerXml;
                        } catch (Exception) {
                            Console.WriteLine("Error parsing from\n" + message);
                        }
                        // Send EMV messages to EMVX, others
                        // to PDCX
                        if (message.Contains("EMV")) {
                            result = ProcessEMV(message, true);
                            saveResult = true;
                        } else if (message.Contains("termSig")) {
                            FlaggedReset();
                            result = GetSignature(true);
                        } else if (device_identifier.Contains("INGENICO") && message.Contains("termEmail")) {
                            result = emailRBA.getEmailSync();
                        } else if (message.Length > 0) {
                            result = ProcessPDC(message);
                            saveResult = true;
                        }
                        result = WrapHttpResponse(result);

                        byte[] response = System.Text.Encoding.ASCII.GetBytes(result);
                        stream.Write(response, 0, response.Length);
                        stream.Close();
                        if (message.Contains("EMV")) {
                            FlaggedReset();
                        }
                    }
                    client.Close();
                }
            } catch (Exception ex) {
                this.LogMessage(ex.ToString());
                try {
                    if (saveResult && result.Length > 0) {
                        parent.SqlLog(keyVal, result);
                    }
                } catch (Exception) {
                    this.LogMessage(keyVal + ": " + result);
                }
            }
        }
    }

    /**
      Pull HTTP body out of string. Simply looking
      for blank line between headers and body
    */
    protected string GetHttpBody(string http_request)
    {
        StringReader sr = new StringReader(http_request);
        string line;
        string ret = "";
        bool headers_over = false;
        while ((line = sr.ReadLine()) != null) {
            if (!headers_over && line == "") {
                headers_over = true;
            } else if (headers_over) {
                ret += line;
            }
        }

        return ret;
    }

    /**
      Add simple HTTP headers to content string
    */
    protected string WrapHttpResponse(string http_response)
    {
        string headers = "HTTP/1.0 200 OK\r\n"
            + "Connection: close\r\n"
            + "Content-Type: text/xml\r\n"
            + "Content-Length: " + http_response.Length + "\r\n" 
            + "Access-Control-Allow-Origin: http://localhost\r\n"
            + "\r\n"; 
        
        return headers + http_response;
    }

    public override void HandleMsg(string msg)
    { 
        // optional predicate for "termSig" message
        // predicate string is displayed on sig capture screen
        if (msg.Length > 7 && msg.Substring(0, 7) == "termSig") {
            //sig_message = msg.Substring(7);
            msg = "termSig";
        }
        if (msg.Length > 10 && msg.Substring(0, 10) == "screenLine") {
            string line = msg.Substring(10);
            msg = "IGNORE";
            if (rba != null) {
                rba.addScreenMessage(line);
            }
        }
        switch(msg) {
            case "termReset":
            case "termReboot":
                if (rba != null) {
                    rba.stubStop();
                }
                lock(emvLock) {
                    emv_reset = true;
                }
                ReInitDevice();
                break;
            case "termManual":
                break;
            case "termApproved":
                FlaggedReset();
                if (rba != null) {
                    rba.showMessage("Approved");
                }
                break;
            case "termDeclined":
                if (rba != null) {
                    rba.showMessage("Declined");
                }
                break;
            case "termError":
                if (rba != null) {
                    rba.showMessage("Error");
                }
                break;
            case "termSig":
                if (rba != null) {
                    rba.stubStop();
                }
                FlaggedReset();
                GetSignature();
                break;
            case "termGetType":
                break;
            case "termGetTypeWithFS":
                break;
            case "termGetPin":
                break;
            case "termWait":
                break;
            case "termFindPort":
                var new_port = this.PortSearch(this.device_identifier);
                if (new_port != "" && new_port != this.com_port && new_port.All(char.IsNumber)) {
                    this.com_port = new_port;
                }
                break;
        }
    }

    public override void SetConfig(string k, string v)
    {
        if (k == "disableRBA" && v == "true") {
            try {
                if (this.rba != null) {
                    rba.stubStop();
                }
            } catch (Exception) {}
            this.rba = null;
        } else if (k == "disableButtons" && v == "true") {
            this.rba.SetEMV(RbaButtons.None);
        } else if (k == "logXML" && v == "true") {
            this.enable_xml_log = true;
        }
    }

    /**
      Supported options:
        -- Global Options --
        * alwaysReset [boolean] default false
            Issue a PadReset command following a transaction. This will make
            the terminal beep until the customer removes their card. Control
            will not be returned to the cashier until the card is removed or
            the reset command times out.
        * logErrors [boolean] default false
            Write error information to the same debug_lane.log file as PHP.
            Errors are logged regardless of whether the verbose switch (-v) 
            is used but not all verbose output is treated as an error & logged
        * logXML [boolean] default false
            Log XML requests & responses to "xml.log" in the current directory.
        * servers [string] default "x1.mercurypay.com;x2.backuppay.com"
            Set PDCX server list
        * terminalID [string] default ""
            Whether this is required varies by processor

        -- Ingencio Specific Options --
        * disableRBA [boolean] default false
            Stops all direct communication with Ingenico terminal.
            Driver will solely utilize Datacap functionality
        * disableButtons [boolean] default false
            Does not display payment type or cashback selection buttons.
            RBA commands can still be used to display static text
            Irrelevant if disableRBA is true
        * disableMessages [boolean] default false
            Does not display approved/declined on screen via RBA
            Irrelevant if disableRBA is true
        * buttons [string] default EMV
            Change labeling of the buttons. Valid options are "credit"
            and "cashback" currently.
            Irrelevant if disableRBA or disableButtons is true
        * defaultMessage [string] default "Welcome"
            Message displayed onscreen at the start of a transaction
            Irrelevant if disableRBA is true
        * cashback [boolean] default true
            Show cashback selections if payment type debit or ebt cash
            is selected.
            Irrelevant if disableRBA or disableButtons is true
    */
    public override void SetConfig(Dictionary<string,string> d)
    {
        if (d.ContainsKey("disableRBA") && d["disableRBA"].ToLower() == "true") {
            try {
                if (this.rba != null) {
                    rba.stubStop();
                }
            } catch (Exception) {}
            this.rba = null;
        }

        if (this.rba != null && d.ContainsKey("disableButtons") && d["disableButtons"].ToLower() == "true") {
            this.rba.SetEMV(RbaButtons.None);
        }

        if (this.rba != null && d.ContainsKey("disableMessages") && d["disableMessages"].ToLower() == "true") {
            this.rba.DisableMessages();
        }

        if (this.rba != null && d.ContainsKey("buttons")) {
            if (d["buttons"].ToLower() == "credit") {
                this.rba.SetEMV(RbaButtons.Credit);
            } else if (d["buttons"].ToLower() == "cashback") {
                this.rba.SetEMV(RbaButtons.Cashback);
            }
        }

        if (this.rba != null && d.ContainsKey("defaultMessage")) {
            this.rba.SetDefaultMessage(d["defaultMessage"]);
        }

        if (d.ContainsKey("alwaysReset") && d["alwaysReset"].ToLower() == "true") {
            this.always_reset = true;
        }

        if (d.ContainsKey("logXML") && d["logXML"].ToLower() == "true") {
            this.enable_xml_log = true;
        }

        if (d.ContainsKey("logErrors") && d["logErrors"].ToLower() == "true") {
            this.enableUnifiedLog();
        }

        if (this.rba != null && d.ContainsKey("cashback") && (d["cashback"].ToLower() == "true" || d["cashback"].ToLower() == "false")) {
            this.rba.SetCashBack(d["cashback"].ToLower() == "true" ? true : false);
        }

        if (d.ContainsKey("servers")) {
            this.server_list = d["servers"];
        }

        if (d.ContainsKey("terminalID")) {
            this.terminalID = d["terminalID"];
        }
    }

    /**
      Process XML transaction using dsiPDCX
      @param xml the request body
      @param autoReset true if the request requires a reset, false if the request IS a reset
    */
    protected string ProcessEMV(string xml, bool autoReset)
    {
        /* 
           Substitute values into the XML request
           This is so the driver can handle any change
           in which hardware device is connected as well
           as so tracking SequenceNo values is not POS'
           problem.
        */
        xml = xml.Replace("{{SequenceNo}}", SequenceNo());
        if (IsCanadianDeviceType(this.device_identifier)) {
            // tag name is different in this case;
            // replace placeholder then the open/close tags
            xml = xml.Replace("{{SecureDevice}}", this.device_identifier);
            xml = xml.Replace("SecureDevice", "PadType");
        } else {
            xml = xml.Replace("{{SecureDevice}}", SecureDeviceToEmvType(this.device_identifier));
        }
        xml = xml.Replace("{{ComPort}}", com_port);
        if (this.terminalID.Length > 0) {
            xml = xml.Replace("{{TerminalID}}", this.terminalID);
        }
        if (this.device_ip.Length > 0) {
            xml = xml.Replace("<ComPort>1</ComPort>", "<ComPort>1</ComPort><PinPadIpAddress>" + this.device_ip + "</PinPadIpAddress><PinPadIpPort>12000</PinPadIpPort>");
        }

        try {
            /**
              Extract HostOrIP field and split it on commas
              to allow multiple IPs
            */
            XmlDocument request = new XmlDocument();
            request.LoadXml(xml);
            var IPs = request.SelectSingleNode("TStream/Transaction/HostOrIP").InnerXml.Split(new Char[]{','}, StringSplitOptions.RemoveEmptyEntries);
            if (this.reverse_servers) {
                Array.Reverse(IPs);
            }
            string result = "";
            foreach (string IP in IPs) {
                // try request with an IP

                // If this is NOT a pad reset request, check the emv_reset
                // flag to see if a reset is needed. If so, execute one
                // and update the flag
                if (autoReset) {
                    FlaggedReset();
                }

                lock(emvLock) {
                    emv_active = true;
                }

                request.SelectSingleNode("TStream/Transaction/HostOrIP").InnerXml = IP;
                result = emv_ax_control.ProcessTransaction(request.OuterXml);

                lock(emvLock) {
                    emv_active = false;
                    // if this is not a reset command, set the reset needed flag
                    if (autoReset) {
                        emv_reset = true;
                    }
                }

                if (enable_xml_log) {
                    using (StreamWriter sw = new StreamWriter(xml_log, true)) {
                        sw.WriteLine(DateTime.Now.ToString() + " (send emv): " + request.OuterXml);
                        sw.WriteLine(DateTime.Now.ToString() + " (recv emv): " + result);
                    }
                }
                XmlDocument doc = new XmlDocument();
                try {
                    doc.LoadXml(result);
                    // track SequenceNo values in responses
                    XmlNode sequence = doc.SelectSingleNode("RStream/CmdResponse/SequenceNo");
                    sequence_no = sequence.InnerXml;
                    XmlNode return_code = doc.SelectSingleNode("RStream/CmdResponse/DSIXReturnCode");
                    XmlNode origin = doc.SelectSingleNode("RStream/CmdResponse/ResponseOrigin");
                    /**
                      On a client/3006 error or server/4003 error keep trying IPs.
                      Both indicate a network connectivity error that may just be
                      transient.
                      003327 is a Com Port access error. May be transient in some cases.
                    */
                    if (origin.InnerXml == "Client" && return_code.InnerXml == "003006") {
                        this.LogMessage("Retry on client 3006 (epay: " + IP + ")");
                        this.reverse_servers = !this.reverse_servers;
                    } else if (origin.InnerXml == "Client" && return_code.InnerXml == "003327") { 
                        this.LogMessage("Retry on client 3006 (COM error)");
                        Thread.Sleep(500);
                        var new_port = this.PortSearch(this.device_identifier);
                        if (new_port != "" && new_port != this.com_port && new_port.All(char.IsNumber)) {
                            this.com_port = new_port;
                        }
                    } else if (origin.InnerXml == "Server" && return_code.InnerXml == "004003") {
                        this.LogMessage("Retry on server 4003 (epay: " + IP + ")");
                    } else {
                        break;
                    }
                } catch (Exception ex) {
                    // response was invalid xml
                    this.LogMessage(ex.ToString());
                    // status is unclear so do not attempt 
                    // another transaction
                    break;
                }
            }

            if (autoReset && this.always_reset) {
                FlaggedReset();
            }

            return result;

        } catch (Exception ex) {
            // request was invalid xml
            this.LogMessage(ex.ToString());
        }

        return "";
    }

    /**
      Process XML transaction using dsiPDCX
    */
    protected string ProcessPDC(string xml)
    {
        lock (pdcLock) {
            pdc_active = true;
        }
        string ret = "";
        try {
            xml = xml.Replace("{{SequenceNo}}", SequenceNo());
            xml = xml.Replace("{{SecureDevice}}", this.device_identifier);
            xml = xml.Replace("{{ComPort}}", com_port);
            if (this.terminalID.Length > 0) {
                xml = xml.Replace("{{TerminalID}}", this.terminalID);
            }
            if (this.device_ip.Length > 0) {
                xml = xml.Replace("<ComPort>1</ComPort>", "<ComPort>1</ComPort><PinPadIpAddress>" + this.device_ip + "</PinPadIpAddress><PinPadIpPort>12000</PinPadIpPort>");
            }

            ret = pdc_ax_control.ProcessTransaction(xml, 1, null, null);
            if (enable_xml_log) {
                using (StreamWriter sw = new StreamWriter(xml_log, true)) {
                    sw.WriteLine(DateTime.Now.ToString() + " (send pdc): " + xml);
                    sw.WriteLine(DateTime.Now.ToString() + " (recv pdc): " + ret);
                }
            }
        } catch (Exception ex) {
            this.LogMessage(ex.ToString());
        }
        lock (pdcLock) {
            pdc_active = false;
        }

        return ret;
    }

    /**
      Get the current sequence number OR the default
    */
    protected string SequenceNo()
    {
        return sequence_no != null ? sequence_no : "0010010010";
    }

    /**
      PDCX initialize device
    */
    protected string InitPDCX()
    {
        string xml="<?xml version=\"1.0\"?>"
            + "<TStream>"
            + "<Admin>"
            + "<MerchantID>MerchantID</MerchantID>"
            + "<TranCode>SecureDeviceInit</TranCode>"
            + "<TranType>Setup</TranType>"
            + "<SecureDevice>"+ this.device_identifier + "</SecureDevice>"
            + "<ComPort>" + this.com_port + "</ComPort>"
            + "<PadType>" + SecureDeviceToPadType(device_identifier) + "</PadType>"
            + "</Admin>"
            + "</TStream>";
        
        return ProcessPDC(xml);
    }

    /**
      Set a lock, check if the reset flag is active.
      If so, issue a reset and clear the flag then
      release the lock
    */
    protected string FlaggedReset()
    {
        string ret = "";
        lock(emvLock) {
            if (emv_reset) {
                ret  = PadReset();
                emv_reset = false;
            }
        }

        return ret;
    }

    /**
      EMVX reset device for next transaction
    */
    protected string PadReset()
    {
        string xml="<?xml version=\"1.0\"?>"
            + "<TStream>"
            + "<Transaction>"
            + "<HostOrIP>127.0.0.1</HostOrIP>"
            + "<MerchantID>MerchantID</MerchantID>"
            + (this.terminalID.Length > 0 ? "<TerminalID>" + this.terminalID + "</TerminalID>" : "")
            + "<TranCode>EMVPadReset</TranCode>"
            + "<SecureDevice>" + SecureDeviceToEmvType(this.device_identifier) + "</SecureDevice>"
            + "<ComPort>" + this.com_port + "</ComPort>"
            + "<SequenceNo>" + SequenceNo() + "</SequenceNo>"
            + "</Transaction>"
            + "</TStream>";
    
        return ProcessEMV(xml, false);
    }

    /**
      PDCX method to get signature from device
    */
    protected string GetSignature(bool udp=true)
    {
        string xml="<?xml version=\"1.0\"?>"
            + "<TStream>"
            + "<Transaction>"
            + "<MerchantID>MerchantID</MerchantID>"
            + "<TranCode>GetSignature</TranCode>"
            + "<SecureDevice>"+ this.device_identifier + "</SecureDevice>"
            + "<ComPort>" + this.com_port + "</ComPort>"
            + "<Account>"
            + "<AcctNo>SecureDevice</AcctNo>"
            + "</Account>"
            + "</Transaction>"
            + "</TStream>";
        string result = ProcessPDC(xml);
        XmlDocument doc = new XmlDocument();
        try {
            doc.LoadXml(result);
            XmlNode status = doc.SelectSingleNode("RStream/CmdResponse/CmdStatus");
            if (status.InnerText != "Success") {
                return null;
            }
            string sigdata = doc.SelectSingleNode("RStream/Signature").InnerText;
            List<Point> points = SigDataToPoints(sigdata);

            string my_location = AppDomain.CurrentDomain.BaseDirectory;
            char sep = Path.DirectorySeparatorChar;
            string ticks = System.Guid.NewGuid().ToString();
            string filename = my_location + sep + "ss-output"+ sep + "tmp" + sep + ticks + ".bmp";
            BitmapBPP.Signature sig = new BitmapBPP.Signature(filename, points);
            if (udp) {
                parent.MsgSend("TERMBMP" + ticks + ".bmp");
            } else {
                return "<img>" + ticks + ".bmp</img>";
            }
        } catch (Exception ex) {
            this.LogMessage(ex.ToString());
        }

        return "<err>Error collecting signature</err>";
    }

    protected string PortSearch(string device)
    {
        switch (device) {
            case "VX805XPI":
            case "VX805XPI_MERCURY_E2E":
                return ComPortUtility.FindComPort("Verifone");
            case "INGENICOISC250":
            case "INGENICOISC250_MERCURY_E2E":
            case "INGENICOISC250_RAPIDCONNECT_E2E":
            case "INGENICOLANE8000_MERCURY_E2E":
            case "INGENICOLANE7000_MERCURY_E2E":
                return ComPortUtility.FindComPort("Ingenico");
            default:
                return "";
        }
    }

    /**
      Translate securedevice strings to padtype strings
    */
    protected string SecureDeviceToPadType(string device)
    {
        switch (device) {
            case "VX805XPI":
            case "VX805XPI_MERCURY_E2E":
                return "VX805";
            case "INGENICOISC250":
            case "INGENICOISC250_MERCURY_E2E":
            case "INGENICOISC250_RAPIDCONNECT_E2E":
                return "ISC250";
            case "INGENICOISC480":
            case "INGENICOISC480_RAPIDCONNECT_E2E":
                return "ISC480";
            case "INGENICOLANE8000_MERCURY_E2E":
                return "LANE8000";
            case "INGENICOLANE7000_MERCURY_E2E":
            case "LANE7000_RAPIDCONNECT_E2E":
                return "LANE7000";
            default:
                return device;
        }
    }

    /**
      Translate pdc securedevice strings to emv securedevice strings
    */
    protected string SecureDeviceToEmvType(string device)
    {
        switch (device) {
            case "VX805XPI":
                return "EMV_VX805_RAPIDCONNECT";
            case "VX805XPI_MERCURY_E2E":
                return "EMV_VX805_MERCURY";
            case "INGENICOISC250_MERCURY_E2E":
                return "EMV_ISC250_MERCURY";
            case "INGENICOISC250_RAPIDCONNECT_E2E":
                return "EMV_ISC250_RAPIDCONNECT_E2E";
            case "INGENICOISC480_RAPIDCONNECT_E2E":
                return "EMV_ISC480_RAPIDCONNECT_E2E";
            case "INGENICOLANE8000_MERCURY":
                return "EMV_LANE8000_MERCURY";
            case "INGENICOLANE7000_MERCURY_E2E":
                return "EMV_LANE7000_MERCURY";
            case "LANE7000_RAPIDCONNECT_E2E":
                return "EMV_LANE7000_RAPIDCONNECT_E2E";
            default:
                return "EMV_" + device;
        }

    }

    protected bool IsCanadianDeviceType(string device)
    {
        switch (device) {
            case "Paymentech1":
            case "Global1":
            case "Moneris1":
                return true;
            default:
                return false;
        }
    }

    protected List<Point> SigDataToPoints(string data)
    {
        char[] comma = new char[]{','};
        char[] colon = new char[]{':'};
        var pairs = from pair in data.Split(colon) 
            select pair.Split(comma);
        var points = from pair in pairs 
            where pair.Length == 2
            select new Point(CoordsToInt(pair[0]), CoordsToInt(pair[1]));

        return points.ToList();
    }

    protected int CoordsToInt(string coord)
    {
        if (coord == "#") {
            return 0;
        } else {
            return Int32.Parse(coord);
        }
    }
}

}

