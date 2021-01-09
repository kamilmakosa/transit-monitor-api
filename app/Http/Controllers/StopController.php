<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Stop;
use App\Departures;

class StopController extends Controller
{
    public function index() {
        return Stop::all();
    }

    public function show($stop_id) {
        return Stop::find($stop_id);
    }

    public function showByCode($stop_code) {
        return DB::table('stops')->where('stop_code', $stop_code)->get();
    }

    public function generateDepartures($stop_id) {
        $departures = new Departures;
        return $departures->generate($stop_id);
    }
}
