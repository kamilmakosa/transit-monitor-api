<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Stop;

class StopController extends Controller
{
    public function index() {
        return Stop::all();
    }

    public function show($stop_id) {
        return Stop::find($stop_id);
    }
}
