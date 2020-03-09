@inject('simpleTask', 'Rodroc\Shared\Tasks\SimpleTask')

@extends('layouts.master')

@section('title')
Vehicles
@endsection

@section('page-styles')

<link href="/plugins/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />


<style type="text/css">
</style>
@endsection

@section('content-header')
      <h1>
        {{ $platenumber }}
        <small class="">Plate Number</small>
      </h1>
      {{ $vehicle->model }}<br />
      {{ $vehicle->fuel }}&nbsp;{{ $vehicle->trans }}<br />      
      {{ 
                Html::link(
                  '/customers/name/?fullname='.urlencode($vehicle->customername),
                  $vehicle->customername)           
            }}<br />
      <ol class="breadcrumb">
        <li><a href="/vehicles"><i class="fa fa-dashboard"></i> Vehicles</a></li>
        <li class="active"><a href="/history/{{ $platenumber }}">{{ $platenumber }}</a></li>
      </ol>
      <br />
      <div class="btn-group">
        <a href="/history/{{ $platenumber }}/find" class="btn btn-primary" aria-label="Left Align"><span class="glyphicon glyphicon glyphicon-search" aria-hidden="true"></span>&nbsp;&nbsp;Find Task/Parts</a>
        <a href="/rescue/register/?platenumber={{ $platenumber }}&customername={{ urlencode($vehicle->customername)}}&blank=0" class="btn btn-warning">
          <i class="fa fa-ambulance"></i>&nbsp;&nbsp;Register for Rescue
        </a>
        <button type="button" class="hidden btn btn-default" aria-label="Center Align"><span class="glyphicon glyphicon-align-center" aria-hidden="true"></span></button>
        <button type="button" class="hidden btn btn-default" aria-label="Right Align"><span class="glyphicon glyphicon-align-right" aria-hidden="true"></span></button>
        <button type="button" class="hidden btn btn-primary" aria-label="Justify"><span class="glyphicon glyphicon-align-justify" aria-hidden="true"></span></button>
      </div>

@endsection

@section('content')
            
  <div class="row">
    <div class="col-sm-12 col-md-12 col-lg-12">
        <div class="box box-danger">
          <div class="box-header with-border">
            <h3 class="box-title">Overdue Preventive Maintenance Schedule (PMS)</h3>
            <div class="box-tools pull-right">
              <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
              </button>
            </div>
            <!-- /.box-tools -->
          </div>
          <!-- /.box-header -->
          <div class="box-body">

            @if($overDuePmsCount>0)
            <table id="overdue-pms" class="table table-hover table-condensed">
            <thead>
            <tr>
              <th>Interval(days)</th>
              <th>PM Task</th>
              <th>Branch/J.O#</th>
              <th>Last Rendered</th>
              <th>Due Date</th>
              <th>Days Past Due</th>
            </tr>
            </thead>
            @foreach($pms as $p)
              @if($p->branchid!=null)
              <tr class="warning" >
                <td>{{ $p->pmtask->interval }}</td>
                <td>{{ $p->pmtask->groupname }}</td>
                <td>{{ $p->branch }} / {{ $p->ordernumber }}</td>
                <td>{{ (new \DateTime($p->taskdate->date))->format('M j, Y') }}</td>
                <td>{{ (new \DateTime($p->duedate->date))->format('M j, Y') }}</td>
                <td>{{ $p->dayspastdue }} day(s)</td>   
              </tr>
              @endif
            @endforeach
          </table>
          @else
            <center>--No Overdue PM Task--</center>
          @endif
          </div>
          <!-- /.box-body -->
        </div>
        <!-- /.box -->
      </div>           
  </div>

  <div class="row">
    <div class="col-sm-12 col-md-12 col-lg-12">
        <div class="box box-warning">
          <div class="box-header with-border">
            <h3 class="box-title">Recommended Preventive Maintenance</h3>
            <div class="box-tools pull-right">
              <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
              </button>
            </div>
            <!-- /.box-tools -->
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <button type="button" class="hidden btn btn-default btn-block"><i class="fa fa-calendar-check-o fa-1x"></i>&nbsp;Generate PMS</button>
            <table id="recommended-pms" class="table table-hover table-condensed">
            <thead>
            <tr>
              <th>Interval(days)</th>
              <th>PM Task</th>
              <th>Remarks</th>
            </tr>
            </thead>              
            @foreach($pms as $p)
              @if($p->branchid==null)
              <tr>
                <td>{{ $p->pmtask->interval }}</td>                
                <td>
                  {{ $p->pmtask->groupname }}
                </td>
                <td>
                  -- no history of task --
                </td>
              </tr>              
              @endif
            @endforeach
          </table>
          </div>
          <!-- /.box-body -->
        </div>
        <!-- /.box -->
      </div>           
  </div>

  <div class="row">
    <div class="col-sm-12 col-md-12 col-lg-12">
      <h3>Repair History</h3>
      <table class="table-responsive">
        <table class="table table-hover table-striped table-condensed">
        <thead>
        <tr>
          <th colspan="2">Order Date</th>
          <th>Branch/Job Order</th>
          <th colspan="2">Mileage in</th>
        </tr>
        </thead>
        @foreach($list as $item)
          <tr class="" style="background-color: #DD4B39;color:white;">
            <td colspan="2">{{ (new \DateTime($item->orderdate->date))->format('Y-m-d H:m') }}</td>
            <td>{{ $simpleTask->getBranchNameById($item->branchid) }} / J.O# <a href="/joborders/view/{{ $item->_id }}" target="_blank" style="color:white;">{{ $item->ordernumber }}</a></td>
            <td colspan="2">{{ $item->mileage_in }}</td>
          </tr>
          @if(count($item->partlist)>0)
            <tr>  
              <td colspan="5" class="">
                <center>
                  <span class="label label-default">Parts</span>
                </center>
              </td>
            </tr>
            @foreach($item->partlist as $p)
            <tr>
              <td>{{ $p->pgroup }}</td>         
              <td colspan="3">{{ $p->partno }}</td>
              <td>{{ $p->qty }}&nbsp;{{ $p->unit }}</td>
            </tr>
            @endforeach
            @else
            <tr>
              <td colspan="5" class="">
                <center> -- no parts ordered --</center>
              </td>
            </tr>
          @endif

          @if(count($item->tasklist)>0)    
            <tr>
              <td colspan="5" class="">
                <center>
                  <span class="label label-default">Labor</span>
                  <span class="badge hidden">Labor</span>
                </center>
              </td>
            </tr>
            @foreach($item->tasklist as $t)
            <tr>
              <td>{{ $t->tgroup }}</td>         
              <td>{{ $t->task }}</td>
              <td colspan="2">    
              {{ $t->details }}        
                <button type="button" class="hidden btn btn-default" data-container="body" data-toggle="popover" data-placement="top" data-content="Vivamus sagittis lacus vel augue laoreet rutrum faucibus.">
                  
                </button>
              </td>
              <td>
              <button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="left" title="{{ $t->tech }}"><i class="fa fa-user"></i></button>
              </td>
            </tr>
            @endforeach
            @else
              <tr>
                <td colspan="5" class="">
                  <center> -- no task rendered --</center>
                </td>
              </tr>
          @endif    

        @endforeach
        </table>
      </table>
      </div>
  </div>
  
@endsection

@section('page-scripts')

<script src="/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/plugins/datatables/dataTables.bootstrap.min.js"></script>

<script>
  
  @if($overDuePmsCount>0)
    $('#overdue-pms').DataTable();
  @endif
  
  $('#recommended-pms').DataTable();

</script>
@endsection