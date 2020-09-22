<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
ini_set('MAX_EXECUTION_TIME', -1);
ini_set('mssql.connect_timeout',0);
ini_set('mssql.timeout',0);
set_time_limit(0);  
ini_set('memory_limit', -1);

//client_buffer_max_kb_size = '50240'
//sqlsrv.ClientBufferMaxKBSize = 50240

class Auto extends CI_Controller {
      var $data = array();
        public $rows = array(), $sort = array(), $over_stock_location = 'total_over_stock_report/';
   
   
    public function __construct(){
		parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $real_date = date("Y-m-d");
$to = date("Y-m-d",strtotime($real_date .' -1 days'));
$from = date("Y-m-d",strtotime($to .' -29 days'));
define("FROM", $from);
define("TO", $to);
		$this->load->model("Auto_model", "auto");
    }
     public function get_over_stock_today($from , $to){
    $this->load->library('excel');
    $products = array();
    $vendors = $this->auto->get_user_vendor();
    foreach($vendors as $i => $vendor){
        $vendor_product = $this->auto->get_vendor_products($vendor->vendor);
            if(!empty($vendor_product)) { 
                foreach($vendor_product as $vp) array_push($products,$vp->ProductID);
                    $description = $this->auto->get_vendor( $vendor->vendor);
                    $description_text = $description[0]->description;
                    $this->over_stock_report($products,$from,$to,$description_text,$vendor->vendor);
                       $products = array();
            }  

          // if($i == 100) break;
        }
        $this->create_total_over_stock_report( $from , $to);
            

  }

   public function over_stock_report($products,$from,$to,$name_head,$value_id=null){
        $se_items =array();
        $counts = 0;
        $supp_items = $this->auto->auto_get_srs_suppliers_item_details2nd($products,$value_id);
        $se_items = array();
        foreach ($supp_items as $res) {
            $qty = 0;
            $extended = 0;
            $sugg_po = 0;
            $qoh = $res->StockRoom;
            $det['barcode'] = $res->ProductCode;
            $det['description'] = $res->Description;
            $det['uom'] = $res->uom;
            $det['cost'] = $res->cost;
            $det['qty_by'] = $res->reportqty;
            $det['qoh'] = $qoh;
            $det['divisor'] = 0;
            $det['total_sales'] = 0;
            $det['avg_off_take'] = 0;
            $det['vendor_description'] = $res->vendor_description;
            $det['sugg_po'] = $sugg_po;
            $det['qty'] = $qty;
            $det['disc1'] = $res->discountcode1;
            $det['disc2'] = $res->discountcode2;
            $det['disc3'] = $res->discountcode3;
            $det['extended'] = $extended;
            $det['srp'] = $res->srp;
            $item[$res->ProductID] = $det;
            $se_items[] = $res->ProductID;
        }
        $divs = $this->auto->overstock_offtake($from,$to,$se_items, 60);
            foreach ($divs as $des) {
                
                if(isset($item[$des->product_id])){
                     $sales =   $des->total_sales/ $item[$des->product_id]['qty_by'];
                     if($sales == 0) echo $sales.PHP_EOL;

                    /*$divisor = $des->divisor;
                    $sales = $des->total_sales/$item[$des->product_id]['qty_by'];
                    $offtake = $sales/$divisor;
                    $offtake = number_format($offtake, 2, '.', '');//round($avg_off_take,2);*/
                    $qoh = $item[$des->product_id]['qoh'];
                    $qoh = ($qoh < 0) ? 0 : $qoh;
                   /* $qty =  $item[$des->product_id]['qty_by'];
                    $stock_out = floor($qoh/$offtake);*/
                    $cof = round($item[$des->product_id]['cost'], 4);
                   /* $days_to_sell = $qoh / $offtake;
                    $indicator = 60;
                    $floor_days = floor($days_to_sell);*/
                    if(  $sales == 0 && $qoh > 0){
                        echo "zero".PHP_EOL;
                        $this->sort[] = 0;
                            $this->rows[] =  array(
                            $des->product_id,
                             strip_tags(trim($item[$des->product_id]['vendor_description'])),
                         strip_tags($item[$des->product_id]['description']) ,
                             trim($item[$des->product_id]['uom']), 
                             "", 
                             round($qoh,4), 
                             '', 
                             $cof, 
                             '', 
                             '0',
                             "",
                             ""
                        );
                    }
                    else {
                    $off_take_maam_weng =  ($item[$des->product_id]['qoh']  ) -  $sales;
                        if($off_take_maam_weng > 0 )  { 
                            $over_stock = $off_take_maam_weng;
                            $offtake = $sales/ 60;
                            $number = number_format(round($over_stock * $cof,4),2);
                            $days_to_sell = ceil($over_stock / $offtake);
                            $this->sort[] = str_replace(",","",$number);
                                $prod_uom = trim($item[$des->product_id]['uom']);
                                $this->rows[] =  array(
                                    $des->product_id,
                                    strip_tags(trim($item[$des->product_id]['vendor_description'])),
                                    strip_tags($item[$des->product_id]['description']),
                                    trim($item[$des->product_id]['uom']), 
                                    round($offtake,4), 
                                    number_format(round($qoh,4),2), 
                                    round($over_stock,4), 
                                    $cof, 
                                    $number, 
                                    $days_to_sell,
                                    $sales , 
                                    $item[$des->product_id]['qoh']
                                );
                        }
                        
                    }

        
                }
            }

      }

