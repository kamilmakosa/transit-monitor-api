<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Vehicle;

class VehicleController extends Controller
{
    public function index() {
        return Vehicle::all();
    }

    public function show($vehicle_id) {
        return Vehicle::find($vehicle_id);
    }

    public function livesimple() {
        echo file_get_contents(Vehicle::vehiclePositionsFile);
    }

    public function live() {
        $json = file_get_contents(Vehicle::vehiclePositionsFile);
        $vehsLive = (array) json_decode($json);
        foreach($vehsLive as $key => $value) {
            $trip_info = DB::table('trips')->where('trip_id', 'LIKE', $value->tripId)->get()[0];
            $vehsLive[$key]->trip_info = $trip_info;
            // $shape = DB::table('shapes')->where('shape_id', $trip_info->shape_id)->get();
            // foreach($shape as $keyShape => $valueShape) {
            //     $vehsLive[$key]->path[] = [$valueShape->shape_pt_lat, $valueShape->shape_pt_lon];
            // }

            // var_dump($vehsLive[$key]);
            // break;
        }
        return $vehsLive;
    }
}
