<?php

use COREPOS\pos\lib\Database;

class PaycardTenders
{
    public function __construct($conf)
    {
        $this->conf = $conf;
    }
    
    private function getIssuerOverride($issuer)
    {
        if ($this->conf->get('PaycardsTenderCodeVisa') && $issuer == 'Visa') {
            return array($this->conf->get('PaycardsTenderCodeVisa'));
        } elseif ($this->conf->get('PaycardsTenderCodeMC') && $issuer == 'MasterCard') {
            return array($this->conf->get('PaycardsTenderCodeMC'));
        } elseif ($this->conf->get('PaycardsTenderCodeDiscover') && $issuer == 'Discover') {
            return array($this->conf->get('PaycardsTenderCodeDiscover'));
        } elseif ($this->conf->get('PaycardsTenderCodeAmex') && $issuer == 'American Express') {
            return array($this->conf->get('PaycardsTenderCodeAmex'));
        } elseif ($this->conf->get('PaycardsTenderCodeAmex') && $issuer == 'AMEX') {
            return array($this->conf->get('PaycardsTenderCodeAmex')); //mercury datacap card type
        } elseif ($this->conf->get('PaycardsTenderCodeAmex') && $issuer == 'DEBIT') {
            return array($this->conf->get('PaycardsTenderCodeDebit')); //mercury datacap card type
        }
        //data cap issuer fields AMEX,DCVR, DEBIT, EBT, M/C, NCG,VISA
        return false;
    }

    private function getTenderConfig($type)
    { 
        switch ($type) {
            case 'DEBIT':
            case 'EMVDC':
                return array(
                    array($this->conf->get('PaycardsTenderCodeDebit')),
                    'DC',
                    'Debit Card',
                );
            case 'EBTCASH':
                return array(
                    array($this->conf->get('PaycardsTenderCodeEbtCash')),
                    'EC',
                    'EBT Cash',
                );
            case 'EBTFOOD':
                return array(
                    array($this->conf->get('PaycardsTenderCodeEbtFood')),
                    'EF',
                    'EBT Food',
                );
            case 'EMV':
            case 'EMVCC':
                return array(
                    array($this->conf->get('PaycardsTenderCodeEmv')),
                    'CC',
                    'Credit Card',
                );
            case 'GIFT':
            case 'PREPAID':
                return array(
                    array($this->conf->get('PaycardsTenderCodeGift')),
                    'GD',
                    'Gift Card',
                );
            case 'CREDIT':
            default:
                return array(
                    array($this->conf->get('PaycardsTenderCodeCredit')),
                    'CC',
                    'Credit Card',
                );
        }
    }

    /**
      Lookup user-configured tender
      Failover to defaults if tender does not exist
      Since we already have an authorization at this point,
      adding a default tender record to the transaction
      is better than issuing an error message
    */
    public function getTenderInfo($type, $issuer)
    {
        $dbc = Database::pDataConnect();
        $lookup = $dbc->prepare('
            SELECT TenderName,
                TenderCode
            FROM tenders
            WHERE TenderCode = ?');
        
        list($args, $defaultCode, $defaultDescription) = $this->getTenderConfig($type);
        $override = $this->getIssuerOverride($issuer);
        if ($override !== false) {
            $args = $override;
        }
        
        $found = $dbc->execute($lookup, $args);
        if ($found === false || $dbc->numRows($found) == 0) {
            return array($defaultCode, $defaultDescription);
        }
        $row = $dbc->fetchRow($found);
        return array($row['TenderCode'], $row['TenderName']);
    }
}

