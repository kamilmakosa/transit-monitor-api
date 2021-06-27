<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Calendar;
use App\Vehicle;

class Departures extends Model
{
    private $tripUpdateFile = '/var/www/html/transit-monitor-api/storage/gtfs-rt/trip_updates.json';

    public function generate($stop_id) {
        $tripUpdates = $this->getTripUpdates();

        $service_id_y = Calendar::getServiceId((date('N')-2+7)%7)[0]->service_id;
        $static_array_yesterday = $this->getDepartureByStop($stop_id, $service_id_y);
        $service_id = Calendar::getServiceId(date('N')-1)[0]->service_id;
        $static_array = $this->getDepartureByStop($stop_id, $service_id);

        $monitor2 = $this->createMonitorYesterday($static_array_yesterday, $tripUpdates, -1);
        $monitor2 = $this->filterDepartures($monitor2);
        $monitor2 = $this->sortDepartures($monitor2);

        $monitor = $this->createMonitor($static_array, $tripUpdates, 0);
        $monitor = $this->filterDepartures($monitor);
        $monitor = $this->sortDepartures($monitor);
        foreach($monitor as $key => $value) {
            if(isset($monitor2[$key])) {
                unset($monitor[$key]);
            }
        }

        $monitor = array_merge($monitor2, $monitor);
        $monitor = array_slice($monitor, 0, 10);

        // return $monitor;
        echo json_encode($monitor, JSON_PRETTY_PRINT);
    }

    // $day_source: today = 0, yeasterday = -1
    private function createMonitorYesterday($static_array, $update_array, $day_source) {
        $monitor = array();
        foreach($static_array as $static_key => $static_value) {
            list($h, $m, $s) = explode(':', $static_value->departure_time);
            if($h < 24) {
                unset($static_array[$static_key]);
            } else {
                $h-=24;
                $static_value->departure_timestamp2 = mktime($h, $m, $s);
                $static_value->departure_time = implode(':', [$h, $m, $s]);

                $static_value->realtime = false;
                $static_value->real_departure_timestamp = intval($static_value->departure_timestamp2);

                if(isset($update_array[$static_value->trip_id])) {
                    $update_value = $update_array[$static_value->trip_id];
                    //$static_value->route_id == $update_value->route_id &&
                    if( $static_value->trip_id == $update_value->trip) {
                        $static_value->vehicle_id = $update_value->vehicle_id;
                        $static_value->vehicle = Vehicle::find($static_value->vehicle_id);
                        $static_value->stop_sequence_now = $update_value->stop_sequence;
                        $static_value->arrival_delay = $update_value->arrival_delay;

                        $static_value->realtime = true;
                        if($static_value->stop_sequence_now > 0) {
                            $static_value->real_departure_timestamp = intval($static_value->departure_timestamp2) + intval($update_value->arrival_delay);
                        }
                        $static_value->real_departure_time = date ("H:i:s", $static_value->real_departure_timestamp);
                    }
                }
                $static_value->source_day = $day_source;

                $monitor[$static_key] = $static_value;
            }
        }
        return $monitor;
    }

    private function createMonitor($static_array, $update_array, $day_source) {
        $monitor = array();
        foreach($static_array as $static_key => $static_value) {
            list($h, $m, $s) = explode(':', $static_value->departure_time);
            $static_value->departure_timestamp2 = mktime($h, $m, $s);
            if($h >= 24) $h-=24;
            $static_value->departure_time = implode(':', [$h, $m, $s]);

            $static_value->realtime = false;
            $static_value->real_departure_timestamp = intval($static_value->departure_timestamp2);

            if(isset($update_array[$static_value->trip_id])) {
                $update_value = $update_array[$static_value->trip_id];
                //$static_value->route_id == $update_value->route_id &&
                if( $static_value->trip_id == $update_value->trip) {
                    $static_value->vehicle_id = $update_value->vehicle_id;
                    $static_value->vehicle = Vehicle::find($static_value->vehicle_id);
                    $static_value->stop_sequence_now = $update_value->stop_sequence;
                    $static_value->arrival_delay = $update_value->arrival_delay;

                    $static_value->realtime = true;
                    if($static_value->stop_sequence_now > 0) {
                        $static_value->real_departure_timestamp = intval($static_value->departure_timestamp2) + intval($update_value->arrival_delay);
                    }
                    $static_value->real_departure_time = date ("H:i:s", $static_value->real_departure_timestamp);
                }
            }
            $static_value->source_day = $day_source;

            $monitor[$static_key] = $static_value;
        }
        return $monitor;
    }

    private function getDepartureByStop($stop_id, $service_id) {
        $pdo = DB::connection()->getPdo();
        
        $trip = "(CASE WHEN INSTR(trip_id, '^') > 0 THEN SUBSTRING(trip_id, INSTR(trip_id, '_')+1, INSTR(trip_id, '^')-INSTR(trip_id, '_')-1) ELSE SUBSTRING(trip_id, INSTR(trip_id, '_')+1, LENGTH(trip_id)-INSTR(trip_id, '_')) END) AS trip";
        $timestamp = "UNIX_TIMESTAMP(CONVERT(CONCAT('1970-01-01', ' ',departure_time), DATETIME)) AS departure_timestamp";
        $columns = "trip_id, " . $trip . ", stop_sequence, arrival_time, departure_time, " . $timestamp . ",  pickup_type, drop_off_type, route_id, service_id, trip_headsign, wheelchair_accessible";
        $columns = "trip_id, " . $trip . ", stop_sequence, arrival_time, departure_time, " . $timestamp . ",  route_id, route_short_name, route_long_name, route_color, route_text_color, service_id, trip_headsign";
        $sql = "SELECT " . $columns . " FROM stop_times JOIN trips USING(trip_id) JOIN routes USING(route_id) WHERE stop_id=" . $stop_id . " AND service_id=" . $service_id;

        // $url = "php/getSQL.php?key=trip&sql=" . urlencode($sql);
        // $json_data = file_get_contents('http://51.178.29.39/poznan_public_transit/'.$url);
        // return (array) json_decode($json_data);

        $stmt = $pdo->query($sql);
        while ( $row = $stmt->fetch(\PDO::FETCH_OBJ) ) {
            $result[$row->trip] = $row;
        }
        return $result;
    }

    private function filterDepartures($departuresArray) {
        return array_filter($departuresArray, function($x) {
            if($x->real_departure_timestamp >= time() && ($x->realtime == false || ($x->realtime == true && $x->stop_sequence_now <= $x->stop_sequence))) {
              return true;
            }
            return false;
        });
    }

    private function sortDepartures($departuresArray) {
        uasort($departuresArray, function($x, $y) {
            if($x->real_departure_timestamp == $y->real_departure_timestamp) {
                return 0;
            }
            return ($x->real_departure_timestamp > $y->real_departure_timestamp) ? 1 : -1;
        });
        return $departuresArray;
    }

    private function getTripUpdates() {
        $json_data = file_get_contents($this->tripUpdateFile);
        $arr1 = json_decode($json_data);
        foreach($arr1 as $key => $value) {
            $obj = new \stdClass();
            $obj->trip = $value->tripId;
            $obj->route_id = $value->routeId;
            $obj->vehicle_id = $value->id;
            $obj->stop_sequence = $value->stopSequence;
            if($obj->stop_sequence == 0 && $value->arrival_delay < 0) {
                $obj->arrival_delay = 0;
            } else {
                $obj->arrival_delay = $value->arrival_delay;
            }
            $update_array[$value->tripId] = $obj;
        }
        return $update_array;
    }
}
