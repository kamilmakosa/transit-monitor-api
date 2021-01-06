<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Stop;

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
}
