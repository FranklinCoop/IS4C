<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

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

/**
  @class BatchesModel
*/
class BatchesModel extends BasicModel 
{

    protected $name = "batches";
    protected $preferred_db = 'op';

    protected $columns = array(
    'batchID' => array('type'=>'INT', 'primary_key'=>True, 'increment'=>True),
    'startDate' => array('type'=>'DATETIME'),
    'endDate' => array('type'=>'DATETIME'),
    'batchName' => array('type'=>'VARCHAR(80)'),
    'batchType' => array('type'=>'SMALLINT'),
    'discountType' => array('type'=>'SMALLINT'),
    'priority' => array('type'=>'INT'),
    'owner' => array('type'=>'VARCHAR(50)'),
    'transLimit' => array('type'=>'TINYINT', 'default'=>0),
    'notes' => array('type'=>'TEXT'),
    'applied' => array('type'=>'TINYINT', 'default'=>0),
    );

    public function doc()
    {
        return '
Depends on:
Wthis->* batchType

Use:
This table contains basic information
for a sales batch. On startDate, items
in batchList with a corresponding batchID
go on sale (as specified in that table) and
with the discount type set here. On endDate,
those same items revert to normal pricing.
        ';
    }

    /**
      Start batch immediately
      @param $id [int] batchID

      This helper method masks some ugly queries
      that cope with UPC vs Likecode values plus
      differences in multi-table UPDATE syntax for
      different SQL flavors. Also provides some
      de-duplication.
    */
    public function forceStartBatch($id, $upc=false)
    {
        $b_def = $this->connection->tableDefinition($this->name);
        $bt_def = $this->connection->tableDefinition('batchType');
        $exit = isset($bt_def['exitInventory']) ? 'exitInventory' : '0 AS exitInventory';
        $batchInfoQ = $this->connection->prepare("SELECT batchType,discountType,{$exit} FROM batches AS b
            LEFT JOIN batchType AS t ON b.batchType=t.batchTypeID WHERE batchID = ?");
        $batchInfoW = $this->connection->getRow($batchInfoQ,array($id));
        if ($batchInfoW['discountType'] < 0) {
            return;
        }

        $optionalS = ($upc !== false) ? ' AND p.upc = ? ' : '';

        $forceQ = "";
        $forceLCQ = "";
        $log = FannieLogger::factory();
        // verify limit columns exist
        $p_def = $this->connection->tableDefinition('products');
        $bl_def = $this->connection->tableDefinition('batchList');
        $costChange = '';
        if (isset($bl_def['cost']) && $batchInfoW['discountType'] < 1) {
            $costChange = ", p.cost = CASE WHEN l.cost IS NOT NULL AND l.cost > 0 THEN l.cost ELSE p.cost END";
        } else {
            $costChange = ", p.special_cost = CASE WHEN l.cost IS NOT NULL AND l.cost > 0 THEN l.cost ELSE 0 END";
        }
        $log->debug("Forcing batch $id");
        $has_limit = (isset($b_def['transLimit']) && isset($p_def['special_limit'])) ? true : false;
        $isHQ = FannieConfig::config('STORE_MODE') == 'HQ' ? true : false;
        if ($batchInfoW['discountType'] > 0) { // item is going on sale
            $forceQ="
                UPDATE products AS p
                    INNER JOIN batchList AS l ON p.upc=l.upc
                    INNER JOIN batches AS b ON l.batchID=b.batchID
                    " . ($isHQ ? ' INNER JOIN StoreBatchMap AS m ON b.batchID=m.batchID and p.store_id=m.storeID ' : '') . "
                SET p.start_date = b.startDate,
                    p.end_date=b.endDate,
                    p.special_price=CASE WHEN l.pricemethod=2 THEN p.normal_price ELSE l.salePrice END,
                    p.specialgroupprice=CASE WHEN l.salePrice < 0 THEN -1*l.salePrice ELSE l.salePrice END,
                    p.specialpricemethod=l.pricemethod,
                    p.specialquantity=l.quantity,
                    " . ($has_limit ? 'p.special_limit=b.transLimit,' : '') . "
                    p.discounttype=b.discounttype,
                    p.mixmatchcode = CASE 
                        WHEN l.pricemethod IN (3,4) AND l.salePrice >= 0 THEN convert(l.batchID,char)
                        WHEN l.pricemethod IN (3,4) AND l.salePrice < 0 THEN convert(-1*l.batchID,char)
                        WHEN l.pricemethod = 0 AND l.quantity > 0 THEN concat('b',convert(l.batchID,char))
                        ELSE p.mixmatchcode 
                    END ,
                    p.modified = NOW()
                    {$costChange}
                WHERE l.upc not like 'LC%'
                    and l.batchID = ?
                    $optionalS";
            if (isset($p_def['batchID'])) {
                $forceQ = str_replace('NOW()', 'NOW(), p.batchID=b.batchID', $forceQ);
            }
                
            $forceLCQ = "
                UPDATE products AS p
                    INNER JOIN upcLike AS v ON v.upc=p.upc
                    INNER JOIN batchList as l ON l.upc=concat('LC',convert(v.likecode,char))
                    INNER JOIN batches AS b ON b.batchID=l.batchID
                    " . ($isHQ ? ' INNER JOIN StoreBatchMap AS m ON b.batchID=m.batchID and p.store_id=m.storeID ' : '') . "
                SET p.start_date = b.startDate,
                    p.end_date=b.endDate,
                    p.special_price=CASE WHEN l.pricemethod=2 THEN p.normal_price ELSE l.salePrice END,
                    p.specialgroupprice=CASE WHEN l.salePrice < 0 THEN -1*l.salePrice ELSE l.salePrice END,
                    p.specialpricemethod=l.pricemethod,
                    p.specialquantity=l.quantity,
                    " . ($has_limit ? 'p.special_limit=b.transLimit,' : '') . "
                    p.discounttype = b.discounttype,
                    p.mixmatchcode = CASE 
                        WHEN l.pricemethod IN (3,4) AND l.salePrice >= 0 THEN convert(l.batchID,char)
                        WHEN l.pricemethod IN (3,4) AND l.salePrice < 0 THEN convert(-1*l.batchID,char)
                        WHEN l.pricemethod = 0 AND l.quantity > 0 THEN concat('b',convert(l.batchID,char))
                        ELSE p.mixmatchcode 
                    END,
                    p.modified = NOW()
                    {$costChange}
                WHERE l.upc LIKE 'LC%'
                    AND l.batchID = ?
                    $optionalS";
            if (isset($p_def['batchID'])) {
                $forceLCQ = str_replace('NOW()', 'NOW(), p.batchID=b.batchID', $forceLCQ);
            }

            if ($this->connection->dbmsName() == 'mssql') {
                $forceQ="UPDATE products
                    SET start_date = b.startDate, 
                    end_date=b.endDate,
                    special_price=l.salePrice,
                        specialgroupprice=CASE WHEN l.salePrice < 0 THEN -1*l.salePrice ELSE l.salePrice END,
                    specialpricemethod=l.pricemethod,
                    specialquantity=l.quantity,
                    " . ($has_limit ? 'special_limit=b.transLimit,' : '') . "
                    discounttype=b.discounttype,
                    mixmatchcode = CASE 
                    WHEN l.pricemethod IN (3,4) AND l.salePrice >= 0 THEN convert(varchar,l.batchID)
                    WHEN l.pricemethod IN (3,4) AND l.salePrice < 0 THEN convert(varchar,-1*l.batchID)
                    WHEN l.pricemethod = 0 AND l.quantity > 0 THEN 'b'+convert(varchar,l.batchID)
                    ELSE p.mixmatchcode 
                    END ,
                    p.modified = getdate()
                    {$costChange}
                    FROM products as p, 
                    batches as b, 
                    batchList as l 
                    WHERE l.upc = p.upc
                    and l.upc not like 'LC%'
                    and b.batchID = l.batchID
                    and b.batchID = ?
                    $optionalS";

                $forceLCQ = "update products set special_price = l.salePrice,
                    end_date = b.endDate,start_date=b.startDate,
                    discounttype = b.discounttype,
                    specialpricemethod=l.pricemethod,
                    specialquantity=l.quantity,
                    specialgroupprice=CASE WHEN l.salePrice < 0 THEN -1*l.salePrice ELSE l.salePrice END,
                    " . ($has_limit ? 'special_limit=b.transLimit,' : '') . "
                    mixmatchcode = CASE 
                        WHEN l.pricemethod IN (3,4) AND l.salePrice >= 0 THEN convert(varchar,l.batchID)
                        WHEN l.pricemethod IN (3,4) AND l.salePrice < 0 THEN convert(varchar,-1*l.batchID)
                        WHEN l.pricemethod = 0 AND l.quantity > 0 THEN 'b'+convert(varchar,l.batchID)
                        ELSE p.mixmatchcode 
                    END ,
                    p.modified = getdate()
                    {$costChange}
                    from products as p left join
                    upcLike as v on v.upc=p.upc left join
                    batchList as l on l.upc='LC'+convert(varchar,v.likecode)
                    left join batches as b on b.batchID = l.batchID
                    where b.batchID=?
                    $optionalS";
            }
        } elseif ($batchInfoW['discountType'] == 0) { // normal price is changing
            $log->debug("$id is a price change batch");
            $forceQ = "
                UPDATE products AS p
                    INNER JOIN batchList AS l ON l.upc=p.upc
                    " . ($isHQ ? ' INNER JOIN StoreBatchMap AS m ON l.batchID=m.batchID and p.store_id=m.storeID ' : '') . "
                SET p.normal_price = l.salePrice,
                    p.modified = now()
                    {$costChange}
                WHERE l.upc not like 'LC%'
                    AND l.batchID = ?
                    $optionalS";

            $scaleQ = "
                UPDATE scaleItems AS s
                    INNER JOIN batchList AS l ON l.upc=s.plu
                SET s.price = l.salePrice,
                    s.modified = now()
                WHERE l.upc not like 'LC%'
                    AND l.batchID = ?
                    $optionalS";

            $forceLCQ = "
                UPDATE products AS p
                    INNER JOIN upcLike AS v ON v.upc=p.upc 
                    INNER JOIN batchList as l on l.upc=concat('LC',convert(v.likecode,char))
                    " . ($isHQ ? ' INNER JOIN StoreBatchMap AS m ON l.batchID=m.batchID and p.store_id=m.storeID ' : '') . "
                SET p.normal_price = l.salePrice,
                    p.modified=now()
                    {$costChange}
                WHERE l.upc LIKE 'LC%'
                    AND l.batchID = ?
                    $optionalS";
            $log->debug($forceLCQ);

            if ($this->connection->dbmsName() == 'mssql') {
                $costChange = str_replace('p.cost', 'cost', $costChange);
                $forceQ = "UPDATE products
                      SET normal_price = l.salePrice,
                      modified = getdate()
                      {$costChange}
                      FROM products as p,
                      batches as b,
                      batchList as l
                      WHERE l.upc = p.upc
                      AND l.upc not like 'LC%'
                      AND b.batchID = l.batchID
                      AND b.batchID = ?
                      $optionalS";

                $scaleQ = "UPDATE scaleItems
                      SET price = l.salePrice,
                      modified = getdate()
                      FROM scaleItems as s,
                      batches as b,
                      batchList as l
                      WHERE l.upc = s.plu
                      AND l.upc not like 'LC%'
                      AND b.batchID = l.batchID
                      AND b.batchID = ?
                      $optionalS";

                $forceLCQ = "update products set normal_price = b.salePrice,
                    modified=getdate()
                    {$costChange}
                    from products as p left join
                    upcLike as v on v.upc=p.upc left join
                    batchList as b on b.upc='LC'+convert(varchar,v.likecode)
                    where b.batchID=?
                    $optionalS";
            }

        }

        $forceP = $this->connection->prepare($forceQ);
        $forceA = array($id);
        if ($upc !== false)
            $forceA[] = $upc;
        $forceR = $this->connection->execute($forceP,$forceA);
        if (!empty($scaleQ)) {
            $scaleP = $this->connection->prepare($scaleQ);
            $scaleA = array($id);
            if ($upc !== false) {
                $scaleA[] = $upc;
            }
            $scaleR = $this->connection->execute($scaleP,$scaleA);
        }
        $forceLCP = $this->connection->prepare($forceLCQ);
        $forceLCA = array($id);
        if ($upc !== false) {
            $forceLCA[] = $upc;
        }
        $forceR = $this->connection->execute($forceLCP,$forceLCA);
        if ($batchInfoW['discountType'] != 0 && $batchInfoW['exitInventory'] == 1) {
            $storeP = $this->connection->prepare('SELECT storeID FROM StoreBatchMap WHERE batchID=?');
            $stores = $this->connection->getAllRows($storeP, array($id));
            $stores = array_map(function ($i) { return $i['storeID']; }, $stores);
            list($inStr, $args) = $this->connection->safeInClause($stores);
            $invP = $this->connection->prepare("
                UPDATE InventoryCounts AS i
                SET i.par=0
                WHERE i.mostRecent=1
                    AND i.storeID IN ({$inStr})
                    AND i.upc IN (
                        SELECT b.upc FROM batchList AS b WHERE b.batchID=?
                    )");
            $args[] = $id;
            $invR = $this->connection->execute($invP, $args);

            $args = array( (1 << (20 - 1)), date('Y-m-d H:i:s'));
            list($inStr, $args) = $this->connection->safeInClause($stores, $args);
            $args[] = $id;
            if ($upc !== false) {
                $args[] = $upc;
            }
            $prodP = $this->connection->prepare("
                UPDATE products AS p
                    INNER JOIN batchList AS b ON p.upc=b.upc
                SET numflag = numflag | ?,
                    modified = ?
                WHERE p.store_id IN ({$inStr})
                    AND b.batchID=?
                    $optionalS"); 
            $this->connection->execute($prodP, $args);
        }

        if ($batchInfoW['discountType'] == 0) {
            //$this->scaleSendPrice($id);
        }

        $updateType = ($batchInfoW['discountType'] == 0) ? ProdUpdateModel::UPDATE_PC_BATCH : ProdUpdateModel::UPDATE_BATCH;
        $this->finishForce($id, $updateType, $has_limit);
        
        $bu = new BatchUpdateModel($this->connection);
        $bu->batchID($id);
        if ($upc !== false) {
            $bu->logUpdate($bu::UPDATE_APPLIED);
        } else {
            $bu->logUpdate($bu::UPDATE_FORCED);
        }
    }

    /**
      Stop batch immediately
      @param $id [int] batchID
    */
    public function forceStopBatch($id)
    {
        // verify limit columns exist
        $b_def = $this->connection->tableDefinition($this->name);
        $p_def = $this->connection->tableDefinition('products');
        $has_limit = (isset($b_def['transLimit']) && isset($p_def['special_limit'])) ? true : false;

        $batchP = $this->connection->prepare('
            SELECT b.discountType,
                CASE WHEN ' . $this->connection->curdate() . ' BETWEEN b.startDate AND b.endDate
                THEN 1 ELSE 0 END AS current
            FROM batches AS b
            WHERE batchID=?');
        $self = $this->connection->getRow($batchP, array($id));
        if ($self == false) {
            // cannot find batch. do not change products
            return false;
        }
        if ($self['discountType'] <= 0) {
            // price change batch or tracking batch. nothing to stop.
            return true;
        }
        if ($self['current'] == 0) {
            // batch is not currently running. nothing to stop.
            return true;
        }

        // unsale regular items
        $unsaleQ = "
            UPDATE products AS p 
                LEFT JOIN batchList as b ON p.upc=b.upc
            SET special_price=0,
                specialpricemethod=0,
                specialquantity=0,
                specialgroupprice=0,
                discounttype=0,
                " . ($has_limit ? 'special_limit=0,' : '') . "
                start_date='1900-01-01',
                end_date='1900-01-01'
            WHERE b.upc NOT LIKE '%LC%'
                AND b.batchID=?";
        if ($this->connection->dbmsName() == "mssql") {
            $unsaleQ = "UPDATE products SET special_price=0,
                specialpricemethod=0,specialquantity=0,
                specialgroupprice=0,discounttype=0,
                " . ($has_limit ? 'special_limit=0,' : '') . "
                start_date='1900-01-01',end_date='1900-01-01'
                FROM products AS p, batchList as b
                WHERE p.upc=b.upc AND b.upc NOT LIKE '%LC%'
                AND b.batchID=?";
        }
        $prep = $this->connection->prepare($unsaleQ);
        $unsaleR = $this->connection->execute($prep,array($id));

        $unsaleLCQ = "
            UPDATE products AS p 
                LEFT JOIN upcLike AS v ON v.upc=p.upc 
                LEFT JOIN batchList AS l ON l.upc=concat('LC',convert(v.likeCode,char))
            SET special_price=0,
                specialpricemethod=0,
                specialquantity=0,
                specialgroupprice=0,
                " . ($has_limit ? 'special_limit=0,' : '') . "
                p.discounttype=0,
                start_date='1900-01-01',
                end_date='1900-01-01'
            WHERE l.upc LIKE '%LC%'
                AND l.batchID=?";
        if ($this->connection->dbmsName() == "mssql") {
            $unsaleLCQ = "UPDATE products
                SET special_price=0,
                specialpricemethod=0,specialquantity=0,
                specialgroupprice=0,discounttype=0,
                " . ($has_limit ? 'special_limit=0,' : '') . "
                start_date='1900-01-01',end_date='1900-01-01'
                FROM products AS p LEFT JOIN
                upcLike AS v ON v.upc=p.upc LEFT JOIN
                batchList AS l ON l.upc=concat('LC',convert(v.likeCode,char))
                WHERE l.upc LIKE '%LC%'
                AND l.batchID=?";
        }
        $prep = $this->connection->prepare($unsaleLCQ);
        $unsaleLCR = $this->connection->execute($prep,array($id));

        $updateType = ProdUpdateModel::UPDATE_PC_BATCH;
        $this->finishForce($id, $updateType, $has_limit);
        $bu = new BatchUpdateModel($this->connection);
        $bu->batchID($id);
        $bu->logUpdate($bu::UPDATE_STOPPED);
    }

    /**
      Cleanup after forcibly starting or stopping a sales batch
      - Update lane item records to reflect on/off sale
      - Log changes to prodUpdate
      @param $id [int] batchID
      @param $updateType [cost] ProdUpdateModel update type
      @param $has_limit [boolean] products.special_limit and batches.transLimit
        columns are present
      
      Separate method since it's identical for starting
      and stopping  
    */
    private function finishForce($id, $updateType, $has_limit=true)
    {
        $columnsP = $this->connection->prepare('
            SELECT p.upc,
                p.normal_price,
                p.special_price,
                p.modified,
                p.specialpricemethod,
                p.specialquantity,
                p.specialgroupprice,
                ' . ($has_limit ? 'p.special_limit,' : '') . '
                p.discounttype,
                p.mixmatchcode,
                p.start_date,
                p.end_date
            FROM products AS p
                INNER JOIN batchList AS b ON p.upc=b.upc
            WHERE b.batchID=?
                AND p.store_id=?');
        $lcColumnsP = $this->connection->prepare('
            SELECT p.upc,
                p.normal_price,
                p.special_price,
                p.modified,
                p.specialpricemethod,
                p.specialquantity,
                p.specialgroupprice,
                ' . ($has_limit ? 'p.special_limit,' : '') . '
                p.discounttype,
                p.mixmatchcode,
                p.start_date,
                p.end_date
            FROM products AS p
                INNER JOIN upcLike AS u ON p.upc=u.upc
                INNER JOIN batchList AS b 
                    ON b.upc = ' . $this->connection->concat("'LC'", $this->connection->convert('u.likeCode', 'CHAR'), '') . '
            WHERE b.batchID=?
                AND p.store_id=?');

        /**
          Get changed columns for each product record
        */
        $upcs = array();
        $columnsR = $this->connection->execute($columnsP, array($id, FannieConfig::config('STORE_ID')));
        while ($w = $this->connection->fetch_row($columnsR)) {
            $upcs[$w['upc']] = $w;
        }
        $columnsR = $this->connection->execute($lcColumnsP, array($id, FannieConfig::config('STORE_ID')));
        while ($w = $this->connection->fetch_row($columnsR)) {
            $upcs[$w['upc']] = $w;
        }

        $update = new ProdUpdateModel($this->connection);
        $update->logManyUpdates(array_keys($upcs), $updateType);

        $applyP = $this->connection->prepare("UPDATE batches SET applied=1 WHERE batchID=?");
        $this->connection->execute($applyP, array($id));

        $updateQ = '
            UPDATE products AS p SET
                p.normal_price = ?,
                p.special_price = ?,
                p.modified = ?,
                p.specialpricemethod = ?,
                p.specialquantity = ?,
                p.specialgroupprice = ?,
                p.discounttype = ?,
                p.mixmatchcode = ?,
                p.start_date = ?,
                p.end_date = ?
                ' . ($has_limit ? ',p.special_limit = ?' : '') . '
            WHERE p.upc = ?';

        /**
          Update all records on each lane before proceeding
          to the next lane. Hopefully faster / more efficient
        */
        $FANNIE_LANES = FannieConfig::config('LANES');
        for ($i = 0; $i < count($FANNIE_LANES); $i++) {
            try {
                $lane_sql = new SQLManager($FANNIE_LANES[$i]['host'],$FANNIE_LANES[$i]['type'],
                    $FANNIE_LANES[$i]['op'],$FANNIE_LANES[$i]['user'],
                    $FANNIE_LANES[$i]['pw']);
            } catch (Exception $ex) {
                continue;
            }
            
            if (!isset($lane_sql->connections[$FANNIE_LANES[$i]['op']]) || $lane_sql->connections[$FANNIE_LANES[$i]['op']] === false) {
                // connect failed
                continue;
            }

            $updateP = $lane_sql->prepare($updateQ);
            foreach ($upcs as $upc => $data) {
                if (intval($upc) < 1000000000000) {
                    $args = array(
                        $data['normal_price'],
                        $data['special_price'],
                        $data['modified'],
                        $data['specialpricemethod'],
                        $data['specialquantity'],
                        $data['specialgroupprice'],
                        $data['discounttype'],
                        $data['mixmatchcode'],
                        $data['start_date'],
                        $data['end_date'],
                    );
                    if ($has_limit) {
                        $args[] = $data['special_limit'];
                    }
                    $args[] = $upc;
                    $lane_sql->execute($updateP, $args);
                }
            }
        }

        /**
         * This is somewhat redundant, but it lets item
         * callbacks to be triggered in the background
         * rather than blocking execution
         */
        $queue = new COREPOS\Fannie\API\jobs\QueueManager();
        $queue->add(array(
            'class' => 'COREPOS\\Fannie\\API\\jobs\\SyncItem',
            'data' => array(
                'upc' => array_keys($upcs),
            ),
        ));

        if (FannieConfig::config('STORE_MODE') === 'HQ' && class_exists('\\Datto\\JsonRpc\\Http\\Client')) {
            $prep = $this->connection->prepare('
                SELECT webServiceUrl FROM Stores WHERE hasOwnItems=1 AND storeID<>?
                ');
            $res = $this->connection->execute($prep, array(\FannieConfig::config('STORE_ID')));
            while ($row = $this->connection->fetchRow($res)) {
                $client = new \Datto\JsonRpc\Http\Client($row['webServiceUrl']);
                $client->query(time(), 'COREPOS\\Fannie\\API\\webservices\\FannieItemLaneSync', array('upc'=>array_keys($upcs), 'fast'=>true));
                $client->send();
            }
        }
    }

    /**
      Fetch all UPCs associated with a batch
      @param $batchID [optional]
      @return [array] of [string] UPC values
      If $batchID is omitted, the model's own batchID
      is used.
    */
    public function getUPCs($batchID=false)
    {
        if ($batchID === false) {
            $batchID = $this->batchID();
        }

        if ($batchID === null) {
            return array();
        }

        $upcs = array();
        $likecodes = array();
        $in_sql = '';
        $itemP = $this->connection->prepare('
            SELECT upc
            FROM batchList
            WHERE batchID=?');
        $itemR = $this->connection->execute($itemP, array($batchID));
        while ($itemW = $this->connection->fetchRow($itemR)) {
            if (substr($itemW['upc'], 0, 2) == 'LC') {
                $likecodes[] = substr($itemW['upc'], 2);
                $in_sql .= '?,';
            } else {
                $upcs[] = $itemW['upc'];
            }
        }

        if (count($likecodes) > 0) {
            $in_sql = substr($in_sql, 0, strlen($in_sql)-1);
            $lcP = $this->connection->prepare('
                SELECT upc
                FROM upcLike
                WHERE likeCode IN (' . $in_sql . ')');
            $lcR = $this->connection->execute($lcP, $likecodes);
            while ($lcW = $this->connection->fetchRow($lcR)) {
                $upcs[] = $lcW['upc'];
            }
        }

        return $upcs;
    }

    public function scaleSendPrice($batchID)
    {
        $prep = $this->connection->prepare("SELECT upc, salePrice FROM batchList WHERE upc LIKE '002%' AND batchID=?");
        $rows = $this->connection->getAllRows($prep, array($batchID));
        if (count($rows) == 0) {
            return;
        }

        $scaleP = $this->connection->prepare('SELECT s.host, s.scaleType, s.scaleDeptName, s.serviceScaleID
            FROM ServiceScaleItemMap AS m
                INNER JOIN ServiceScales AS s ON s.serviceScaleID=m.serviceScaleID
            WHERE m.upc=?');
        foreach ($rows as $row) {
            $items = array();
            $items[] = array(
                'RecordType' => 'ChangeOneItem',
                'PLU' => COREPOS\Fannie\API\item\ServiceScaleLib::upcToPLU($row['upc']),
                'Price' => $row['salePrice'],
            );
            $scales = array();
            $scaleR = $this->connection->execute($scaleP, array($row['upc']));
            while ($scaleW = $this->connection->fetchRow($scaleR)) {
                $scales[] = array(
                    'host' => $scaleW['host'],
                    'type' => $scaleW['scaleType'],
                    'dept' => $scaleW['scaleDeptName'],
                    'id' => $scaleW['serviceScaleID'],
                    'new' => false,
                );
            }
            COREPOS\Fannie\API\item\HobartDgwLib::writeItemsToScales($items, $scales);
            COREPOS\Fannie\API\item\EpScaleLib::writeItemsToScales($items, $scales);
        }
    }

    protected function hookAddColumnowner()
    {
        // copy existing values from batchowner.owner to
        // new batches.owner column
        if ($this->connection->table_exists('batchowner')) {
            $dataR = $this->connection->query('SELECT batchID, owner FROM batchowner');
            $tempModel = new BatchesModel($this->connection);
            while($dataW = $this->connection->fetch_row($dataR)) {
                $tempModel->reset();
                $tempModel->batchID($dataW['batchID']);
                if ($tempModel->load()) {
                    $tempModel->owner($dataW['owner']);
                    $tempModel->save();
                }
            }
        }
    }
}

