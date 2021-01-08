<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Calendar extends Model
{
    protected $table = "calendar";
    protected $primaryKey = "service_id";

    private const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public static function getServiceId($day) {
        $dayName = Calendar::days[$day];
        return DB::table('calendar')->select('service_id')->where($dayName, 1)->get();
    }

    public static function getByDay() {
        $calendar = Calendar::all();
        $dayNames = Calendar::days;
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
