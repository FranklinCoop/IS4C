<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op

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

namespace COREPOS\pos\lib\Scanning\SpecialUPCs;
use COREPOS\pos\lib\Scanning\SpecialUPC;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\DiscountModule;
use COREPOS\pos\lib\DisplayLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\TransRecord;

/**
  @class HouseCoupon
  WFC style custom store coupons

  This class looks for UPC prefix 00499999

  The remainder of the UPC is an ID value
  to look up requirement(s) and discount
  via the houseCoupons and houseCouponItems
  tables
*/
class HouseCoupon extends SpecialUPC 
{

    public function isSpecial($upc)
    {
        $prefix = $this->session->get('houseCouponPrefix');
        if ($prefix == '') {
            $prefix = '00499999';
        }

        if (substr($upc,0,strlen($prefix)) == $prefix) {
            return true;
        }

        return false;
    }

    public function handle($upc, $json)
    {
        $coupID = ltrim(substr($upc, -5), "0");

        $qualified = $this->checkQualifications($coupID);
        if ($qualified !== true) {
            $json['output'] = $qualified;
            return $json;
        }

        $available = $this->checkLimits($coupID);
        if ($available !== true) {
            $json['output'] = $available;
            return $json;
        }

        if ($coupID == 321) {
            $json['main_frame'] = MiscLib::baseURL() . 'gui-modules/EmailPage.php';
            return $json;
        }

        $add = $this->getValue($coupID);
        TransRecord::addhousecoupon($upc, $add['department'], -1 * $add['value'], $add['description'], $add['discountable'], $add['tax']);

        $json['output'] = DisplayLib::lastpage();
        $json['udpmsg'] = 'goodBeep';
        $json['redraw_footer'] = true;

        return $json;
    }

    /**
      helper - lookup coupon record
    */
    private function lookupCoupon($coupID)
    {
        $dbc = Database::pDataConnect();
        $infoQ = "SELECT endDate," 
                    . $dbc->identifierEscape('limit') . ",
                    discountType, 
                    department,
                    discountValue, 
                    minType, 
                    minValue, 
                    memberOnly, 
                    endDate,
                    CASE 
                        WHEN endDate IS NULL THEN 0 
                        ELSE ". $dbc->datediff('endDate', $dbc->now()) . " 
                    END AS expired";
        if ($this->session->get('NoCompat') == 1) {
            $infoQ .= ", description, 
                        startDate,
                        CASE 
                          WHEN startDate IS NULL THEN 0 
                          ELSE ". $dbc->datediff('startDate', $dbc->now()) . " 
                        END as preStart,
                        virtualOnly,
                        " . $dbc->escape("maxValue");
        } else {
            // new(ish) columns 16apr14
            $hctable = $dbc->tableDefinition('houseCoupons');
            if (isset($hctable['description'])) {
                $infoQ .= ', description';
            } else {
                $infoQ .= ', \'\' AS description';
            }
            if (isset($hctable['startDate'])) {
                $infoQ .= ", startDate,
                            CASE 
                              WHEN startDate IS NULL THEN 0 
                              ELSE ". $dbc->datediff('startDate', $dbc->now()) . " 
                            END as preStart";
            } else {
                $infoQ .= ', \'1900-01-01\' AS startDate, 0 AS preStart';
            }
            $infoQ .= isset($hctable['virtualOnly']) ? ', virtualOnly ' : ', 0 AS virtualOnly ';
            $mval = $dbc->identifierEscape('maxValue');
            $infoQ .= isset($hctable['maxValue']) ? ", {$mval} " : ", 0 AS {$mval} ";
        }
        $infoQ .= " FROM  houseCoupons 
                    WHERE coupID=" . ((int)$coupID);
        $infoR = $dbc->query($infoQ);
        if ($dbc->num_rows($infoR) == 0) {
            return false;
        }

        return $dbc->fetch_row($infoR);
    }

    private function errorOrQuiet($msg, $quiet)
    {
            if ($quiet) {
                return false;
            }
            return DisplayLib::boxMsg(
                $msg,
                '',
                false,
                DisplayLib::standardClearButton()
            );
    }

    /**
     * Determine if the customer is a member
     * @param $couponFlag [int] value of houseCoupons.memberOnly
     * @return [boolean]
     */
    private function isMember($couponFlag)
    {
        $isMem = false;
        if ($this->session->get('isMember') == 1) {
            $isMem = true;
        } elseif ($this->session->get('memberID') == $this->session->get('visitingMem') && $couponFlag == 2) {
            $isMem = true;
        } elseif ($this->session->get('memberID') == '0') {
            $isMem = false;
        } elseif ($this->session->get('memberID') == 5608) {
            $isMem = true;
        }

        return $isMem;
    }

