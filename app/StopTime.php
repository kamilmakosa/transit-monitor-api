<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StopTime extends Model
{
    protected $table = "stop_times";
    protected $primaryKey = [
        'trip_id', 'stop_sequence'
    ];
}
