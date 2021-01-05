<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Vehicle;

class VehicleController extends Controller
{
    public function index() {
        return Vehicle::all();
    }

    public function show($vehicle_id) {
        return Vehicle::find($vehicle_id);
    }
}
