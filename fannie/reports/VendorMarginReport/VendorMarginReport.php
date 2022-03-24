<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class VendorMarginReport extends FannieReportPage 
{
    public $description = '[Vendor Margin Report] Show Target Margin by vendor and department.'; 
    public $report_set = 'Vendors';
    protected $title = "Fannie : Vendor Margin Report";
    protected $header = "Vendor Margin Report";
    protected $report_cache = 'none';
    protected $grandTTL = 1;
    protected $multi_report_mode = True;
    protected $sortable = True;

    protected $report_headers = array('upc','item', 'brand','price', 'cost', 'margin', 'dept_no', 'dept_name', 'in use');
    protected $required_fields = array('vendor');

	function report_description_content() {
		return(array('<p></p>'));
	}

	function fetch_report_data()
    {
		global $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB, $FANNIE_EQUITY_DEPARTMENTS,
			$FANNIE_COOP_ID;
        $dbc = FannieDB::get($FANNIE_OP_DB);
		$vendor = FormLib::get_form_value('vendor');
		$superDept = FormLib::get_form_value('department');
		$data = array();

		$departments = $this->getDeptRange($superDept);

		//setup a set of department arrays.
		$deptList = "";
		$report[] = array();
		foreach ($departments as $key => $department) {
			$deptArray = array($department[1] => array());
			$report = array_merge($report, $deptArray);
			$deptList .= $department[0].", ";
		}
		$deptList = substr($deptList, 0, -2);
		//echo var_dump($report);
		//echo $deptList;

		$args = array($vendor);

		$query = $dbc->prepare("SELECT p.upc, p.description, p.brand, p.normal_price, p.cost, (p.normal_price - p.cost)/p.normal_price as margin, p.department, d.dept_name, p.inUse
				from core_op.products p
				join core_op.departments d on p.department = d.dept_no
				where p.inUse = 1 and p.department in ({$deptList}) and p.store_id =1 and p.default_vendor_id =?
				order by p.department");
		//echo $query;
		$result = $dbc->execute($query,$args);


		while($row = $dbc->fetchRow($result)) {
            $str = $row[7];
            //echo $str;
            $report[$str][] = array($row[0],$row[1],$row[2],$row[3],$row[4],sprintf("%.2f%%", $row[5] * 100),$row[6],$row[7],$row[8]);
        }

        foreach ($report as $key => $table) {
        	if(!empty($table)) {
        		$data[] = $table;
        	}
        }

        //echo var_dump($report);

		//$report = array($dept1,$dept2);
	
		$data = $data;
		
		return $data;
	}

	private function getDeptRange($superDept) {
		$dbc = $this->connection;
		$ret = array();
		
		$where = '';
		if ($superDept != 0) {
			$where = " WHERE s.superID = {$superDept}";
		}

		$query = "SELECT d.dept_no, d.dept_name FROM core_op.departments d
				  JOIN core_op.superdepts s on d.dept_no = s.dept_ID
				  {$where} ORDER BY d.dept_no";


		$prep = $dbc->prepare($query);
		$result = $dbc->execute($prep,array());

        while($row = $dbc->fetchRow($result)) {
            $ret[] = array($row[0],$row[1]);
        }

		return $ret;
	}


	function calculate_footers($data)
    {
    	$price = 0.0;
        $cost = 0.0;
        foreach($data as $row) {
            if (isset($row['meta'])) {
                continue;
            }
            $price += $row[3];
            $cost += $row[4];
        }
        return array('','','',$price, $cost, sprintf("%.2f%%", ($price - $cost)/$price)* 100);
	}

	function form_content()
    {
    	$this->addScript('../../item/autocomplete.js');
    	$vendor = new VendorsModel($this->connection);
 		$superDepts = new SuperDeptNamesModel($this->connection);
        
        ob_start();
        ?>
        <form action=VendorMarginReport.php method=get>
        <div class="form-group">
        	   <div class="form-group">
        			<label>Vendor</label>
        			<select name="vendor" class="form-control chosen-select">
            			<?php echo $vendor->toOptions(); ?>
        			</select> 
        			<label>Department</label>
        			<select name="department" class="form-control chosen-select">
        				<?php echo $superDepts->toOptions(-999); ?>
        			</select>		          
    			</div>
        </div>
        <div class="form-group">
            <label>Excel <input type=checkbox name=excel /></label>
        </div>
        <p>
        <button type=submit name=submit value="Submit"
            class="btn btn-default">Submit</button>
        </p>
        </form>
        <?php
        return ob_get_clean();
	}

}

FannieDispatch::conditionalExec(false);

?>