  public function create_total_over_stock_report( $from , $to){
        $sort = $this->sort;
        $rows = $this->rows;
       
        $month_name = $from.'_'.$to;
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $BStyle = array(
          'borders' => array(
            'allborders' => array(
              'style' => PHPExcel_Style_Border::BORDER_THIN
            )
          )
        );
 $i = 1;
                 $objPHPExcel->getActiveSheet()->setCellValue('A'.$i, "Product ID");
                    $objPHPExcel->getActiveSheet()->setCellValue('B'.$i, "Vendor");
                     $objPHPExcel->getActiveSheet()->setCellValue('C'.$i, "Description");
                      $objPHPExcel->getActiveSheet()->setCellValue('D'.$i, "UOM");
                       $objPHPExcel->getActiveSheet()->setCellValue('E'.$i, "Offtake");
                        $objPHPExcel->getActiveSheet()->setCellValue('F'.$i, "Total Inventory");
                         $objPHPExcel->getActiveSheet()->setCellValue('G'.$i, "Over Stock");
                          $objPHPExcel->getActiveSheet()->setCellValue('H'.$i, "Selling Price");
                           $objPHPExcel->getActiveSheet()->setCellValue('I'.$i, "Total Cost");
                            $objPHPExcel->getActiveSheet()->setCellValue('J'.$i, "Day To Sell");

 $objPHPExcel->getActiveSheet()->setCellValue('L'.$i, "Sales");
                            $objPHPExcel->getActiveSheet()->setCellValue('M'.$i,"Inventory");
                foreach($rows as $key => $row){
                        $i = $key + 2;
                    $objPHPExcel->getActiveSheet()->setCellValue('A'.$i, $row[0]);
                    $objPHPExcel->getActiveSheet()->setCellValue('B'.$i, $row[1]);
                     $objPHPExcel->getActiveSheet()->setCellValue('C'.$i, $row[2]);
                      $objPHPExcel->getActiveSheet()->setCellValue('D'.$i, $row[3]);
                       $objPHPExcel->getActiveSheet()->setCellValue('E'.$i, $row[4] );
                        $objPHPExcel->getActiveSheet()->setCellValue('F'.$i, $row[5]);
                         $objPHPExcel->getActiveSheet()->setCellValue('G'.$i, $row[6]);
                          $objPHPExcel->getActiveSheet()->setCellValue('H'.$i, $row[7]);
                           $objPHPExcel->getActiveSheet()->setCellValue('I'.$i, $row[8]);
                            $objPHPExcel->getActiveSheet()->setCellValue('J'.$i, $row[9]);
                            $objPHPExcel->getActiveSheet()->setCellValue('L'.$i, $row[10]);
                            $objPHPExcel->getActiveSheet()->setCellValue('M'.$i, $row[11]);
                }
        $objPHPExcel->getActiveSheet()->getStyle('A1:J1')->applyFromArray($BStyle);
        $objPHPExcel->getActiveSheet()->setTitle('Simple');
        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $title = BRANCH_NAME."_TOTAL_Over_Stock_".$month_name;
        echo $title.PHP_EOL;
        $objWriter->save("total_over_stock_report/".$title.'.xlsx');           
    }

public function show_branch(){

        echo BRANCH_USE. " " . BRANCH_NAME; sleep(10);
    }
    public function generate_product_history($from, $to){

    $day = 86400; // Day in seconds  
        $format = 'Y-m-d'; // Output format (see PHP date funciton)  
        $sTime = strtotime($from); // Start as time  
        $eTime = strtotime($to); // End as time  
        $numDays = round(($eTime - $sTime) / $day) + 1;  
        $days = array();  

        for ($d = 0; $d < $numDays; $d++) {  
            $days[] = date($format, ($sTime + ($d * $day)));  
        } 

        foreach($days as $date) $this->create_product_history($date);
  }

    public   function date2Sql($date){
        return date('Y-m-d', strtotime($date));
     }

     public function generate_everyday(){
         $this->create_product_history();
         echo 'Product History Created';
         $this->create_received_history();

       // $this->index();
     }

  
public function throw_po(){
    $data = $this->auto->throw_po(); 
    $bool = (count($data) > 0) ? true : false;
    while($bool){
        foreach($data as $row){
            $id = $row["id"];
            unset($row["id"]);
            $this->auto->execute_queue($row, $id);
        } 
        $data = $this->auto->throw_po(); 
        $bool = (count($data) > 0) ? true : false; 
    } 
}
 public function calculate_discount($disc,$amount,$retrunDeduc=false){
        $total = $amount;
        if($disc['percent'] == 1){
            $deduc = ($disc['amount']/100) * $total;
        }
        else{
            $deduc = $disc['amount'];
        }

        if($disc['plus'] == 1){
            $total += $deduc;
        }
        else{
            $total -= $deduc;
        }
        
        if($disc['plus'] == 1)
            $deduc = -$deduc;
        if($retrunDeduc)
            return $deduc;
        else
            return $total;
    }
    function date_diff2 ($date1, $date2, $period) 
{

/* expects dates in the format specified in $DefaultDateFormat - period can be one of 'd','w','y','m'
months are assumed to be 30 days and years 365.25 days This only works
provided that both dates are after 1970. Also only works for dates up to the year 2035 ish */

	$date1 = $this->date2sql($date1);
	$date2 =  $this->date2sql($date2);
	list($year1, $month1, $day1) = explode("-", $date1);
	list($year2, $month2, $day2) = explode("-", $date2);

	$stamp1 = mktime(0,0,0, (int)$month1, (int)$day1, (int)$year1);
	$stamp2 = mktime(0,0,0, (int)$month2, (int)$day2, (int)$year2);
	$difference = $stamp1 - $stamp2;

/* difference is the number of seconds between each date negative if date_ 2 > date_ 1 */

	switch ($period) 
	{
		case "d":
			return (int)($difference / (24 * 60 * 60));
		case "w":
			return (int)($difference / (24 * 60 * 60 * 7));
		case "m":
			return (int)($difference / (24 * 60 * 60 * 30));
		case "s":
			return $difference;
		case "y":
			//return (int)($difference / (24 * 60 * 60 * 365.25));
			return (int)($difference / (24 * 60 * 60 * 365));
		default:
			Return 0;
	}
}

    

