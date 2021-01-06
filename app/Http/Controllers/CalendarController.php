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
        $calendar = $this->index();
        $dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $response = [];
        foreach($calendar as $service) {
            foreach($dayNames as $day) {
                if ($service[$day] == 1) {
                    $key = array_search($day, $dayNames);
                    $response[$key] = [
                        'day' => $day,
                        'service_id' => $service['service_id'],
                        'start_date' => $service['start_date'],
                        'end_date' => $service['end_date']
                    ];
                }
            }
        }
        for($key=0; $key<7; $key++) {
            if(!array_key_exists($key, $response)) {
                $response[$key] = [
                    'day' => $dayNames[$key],
                    'service_id' => 0,
                    'start_date' => $service['start_date'],
                    'end_date' => $service['end_date']
                ];
            }
        }
        ksort($response);
        return $response;
    }
}