    /**
      Validate coupon exists, is not expired, and
      transaction meets required qualifications
      @param $coupID [int] coupon ID
      @param $quiet [boolean] just return false rather than
        an error message on failure
      @return [boolean] true or [string] error message
    */
    public function checkQualifications($coupID, $quiet=false)
    {
        $infoW = $this->lookupCoupon($coupID);
        if ($infoW === false) {
            return $this->errorOrQuiet(_("coupon not found"), $quiet);
        }

        if ($infoW["expired"] < 0) {
            $expired = substr($infoW["endDate"], 0, strrpos($infoW["endDate"], " "));
            return $this->errorOrQuiet(_("coupon expired "). $expired, $quiet);
        } elseif ($infoW['preStart'] > 0) {
            return $this->errorOrQuiet(_("coupon not available yet "), $quiet);
        }

        /* check for member-only, longer use tracking
           available with member coupons */
        if ($infoW["memberOnly"] >= 1 && !$this->isMember($infoW['memberOnly'])) {
            if ($quiet) {
                return false;
            }
            return DisplayLib::boxMsg(
                _("Apply member number first"),
                _('Member only coupon'),
                false,
                array_merge(array(_('Member Search [ID]') => 'parseWrapper(\'ID\');'), DisplayLib::standardClearButton())
            );
        }

        $prefix = $this->session->get('houseCouponPrefix');
        if ($prefix == '') {
            $prefix = '00499999';
        }
        $upc = $prefix . str_pad($coupID, 5, '0', STR_PAD_LEFT);

        /*Check if this coupon exludeds other coupons there are two because then an exclusion doesn't require two enteies in
         *houseCoouponItems.
         *check if the coupon is excluded because it has an EXCLUDE Entry for a previously entered coupon.*/
        $transDB = Database::tDataConnect();
        $excludeQ = 'SELECT SUM(l.quantity) as qty, l.`description`
            FROM localtemptrans AS l
            INNER JOIN ' . $this->session->get('pDatabase') . $transDB->sep() . 'houseCouponItems AS h 
            ON h.upc=l.upc
            WHERE h.`type` = \'EXCLUDE\' and h.coupID=' . ((int)$coupID);
        $excludeR = $transDB->query($excludeQ);
        $excludeW = $transDB->fetch_row($excludeR);
        if($excludeW) {
            if($excludeW[0] != 0 && !is_null(excludeW[1])){
                return $this->errorOrQuiet(_('Coupon does not stack with:</br>'.$excludeW[1]), false);
            }
        }

        /* check if this coupon is exluded because of an entery in an applied coupon has an EXCLUDE Entery for it. */
        $excludeQ = "SELECT SUM(l.quantity) as qty, l.`description` 
            FROM  opdata.houseCouponItems AS h
            JOIN localtemptrans AS l on LPAD(h.coupID, 13, '0049999900000') = l.upc
            WHERE h.upc = '".$upc."' AND h.`type` = 'EXCLUDE'";
        $excludeR = $transDB->query($excludeQ);
        $excludeW = $transDB->fetch_row($excludeR);
        if($excludeW) {
            if($excludeW[0] != 0 && !is_null(excludeW[1])){
                return $this->errorOrQuiet(_('Coupon does not stack with:</br>'.$excludeW[1]), false);
            }
        }
        /* Check that the coupon has not been previously applied over the max value */
        $maxValue = $infoW['maxValue'];
        if($maxValue != 0) {
            $totalQ = 'SELECT SUM(-total) FROM localtemptrans AS l WHERE l.upc ='.$upc;
            $totalR = $transDB->query($totalQ);
            $totalW = $transDB->fetch_row($totalR);
            if($totalW) {
                if($totalW[0] >= $maxValue) {
                    return $this->errorOrQuiet(_('Coupon already applied.'), false);
                }
            }
        }

        /* verify the minimum purchase has been made */
        $transDB = Database::tDataConnect();
        switch ($infoW["minType"]) {
            case "Q": // must purchase at least X
            case "Q+": // must purchase more than X
                $minQ = "select case when sum(ItemQtty) is null
                    then 0 else sum(ItemQtty) end
                    " . $this->baseSQL($transDB, $coupID, 'upc');
                $minR = $transDB->query($minQ);
                $minW = $transDB->fetch_row($minR);
                $validQtty = $minW[0];
                if ($infoW['minType'] == 'Q+' && $validQtty <= $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'Q' && $validQtty < $infoW['minValue']) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case "Q-": // must purchase at least one, no more than X
                $minQ = "select case when sum(ItemQtty) is null
                    then 0 else sum(ItemQtty) end
                    " . $this->baseSQL($transDB, $coupID, 'upc');
                $minR = $transDB->query($minQ);
                $minW = $transDB->fetch_row($minR);
                $validQtty = $minW[0];
                if ($infoW['minType'] == 'Q+' && $validQtty <= $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'Q' && $validQtty < $infoW['minValue']) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'Q-' && $validQtty < 1) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case 'D': // must purchase at least amount in $ from department
            case 'D+': // must purchase more than amount in $ from department
                $minQ = "select case when sum(total) is null
                    then 0 else sum(total) end
                    " . $this->baseSQL($transDB, $coupID, 'department');
                $minR = $transDB->query($minQ);
                $minW = $transDB->fetch_row($minR);
                $validQtty = $minW[0];
                if ($infoW['minType'] == 'D+' && $validQtty <= $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'D' && $validQtty < $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case 'N': // Inversion of D/D+.
            case 'N+': // Must purchase amount outside listed departments
                $deptQ = $transDB->prepare('SELECT upc FROM ' . $this->session->get('pDatabase') . $dbc->sep()
                    . 'houseCouponItems WHERE coupID=?');
                $deptR = $transDB->execute($deptQ, array($coupID));
                $depts = array();
                while ($deptW = $transDB->fetchRow($deptR)) {
                    $depts[] = $deptW['upc'];
                }
                if (count($depts) == 0) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                list($nStr, $nArgs) = $transDB->safeInClause($depts);
                $minQ = $transDB->prepare("SELECT sum(total) FROM localtemptrans
                    WHERE trans_type IN ('I', 'D', 'M')
                        AND department NOT IN ({$nStr})");
                $minR = $transDB->execute($minQ, $nArgs);
                $minW = $transDB->fetch_row($minR);
                $validQtty = $minW[0];
                if ($infoW['minType'] == 'D+' && $validQtty <= $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'D' && $validQtty < $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case 'C': // must purchase at least amount in qty (count) from department
            case 'C+': // must purchase more than amount in qty (count) from department
            case 'C!':
            case 'C^':
                $minQ = "select case when sum(ItemQtty) is null
                    then 0 else sum(ItemQtty) end
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                    AND l.trans_type IN ('I','D')";
                if ($infoW['minType'] == 'C!' || $infoW['minType'] == 'C^') {
                    $minQ .= ' AND l.discounttype=0 ';
                }
                $minR = $transDB->query($minQ);
                $minW = $transDB->fetch_row($minR);
                $validQtty = $minW[0];
                if ($infoW['minType'] == 'C+' && $validQtty <= $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'C^' && $validQtty <= $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'C' && $validQtty < $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == 'C!' && $validQtty < $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case 'M': // must purchase at least X qualifying items
                  // and some quantity corresponding discount items
                $minQ = "select case when sum(ItemQtty) is null then 0 else
                    sum(ItemQtty) end
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type = ";
                //$minR = $transDB->query($minQ . "'QUALIFIER'");
                $minR = $transDB->query($minQ . "'QUALIFIER' OR h.type = 'BOTH'");
                $minW = $transDB->fetch_row($minR);
                $validQtty = $minW[0];

                $min2R = $transDB->query($minQ . "'DISCOUNT'");
                $min2W = $transDB->fetch_row($min2R);
                $validQtty2 = $min2W[0];

                if ($validQtty < $infoW["minValue"] || $validQtty2 <= 0) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case 'MX': // must purchase at least $ from qualifying departments
                       // and some quantity discount items
                       // (mix "cross")
                $minQ = "select case when sum(total) is null
                    then 0 else sum(total) end
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                        AND h.type='QUALIFIER'";
                $minR = $transDB->query($minQ);
                $minW = $transDB->fetch_row($minR);
                $validQtty = $minW[0];

                $min2Q = "select case when sum(ItemQtty) is null then 0 else
                    sum(ItemQtty) end
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type = 'DISCOUNT'";
                $min2R = $transDB->query($min2Q);
                $min2W = $transDB->fetch_row($min2R);
                $validQtty2 = $min2W[0];

                if ($validQtty < $infoW["minValue"] || $validQtty2 <= 0) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case '$': // must purchase at least $ total items
            case '$+': // must purchase more than $ total items
                $minQ = "SELECT sum(total) FROM localtemptrans
                    WHERE trans_type IN ('I', 'D', 'M')";
                $minR = $transDB->query($minQ);
                $minW = $transDB->fetch_row($minR);
                $validAmt = $minW[0];
                if ($infoW['minType'] == '$+' && $validAmt <= $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                } elseif ($infoW['minType'] == '$' && $validAmt < $infoW["minValue"]) {
                    return $this->errorOrQuiet(_('coupon requirements not met'), $quiet);
                }
                break;
            case '': // no minimum
            case ' ':
                break;
            default:
                return $this->errorOrQuiet(_('unknown minimum type ') . $infoW['minType']);
        }

        return true;
    }

    /**
      Check how many times the coupon has been used and 
      compare against usage limits - e.g., one per transaction,
      one per member, etc. This is a separate method from
      checkQualifications() so that calling code has the option
      of working around limits via voids or amount adjustments
      @param $coupID [int] coupon ID
      @return [boolean] true or [string] error message
    */
    public function checkLimits($coupID, $quiet=false)
    {
        $infoW = $this->lookupCoupon($coupID);
        if ($infoW === false) {
            return $this->errorOrQuiet(_('coupon not found'), $quiet);
        }

        /* limit types 
            0 => total coupons used, 
            1 => total coupons used today, 
         */
        $limitType = 0;
        if ($infoW['limit'] > 100 && $infoW['limit'] < 1000) {
            $limitType = 1;
        }

        $prefix = $this->session->get('houseCouponPrefix');
        if ($prefix == '') {
            $prefix = '00499999';
        }
        $upc = $prefix . str_pad($coupID, 5, '0', STR_PAD_LEFT);

        /* check the number of times this coupon
         * has been used in this transaction
         * against the limit */
        $transDB = Database::tDataConnect();
        $limitQ = "select case when sum(ItemQtty) is null
            then 0 else sum(ItemQtty) end
            from localtemptrans where
            upc = '" . $upc . "'" ;
        $limitR = $transDB->query($limitQ);
        $limitW = $transDB->fetch_row($limitR);
        $timesUsed = $limitW[0];
        if ($timesUsed >= $infoW["limit"]) {
            return $this->errorOrQuiet(_('coupon already applied'), $quiet);
        }

        /**
          For members, enforce limits against longer
          transaction history
        */
        if ($infoW["memberOnly"] == 1 && $this->session->get("standalone")==0 
            && $this->session->get('memberID') != $this->session->get('visitingMem')) {

            /**
              A virtual-only coupon MUST exist in the houseVirtualCoupons table for the 
              current member. If a record is present the coupon is allowed.
            */
            if ($infoW['virtualOnly']) {
                $opDB = Database::pDataConnect();
                $chkP = $opDB->prepare("SELECT coupID FROM houseVirtualCoupons WHERE coupID=? AND card_no=?");
                $chkR = $opDB->execute($chkP, array($coupID, $this->session->get('memberID')));
                if ($opDB->numRows($chkR) === 0) {
                    return $this->errorOrQuiet(_('coupon not available<br />on this account'), $quiet);
                }

                return true;
            }

            $mDB = Database::mDataConnect();
            $tDB = Database::tDataConnect();
            if ($mDB === false) {
                return true;
            }
            $mAlt = Database::mAltName();

            // Find qty of coupons applied in past 90 days
            $altQ = "SELECT upc, card_no, SUM(quantity) AS quantity, MAX(tdate) AS tdate
                    FROM {$mAlt}dlog_90_view
                    WHERE trans_type='T'
                        AND trans_subtype='IC'
                            AND upc='{$upc}'
                            AND card_no=" . ((int)$this->session->get('memberID')) . "
                            AND tdate BETWEEN '{$infoW['startDate']}' AND '{$infoW['endDate']}'
                     GROUP BY upc, card_no";
            // find qty of coupon applied today
            $lim1Qm  = "SELECT upc, card_no, SUM(quantity) AS quantity, MAX(tdate) AS tdate
                    FROM {$mAlt}dlog
                    WHERE trans_type='T'
                        AND trans_subtype='IC'
                            AND upc='{$upc}'
                            AND card_no=" . ((int)$this->session->get('memberID')) . "
                     GROUP BY upc, card_no";
            $lim1Qt = "SELECT SUM(quantity) AS quantity 
                    FROM localtemptrans 
                    WHERE trans_subtype = 'IC' 
                        AND upc = '$upc'";

            if ($limitType == 1) {
                // limit = qty of coupons used today
                $mRes = $mDB->query($lim1Qm);
                $tRes = $tDB->query($lim1Qt);
                if ($mDB->num_rows($mRes) > 0 || $tDB->num_rows($tRes) > 0) {
                    $mRow = $mDB->fetch_row($mRes);
                    $tRow = $tDB->fetch_row($tRes);
                    $uses = $mRow['quantity'] + $tRow['quantity'];
                    if ($uses >= $infoW["limit"] - 100) {
                        return $this->errorOrQuiet(_('daily coupon limit already reached<br />on this membership'), $quiet);
                    }
                }
            } else {
                // limit = qty of coupons used in last 90 days
                $mRes = $mDB->query($altQ);
                if ($mDB->num_rows($mRes) > 0) {
                    $mRow = $mDB->fetch_row($mRes);
                    $uses = $mRow['quantity'];
                    if ($uses >= $infoW["limit"]) {
                        return $this->errorOrQuiet(_('coupon already used<br />on this membership'), $quiet);
                    }
                }
            }
        }

        return true;
    }

    
    /**
      Get information about how much the coupon is worth
      @param $coupID [int] coupon ID
      @return array with keys:
        value => [float] coupon value
        department => [int] department number for the coupon
        description => [string] description for coupon
        discountable => [int] whether the coupon should be included in discountable total
    */
    public function getValue($coupID)
    {
        $infoW = $this->lookupCoupon($coupID);
        if ($infoW === false) {
            return array('value' => 0, 'department' => 0, 'description' => '');
        }

        $transDB = Database::tDataConnect();
        /* if we got this far, the coupon
         * should be valid
         */
        $value = 0;
        $description = isset($infoW['description']) ? $infoW['description'] : '';
        $discountable = 1;
        $tax = 0;
        switch ($infoW["discountType"]) {
            case "Q": // quantity discount
                // discount = coupon's discountValue
                // times the cheapeast coupon item
                $valQ = "select CASE WHEN l.scale=1 OR l.upc LIKE '002%' THEN total ELSE unitPrice END as unitPrice,
                        department, trans_id
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type in ('BOTH', 'DISCOUNT')
                    and l.total > 0
                    order by unitPrice asc ";
                $valR = $transDB->query($valQ);
                $valW = $transDB->fetch_row($valR);
                $value = $valW[0] * $infoW["discountValue"];
                // if the item is free, auto-remove tax
                // partial removal for partially discounted items
                // is not currently an option
                if ($infoW['discountValue'] == 1) {
                    $prep = $transDB->prepare("UPDATE localtemptrans SET tax=0 WHERE trans_id=?");
                    $transDB->execute($prep, array($valW['trans_id']));
                }
                break;
            case 'BG': // BOGO
                $valQ = 'SELECT SUM(l.total), SUM(l.quantity) '
                        . $this->baseSQL($transDB, $coupID, 'upc') . "
                        and h.type in ('BOTH', 'DISCOUNT')";
                $valP = $transDB->prepare($valQ);
                $valW = $transDB->getRow($valP);
                $value = $valW[0];
                $qty = $valW[1];
                if ($qty % 2 != 0) {
                    $value -= ($value/$qty);
                }
                $value = MiscLib::truncate2($value/2);
                if ($value > 0 && $value > $infoW['discountValue']) {
                    $value = $infoW['discountValue'];
                }
                break;
            case 'BI': // BOGO Mixed Item
                $qtyQ = 'SELECT SUM(l.quantity) '
                        . $this->baseSQL($transDB, $coupID, 'upc') . "
                        and h.type in ('BOTH')";
                $qtyP = $transDB->prepare($qtyQ);
                $qtyW = $transDB->getRow($qtyP);
                $qty = floor($qtyW[0]/2);
                if ($infoW['discountValue']*2 < $qty) {
                    $qty = $infoW['discountValue']; 
                }
                $valQ = 'SELECT l.total, l.quantity, l.unitPrice'
                        . $this->baseSQL($transDB, $coupID, 'upc') . "
                        and h.type in ('BOTH')
                        ORDER BY unitPrice ASC
                ";
                $valP = $transDB->prepare($valQ);
                $valR = $transDB->execute($valP);
                $tmpVal = 0;
                $arr = array();
                while ($row = $transDB->fetchRow($valR)) {
                    if ($row['quantity'] > 1) {
                        for ($i=$row['quantity']; $i>1; $i--) {
                            $arr[] = $row['unitPrice'];
                        }
                    } else {
                        $arr[] = $row['total'];
                    }
                }
                for ($i=0; $i<$qty; $i++) {
                    $tmpVal += $arr[$i];
                }
                $value = $tmpVal;

                break;
            case 'BQ': // Quantity-capped BOGO
                // get total number of coupon items
                $priceQ = 'SELECT unitPrice, quantity '
                        . $this->baseSQL($transDB, $coupID, 'upc') . "
                        and h.type in ('BOTH', 'DISCOUNT')
                        ORDER BY unitPrice ASC";
                $priceR = $transDB->query($priceQ);
                $value = 0;
                $tmp = array();
                while ($priceW = $transDB->fetchRow($priceR)) {
                    $x = $priceW['quantity'];
                    for ($x; $x > 0; $x--) {
                        $tmp[] = $priceW['unitPrice'];
                    }
                }
                $f = floor(count($tmp) / 2);
                for ($f; $f > 0; $f--) {
                    $value += $tmp[$f-1];
                }
                break;
            case 'BH': // BOHO
                $valQ = 'SELECT SUM(l.total), SUM(l.quantity) '
                        . $this->baseSQL($transDB, $coupID, 'upc') . "
                        and h.type in ('BOTH', 'DISCOUNT')";
                $valP = $transDB->prepare($valQ);
                $valW = $transDB->getRow($valP);
                $value = $valW[0];
                $qty = $valW[1];
                if ($qty % 2 != 0) {
                    $value -= ($value/$qty);
                }
                $value *= 0.5;
                $value = MiscLib::truncate2($value/2);
                if ($value > 0 && $value > $infoW['discountValue']) {
                    $value = $infoW['discountValue'];
                }
                break;
            case 'BM': // BOHO - mixed item. Buy one, get a diff one half-off
                $qualQ = 'SELECT SUM(l.total), SUM(l.quantity) '
                    . $this->baseSQL($transDB, $coupID, 'upc') . '
                    AND h.type in ("QUALIFIER")';
                $qualP = $transDB->prepare($qualQ);
                $qualW = $transDB->getRow($qualP);
                $qualValue = $qualW[0];
                $qualQty = $qualW[1];
                $valQ = 'SELECT l.total '
                        . $this->baseSQL($transDB, $coupID, 'upc') . "
                        and h.type in ('BOTH', 'DISCOUNT')
                        ORDER BY total ASC LIMIT " . $qualQty;
                $valP = $transDB->prepare($valQ);
                $valW = $transDB->execute($valP);
                $value = 0;
                while ($row = $transDB->fetchRow($valW)) {
                    $value += $row['total'];
                }
                $value = MiscLib::truncate2($value/2);
                break;
            case 'B+': // BOHO - variably priced items
                $discoVal = $infoW["discountValue"];
                // get number of qualifiers in transaction
                $qualQ = 'SELECT SUM(l.quantity) '
                    . $this->baseSQL($transDB, $coupID, 'upc') . '
                    AND h.type in ("QUALIFIER", "BOTH")';
                $qualP = $transDB->prepare($qualQ);
                $qualW = $transDB->getRow($qualP);
                // qualQty = total quantity of qualifier items found
                $qualQty = $qualW[0] / 2;
                $qualQty = floor($qualQty); 
                $deptQ = "SELECT total AS value, quantity
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    AND h.type IN ('BOTH', 'DISCOUNT')
                    AND l.total > 0
                    ORDER BY unitPrice ASC 
                    LIMIT " . $qualQty;
                $deptP = $transDB->prepare($deptQ);
                $deptR = $transDB->execute($deptP);
                $j = 0;
                $curQty = null;
                $deptPrice = 0;
                while ($row = $transDB->fetchRow($deptR)) {
                    if ($j < $qualQty && $j < $discoVal) {
                        unset($curQty);
                        $curQty = $row['quantity'];
                        for ($i=0; $i<$curQty; $i++) {
                            $deptPrice += $row['value']; 
                            $j++;
                        }
                    }
                }
                $value = $deptPrice / 2;
                break;
            case "P": // discount price
                // query to get the item's department and current value
                // current value minus the discount price is how much to
                // take off
                $value = $infoW["discountValue"];
                $deptQ = "select department, (total/quantity) as value 
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type in ('BOTH', 'DISCOUNT')
                    and l.total > 0
                    order by unitPrice asc ";
                $deptR = $transDB->query($deptQ);
                $row = $transDB->fetch_row($deptR);
                $value = $row[1] - $value;
                break;
            case "P+": // set price, mixed prices
                $discoVal = $infoW["discountValue"];
                // get number of qualifiers in transaction
                $qualQ = 'SELECT SUM(l.quantity) '
                    . $this->baseSQL($transDB, $coupID, 'upc') . '
                    AND h.type in ("QUALIFIER")';
                $qualP = $transDB->prepare($qualQ);
                $qualW = $transDB->getRow($qualP);
                // qualQty = total quantity of qualifier items found
                $qualQty = $qualW[0];
                $deptQ = "SELECT (total/quantity) AS value, quantity, tax, foodstamp
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    AND h.type IN ('BOTH', 'DISCOUNT')
                    AND l.total > 0
                    AND unitPrice < 10
                    ORDER BY unitPrice ASC 
                    LIMIT " . $qualQty;
                $deptP = $transDB->prepare($deptQ);
                $deptR = $transDB->execute($deptP);
                // $j = number of eligible discount items found;
                $j = 0;
                // $deptPrice = total price of discount items found
                $deptPrice = 0;
                $findTax = 999;
                while ($row = $transDB->fetchRow($deptR)) {
                    // only tally discount item price if elible based on qualifier qty
                    if ($j < $qualQty) {
                        unset($curQty);
                        $curQty = $row['quantity'];
                        for ($i=0; $i<$curQty; $i++) {
                            $deptPrice += $row['value']; 
                            $j++;
                        }
                        if ($row['foodstamp'] && $row['tax'] < $findTax) {
                            $findTax = $row['tax'];
                        }
                    }
                }
                if ($findTax < 999) {
                    $tax = $findTax;
                }
                $price = $discoVal * $j;
                $value = ($deptPrice != 0) ? $price - $deptPrice : 0;
                $value *= -1;
                break;
            case "FD": // flat discount for departments
                // simply take off the requested amount
                // scales with quantity for by-weight items
                $value = $infoW["discountValue"];
                $valQ = "select department, quantity 
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                    and h.type in ('BOTH', 'DISCOUNT')
                    and l.total > 0
                    order by unitPrice asc ";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = $row[1] * $value;
                break;
            case "MD": // mix discount for departments
                // take off item value or discount value
                // whichever is less
                $value = $infoW["discountValue"];
                $valQ = "select department, l.total 
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                    and h.type in ('BOTH', 'DISCOUNT')
                    and l.total > 0
                    order by l.total desc ";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = ($row[1] < $value) ? $row[1] : $value;
                break;
            case "AD": // all department discount
                // apply discount across all items
                // scales with quantity for by-weight items
                $value = $infoW["discountValue"];
                $valQ = "select sum(quantity) 
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                    and h.type in ('BOTH', 'DISCOUNT')
                    and l.total > 0
                    order by unitPrice asc ";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = $row[1] * $value;
                break;
            case "FI": // flat discount for items
                // simply take off the requested amount
                // scales with quantity for by-weight items
                $value = $infoW["discountValue"];
                $valQ = "select l.upc, quantity 
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type in ('BOTH', 'DISCOUNT')
                    and l.total > 0
                    order by unitPrice asc";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = $row[1] * $value;
                break;
            case 'PI': // per-item discount
                    // take of the request amount times the
                    // number of matching items.
                $value = $infoW["discountValue"];
                $valQ = "
                    SELECT 
                       SUM(CASE WHEN ItemQtty IS NULL THEN 0 ELSE ItemQtty END) AS qty
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type in ('BOTH', 'DISCOUNT')";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                if ($row['qty'] > $infoW['minValue'] && $infoW['minType'] == 'Q-') {
                    $value *= $infoW['minValue'];
                } else {
                    $value = $row['qty'] * $value;
                }
                break;
            case 'PS': // per set of items
                $value = $infoW["discountValue"];

                $qualQ = "
                    SELECT 
                       SUM(CASE WHEN ItemQtty IS NULL THEN 0 ELSE ItemQtty END) AS qty
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type in ('BOTH', 'QUALIFIER')";
                $qualR = $transDB->query($qualQ);
                $qualW = $transDB->fetch_row($qualR);

                $discQ = "
                    SELECT 
                       SUM(CASE WHEN ItemQtty IS NULL THEN 0 ELSE ItemQtty END) AS qty
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type in ('BOTH', 'DISCOUNT')";
                $discR = $transDB->query($discQ);
                $discW = $transDB->fetch_row($discR);

                $sets = ($qualW['qty'] > $discW['qty']) ? $discW['qty'] : $qualW['qty'];
                $sets = ($sets % 2 == 0) ? $sets : $sets -= 1;
                $value = $sets * $value;
                break;
            case 'SC':
                $giftQ = "
                    SELECT COUNT(*) AS cards,
                       SUM(CASE WHEN total IS NULL THEN 0 ELSE total END) AS ttl
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                    and h.type in ('BOTH', 'DISCOUNT') AND l.trans_type='D'";
                $giftR = $transDB->query($giftQ);
                $giftW = $transDB->fetchRow($giftR);
                $freeCards = floor($giftW['ttl'] / $infoW['minValue']);
                $value = $infoW['discountValue'] * $freeCards;
                if ($value > 80) {
                    $value = 80;
                }
                $discountable = 0;
                $curR = $transDB->prepare("SELECT SUM(-1 * total) AS ttl FROM translog.localtemptrans WHERE upc='0049999900467'");
                $current = $transDB->getValue($curR);
                if ($value - $current) {
                    TransRecord::addtender('Store Credit', 'SC', $value - $current);
                }
                \CoreLocal::set("receiptToggle",1);
                break;
            case "F": // completely flat; no scaling for weight
                $value = $infoW["discountValue"];
                $discountable = 0;
                break;
            case "FC": // flat but capped at current amount due
                $value = $infoW["discountValue"];
                Database::getsubtotals();
                if ($value > $this->session->get('amtdue')) {
                    $value = $this->session->get('amtdue');
                }
                break;
            case "%": // percent discount on all items
                Database::getsubtotals();
                $value = $infoW["discountValue"] * $this->session->get("discountableTotal");
                break;
            case "%B": // better percent discount applies
                Database::getsubtotals();
                $couponDiscount = (int)($infoW['discountValue']*100);
                $value = 0;
                if ($couponDiscount > $this->session->get('percentDiscount')) {
                    // coupon discount is better than customer's discount
                    // apply coupon & zero out customer's discount
                    $value = $infoW["discountValue"] * $this->session->get("discountableTotal");
                    $this->session->set('percentDiscount', 0);
                    $transDB->query('UPDATE localtemptrans SET percentDiscount=0');
                }
                $discountable = 0;
                break;
            case "%I": // percent discount on all relevant items
                $valQ = "select sum(total), max(tax)
                    " . $this->baseSQL($transDB, $coupID, 'upc') . "
                    and h.type in ('BOTH', 'DISCOUNT')";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = $row[0] * $infoW["discountValue"];
                $tax = $row[1];
                break;
            case "%D": // percent discount on all items in give department(s)
                $valQ = "select sum(total) 
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                    and h.type in ('BOTH', 'DISCOUNT') AND l.discountable >= 0 and trans_subtype != 'IC'";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = $row[0] * $infoW["discountValue"];
                break;
            case "%S": // percent discount on all items in give department(s)
                        // excluding sale items
                $valQ = "select sum(total) 
                    " . $this->baseSQL($transDB, $coupID, 'department') . "
                    and h.type in ('BOTH', 'DISCOUNT') AND l.discounttype = 0";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = $row[0] * $infoW["discountValue"];
                break;
            case "%E": // better percent discount applies to specified department only
                Database::getsubtotals();
                $couponDiscount = (int)($infoW['discountValue']*100);
                $value = 0;
                if ($couponDiscount > $this->session->get('percentDiscount')) {
                    // coupon discount is better than customer's discount
                    // apply coupon & exclude those items from customer's discount
                    $valQ = "select sum(total) 
                        " . $this->baseSQL($transDB, $coupID, 'department') . "
                        and h.type in ('BOTH', 'DISCOUNT')";
                    $valR = $transDB->query($valQ);
                    $row = $transDB->fetch_row($valR);
                    $value = $row[0] * $infoW["discountValue"];                 

                    $clearQ = "
                        UPDATE localtemptrans AS l 
                            INNER JOIN " . $this->session->get('pDatabase') . $transDB->sep() . "houseCouponItems AS h ON l.department = h.upc
                        SET l.discountable=0
                        WHERE h.coupID = " . $coupID . "
                            AND h.type IN ('BOTH', 'DISCOUNT')";
                    $clearR = $transDB->query($clearQ);
                }
                break;
            case 'PD': // modify customer percent discount
                   // rather than add line-item
                $couponPD = $infoW['discountValue'] * 100;
                DiscountModule::updateDiscount(new DiscountModule($couponPD, 'HouseCoupon'));
                // still need to add a line-item with the coupon UPC to the
                // transaction to track usage
                $value = 0;
                $description = $couponPD . ' % Discount Coupon';
                break;
            case 'OD': // override customer percent discount
                   // rather than add line-item
                $couponPD = $infoW['discountValue'] * 100;
                DiscountModule::updateDiscount(new DiscountModule(0, 'custdata'));
                DiscountModule::updateDiscount(new DiscountModule($couponPD, 'HouseCoupon'));
                // still need to add a line-item with the coupon UPC to the
                // transaction to track usage
                $value = 0;
                $description = $couponPD . ' % Discount Coupon';
                break;
            case "%Q": //set quantity discount
                $valQ = "select sum(total)
                " . $this->baseSQL($transDB, $coupID, 'department') . "
                and h.type in ('BOTH', 'DISCOUNT') AND l.discountable >= 0";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $value = $row[0] * $infoW["discountValue"];
                break;
            case "FE": // flat discount that turns off other discounts.
                //how much off is the customer getting;
                Database::getsubtotals();
                $couponPD = 20/$this->session->get('amtdue');
                if ($couponPD > $this->session->get('percentDiscount')) {
                    $prefix = $this->session->get('houseCouponPrefix');
                    if ($prefix == '') {
                        $prefix = '00499999';
                    }
                    $upc = $prefix . str_pad($coupID, 5, '0', STR_PAD_LEFT);
                    $usedValue = 0;
                    $usedQ = 'SELECT SUM(-total) as usedValue FROM localtemptrans AS l WHERE l.upc ='.$upc;
                    $usedR = $transDB->query($usedQ);
                    $usedW = $transDB->fetch_row($usedR);
                    if ($usedW) {  $usedValue = $usedW['usedValue'];  }
                    $valQ = "SELECT sum(total)
                    FROM localtemptrans AS l
                    WHERE l.trans_type in ('D','I')";
                    $valR = $transDB->query($valQ);
                    $row = $transDB->fetch_row($valR);
                    $scale = floor($row[0]/100);
                    if($scale > 3) {$scale = 2;}
                    $value = $infoW["discountValue"] * $scale;
                    if($value > $infoW['maxValue'] - $usedValue) {
                        $value = $infoW['maxValue'] - $usedValue;
                    }
                    $discountable = 0;
                    DiscountModule::updateDiscount(new DiscountModule(0, 'custdata'));
                } else {

                }
                break;
        }

        if ($infoW['maxValue'] > 0 && $value > $infoW['maxValue']) {
            $value = $infoW['maxValue'];
        }

        return array('value' => $value, 'department' => $infoW['department'], 'description' => $description, 'discountable'=>$discountable, 'tax'=>$tax);
    }

    public function handleVoid($upc, $json)
    {
        $coupID = ltrim(substr($ubc,5), "0");
        $infoW = $this->lookupCoupon($coupID);
        if ($infoW === false) {
            return $infoW;
        }

        $transDB = Database::tDataConnect();
        /* if we got this far, the coupon
         * should be valid
         */
        $value = 0;
        $discountable = 1;
        $tax = 0;
        switch ($infoW["discountType"]) {
            case "%E": // better percent discount applies to specified department only
                Database::getsubtotals();
                $couponDiscount = (int)($infoW['discountValue']*100);
                $value = 0;
                if ($couponDiscount > $this->session->get('percentDiscount')) {
                    // coupon discount is better than customer's discount
                    // apply coupon & exclude those items from customer's discount
                    $valQ = "select sum(total) 
                        " . $this->baseSQL($transDB, $coupID, 'department') . "
                        and h.type in ('BOTH', 'DISCOUNT')";
                    $valR = $transDB->query($valQ);
                    $row = $transDB->fetch_row($valR);
                    $value = $row[0] * $infoW["discountValue"];                 

                    $clearQ = "
                        UPDATE localtemptrans AS l 
                            INNER JOIN " . $this->session->get('pDatabase') . $transDB->sep() . "houseCouponItems AS h ON l.department = h.upc
                        SET l.discountable=0
                        WHERE h.coupID = " . $coupID . "
                            AND h.type IN ('BOTH', 'DISCOUNT')";
                    $clearR = $transDB->query($clearQ);
                }
                break;
            case 'PD': // modify customer percent discount
                   // rather than add line-item
                $couponPD = $infoW['discountValue'] * 100;
                DiscountModule::updateDiscount(new DiscountModule($couponPD, 'HouseCoupon'));
                // still need to add a line-item with the coupon UPC to the
                // transaction to track usage
                $value = 0;
                $description = $couponPD . ' % Discount Coupon';
                break;
            case 'OD': // override customer percent discount
                   // rather than add line-item
                $couponPD = $infoW['discountValue'] * 100;
                DiscountModule::updateDiscount(new DiscountModule(0, 'custdata'));
                DiscountModule::updateDiscount(new DiscountModule($couponPD, 'HouseCoupon'));
                // still need to add a line-item with the coupon UPC to the
                // transaction to track usage
                $value = 0;
                $description = $couponPD . ' % Discount Coupon';
                break;
            case "FE": // flat discount that turns off other discounts.
                $prefix = $this->session->get('houseCouponPrefix');
                if ($prefix == '') {
                    $prefix = '00499999';
                }
                $upc = $prefix . str_pad($coupID, 5, '0', STR_PAD_LEFT);
                $usedValue = 0;
                $usedQ = 'SELECT SUM(-total) as usedValue FROM localtemptrans AS l WHERE l.upc ='.$upc;
                $usedR = $transDB->query($usedQ);
                $usedW = $transDB->fetch_row($usedR);
                if ($usedW) {  $usedValue = $usedW['usedValue'];  }
                $valQ = "SELECT sum(total)
                FROM localtemptrans AS l
                WHERE l.trans_type in ('D','I')";
                $valR = $transDB->query($valQ);
                $row = $transDB->fetch_row($valR);
                $scale = floor($row[0]/100);
                if($scale > 3) {$scale = 2;}
                $value = $infoW["discountValue"] * $scale;
                if($value > $infoW['maxValue'] - $usedValue) {
                    $value = $infoW['maxValue'] - $usedValue;
                }
                $discountable = 0;
                DiscountModule::updateDiscount(new DiscountModule(0, 'custdata'));
                break;
            default:
                return false;
                break;
        }

    }

    /**
      This FROM/WHERE is super repetitive
    */
    private function baseSQL($dbc, $coupID, $mode='upc')
    {
        $ret = '
            FROM localtemptrans AS l
                INNER JOIN ' . $this->session->get('pDatabase') . $dbc->sep() . 'houseCouponItems AS h 
                ON h.upc=' . ($mode=='upc' ? 'l.upc' : 'l.department') . '
            WHERE h.coupID=' . ((int)$coupID);
        return $ret;
    }
}