    public function auto_generate_pr($sf){
        

        echo $sf["frequency"].PHP_EOL;
    	$this->po_set_settings($sf);
        echo "Set Settings" . PHP_EOL;  
        $this->create_purchase_request();
        echo "Set Form" . PHP_EOL;  
       // $this->save_po($sf);
        echo "Set Save" . PHP_EOL;
    }

    public function po_set_settings($sf){
    	$manual = 0;
        if($this->input->post('manual'))
            $manual = $this->input->post('manual');

        $today = date("Y-m-d");
        $sup = $sf["supplier"];
        $bra = $sf["code"];
        $from = $sf['from'];
        $to = $sf['to'];
        $within_period = 0;
        if(isset($sf["within_period"]))
          if($sf["within_period"] == 1) $within_period = 1;
        $del_date = date('m/d/Y',(strtotime ( '+'.$sf['delivery_day'].' day' , strtotime ( $today ) ) ));
        $valid_date = date('m/d/Y',(strtotime ( '+'.$sf['valid_until'].' day' , strtotime ( $today ) ) ));

        $sel_days = $sf['selling'];
        $get_branch_details = $this->auto->main_get_branch_details($bra);
        $get_branch_details = json_decode(json_encode($get_branch_details),true);
        $result = $this->auto->get_srs_suppliers_details($sup);
        $res = $result[0];
        $zipC = "";
        if($res->zipcode != ""){
            $zipC .= $res->zipcode;
        }
        if($res->country != ""){
            $zipC .= ($res->zipcode != "" ? ",":"").$res->country;
        }
        $supp_det = array("name"=>$res->description,"email"=>$res->email,"terms"=>$res->term_desc,"address"=>$res->address,"city"=>$res->city,"zipC"=>$zipC,"person"=>$res->contactperson,"code"=>$sup);

        $dateDiff = strtotime($from) - strtotime($to); 
        $days_back = ceil($dateDiff/(60*60*24)) + 1;

        $po_setup = array(
            "del_date"=>$del_date,
            "supplier"=>$supp_det,
            "from"=>$from,
            "to"=>$to,
            "selling_days"=>$sel_days,
            "days_back"=>$days_back,
            "manual"=>$manual,
            "valid_date" => $valid_date,
            "within_period" => $within_period,
            "user_id" => $sf["user_id"],
            "auto_po" => $sf["auto_po"],
            "min" => $sf["minimum"],
            "max" => $sf["maximum"],
            "expire_po" => $sf["expire_po"]
        );

        $this->session->set_userdata('po_setup',$po_setup);
    }

