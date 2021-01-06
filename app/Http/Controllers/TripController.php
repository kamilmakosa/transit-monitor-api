<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Trip;
use App\Calendar;

class TripController extends Controller
{
    public function index() {
        return DB::table('trips')->get();
    }

    public function show($trip_id) {
        return DB::table('trips')->where('trip_id', 'LIKE', '%_'.$trip_id.'%')->get();
    }

    public function indexByRoute($route_id, Request $request) {
        if(!$request->day && $request->day != "0") {
            return DB::table('trips')->where('route_id', $route_id)->get();
        } else {
            $calendar = new Calendar;
            $service_id = $calendar->getServiceId($request->day);
            $service_id = $service_id[0]->service_id;
            return DB::table('trips')->where('route_id', $route_id)->where('service_id', $service_id)->get();
        }
  }
}
