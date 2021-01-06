<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Calendar extends Model
{
    protected $table = "calendar";
    protected $primaryKey = "service_id";

    public function getServiceId($day) {
        $dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $dayName = $dayNames[$day];
        return DB::table('calendar')->select('service_id')->where($dayName, 1)->get();
    }
}