	public function create_purchase_request(){

        if($this->session->userdata('po_setup')) $settings = $this->session->userdata('po_setup');
        else return true;
        $uom_piece = array('piece', 'pc', 'pcs','pck');
        $from = $settings["from"];
        $to = $settings["to"];
        $supplier_code = $settings["supplier"]["code"];
        echo $supplier_code.PHP_EOL;
        $branch_code = BRANCH_USE;
        $supp_items = $this->auto->get_srs_suppliers_item_details(null,$supplier_code);
        $se_items = array();
        foreach ($supp_items as $res) {   
            $qty = 0;
            $extended = 0;
            $sugg_po = 0;
        
            $qoh = $res->StockRoom;
            $det['barcode'] = $res->ProductCode;
            $det['description'] = $res->Description;
            $det['uom'] = $res->uom;
            $det['cost'] = $res->cost;
            $det['qty_by'] = $res->reportqty;
            $det['qoh'] = $qoh;
            $det['divisor'] = 0;
            $det['total_sales'] = 0;
            $det['avg_off_take'] = 0;
            $sell_days = $this->auto->get_selling_days_item_by_supplier_branch($branch_code,$supplier_code,$res->ProductCode);
            $det['avg_off_take_x'] = ($sell_days == 0 || $sell_days == null) ? $settings['selling_days'] : $sell_days;
            $det['sell_days'] = $det['avg_off_take_x'];
            $det['sugg_po'] = $sugg_po;
            $det['qty'] = $qty;
            $det['disc1'] = $res->discountcode1;
            $det['disc2'] = $res->discountcode2;
            $det['disc3'] = $res->discountcode3;
            $det['extended'] = $extended;
            $item[$res->ProductID] = $det;
            $se_items[] = $res->ProductID;
        }

      
        $min_purchase_piece =  $this->auto->get_frequency(null,$branch_code,$supplier_code);
        $case_order_piece = 0;
        $truckLoad = array();

             if(!empty($min_purchase_piece)) {
                $min_purchase_piece = $min_purchase_piece[0];
                $truckLoadId= $min_purchase_piece["id"];
                if($min_purchase_piece["status"] == 1) {
                  $min_purchase_piece = $min_purchase_piece["min_pc"];
                  $truckLoad = $this->auto->getAllTruckLoad($truckLoadId);
                } else $min_purchase_piece = 6;
            } else $min_purchase_piece = 6;


            ##rhan_9619 check last PO ## 
            $last_purch =  $this->auto->get_last_po($supplier_code);
            
            if($last_purch){

                $po = $last_purch->reference;
                echo 
                $last_po_date =  date('Y-m-d',strtotime($last_purch->trans_date));
                $last_po_expiration = date('Y-m-d', strtotime('+'.$settings['expire_po'].'days', strtotime($last_po_date))); 

               
                echo $po.'>'.$last_po_date.'>-- last_po_expiration'.$last_po_expiration.'<';


            

                $received_history = $this->auto->get_received_history($po);
                if($received_history){
                     $rh_items = array();
                    foreach ($received_history as $rh) {
                        $d["po_reference"] = $po;
                        $d["freq"] = 0.60;
                        $d["ord_qty"] = $rh->ord_qty;
                        $d["qty"] = $rh->qty;
                        $d["po_expired"] = (date('Y-m-d') > $last_po_expiration ? 1 : 0);
                        $rh_items[$rh->stock_id] = $d;
                    }
                }

               
            }
            
         ##rhan_9619##
        if($se_items){

            $se_items_array =  implode("','", $se_items);
            $se_items_array =  "'".$se_items_array."'";

        }else{

            $se_items_array ="''";
        }

        $divs = $this->auto->get_srs_items_po_divisor($from , $to, $se_items_array);

        $divs_items = array();


        foreach ($divs as $des) {

            if(isset($item[$des->product_id])){

            $d_items["last_month_offtake"] = $des->last_month_offtake;
            $d_items["divisor"] = $des->divisor;
            $d_items["total_sales"] =$des->total_sales/$item[$des->product_id]['qty_by'];

             $avg_off_take = ($d_items["total_sales"])/$d_items["divisor"];
             $avg_off_take = number_format($avg_off_take, 2, '.', '');//round($avg_off_take,2);

                    $filter_off_take = 0;
                    $filter_sales = 0;
                    $rounding_off = 0;
                    $qoh_before_ordering = .5;

             $d_items["avg_off_take"] = $avg_off_take;

             $qoh_ = $item[$des->product_id]['qoh'] > 0 ? $item[$des->product_id]['qoh'] : 0;
             $sugg_po = (($avg_off_take > $filter_off_take ? $avg_off_take : 0)* $item[$des->product_id]['avg_off_take_x']) - $qoh_;
                 

                  if ($d_items["total_sales"] < $filter_sales) $sugg_po  = 0;
                        $sugg_po = ceil($sugg_po-$rounding_off);
                        if($sugg_po < 0){
                            $sugg_po  = 0;
                        }

                         echo $sugg_po.'=sugg_po';

                        if(in_array(strtolower($item[$des->product_id]['uom']), $uom_piece)){
                            $qty_times = $min_purchase_piece;
                            $sugg_po = $sugg_po/$qty_times;
                            $sugg_po = ceil($sugg_po);
                            $sugg_po = $sugg_po * $qty_times;
                        }
                        else if( isset($truckLoad[$item[$des->product_id]['uom']])  ){
                            $qty_times = $truckLoad[$item[$des->product_id]['uom']];
                            $sugg_po = $sugg_po/$qty_times;
                            $sugg_po = ceil($sugg_po);
                            $sugg_po = $sugg_po * $qty_times;
                        }


                       /* $des->daily_offtake = 10;
                        $des->d15_days =0;*/

                echo PHP_EOL.'daily_offtake-->'.$des->daily_offtake.PHP_EOL;
                echo PHP_EOL.'d15_days-->'.$des->d15_days.PHP_EOL;

                 if($des->daily_offtake >= 10 && $des->d15_days == 0){

                      $last_order_po =  $this->auto->get_last_order_po($supplier_code,$des->product_id);
                      $sugg_po = $last_order_po->ord_qty;
                      $last_po = $last_order_po->reference;
                      $remarks = 'last po sugg with zero offtake for 15 days';

                      if($sugg_po == 0){
                        #test#
                         $d_items["last_month_offtake"] =$des->last_month_offtake/$item[$des->product_id]['qty_by'];
                         $avg_off_take_ = ($d_items["last_month_offtake"])/$d_items["divisor"];
                         $avg_off_take_ = number_format($avg_off_take_, 2, '.', '');
                         $qoh_ = $item[$des->product_id]['qoh'] > 0 ? $item[$des->product_id]['qoh'] : 0;
                         $sugg_po = (($avg_off_take_ > $filter_off_take ? $avg_off_take_ : 0) * $item[$des->product_id]['avg_off_take_x']) - $qoh_;
                         $remarks =   $d_items["last_month_offtake"].'qoh:'.$qoh_.'last po sugg with zero offtake for 15 days with no auto po'.$item[$des->product_id]['avg_off_take_x'];
                      }

                      $types = 1;

                      echo "last po sugg with zero offtake for 15 days".$sugg_po;
                      $this->auto->insert_auto_purch_history($supplier_code,$des->product_id,$sugg_po,$last_po,$types,$remarks);

                 }       

                $qty = $sugg_po;

                $d_items["sugg_po"] = $sugg_po;
                $d_items["qty"] = ceil($qty);
                $d_items["cost"] = $item[$des->product_id]['cost'];
                $d_items["sugg_po"] = $sugg_po;

                $divs_items[$des->product_id] = $d_items;

               echo $sugg_po."<--sug->".$divs_items[$des->product_id]["sugg_po"].PHP_EOL;
            }
            
        }


         #rhan
        foreach ($se_items as $product_id ) {
            # code...
            echo $product_id.PHP_EOL;

            if($divs_items[$product_id]){

                echo "in history ";

                $item[$product_id]["divisor"] = $divs_items[$product_id]["divisor"];
                $item[$product_id]["total_sales"] = $divs_items[$product_id]["total_sales"];
                $item[$product_id]["avg_off_take"] = $divs_items[$product_id]["avg_off_take"];

                if($rh_items[$product_id]){ // check reecived history

                    echo 'in last po'.PHP_EOL;
                    $last_po_qty = $rh_items[$product_id]["ord_qty"];
                    $received_qty = $rh_items[$product_id]["qty"];
                    $po_reference = $rh_items[$product_id]["po_reference"];

                    $based_po = ($last_po_qty * $rh_items[$product_id]["freq"]);

                    echo 'old sugg ='.$divs_items[$product_id]["sugg_po"].PHP_EOL;
                    
                    if($received_qty <  $based_po){ // if received quantity meet order by percentage
                      //  $item[$product_id]["sugg_po"] = $last_po_qty;
                          $times_sugg = ($divs_items[$product_id]["sugg_po"] * 2);

                        if($times_sugg < $last_po_qty){
                            $item[$product_id]["sugg_po"] = $divs_items[$product_id]["sugg_po"];
                        }else{
                             $item[$product_id]["sugg_po"] = $divs_items[$product_id]["sugg_po"];
                        }
                       // $this->auto->update_purch_status($po_reference);
                        $this->auto->insert_auto_purch_history($supplier_code,$product_id,$last_po_qty,$po_reference,2,'sugg based on last received quantity 80 precent not meet');
                    }else{
                        $item[$product_id]["sugg_po"] = $divs_items[$product_id]["sugg_po"];
                    }

                    
                     echo 'last_po_qty'.$last_po_qty.PHP_EOL;
                     echo 'received qty'.$received_qty.PHP_EOL;
                     echo 'sugg='.$item[$product_id]["sugg_po"].PHP_EOL;
                    

                }else{

                     echo " not in last po".PHP_EOL;
                     echo "old sugg = ".$divs_items[$product_id]["sugg_po"].PHP_EOL;
                     $item[$product_id]["sugg_po"] = $divs_items[$product_id]["sugg_po"];

                     echo "sugg".$item[$product_id]["sugg_po"].PHP_EOL;
                    
                }



                $item[$product_id]["qty"] = $item[$product_id]["sugg_po"];
                $item[$product_id]["extended"] = ($item[$product_id]["qty"] * $divs_items[$product_id]["cost"]);
                echo $item[$product_id]["extended"];



            }else{
                
                #DEBUG PLEASE CHECK THIS BLOKED OF CODE>#
                /*echo " not in history".PHP_EOL;

                if($rh_items[$product_id]){

                    $item[$product_id]["divisor"] = 30;
                    $item[$product_id]["total_sales"] = 0;
                    $item[$product_id]["avg_off_take"] = 0;

                    $last_po_qty = $rh_items[$product_id]["ord_qty"];
                    $received_qty = $rh_items[$product_id]["qty"];

                    echo 'in last po '.PHP_EOL;
                    echo 'last po qty '.$last_po_qty.PHP_EOL;
                    echo 'received_qty'.$received_qty.PHP_EOL;
                    echo 'sugg='.$rh_items[$product_id]["ord_qty"].PHP_EOL;

                    $item[$product_id]["sugg_po"] = $rh_items[$product_id]["ord_qty"];

                    $item[$product_id]["qty"] = $item[$product_id]["sugg_po"];
                    $item[$product_id]["extended"] =  ($item[$product_id]["qty"] * $divs_items[$product_id]["cost"]);
                    echo $item[$product_id]["extended"];
                     $this->auto->insert_auto_purch_history($supplier_code,$product_id,$last_po_qty,'',2,'sugg based on last order but not on past 30 days sales');
                
                }else{

                    echo " not in last po".PHP_EOL;

                }


            }

            echo "____________________".PHP_EOL;*/

        }
        #rhan   


        $discounts = $disc = array();
        $discs = $this->purify_discs($item);
        if (count($discs) > 0)
            $discounts = $this->auto->get_srs_discounts($discs);
        foreach ($discounts as $dis) {
            $disc[$dis->DiscountCode] = array(
                "code" => $dis->DiscountCode,
                "desc" => $dis->Description,
                "amount" => $dis->Amount,
                "percent" => $dis->Percent,
                "plus" => $dis->Plus
            );
        }
       
        foreach ($item as $item_id => $det) {
            $extended = $det['cost'];
            for ($i=1; $i <= 3; $i++) { 
                if($det['disc'.$i] != null){
                    if(isset($disc[$det['disc'.$i]])){
                        $det['disc'.$i] = $disc[$det['disc'.$i]];
                        $extended = $this->calculate_discount($det['disc'.$i],$extended);
                    }
                }
            }
            $extended = $extended * $det['qty'];
            if($extended < 0) $extended = 0;
            $det['extended'] = $extended;
            $item[$item_id] = $det;
        }
        $this->session->set_userdata('po_cart',$item);
    }

 public function index(){

       $user = null;
         echo date("Y-m-d h:i:s").PHP_EOL;
         $supplier = null;
        // $supplier = "ATCCMC001";
		 $excluded_vendors=array();

		$excluded_vendors = $this->auto->get_frequency_excluded();
		//$excluded_vendors=implode(",", $excluded_vendors);
		
        $supplier_frequency = $this->auto->get_frequency($user,BRANCH_USE,$supplier,array(),"1","1", null, null, "1",$excluded_vendors);
        $last_date = date('Y-m-t');
        $last_day = explode("-", $last_date);
        $day_last = $last_day[2];
        $month = date('F');
        $year = date('Y');
        $dayToday = date('l',strtotime(date('Y-m-d')));
        $date_today = date('Y-m-d');
        $special_array = array("F1", "F5", "F6", "F7");
        $to = TO;
        $from = FROM;
         foreach($supplier_frequency as $sf){
            $date_created = $sf["date_created"];
            unset($sf["date_created"]);
            $day_chosen = $sf["days"];
            $po_schedule = $sf["frequency"];
            
            //if($po_schedule == "F4") continue;

            if($po_schedule == 'F4' && $day_chosen == $dayToday){
                $sf["from"] = $from;
                $sf["to"] = $to;
                $sf["expire_po"] = 5;
                $this->auto_generate_pr($sf);   
            }
            else if( in_array($po_schedule,$special_array) && $day_chosen == $dayToday)
            {
                $check_week = 0;

                for($x=1; $x< $day_last; $x++){
                    
                    if(strlen($x) != 2)
                        $append = '0'.$x;
                    else
                        $append = $x;

                    $day_pick = date('Y-m-'.$append);
                    
                    $dayCheck = date('l',strtotime($day_pick));

                    if($dayCheck == $day_chosen){

                        $check_week++;
                        if($po_schedule == "F1" and $check_week == 1){
                             $first_date = $day_pick;
                            break;
                        }
                        else if($po_schedule == "F5" and $check_week == 2){
                             $first_date = $day_pick;
                            break;
                        }
                        else if($po_schedule == "F6" and $check_week == 3){
                             $first_date = $day_pick;
                            break;
                        }
                        else if($po_schedule == "F7" and $check_week == 4){
                             $first_date = $day_pick;
                            break;
                        }
                    }
                }
               
                if($first_date == $date_today)
                {
                    $sf["from"] = $from;
                    $sf["to"] = $to;
                    $sf["expire_po"] = 15;
                    $this->auto_generate_pr($sf);
                }
            }
            else if($po_schedule == 'F2'){
                $po_to = date("Y-m-d", strtotime($date_today));
                $po_from = date("Y-m-d", strtotime($date_created));
                if($po_to > $po_from){
                    $detector = $this->date_diff2($po_to, $po_from, "d");
                    if($detector != 0){
                        $determine = (int) ($detector / 7);
                        if( $determine % 2 == 0 && $day_chosen == $dayToday){
                            $sf["from"] = $from;
                            $sf["to"] = $to;
                            $sf["expire_po"] = 10;
                            $this->auto_generate_pr($sf); 
                        }
                    }
                }
            } 
        }
        $this->throw_po();
        echo date("Y-m-d h:i:s").PHP_EOL;
       
    }

	public function purify_discs($items){
        $discs = array();
        foreach ($items as $item_id => $det) {
            // if($det['disc1'] != null)
                $discs[] = $det['disc1'];            
            // if($det['disc2'] != null)
                $discs[] = $det['disc2'];
            // if($det['disc3'] != null)
                $discs[] = $det['disc3'];
        }  
        $discs = array_unique($discs);
        return array_filter($discs);
    }

    public function get_po_cart_total($json=true){
        $po_cart = $this->session->userdata('po_cart');

        $qty = 0;
        $amount = 0;
        foreach ($po_cart as $item_id => $item) {
            $qty += $item['qty'];                      
           
            $amount += $item['extended'];                      
        }
        if($json)
            echo json_encode(array('qty'=>$qty,'amount'=>$amount));
        else 
            return array('qty'=>ceil($qty),'amount'=>$amount); 
    }

    public function save_po($sf , $auto_generate = 1, $draft = 1){
        $settings = $this->session->userdata("po_setup");
        $totals = $this->get_po_cart_total(false);
        $net_total = $totals['amount'];
        $within_period  = 0;
        if(isset($settings["within_period"]))
          if($settings["within_period"] == 1) $within_period = 1; 
        if($net_total > 0 ){
                    $po_cart = $this->session->userdata("po_cart");
                    $error_msg = null;
                        $sup = $settings['supplier'];
                        $branch = $this->auto->main_get_branch_details(BRANCH_USE);
                       $po_head = array(
                            //"order_no"=>$po_id,
                            "trans_type"=>PR_TRANS,
                            "supplier_id"=>$sup['code'],
                            "supplier_name"=>$sup['name'],
                            "supplier_email"=>$sup['email'],
                            "br_code"=>$branch->code,
                            "delivery_address"=>$branch->address,
                            "delivery_date"=>$this->date2Sql($settings['del_date']),
                            "net_total"=>$net_total,
                            "sales_from"=>$this->date2Sql($settings['from']),
                            "sales_to"=>$this->date2Sql($settings['to']),
                            "selling_days"=>$settings['selling_days'],
                            "manual"=> 0,
                            "valid_date"=> $this->date2Sql($settings['valid_date']),
                            "auto_generate"=> $auto_generate,
                            "draft" => $draft,
                            "within_period" => $within_period
                       ); 

					  $po_details = array();
                        foreach ($po_cart as $item_id => $row) {
                            if($row['qty'] > 0){
                                $dstr = '';
                                $extended =  $row['cost'];
                                for ($i=1; $i <= 3 ; $i++) { 
                                    $disc = $row['disc'.$i];
                                    if(is_array($disc)){
                                        $dstr .= $disc['code']."=>".$row['qty'] *($deduction = $this->calculate_discount($disc,$extended,true)).",";
                                        $extended -= $deduction;
                                    }
                                }
                                
                                $extended = $row['qty'] * $row['cost'];

                                $discounts_string = (substr($dstr,0,-1));
                                
                                if ($discounts_string == '0')
                                    $discounts_string = '';
                                    
                                $det = array(
                                  //  "order_no"=>$po_id,
                                    "trans_type"=>PR_TRANS,
                                    "stock_id"=>$item_id,
                                    "barcode"=>$row['barcode'],
                                    "description"=>$row['description'],
                                    "unit_id"=>$row['uom'],
                                    "unit_price"=>$row['cost'],
                                    "discounts"=>$discounts_string,
                                    "sgstd_qty"=>$row['sugg_po'],
                                    "ord_qty"=>$row['qty'],
                                    "selling_days"=>$row['sell_days'],
                                );

                               $po_details[] = $det;
                            }
                        }
                        $po_head = json_encode($po_head);
						$po_details = json_encode($po_details);
                        if($settings["auto_po"] == 1){
                           $settings["auto_po"] = 1;
                        }
                        $branch_use = BRANCH_NAME;
						$this->auto->auto_save_details($po_head, $po_details, $settings["user_id"], $settings["auto_po"], $branch_use, $sup['code']);
                        $this->session->unset_userdata('po_cart');
                        $this->session->unset_userdata('po_manual_cart');
                   
        }
    }  

   
    function date_diff(DateTime $date1, DateTime $date2) {
    
    $diff = new DateInterval();
    
    if($date1 > $date2) {
      $tmp = $date1;
      $date1 = $date2;
      $date2 = $tmp;
      $diff->invert = 1;
    } else {
      $diff->invert = 0;
    }

    $diff->y = ((int) $date2->format('Y')) - ((int) $date1->format('Y'));
    $diff->m = ((int) $date2->format('n')) - ((int) $date1->format('n'));
    if($diff->m < 0) {
      $diff->y -= 1;
      $diff->m = $diff->m + 12;
    }
    $diff->d = ((int) $date2->format('j')) - ((int) $date1->format('j'));
    if($diff->d < 0) {
      $diff->m -= 1;
      $diff->d = $diff->d + ((int) $date1->format('t'));
    }
    $diff->h = ((int) $date2->format('G')) - ((int) $date1->format('G'));
    if($diff->h < 0) {
      $diff->d -= 1;
      $diff->h = $diff->h + 24;
    }
    $diff->i = ((int) $date2->format('i')) - ((int) $date1->format('i'));
    if($diff->i < 0) {
      $diff->h -= 1;
      $diff->i = $diff->i + 60;
    }
    $diff->s = ((int) $date2->format('s')) - ((int) $date1->format('s'));
    if($diff->s < 0) {
      $diff->i -= 1;
      $diff->s = $diff->s + 60;
    }
    
    $start_ts   = $date1->format('U');
    $end_ts   = $date2->format('U');
    $days     = $end_ts - $start_ts;
    $diff->days  = round($days / 86400);
    
    if (($diff->h > 0 || $diff->i > 0 || $diff->s > 0))
      $diff->days += ((bool) $diff->invert)
        ? 1
        : -1;

    return $diff;
    
  }  

  public function create_product_history($date = null){
    if($date==null) $date = date("Y-m-d", strtotime("-1 day"));
    echo "Create Product History ".$date.PHP_EOL;
    $this->auto->delete_product_history($date);
    $record = $this->auto->get_item_total_sales($date); 
    $this->auto->insert_prod_history_summary_sales($record, $date);
    $wholesale = $this->auto->update_excluded_wholesale($date);
    if(count($wholesale) > 0)  echo "Wholesale Update ".$date.PHP_EOL;
    $this->auto->update_wholesale($wholesale, $date);
  }


   public function generate_out_of_stock($today = null)
   {
    $from = FROM; 
    $to = TO;
    if($today == null){
    $today = date("Y-m-d");
    $real_date = $today;
    $to = date("Y-m-d",strtotime($real_date .' -1 days'));
    $from = date("Y-m-d",strtotime($to .' -29 days'));
    }
        $vendorcode = $this->auto->get_default_vendor();
        foreach($vendorcode as $vendor)
        {
            $vendor_products = array();
            $products = $this->auto->get_vendor_products($vendor["vendorcode"]);
            foreach($products as $product) array_push($vendor_products, $product->ProductID);
             echo 'Creating data for '.$vendor["vendorcode"].' ..... ' . PHP_EOL;
            $this->out_of_stock_report($vendor_products,$from,$to,$vendor["vendorcode"],$today, $vendor["user_id"]);
            $products = array();
            echo 'Output data '.$vendor["vendorcode"].'.....' . PHP_EOL;
        }
       
   }



  public function create_received_history($date = null){

        $record = $this->auto->get_received_purchases($date); 
        $this->auto->insert_received_history_summary($record, $date);
        echo "received_history_created".PHP_EOL;
         $branch_code = BRANCH_USE;
         $record_po = $this->auto->get_po_purchases($date,$branch_code); 
         $this->auto->insert_rpo_history_summary($record_po, $date);
         echo "po_history_created";
  }


   public function out_of_stock_report($products,$from,$to,$value_id=null,$today, $purch_id=null){ 
        $se_items =array();
        $sort = array();
        $rows = array();
        $counts = 0;
        $supp_items = $this->auto->auto_get_srs_suppliers_item_details2nd($products,$value_id);
        $se_items = array();
        foreach ($supp_items as $res) {
            $qty = 0;
            $extended = 0;
            $sugg_po = 0;
            $qoh = $res->StockRoom;
            $det['barcode'] = $res->ProductCode;
            $det['levelField'] = $res->LevelField1Code;
            $det['description'] = $res->Description;
            $det['uom'] = $res->uom;
            $det['cost'] = $res->cost;
            $det['qty_by'] = $res->reportqty;
            $det['qoh'] = $qoh;
            $det['divisor'] = 0;
            $det['total_sales'] = 0;
            $det['avg_off_take'] = 0;
            $det['vendor_description'] = $res->vendor_description;
            $det['vendor_code'] = $res->VendorCode;
            $det['sugg_po'] = $sugg_po;
            $det['qty'] = $qty;
            $det['disc1'] = $res->discountcode1;
            $det['disc2'] = $res->discountcode2;
            $det['disc3'] = $res->discountcode3;
            $det['extended'] = $extended;
            $det['srp'] = $res->srp;
            $item[$res->ProductID] = $det;
            $se_items[] = $res->ProductID;
        }
   
        //$divs = $this->auto->get_srs_items_po_divisor($from,$to,$se_items);
        
            /*foreach ($divs as $des) {
                
                if(isset($item[$des->product_id])){
                    $divisor = $des->divisor;
                    $sales = $des->total_sales/$item[$des->product_id]['qty_by'];
                    $offtake = $sales/$divisor;
                    $offtake = number_format($offtake, 2, '.', '');//round($avg_off_take,2);
                      $qoh = $item[$des->product_id]['qoh'];
                      $qoh = ($qoh < 0) ? 0 : $qoh;
                       $qty =  $item[$des->product_id]['qty_by'];
                       if($offtake == 0) continue;
                    $stock_out = floor($qoh/$offtake);
                if ( $stock_out < 7 && $stock_out != 0) {
                        
                        $ord_qty = 0;
                        $delivery_date = $this->auto->get_out_of_stock_po_date($des->product_id,BRANCH_USE);
                        $day_forcast = 7 - $stock_out;
                        $status = "";
                        if(empty($delivery_date))  continue;
                        if($today == $delivery_date->delivery_date) continue;
                            $day_os = date('Y-m-d',strtotime("+".$stock_out." day"));

                            if(!empty($delivery_date) && $delivery_date->delivery_date >= $today) {
                                if($day_os <= $delivery_date->delivery_date){
                                     $ord_qty = $delivery_date->ord_qty;
                                     $status = $delivery_date->delivery_date;
                                     $day_forcast =   (strtotime($delivery_date->delivery_date) - strtotime($day_os)) / (60 * 60 * 24);
                                     $status = $delivery_date->delivery_date;
                                } else if ($day_os > $delivery_date->delivery_date) {
                                    $ord_qty = $delivery_date->ord_qty;
                                    $status = $delivery_date->delivery_date;
                                    $day_forcast =  0;
                                    continue;
                                }
                            }
                            $cof = round($item[$des->product_id]['srp'], 4);
                            $days = $stock_out.' Days';
                            $projected = $day_forcast * $cof * $offtake;
                            $projected = round($projected, 4);
                            $sort[] = str_replace(",","",$projected);
                            $loss_sales = 0;
                            if($day_forcast == 7 && $status == "" && $offtake > 0 ){
                                $loss_sales =  $projected/7;
                            }

                            if($delivery_date->delivery_date != date('Y-m-d') )
                            $rows[] = array(
                                "product_id" => $des->product_id, 
                                "vendor_description" => strip_tags(trim($item[$des->product_id]['vendor_description'])),
                                "product_description" => strip_tags($item[$des->product_id]['description']),
                                "uom" => trim($item[$des->product_id]['uom']),
                                "cof" =>$cof, 
                                "offtake" => round($offtake, 4), 
                                "qoh" => round($qoh, 4), 
                                "days" => $days, 
                                "day_forcast" => $day_forcast, 
                                "projected" => $projected,
                                "status" => $status,
                                "order_qty" =>$ord_qty,
                                "date_today" => $today,
                                "branch" => BRANCH_USE,
                                "levelfield" => trim($item[$des->product_id]['levelField']),
                                "vendorcode" => strip_tags($item[$des->product_id]['vendor_code']),
                                "lost_sales" => $loss_sales

                            );
                    }     
                    
                }
            }*/

             $divs = $this->auto->get_srs_items_po_divisor($from,$to,$se_items);
            foreach ($divs as $des) {
                
                if(isset($item[$des->product_id])){
                    $divisor = $des->divisor;
                    $sales = $des->total_sales/$item[$des->product_id]['qty_by'];
                    $offtake = $sales/$divisor;
                    $offtake = number_format($offtake, 2, '.', '');//round($avg_off_take,2);
                      $qoh = $item[$des->product_id]['qoh'];
                      $qoh = ($qoh < 0) ? 0 : $qoh;
                       $qty =  $item[$des->product_id]['qty_by'];

                    if($qoh > 0 )$stock_out = floor($qoh/$offtake);
                    else $stock_out = 0;

              

                if ( $stock_out < 7 && $offtake != "0.00"/*&& $stock_out != 0*/) {
                        
                        $ord_qty = 0;
                        $delivery_date = $this->auto->get_out_of_stock_po_date($des->product_id,BRANCH_USE);
                        $day_forcast = 7 - $stock_out;
                        $status = "";
                        if($today == $delivery_date->delivery_date) continue;
                            $day_os = date('Y-m-d',strtotime("+".$stock_out." day"));

                            if(!empty($delivery_date) && $delivery_date->delivery_date >= $today) {
                                if($day_os <= $delivery_date->delivery_date){
                                     $ord_qty = $delivery_date->ord_qty;
                                     $status = $delivery_date->delivery_date;
                                     $day_forcast =   (strtotime($delivery_date->delivery_date) - strtotime($day_os)) / (60 * 60 * 24);
                                     $status = $delivery_date->delivery_date;
                                } else if ($day_os > $delivery_date->delivery_date) {
                                    $ord_qty = $delivery_date->ord_qty;
                                    $status = $delivery_date->delivery_date;
                                    $day_forcast =  0;
                                    continue;
                                }
                            }
                            $cof = round($item[$des->product_id]['srp'], 4);
                            $days = $stock_out.' Days';
                            $projected = $day_forcast * $cof * $offtake;
                            $projected = round($projected, 4);
                            $sort[] = str_replace(",","",$projected);
                            $loss_sales = 0;
                            if($day_forcast == 7 && $status == "" && $offtake > 0 ){
                                $loss_sales =  $projected/7;
                            }
                            if($delivery_date->delivery_date != date('Y-m-d') )
                            $rows[] = array(
                                "product_id" => $des->product_id, 
                                "vendor_description" => strip_tags(trim($item[$des->product_id]['vendor_description'])),
                                "product_description" => strip_tags($item[$des->product_id]['description']),
                                "uom" => trim($item[$des->product_id]['uom']),
                                "cof" =>$cof, 
                                "offtake" => round($offtake, 4), 
                                "qoh" => round($qoh, 4), 
                                "days" => $days, 
                                "day_forcast" => $day_forcast, 
                                "projected" => $projected,
                                "status" => $status,
                                "order_qty" =>$ord_qty,
                                "date_today" => $today,
                                "branch" => BRANCH_USE,
                                "levelfield" => trim($item[$des->product_id]['levelField']),
                                "vendorcode" => strip_tags($item[$des->product_id]['vendor_code']),   
                                "lost_sales" => $loss_sales
                            );
                    }     
                    
                }
            }
         
        $this->auto->insert_out_of_stock($rows);   
    } 

    public function throw_os(){
       $data = $this->auto->throw_os(); 
       $bool = (count($data) > 0) ? true : false;
       while($bool){  
         foreach($data as $row){
            $id = $row["id"];
            unset($row["id"]);
            unset($row["throw"]);
            $this->auto->execute_queue($row, $id, "out_of_stock");
        }   
       $data = $this->auto->throw_os(); 
       $bool = (count($data) > 0) ? true : false;
    }
}


}
