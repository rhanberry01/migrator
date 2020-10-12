<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Auto_model extends CI_Model {
    public $local_db, $customer_code = array();
    public function __construct(){
		parent::__construct();
		$this->local_db = "branch_nova";
		$this->customer_code = $this->get_customer_code();

	}

	## start for additional formula ##
	##8282020

		public function get_last_po($supplier = null){
		$this->db = $this->load->database('default', TRUE);
		$sql = "select DISTINCT reference,supplier_id,trans_date from po_history 
				where supplier_id ='".$supplier."'
				ORDER BY trans_date desc LIMIT 1";
		$query = $this->db->query($sql);
	    $result = $query->row();
	    return $result;

	}



	public function get_received_history($po = null){
		$this->db = $this->load->database('default', TRUE);
		$sql = "select trans_date,stock_id,ord_qty,IFNULL(qty,0) as qty
				from po_history as poh
				LEFT JOIN received_history as rh ON
				poh.supplier_id = rh.VendorCode and 
				poh.reference = rh.PurchaseOrderNo and poh.stock_id = rh.ProductID
				WHERE  reference ='".$po."'";
		$query = $this->db->query($sql);
	    $result = $query->result();
	    return $result;

	}

	function insert_batch_average_offtake_history($data=array()){
			$this->db = $this->load->database("default", True);
			$this->db->insert_batch("average_offtake_history", $data);
	}


	function insert_batch_computations_history($data=array()){
			$this->db = $this->load->database("default", True);
			$this->db->insert_batch("computations_history", $data);
	}



	public function get_max_last_offtake($supp_code = null){
		$this->db = $this->load->database('default', TRUE);
		$sql = "SELECT max(date_added) as date_added,product_id,avg_off_take FROM `average_offtake_history`
				where supplier_code ='".$supp_code."' GROUP BY product_id";
		$query = $this->db->query($sql);
	    $result = $query->result();
	    return $result;


	}


	public function get_received_purchases($date){

		$this->db = $this->load->database($this->local_db, TRUE);
		$sql = "select cast(PostedDate as date) as PostedDate,
					   PurchaseOrderNo,
					   rl.ReceivingID,
					   r.VendorCode,
					   ProductID,
					   qty,
					   pack,
					   sum(totalqtypurchased) as qtypurchased 
				from Receiving as r
				INNER JOIN ReceivingLine as rl
				on r.ReceivingID = rl.ReceivingID
				where cast(PostedDate as date) >='".date('Y-m-d',strtotime('-30 days'))."' and PurchaseOrderNo <> '0' and  PurchaseOrderNo like '%PO%'
				GROUP BY PurchaseOrderNo,rl.ReceivingID,r.VendorCode,ProductID,qty,pack, cast(PostedDate as date)";
	    $res = $this->db->query($sql);
	    $res = $res->result_array();
	    return $res;
	} 



	public function insert_received_history_summary($rows,$date_posted)
    {
    	$this->db = $this->load->database("default", true);
    	foreach($rows as $row){

		$PostedDate = $row["PostedDate"];
		$PurchaseOrderNo = $row["PurchaseOrderNo"];
		$ReceivingID = $row["ReceivingID"];
		$VendorCode = $row["VendorCode"];
		$ProductID = $row["ProductID"];
		$totalqtypurchased = $row["qtypurchased"];
		$qty = $row["qty"];
		$pack = $row["pack"];

			$sql = " INSERT INTO received_history (PurchaseOrderNo,ReceivingID,VendorCode,ProductID,totalqtypurchased,po_qty,qty,pack,dateposted)
			VALUES('".$PurchaseOrderNo."',".$ReceivingID.",'".$VendorCode."',".$ProductID.",".$totalqtypurchased.",0,".$qty.",".$pack.",'".$PostedDate."') ";
			$stat = $this->db->query($sql);

	    }
    }

    public function get_po_purchases($date,$branch_code){
		$this->db = $this->load->database("branch_po", TRUE);
		$sql = "select trans_date,reference,supplier_id,stock_id,barcode,ord_qty from refs as r
				INNER JOIN purch_orders as pch
				on r.trans_id = pch.order_no and r.trans_type = pch.trans_type
				INNER JOIN purch_order_details as pchd  on  pchd.trans_type = pch.trans_type and pchd.order_no = pch.order_no
				where pch.trans_date >='".date('Y-m-d',strtotime('-30 days'))."' and r.trans_type ='16' and pch.auto_generate ='1' and status != 2  
				and pch.br_code ='".$branch_code."'";
		$res = $this->db->query($sql);
	    $res = $res->result_array();
	    return $res;
	}


	public function insert_rpo_history_summary($rows,$date_posted)
    {
    	$this->db = $this->load->database("default", true);
    	foreach($rows as $row){

		$trans_date = $row["trans_date"];
		$reference = $row["reference"];
		$supplier_id = $row["supplier_id"];
		$stock_id = $row["stock_id"];
		$barcode = $row["barcode"];
		$ord_qty = $row["ord_qty"];

			$sql = " INSERT INTO po_history (trans_date,reference,supplier_id,barcode,stock_id,ord_qty)
			VALUES('".$trans_date."','".$reference."','".$supplier_id."','".$barcode."','".$stock_id."','".$ord_qty."') ";
			$this->db->query($sql);

	    }
    } 


    function get_sales_w_eliminated_days($from,$to,$items=array(),$pcs_condition,$multiplicator_condition,$monthly_divisor){
    	$month = date("m",strtotime($to));
		$year = date('Y-01-01');

		if($month == '1'){

			$from = date('Y-11-01');
			$to = date('Y-11-30');

			$from = date('Y-m-d', strtotime('-1 year',strtotime($from)));
			$to = date('Y-m-d', strtotime('-1 year',strtotime($to)));
			$year = date('Y-m-d', strtotime('-1 year',strtotime($year)));
		}

		 $this->db = $this->load->database("default", true);
		 $sql = "
			select f.product_id,sum(f.sales_perday) as offtakewitheliminateddays from 
			(
			select a.date_posted,a.product_id,a.total_sales as sales_perday,b.total_sales, b.total_sales*".$multiplicator_condition." as monthlyofftakecondition

			 FROM
			(
			SELECT 
			date_posted,
			product_history.product_id,
			product_history.selling_area_out - product_history.wholesale_qty as total_sales
			FROM `product_history` 
			WHERE `product_history`.`date_posted` >= '".$from."' AND `product_history`.`date_posted` <=  '".$to."' 
			AND ((product_history.day_total >0) OR (selling_area_out > 0)) AND `product_history`.`product_id` 
			IN(".$items.") ) as a LEFT JOIN
			(
			SELECT 
			product_history.product_id, 
			((sum(product_history.selling_area_out) - sum(product_history.wholesale_qty))/".$monthly_divisor.") as total_sales
			FROM `product_history` 
			WHERE `product_history`.`date_posted` >= '".$from."' AND `product_history`.`date_posted` <= '".$to."' 
			AND ((product_history.day_total >0) OR (selling_area_out > 0)) AND `product_history`.`product_id` 
			IN(".$items.") 
			GROUP BY `product_history`.`product_id`
			) as b 
			on a.product_id = b.product_id 
			HAVING  ((sales_perday < monthlyofftakecondition))
			) as f GROUP BY f.product_id";

		//echo $sql.PHP_EOL;
		$res = $this->db->query($sql);
	    $res = $res->result();
	    return $res;

    }


    function get_eliminated_days($from,$to,$items=array(),$pcs_condition,$multiplicator_condition,$monthly_divisor){
    	
    	$month = date("m",strtotime($to));
		$year = date('Y-01-01');

		if($month == '1'){

			$from = date('Y-11-01');
			$to = date('Y-11-30');

			$from = date('Y-m-d', strtotime('-1 year',strtotime($from)));
			$to = date('Y-m-d', strtotime('-1 year',strtotime($to)));
			$year = date('Y-m-d', strtotime('-1 year',strtotime($year)));
		}

		 $this->db = $this->load->database("default", true);
		 $sql = "
			select b.product_id,GROUP_CONCAT(b.date_posted) as eliminated_dates,count(b.date_posted) as eliminated_days  FROM
			(
			select a.date_posted,a.product_id,a.total_sales as sales_perday,b.total_sales, b.total_sales*".$multiplicator_condition." as monthlyofftakecondition

			 FROM
			(
			SELECT 
			date_posted,
			product_history.product_id,
			product_history.selling_area_out - product_history.wholesale_qty as total_sales
			FROM `product_history` 
			WHERE `product_history`.`date_posted` >= '".$from."' AND `product_history`.`date_posted` <=  '".$to."' 
			AND ((product_history.day_total >0) OR (selling_area_out > 0)) AND `product_history`.`product_id` 
			IN(".$items.") ) as a LEFT JOIN
			(
			SELECT 
			product_history.product_id, 
			((sum(product_history.selling_area_out) - sum(product_history.wholesale_qty))/".$monthly_divisor.") as total_sales
			FROM `product_history` 
			WHERE `product_history`.`date_posted` >= '".$from."' AND `product_history`.`date_posted` <= '".$to."' 
			AND ((product_history.day_total >0) OR (selling_area_out > 0)) AND `product_history`.`product_id` 
			IN(".$items.") 
			GROUP BY `product_history`.`product_id`
			) as b 
			on a.product_id = b.product_id 
			HAVING  ((sales_perday > monthlyofftakecondition) and (sales_perday > ".$pcs_condition."))
			) as b GROUP BY b.product_id";
		//echo $sql;
		$res = $this->db->query($sql);
	    $res = $res->result();
	    return $res;

    }

	##8282020
	## end --rhan ##

	
    public function get_customer_code(){
        $array = array();
    	$this->db = $this->load->database("default", true); 
        $result = $this->db->get("excluded_whoiesale_customer");
        $result = $result->result_array();
	    foreach($result as $row) array_push($array,  $row["wholesale_customer_name"]);	
    	return $array;
    } 

    public function delete_product_history($date){
    	  $this->db = $this->load->database("default", true); 
          $this->db->where("date_posted", $date);
          $this->db->delete("product_history");    
    } 

    public function get_default_vendor(){
    	$this->db = $this->load->database("default", TRUE);
		$this->db->select("distinct(vendor) as vendorcode, user_id");
		$query = $this->db->get("user_vendor");
		return $query->result_array();
    }


     public function insert_out_of_stock($data){
        $this->db= $this->load->database("default", true);
        if(count($data) == 1)  $this->db->insert("out_of_stock", $data[0]);
        else if(count($data) > 1) $this->db->insert_batch("out_of_stock", $data);
    }


    public function get_out_of_stock_po_date($product_id,$br_code){
		$this->db = $this->load->database('po', TRUE);
		$this->db->select('purch_orders.delivery_date as delivery_date, purch_order_details.ord_qty');
		$this->db->from('purch_orders');
		$this->db->join('purch_order_details', 'purch_order_details.order_no = purch_orders.order_no');
		$this->db->where('purch_order_details.stock_id', $product_id);
		$this->db->where('purch_orders.trans_type', 16);
		$this->db->where('purch_order_details.trans_type', 16);
		$this->db->where('purch_orders.status =', 0);
		$this->db->where('purch_orders.br_code', $br_code);
		$this->db->where('purch_orders.manual', 0);
		$this->db->order_by('purch_orders.delivery_date desc');
		$this->db->limit(1);
		$result =  $this->db->get();
		$query = $result->result(); 
		if($query)
		return $query[0];
		else return array();
	}
 
    public function get_user_vendor($user_id=null, $user_vendor = null, $not_in = array())
	{
		$this->db = $this->load->database('default', TRUE);
		$this->db->select('user_vendor.*');
		$this->db->from('user_vendor');


		if($user_id!=null) $this->db->where('user_vendor.user_id =',$user_id);
                if($user_vendor != null) $this->db->where('user_vendor.vendor' , $user_vendor);
                if(!empty($not_in)) $this->db->where_not_in("user_vendor.vendor", $not_in);
		$this->db->group_by('user_vendor.vendor');

		$query =  $this->db->get();

		$row = $query->result();
		return $row;
	}



	public function get_vendor($code){
		$this->db = $this->load->database($this->local_db, TRUE);
		$this->db->select('*');
		$this->db->from('vendor');
		$this->db->where('vendor.vendorcode',$code);
		$result =  $this->db->get();
		return $result->result(); 
	}

 public function auto_get_srs_suppliers_item_details2nd($item_code=null,$sup_code=null){
		$this->db = $this->load->database($this->local_db, TRUE);
		$exclude_db = $this->load->database("default", true);
		$exclude_db->where("VendorCode", $sup_code);
		$exclude_db->select("ProductID");
		$exclude_items = $exclude_db->get("exclude_items");
		$exclude_items = $exclude_items->result_array();
		$items = array();
		foreach($exclude_items as $index => $it) array_push($items, $it["ProductID"]);
		
		$this->db->select('vendor_products.VendorProductCode,
					  vendor_products.Description,
					  vendor_products.ProductID,
					  products.ProductCode,
					  vendor_products.VendorCode,
					  vendor.description as vendor_description,
					  vendor_products.uom,
					  vendor_products.cost,
					  vendor_products.discountcode1,
					  vendor_products.discountcode2,
					  vendor_products.discountcode3,
					  ((products.StockRoom + products.SellingArea) / vendor_products.qty) as StockRoom,
					  vendor_products.qty as reportqty,
					  pos_products.srp as srp,
					  products.LevelField1Code,					  
					 ');
		$this->db->from('vendor_products');
		$this->db->join('products','vendor_products.ProductID = products.ProductID');
		$this->db->join('vendor','vendor.vendorcode = vendor_products.VendorCode');
		$this->db->join('pos_products','vendor_products.ProductID = pos_products.ProductID AND vendor_products.uom = pos_products.uom');
        if(!empty($items))  $this->db->where_not_in("vendor_products.ProductID", $items);
		       
		$this->db->where('products.inactive =','0');
                $this->db->where('vendor_products.defa =', 1);
		$this->db->where_in('vendor_products.ProductID',$item_code);
	        if($sup_code != null) $this->db->where('vendor.vendorcode =',$sup_code);	
		$this->db->order_by('vendor_products.Description');
		$query =  $this->db->get();
		return $query->result();
	}

function overstock_offtake($from,$to,$items=array(), $days = 30){
	
		$this->db = $this->load->database("default", TRUE);
		$this->db->select("product_history.product_id,'".$days."' as divisor,sum(product_history.selling_area_out) as total_sales",false);
		$this->db->from('product_history');
			
		$this->db->where('product_history.date_posted >=',$from);	
		$this->db->where('product_history.date_posted <=',$to);	
		// $this->db->where('product_history.selling_area_out >','0');	
		//$this->db->where('((product_history.day_total > 0) OR  (selling_area_out > 0))');	
		if(!empty($items))
			$this->db->where_in('product_history.product_id',$items);
		$this->db->group_by('product_history.product_id');
		$query =  $this->db->get();

		return $query->result();
	}



  public function get_srs_suppliers_item_details($item_code=null,$sup_code=null){
		$this->db = $this->load->database($this->local_db, TRUE);
		$exclude_db = $this->load->database("default", true);
		$exclude_db->where("VendorCode", $sup_code);
		$exclude_db->select("ProductID");
		$exclude_items = $exclude_db->get("exclude_items");
		$exclude_items = $exclude_items->result_array();
		$items = array();
		foreach($exclude_items as $index => $it) array_push($items, $it["ProductID"]);
		$this->db->select('vendor_products.VendorProductCode,
					  vendor_products.Description,
					  vendor_products.ProductID,
					  products.ProductCode,
					  vendor_products.VendorCode,
					  vendor_products.uom,
					  vendor_products.cost,
					  vendor_products.discountcode1,
					  vendor_products.discountcode2,
					  vendor_products.discountcode3,
					  ((products.StockRoom + products.SellingArea) / vendor_products.qty) as StockRoom,
					  vendor_products.qty as reportqty					  
					 ');
		$this->db->from('vendor_products');
		$this->db->join('products','vendor_products.ProductID = products.ProductID');
		  $this->db->where('products.inactive',0);
		$this->db->where('vendor_products.defa',1);
		if(!empty($items))  $this->db->where_not_in("vendor_products.ProductID", $items);
		if($item_code != null)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     
		{
			$this->db->where('vendor_products.ProductID =',$item_code);
		}
		if($sup_code != null)
			$this->db->where('vendor_products.VendorCode =',$sup_code);
		$this->db->order_by('vendor_products.Description');
		$query =  $this->db->get();
		return $query->result();
	}


	public function get_vendor_products($code){
		$this->db = $this->load->database($this->local_db, TRUE);
		$this->db->select('ProductID, Description');
		$this->db->from('vendor_products');
		$this->db->where('vendor_products.VendorCode',$code);
		$result =  $this->db->get();
        return $result->result(); 
	}

	function get_item_total_sales($date)
    {
      $this->db = $this->load->database($this->local_db, TRUE);
	  $sql = "SELECT ProductID,
					SUM(Extended) AS total_sales,
					SUM(AverageUnitCost * 
						(CASE
							WHEN [Return] = 0 THEN
								TotalQty
							ELSE
								- TotalQty
							END
						)
						) AS total_cost,
					SUM((CASE
							WHEN [Return] = 0 THEN
								TotalQty
							ELSE
								- TotalQty
							END
						))AS qty
				FROM
					[dbo].[FinishedSales]
				WHERE CAST(LogDate as DATE)= '".$date."'
				AND Voided = 0
				GROUP BY ProductID, LogDate";
					
	
	    $res = $this->db->query($sql);
	    $res = $res->result_array();
	    return $res;
    }

    function insert_prod_history_summary_sales($rows,$date_posted)
    {
    	$this->db = $this->load->database("default", true);
    	foreach($rows as $row){
		$prod_id = $row["ProductID"] + 0;
		$t_sales = $row["total_sales"] + 0;
		$t_cost = $row["total_cost"] + 0;
		$t_sales_qty = $row["qty"] + 0;
		$w_sales = 0;

		$sql = " INSERT INTO product_history (date_posted, product_id, beginning_selling_area, selling_area_in, selling_area_out, day_total,
					wholesale_qty,total_sales,total_cost)
			VALUES('".$date_posted."',".$prod_id.",0,0,$t_sales_qty,0,$w_sales, $t_sales, $t_cost) ";
			$this->db->query($sql);
	    }
    }

    function update_excluded_wholesale($date_)
    {
	    $supp_codes = $this->customer_code;
	    if(empty($supp_codes)) return array();
	    $where = (count($supp_codes) > 1) ? "b.CustomerCode IN ('". implode("','",$supp_codes) ."')" : "b.CustomerCode = '".$supp_codes[0]."'";
		//$where = (count($supp_codes) > 1) ? "a.CustomerCode IN ('". implode("','",$supp_codes) ."')" : "a.CustomerCode = '".$supp_codes[0]."'";
	    $this->db= $this->load->database($this->local_db, TRUE);
	   
	   // $sql = "SELECT b.ProductID, b.Description, CONVERT(DECIMAL(16,2), SUM(b.TotalQty)) as ws_total
					// FROM FinishedTransaction a
					// JOIN FinishedSales b ON (a.TransactionNo = b.TransactionNo AND a.TerminalNo = b.TerminalNo)
					// WHERE ".$where." AND
					// CAST(a.LogDate as DATE) = '".$date_."'
					// GROUP BY b.ProductID, b.Description
					// ORDER BY b.Description";
					
		$sql = "SELECT a.ProductID,
		a.Description,
		SUM((CASE WHEN a.[Return] = 0 THEN a.TotalQty ELSE -a.TotalQty END)) as ws_total
		FROM [FinishedSales] as a
		LEFT JOIN FinishedTransaction b ON (a.TransactionNo = b.TransactionNo AND a.TerminalNo = b.TerminalNo)
		WHERE ".$where."
		AND CAST(a.LogDate as DATE)= '".$date_."'
		AND CAST(b.LogDate as DATE)= '".$date_."'
		AND a.Voided = 0
		GROUP BY a.ProductID, a.Description
		ORDER BY a.Description";

		$res = $this->db->query($sql);
	    $res = $res->result_array();
	    return $res;
    }

    function update_wholesale($rows, $date_){
    	$this->db= $this->load->database("default", true);
    	foreach($rows as $row)
	     {
			$sql = "UPDATE product_history SET 
								wholesale_qty = ". $row['ws_total'] ."
							WHERE date_posted = '".$date_."'
							AND product_id = ". $row["ProductID"];
			$this->db->query($sql);
	     }
    }

	public function get_custom_val($tbl,$col,$where,$val){
		$this->db = $this->load->database($this->local_db, TRUE);
		if(is_array($col)){
			$colTxt = "";
			foreach ($col as $col_txt) {
				$colTxt .= $col_txt.",";
			}
			$colTxt = substr($colTxt,0,-1);
			$this->db->select($tbl.".".$colTxt);
		}
		else{
			$this->db->select($tbl.".".$col);
		}

		$this->db->from($tbl);
		$this->db->where($tbl.".".$where,$val);
		$query = $this->db->get();
		$result = $query->result();
		if(count($result) > 0){
			return $result[0];
		}
		else
			return "";
	}

	public function execute_queue($query = array(), $id, $table = "auto_purchase"){
            $this->db = $this->load->database("main_po", TRUE);
            if($this->db->conn_id){
                if($this->db->insert($table,$query)){
                    $this->db = $this->load->database("default", true);
                    $this->db->where("id", $id);
                    $this->db->update($table, array("throw"=>1));
                    return true;       
                } else return false;
            } return false;
    } 
   

    public function throw_po(){
    	$this->db =$this->load->database("default", true);
    	$this->db->where("throw", 0);
    	$query = $this->db->get("auto_purchase");	
    	return $query->result_array();
    }


    public function throw_po_computation_history(){
    	$this->db =$this->load->database("default", true);
    	$this->db->where("throw", 0);
    	$query = $this->db->get("computations_history");	
    	return $query->result_array();
    }


    public function throw_os(){
    	$this->db =$this->load->database("default", true);
    	$this->db->where("throw", 0);
    	$query = $this->db->get("out_of_stock");	
    	return $query->result_array();
    }

    

    public function get_srs_suppliers_details($code=null){
        $this->db = $this->load->database($this->local_db, TRUE);
        $this->db->select('vendor.vendorcode,
                      vendor.description,
                      vendor.address,
                      vendor.city,
                      vendor.zipcode,
                      vendor.contactperson,
                      vendor.country,
                      vendor.email,
                      terms.description as term_desc
                     ');
        $this->db->from('vendor');
        $this->db->join('terms','vendor.terms = terms.TermID','left');
        if($code != null)
            $this->db->where('vendor.vendorcode =',$code);
        $query =  $this->db->get();
        return $query->result();
    }

   

	function auto_save_details($head,$items,$user_id, $auto_po = 0, $branch, $supplier){
			$this->db = $this->load->database("default", True);
			$this->db->insert("auto_purchase", array("date_added"=>date("Y-m-d"),"branch"=>$branch, "supplier"=>$supplier, "user_id" => $user_id, "po_head" => $head, "po_details" =>$items, "auto_po" => $auto_po ) );
	}


	function get_srs_items_po_divisor($from,$to,$items=array()){


		$month = date("m",strtotime($to));
		$year = date('Y-01-01');
		if($month == '1'){

			$from = date('Y-11-01');
			$to = date('Y-11-30');

			$from = date('Y-m-d', strtotime('-1 year',strtotime($from)));
			$to = date('Y-m-d', strtotime('-1 year',strtotime($to)));
			$year = date('Y-m-d', strtotime('-1 year',strtotime($year)));
		}


		$this->db = $this->load->database("default", true);
		$this->db->select("product_history.product_id,'30' as divisor,sum(product_history.selling_area_out) - sum(product_history.wholesale_qty) as total_sales",false);
		$this->db->from('product_history');
			
		$this->db->where('product_history.date_posted >=',$from);	
		$this->db->where('product_history.date_posted <=',$to);	
		// $this->db->where('product_history.selling_area_out >','0');	
		$this->db->where('((product_history.day_total > 0) OR  (selling_area_out > 0))');	
		if(!empty($items))
			$this->db->where_in('product_history.product_id',$items);
		$this->db->group_by('product_history.product_id');
		$query =  $this->db->get();
		///return $this->db->last_query();
		 return $query->result();
	

	}

	public function get_srs_discounts($discs=array()){
		$this->db = $this->load->database($this->local_db, TRUE);
		$this->db->select('Discounts.*');
		$this->db->from('Discounts');
		$this->db->where_in('Discounts.DiscountCode',$discs);	
		$query =  $this->db->get();
		return $query->result();
	}

	public function get_selling_days_item_by_supplier_branch($branch_code,$supplier_code,$product_code){
        $this->db = $this->load->database('default', TRUE);
		$this->db->select(' ifnull(supplier_frequency_items.selling_days, 0) as selling_days ',false);
		$this->db->from('supplier_frequency_items');
		$this->db->join('supplier_frequency', 'supplier_frequency.id = supplier_frequency_items.supplier_frequency_id');
		$this->db->where('supplier_frequency.supplier =',$supplier_code);
		$this->db->where('supplier_frequency_items.product_code =',$product_code);
		$this->db->where('supplier_frequency.branch =',$branch_code);
		$this->db->limit(1);
		$query =  $this->db->get();
		$row = $query->result(); 
		if(count($row) == 0) return 0;
		return $row[0]->selling_days;
	}

	public function main_get_branch_details($branch_code){	
		$this->db = $this->load->database('default', TRUE);
		$this->db->select('*');
		$this->db->from('branches');
		$this->db->where('code ',$branch_code);
		$query =  $this->db->get();
		$row = $query->result();
		return $row[0];
	}

	 public function getAllTruckLoad($id){
        $this->db = $this->load->database("default", true);
        $this->db->where("supplier_id", $id);
        $result = $this->db->get("truck_load");
        $result = $result->result_array();
        $array = array();
        foreach($result as $row) $array[$row["uom"]] = $row["qty"];
        return $array;
    }
	
	public function get_frequency_excluded(){
		$this->db = $this->load->database('default', TRUE);
		$sql = "SELECT vendor_code FROM supplier_frequency_excluded";
		$query = $this->db->query($sql);
	    $result = $query->result();
		
		foreach ($result as $vendor_res) {
			$excluded_vendors[] = "".$vendor_res->vendor_code."";
		}

	    return $excluded_vendors;
	}

    public function get_frequency($user_id=null,$branch = null, $supplier = null,$limit=null,$status=null,$resume=null,$frequency=null,$week_day=null, $auto_po = null,$excluded_vendors=null){
        $this->db = $this->load->database('default', TRUE);
        $this->db->select('supplier_frequency.date_created,supplier_frequency.id,branches.ci_ms_database as ms,supplier_frequency.valid_until, supplier_frequency.delivery_date, supplier_frequency.selling, supplier_frequency.supplier, branches.name, supplier_frequency.frequency, supplier_frequency.days, branches.code,  supplier_frequency.branch as sf_branch, supplier_frequency.supplier as sf_supplier, supplier_frequency.valid_until as valid_until, supplier_frequency.delivery_date as delivery_day,supplier_frequency.auto_po as auto_po,supplier_frequency.status, users.fname, users.lname,supplier_frequency.resume,supplier_frequency.user_id as user_id,supplier_frequency.within_period as within_period,supplier_frequency.minimum,supplier_frequency.maximum, supplier_frequency.min_pc');
        $this->db->from('supplier_frequency');
        $this->db->join('branches','supplier_frequency.branch = branches.code');
        //$this->db->join('user_vendor','user_vendor.vendor = supplier_frequency.supplier');
                $this->db->join('users','users.id = supplier_frequency.user_id');
        if($branch != null)
            $this->db->where('supplier_frequency.branch', $branch);
        if($supplier != null)
            $this->db->where('supplier_frequency.supplier', $supplier);
        if($user_id != null)
            $this->db->where('users.id', $user_id);

        if($status != null) 
            $this->db->where('supplier_frequency.status',$status);
        //if($auto_po != null) $this->db->where("auto_po", $auto_po);
                if($resume != null)
                        $this->db->where('supplier_frequency.resume',$resume);

        if($limit !=null)
            $this->db->limit($limit[0],$limit[1]);

                if($frequency != null)
                        $this->db->where('supplier_frequency.frequency',$frequency);

                 if($week_day != null)
                        $this->db->where('supplier_frequency.days',$week_day);
					if($excluded_vendors != null)	
					$this->db->where_not_in('supplier_frequency.supplier', $excluded_vendors);

                $this->db->order_by('branches.name');
                 $this->db->order_by('supplier_frequency.supplier');
        $query = $this->db->get();
                //echo $this->db->last_query();
        return $query->result_array();
    }

	
}