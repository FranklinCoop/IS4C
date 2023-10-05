<?php
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

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

/**
*/
class SpinsSubmitLoopTask extends FannieTask 
{
    public $name = 'Spins Loop Command Line Only';

    public $description = 'Generates A range of Spins weeks Only run via command line "php /pos/fannie/classlib2.0/FannieTask.php SpinsSubmitLoopTask"';

    public $default_schedule = array(
        'min' => 0,
        'hour' => 4,
        'day' => '*',
        'month' => '*',
        'weekday' => '2',
    );

    public function run()
    {
        global $argc, $argv, $FANNIE_OP_DB, $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $dateObj = new SpinsDate($FANNIE_PLUGIN_SETTINGS['SpinsOffset']);

        $iso_week = date('W');
        $iso_week--;
        $year = date('Y');
        if ($iso_week <= 0) {
            $iso_week = 52;
            $year--;
        }
        $upload = true;
        $today = strtotime("now");
        $loopStart = strtotime("2023-08-26");
        /**
          Handle additional args
        */
        //echo $argc;
        //print(var_dump($argv));

        if (isset($argv) && is_array($argv)) {
            foreach($argv as $arg) {
                //echo $arg."substr: \"".substr($arg, 0, 5)."\"\n";
                if (is_numeric($arg)) {
                    //$iso_week = $arg;
                    $dateObj = new SpinsDate($FANNIE_PLUGIN_SETTINGS['SpinsOffset'], $arg);
                } else if ($arg == '--file') {
                    $upload = false;
                } else if (substr($arg, 0, 7) == '--start') {
                    $parts = explode('=', $arg);
                    echo var_dump($parts)."\n";
                    $loopStart = strtotime($parts[1]);
                } else if (substr($arg, 0, 5) == '--end') {
                    $parts = explode('=', $arg);
                    echo var_dump($parts)."\n";
                    $today = strtotime($parts[1]);
                }
            }
        }

        /**
          Keep SPINS week number separate for logging purposes
        */
        //$spins_week = $iso_week;
        $spins_week = $dateObj->spinsWeek();
        //if (isset($FANNIE_PLUGIN_SETTINGS['SpinsOffset'])) {
        //    $iso_week += $FANNIE_PLUGIN_SETTINGS['SpinsOffset'];
        //}

        while ($loopStart < $today) {
                    // walk forward to Sunday
            $start = $loopStart;
            $end = $loopStart;
            while (date('w', $end) != 0) {
                $end = mktime(0,0,0,date('n',$end),date('j',$end)+1,date('Y',$end));
            }
            $week = (int)date('W', $end);
            $dateObj = new SpinsDate($FANNIE_PLUGIN_SETTINGS['SpinsOffset'], $week);
            $spins_week = $dateObj->spinsWeek();
            $dlog = DTransactionsModel::selectDlog(date('Y-m-d', $start), date('Y-m-d',$end));

            $lastDay = date("M d, Y", $end) . ' 11:59PM'; 

            $this->cronMsg('SPINS data for week #' . $spins_week . '(' . date('Y-m-d', $start) . ' to ' . date('Y-m-d', $end) . ')', FannieLogger::INFO);

            // Odd "CASE" statement is to deal with special order
            // line items the have case size & number of cases
            $dataQ = "SELECT d.upc, p.description,
                    SUM(CASE WHEN d.quantity <> d.ItemQtty AND d.ItemQtty <> 0 THEN d.quantity*d.ItemQtty ELSE d.quantity END) as quantity,
                    SUM(d.total) AS dollars,
                    '$lastDay' AS lastDay
                  FROM $dlog AS d
                    " . DTrans::joinProducts('d', 'p', 'INNER') . "
                  WHERE p.Scale = 0
                    AND d.upc > '0000000999999' 
                    AND tdate BETWEEN ? AND ? AND d.store_id=?
                  GROUP BY d.upc, p.description";

            $filename = $FANNIE_PLUGIN_SETTINGS['SpinsPrefix'];
            if ($this->config->get('STORE_MODE') == 'HQ') {
                $filename .= sprintf('%02d', $this->config->get('STORE_ID'));
            }
            if (!empty($filename)) {
                $filename .= '_';
            }
            $filename .= date('mdY', $dateObj->endTimeStamp()) . '.csv';

            $outfile = sys_get_temp_dir()."/".$filename;
            $fp = fopen($outfile,"w");

            $dataP = $dbc->prepare($dataQ);
            $args = array(date('Y-m-d 00:00:00', $start), date('Y-m-d 23:59:59', $end), $this->config->get('STORE_ID'));
            $dataR = $dbc->execute($dataP, $args);
            while($row = $dbc->fetch_row($dataR)){
                for($i=0;$i<4; $i++){
                    if ($i==2 || $i==3) {
                        $row[$i] = sprintf('%.2f', $row[$i]);
                    }
                    fwrite($fp,"\"".$row[$i]."\",");
                }
                fwrite($fp,"\"".$row[4]."\"\n");
            }
            fclose($fp);

            if ($upload) {
                $server = isset($FANNIE_PLUGIN_SETTINGS['SpinsFtpServer']) ? $FANNIE_PLUGIN_SETTINGS['SpinsFtpServer'] : 'ftp.spins.com';
                $this->cronMsg("will attempt FTP upload to: $server", FannieLogger::INFO);
    
                $attempts = 0;
                $maxAttempts = isset($FANNIE_PLUGIN_SETTINGS['SpinsUploadAttempts']) ? $FANNIE_PLUGIN_SETTINGS['SpinsUploadAttempts'] : 1;
                $delay = isset($FANNIE_PLUGIN_SETTINGS['SpinsRetryDelay']) ? $FANNIE_PLUGIN_SETTINGS['SpinsRetryDelay'] : 30;
                while (true) {
                    if ($FANNIE_PLUGIN_SETTINGS['SpinsSftp'] && $this->sftp_upload($server, $outfile, $filename)) {
                        $this->cronMsg('SFTP upload successful', FannieLogger::INFO);
                        break;
                    } elseif (!$FANNIE_PLUGIN_SETTTINGS['SpinsSftp'] && $this->upload($server, $outfile, $filename)) {
                        $this->cronMsg('FTP upload successful', FannieLogger::INFO);
                        break;
                    }
                    $attempts++;
                    $this->cronMsg("FTP upload attempt #$attempts of $maxAttempts failed", FannieLogger::WARNING);
                    if ($attempts >= $maxAttempts) {
                        $this->cronMsg("Reached max of $maxAttempts attempts; giving up on FTP upoad", FannieLogger::ERROR);
                        break;
                    }
                    sleep($delay);
                }
    
                unlink($outfile);
    
            } else {
                rename($outfile, './' . $filename);    
                $this->cronMsg('Generated file: ' . $filename, FannieLogger::INFO);
            }
            $loopStart = mktime(0,0,0,date('n',$end),date('j',$end)+1,date('Y',$end));
        }

    }
    public function upload($server, $localPath, $filename) {
        global $FANNIE_PLUGIN_SETTINGS;
    
        $conn_id = ftp_connect($server);
        $login_id = ftp_login($conn_id, $FANNIE_PLUGIN_SETTINGS['SpinsFtpUser'], $FANNIE_PLUGIN_SETTINGS['SpinsFtpPw']);
        if (!$conn_id || !$login_id) {
            $this->cronMsg('FTP Connection failed', FannieLogger::ERROR);
            return false;
        }
    
        $remoteDir = isset($FANNIE_PLUGIN_SETTINGS['SpinsFtpDir']) ? $FANNIE_PLUGIN_SETTINGS['SpinsFtpDir'] : 'data';
        if ($remoteDir) {
            ftp_chdir($conn_id, $remoteDir);
        }
        ftp_pasv($conn_id, true);
        $uploaded = ftp_put($conn_id, $filename, $localPath, FTP_ASCII);
        ftp_close($conn_id);
        return $uploaded;
    }
    
    public function sftp_upload($server, $localPath, $filename)
    {
        $settings = FannieConfig::config('PLUGIN_SETTINGS');
        $adapter = new SftpAdapter(array(
            'host' => $server,
            'username' => $settings['SpinsFtpUser'],
            'password' => $settings['SpinsFtpPw'],
            'port' => 22,
        ));
    
        $filesystem = new Filesystem($adapter);
        $success = $filesystem->put($settings['SpinsFtpUser'] . '/' . $filename, file_get_contents($localPath));
    
        return $success;
    }

}

