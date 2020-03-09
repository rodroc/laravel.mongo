<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;

use Rodroc\Shared\MongoClient;
use Rodroc\Shared\Enums\Collection;
use Rodroc\Shared\PMS;
use Rodroc\Shared\Tasks\SimpleTask;
use Rodroc\Shared\Util\MongoHelper;
use Rodroc\Shared\Traits\RoutineTrait;

use MongoDB\Model\BSONDocument;
use MongoDB\BSON\UTCDateTime;

use Dompdf\Dompdf;

class HistoryController extends Controller
{
	protected $_db;

	public function __construct(){
        $this->_db=MongoClient::db();

        $this->middleware('auth');

        if(!Auth::user()){
            return redirect()->route('login');
        }
        
	}

    public function index($plateNumber){

    	$vehicle=$this->_db->{Collection::VEHICLES}->findOne([
    		'platenumber'=> [
                      '$regex'=> "^{$plateNumber}$", '$options'=>'i'
                    ]
    	]);
    	
    	if(!$vehicle){
    		return 'Could not find the vehicle';
    	}

        $pms=new PMS($this->_db);
        $pmSchedules=$pms->getAllSchedules($plateNumber,true);
        $overDuePmsCount=0;
        foreach($pmSchedules as $pms){
            if($pms->branchid!=null){
                $overDuePmsCount++;
            }
        }

    	$cursor=$this->_db->{Collection::JOB_ORDERS}->find([
            'deleted'=>0,
    		'platenumber'=> [
                      '$regex'=> "^{$plateNumber}$", '$options'=>'i'
                    ]
    	],
        [
           'sort'=>['orderdate.date'=>-1]
        ]
        );    	

    	$list=MongoHelper::cursorToObjectList($cursor);
    	return view('history.joborders',[
    		'platenumber'=>$plateNumber,
    		'vehicle'=>$vehicle,
    		'list'=>$list,
            'overDuePmsCount'=>$overDuePmsCount,
            'pms'=>$pmSchedules
    	]);
    }
    
    public function find(Request $request){

        $plateNumber=$request->plateNumber;

        $vehicle=$this->_db->{Collection::VEHICLES}->findOne([
            'platenumber'=> [
                      '$regex'=> "^{$plateNumber}$", '$options'=>'i'
                    ]
        ]);

        if(!$vehicle){
            return 'Could not find the vehicle';
        }
 
        $searchResult=[];

        $keywords=$request->keywords;
        if( $request->isMethod('get') || empty($keywords) ) $loadSearchPage=true;
        else $loadSearchPage=false;

        $viewData=[
                'platenumber'=>$plateNumber,
                'vehicle'=>$vehicle,
                'vehicleJSON'=>json_encode($vehicle),
                'keywords'=>null,
                'searchResult'=>[]
        ];

        if( $loadSearchPage ) {
            return view('history.find',$viewData);
        }

        $viewData['keywords']=$keywords;

        $keywordList = preg_split("/[\s,]+/", $keywords);

        $taskPattern=PMS::buildMongoRegXpattern($keywordList);
        $partsPattern=PMS::buildMongoRegXpattern($keywordList);

        $cursor=$this->_db->{Collection::JOB_ORDERS}->find([
            'deleted'=>0,
            'platenumber'=>[
                  '$regex'=> "^{$plateNumber}$", '$options'=>'i'
                ],
            '$or'=>[

                [
                    'tasklist.task'=>[
                        '$regex'=> $taskPattern , '$options'=>'i'
                    ]
                ],

                [
                    'partlist.partno'=>[
                        '$regex'=> $partsPattern , '$options'=>'i'
                    ]
                ]
            ]
        ],
        [
            //'limit'=>$limit,
            //'sort'=>['orderdate'=>-1,'tasklist.taskdate.date'=>-1]
        ]
        );

        $objList=MongoHelper::cursorToObjectList($cursor);
        $listCount=RoutineTrait::sizeOfList($objList);

        $taskJO=[];
        $partJO=[];

        foreach($objList as $doc){

            //iterate tasklist
            foreach($doc->tasklist as $t){

                //continue;

                foreach($keywordList as $word){

                    $matchedTask=false;
                    $matchedDetails=false;
                    //match task and details to keywords

                    if( preg_match('/^.*(' .$word .').*/i',$t->task,$matches) ){

                        $matchedTask=true;

                    }elseif( strlen(trim($t->details))>0 ){

                        if( preg_match('/^.*(' .$word .').*/i',$t->details,$matches) ){
                            $matchedDetails=true;
                        }

                    }

                    if( $matchedTask || $matchedDetails ){

                        if( in_array($doc->ordernumber, $taskJO ) ){
                            //task already added to result 
                            //continue;
                        }
                        $taskJO[]=$doc->ordernumber;

                        $item=new \stdClass();
                        $item->orderdate=$doc->orderdate;//date->format('Y-m-d');
                        $item->ordernumber=$doc->ordernumber;
                        $item->category='Task';
                        $item->qty=$t->qty;
                        $item->unit=null;
                        if( $matchedTask ){
                            $item->description=preg_replace('/[\n\t]/','',$t->task);
                            $item->details=$t->details;
                        }else{
                            $item->description=preg_replace('/[\n\t]/','',$t->details);
                            $item->details=$t->details;
                        }
                        $item->branchname=SimpleTask::getBranchNameById($doc->branchid);
                        $item->subtotal=$t->subtotal;

                        $searchResult[]=$item;
                    }

                }//foreach keywordList

            }//taskList

            //iterate partlist
            foreach($doc->partlist as $p){

                foreach($keywordList as $word){

                    if( preg_match('/^.*(' .$word .').*/i',$p->partno,$matches) ){

                        if( in_array($doc->ordernumber, $partJO  ) ){
                            //part already added to result
                            //continue;
                        }
                        $partJO[]=$doc->ordernumber;

                        $item=new \stdClass();
                        $item->orderdate=$doc->orderdate;//->date->format('Y-m-d');
                        $item->ordernumber=$doc->ordernumber;
                        $item->category='Part';
                        $item->qty=$p->qty;
                        $item->unit=$p->unit;
                        $item->description=preg_replace('/[\n\t]/','',$p->partno);
                        $item->details=null;
                        $item->branchname=SimpleTask::getBranchNameById($doc->branchid);
                        $item->subtotal=$p->subtotal;

                        $searchResult[]=$item;

                        
                    }//preg_match

                }//foreach keywordList

            }//foreach partList


        }//objList

        //dd($searchResult);

        $viewData['searchResult']=$searchResult;

        return view('history.find',$viewData);

    }

    public function createpdfjoitems(Request $request){
        $plateNumber=$request->plateNumber;
        $vehicle=$this->_db->{Collection::VEHICLES}->findOne([
            'platenumber'=> [
                      '$regex'=> "^{$plateNumber}$", '$options'=>'i'
                    ]
        ]);

        $items=$request->items;
        $dompdf = new Dompdf();
        $platenumber=$request->plateNumber;
        $list=json_decode($items);
   
        $today=RoutineTrait::getDateTime();

        $view=\View::make('history.partials.selected-jo-items', [
            'today'=> RoutineTrait::getDateTime(),
            'vehicle'=>$vehicle,
            'keywords'=> $request->keywords,
            'list'=>$list,
        ]);
        $dompdf->loadHtml($view->render());

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'landscape');
        // Render the HTML as PDF
        $dompdf->render();
        // Output the generated PDF to Browser
        $dompdf->stream($today->format('Y-m-d His') .' P.R Supporting Doc for ' .$vehicle->platenumber .'.pdf');
    }

}
