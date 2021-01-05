<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Route;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function index() {
        return Route::all()->sortBy('route_id')->keyBy('route_id');
    }

    public function show($route_id) {
        return Route::find($route_id);
    }
}
