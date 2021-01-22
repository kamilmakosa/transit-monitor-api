<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = "vehicles";
    protected $primaryKey = "vehicle";

    const vehiclePositionsFile = '/var/www/html/transit-monitor-api/storage/gtfs-rt/vehicle_positions.json';
}
