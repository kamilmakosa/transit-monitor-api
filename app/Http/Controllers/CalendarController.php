<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Calendar;

class CalendarController extends Controller
{
    public function index() {
        return Calendar::all();
    }

    public function show($service_id) {
        return Calendar::find($service_id);
    }

    public function indexByDay() {
        return Calendar::getByDay();
    }
}
