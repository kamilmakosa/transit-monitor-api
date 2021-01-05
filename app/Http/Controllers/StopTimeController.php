<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\StopTime;
use Illuminate\Support\Facades\DB;

class StopTimeController extends Controller
{
    public function index() {
        return StopTime::all();
    }

    public function show($trip_id) {
        return DB::table('stop_times')->where('trip_id', 'LIKE', '%_'.$trip_id.'%')->orderBy('stop_sequence')->get();
    }
}
