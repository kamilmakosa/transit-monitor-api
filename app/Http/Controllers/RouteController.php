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

    public function showStops(Request $request, $route_id) {
    $direction_id = $request->query('direction', 0);
  	return DB::table('stop_times')
      ->select('stop_times.stop_sequence', 'stops.stop_id', 'stops.stop_name')
      ->join('stops', function($join) {
        $join->on('stop_times.stop_id', '=', 'stops.stop_id');
      })
      ->where('trip_id', function($query) use ($route_id, $direction_id) {
        $query->select('trip_id')->from('trips')
        ->where('route_id', '=', $route_id)
        ->where('direction_id', '=', $direction_id )
        ->whereRaw('INSTR(trip_id, "+") > 0')
        ->limit(1);
      })
      ->orderBy('departure_time')
      ->get(); //toSql()
  }

  public function showTracks($route_id, $option = array("stop-hash" => true)) {
    $defaultOption = array(
      "stop-list" => false,
      "stop-hash" => false,
    );

    $option = array_merge($defaultOption, $option);

    $trips = DB::table('trips')->distinct()
      ->select('route_id', 'trip_headsign', 'direction_id', 'shape_id')
      ->where('route_id', '=', $route_id)
      ->orderBy('direction_id')
      ->get();

    foreach ($trips as $key => $trip) {
      // var_dump($trip);
      $direction_id = $trip->direction_id;
      $trip_headsign = $trip->trip_headsign;
      $shape_id = $trip->shape_id;

      $trip->first_stop = null;
      $trip->last_stop = null;

      $trip->stops = DB::table('stop_times')
      ->select('stop_times.stop_sequence', 'stops.stop_id', 'stops.stop_name', 'stop_times.stop_headsign', 'stop_times.trip_id')
      ->join('stops', function($join) {
        $join->on('stop_times.stop_id', '=', 'stops.stop_id');
      })
      ->where('trip_id', function($query) use ($route_id, $direction_id, $trip_headsign, $shape_id) {
        $query->select('trip_id')->from('trips')
        ->where('route_id', '=', $route_id)
        ->where('direction_id', '=', $direction_id)
        ->where('trip_headsign', '=', $trip_headsign)
        ->where('shape_id', '=', $shape_id)
        ->limit(1);
      })
      ->orderBy('stop_sequence')
      ->get();

      $trip->first_stop = $trip->stops[0]->stop_id.' | '.$trip->stops[0]->stop_sequence.'. '.$trip->stops[0]->stop_name;
      // $trip->last_stop = array_slice($trip->stops->toArray(), -1)[0];
      $last_key = array_key_last($trip->stops->toArray());
      $trip->last_stop = $trip->stops[$last_key]->stop_id.' | '.$trip->stops[$last_key]->stop_sequence.'. '.$trip->stops[$last_key]->stop_name;
      $trip->trip_id = $trip->stops[0]->trip_id;
      $trip->service_id = substr($trip->trip_id, 0, strpos($trip->trip_id, '_'));
      $trip->stops = $trip->stops->toArray();
      array_walk($trip->stops, function (&$v, $key) {
        unset($v->trip_id);
      });

      if($option['stop-hash']) {
        // dd($trip->stops->toArray());
        $trip->stop_hash = implode(',', array_column($trip->stops, 'stop_id'));
      }

      if(!$option['stop-list']) {
        unset($trip->stops);
      }
    }

    return $trips->toJson(JSON_PRETTY_PRINT);
  }

  public function checkAllTracks() {
    $routes = $this->index();
    foreach($routes as $route) {
      $tracks = json_decode($this->showTracks($route->route_id));
      $hashes = array_column($tracks, 'stop_hash');
      $c1 = count($hashes);
      $c2 = count(array_unique($hashes));
      if($c1 == $c2) continue;
      echo $route->route_id." RESULT: ".$c1." ".$c2."<br/>\n";
      dump($tracks);
      echo "<br/>\n";
    }
    // return json_encode($tracks, JSON_PRETTY_PRINT);
  }

  public function showStopPlan(Request $request, $route_id) {
    $mode = $request->query('mode', 'prod');

    $tracks = json_decode($this->showTracks($route_id, ["stop-hash" => true, "stop-list" => true]));

    $hashes = array_column($tracks, 'stop_hash');
    $unique_hashes = array_unique($hashes);
    if($mode == 'dev') {
      dump($tracks);
      dump($unique_hashes);
    }
    foreach ($tracks as $key => $track) {
      if(($array_key = array_search($track->stop_hash, $unique_hashes)) !== false) {
        unset($unique_hashes[$array_key]);
      } else {
        unset($tracks[$key]);
      }
    }
// $primary_tracks = array();
    // Step 1. Find primary track
    foreach ($tracks as $key => $track) {
      if(strpos($track->trip_id, '+') !== false) { //&& in_array( (int) $track->service_id, [5, 3, 7])
        if($mode == 'dev') {
          echo 'Find Primary Track [index '.$key.']<br/>';
        }
        $primary_tracks[$track->direction_id] = $track;
        unset($tracks[$key]);
      }
    }
    if($mode == 'dev') {
      echo "Primary Tracks:";
      dump($primary_tracks);
      echo "Other Tracks:";
      dump($tracks);
    }

    // Step 2. Merge branches
    foreach ($tracks as $key => $track) {
      $direction_id = $track->direction_id;
      if(!array_key_exists($direction_id, $primary_tracks)) continue;
      // dd($track);
      $primary_track = explode(',', $primary_tracks[$direction_id]->stop_hash);
      $extra_track = explode(',', $track->stop_hash);
      $common = $this->get_longest_common_subarray($primary_track, $extra_track);
$nocommon = [];
      if($extra_track[0] == $primary_track[0] && $extra_track[count($extra_track)-1] == $primary_track[count($primary_track)-1]) {
        $t = $primary_track; //primary without common
        array_splice($t, array_search($common[0], $t), count($common));
        $common_nocommon = $this->get_longest_common_subarray($t, $extra_track);
        // dd($common_nocommon);
      } else if($extra_track[0] == $primary_track[0]) {
        // dump('X');
        $nocommon = array_slice($extra_track, array_search($common[count($common)-1], $extra_track)+1);
        // dump($nocommon);
      } else if($extra_track[count($extra_track)-1] == $primary_track[count($primary_track)-1]) {
        // dump('Y');
        $nocommon = array_slice($extra_track, 0, array_search($common[0], $extra_track));
      } else {
        // dump(array(
        //   "primary_track_length" => count($primary_track),
        //   "common_track_length" => count($common),
        //   "nocommon_track_length" => count($nocommon)
        // ));
      }
      // dump($common);
      $t = $primary_track; //primary without common
      array_splice($t, array_search($common[0], $t), count($common));
      // dump($t);
      // dump($nocommon);
      $common_nocommon = $this->get_longest_common_subarray($t, $nocommon);
      // dd($common_nocommon);
      if(count($common_nocommon) == 1) {
        // echo 'wspolny koniec';
      } else if(count($common_nocommon) > 0 && $common_nocommon[count($common_nocommon)-1] == $primary_track[count($primary_track)-1]) {
        // echo 'wspolny koniec';
      }

      if($mode == 'dev') {
        dump($common);
        dump($nocommon);
        dump($common_nocommon);
      }
      if(count($nocommon) == 0) {
        if(array_search($common[0], $primary_track) === 0) {
          if($mode == 'dev') echo 'trasa skrocona na koncu';
        } else if(array_search($common[0], $primary_track) + count($common) == count($primary_track)) {
          if($mode == 'dev') echo 'trasa skrocona na poczatku';
        } else {
          if($mode == 'dev') echo 'trasa skrocona z obu koncow';
        }
      } else if(array_search($nocommon[0], $extra_track) === 0) {
        if($mode == 'dev') echo 'inny poczatek';
        $primary_tracks[$direction_id]->stops[array_search($common[0], $primary_track)]->before = array_slice($track->stops, array_search($nocommon[0], $extra_track), count($nocommon));
      } else if(array_search($nocommon[0], $extra_track) + count($nocommon) == count($extra_track)) {
        if($mode == 'dev') echo 'inny koniec';
        $primary_tracks[$direction_id]->stops[array_search($common[count($common)-1], $primary_track)]->after = array_slice($track->stops, array_search($nocommon[0], $extra_track), count($nocommon));
      }
    }
    $direction_id = $request->query('direction', min(array_keys($primary_tracks)));
    if($mode == 'dev') dd($primary_tracks[$direction_id]);
    return $primary_tracks[$direction_id]->stops;
    // dd($primary_tracks[$direction_id]);
  }

  public function showDetails($route_id) {
    // $routes = \App\Agency::all();
    $route = $this->show($route_id);

    // foreach ($routes as $key => &$route) {
      $route['agency_name'] = \App\Agency::getAgencyName($route['agency_id']);
      $route['extra_info'] = $this->showExtraInfos($route['route_id']);
    // }

    dump($route->toArray());
  }

  public function showHeadsigns($route_id) {
    $headsigns = DB::select("SELECT route_id, trip_headsign, direction_id, COUNT(trip_headsign) as count FROM trips WHERE route_id = ? GROUP BY route_id, trip_headsign, direction_id", [$route_id]);
    $result = [];
    foreach ($headsigns as $key => $record) {
      if(array_key_exists($record->direction_id, $result)) {
        if($result[$record->direction_id]->count < $record->count) {
          $result[$record->direction_id] = $record;
        }
      } else {
        $result[$record->direction_id] = $record;
      }
    }
    return $result;

    // DB::enableQueryLog();
    return DB::table('trips')->distinct()
      ->select('route_id', 'trip_headsign', 'direction_id')
      ->where('route_id', '=', $route_id)
      ->whereRaw('INSTR(trip_id, "+") > 0 AND service_id IN (5, 1, 7)')
      ->orderBy('direction_id')
      ->get();
      // dd(DB::getQueryLog());
  }

  public function showHeadsignsByStopId($stop_id) {
    return DB::table('trips')->distinct()
      ->select('route_id', 'trip_headsign')
      ->join('stop_times', function($join) {
        $join->on('trips.trip_id', '=', 'stop_times.trip_id');
      })
      ->where('stop_id', '=', $stop_id)
      ->whereRaw('INSTR(trips.trip_id, "+") > 0')
      ->orderBy('route_id')
      ->get();
  }

  public function showHeadsignsByStopName($stop_name) {
    return DB::table('stop_times')->distinct()
      ->select('stop_times.stop_id', 'trips.route_id', 'trips.trip_headsign')
      ->join('trips', function($join) {
        $join->on('trips.trip_id', '=', 'stop_times.trip_id');
      })
      ->join('stops', function($join) {
        $join->on('stop_times.stop_id', '=', 'stops.stop_id');
      })
      ->where('stop_name', '=', $stop_name)
      ->whereRaw('INSTR(trips.trip_id, "+") > 0')
      ->orderBy('trips.route_id')
      ->get();
  }

  public function showExtraInfos($route_id) {
    $result = DB::table('routes')->distinct()
      ->select('route_desc')
      ->where('route_id', '=', $route_id)
      ->get();

    $route_desc = ($result->toArray())[0]->route_desc;
    $route_desc = explode('|', $route_desc);
    $response = array();
    foreach ($route_desc as $key => $value) {
      $route_desc[$key] = explode('^', $value);
      if(array_key_exists(0, $route_desc[$key])) {
        $response[$key]['track'] = trim($route_desc[$key][0]);
      }
      foreach ($route_desc[$key] as $key2 => $value) {
        if($key2) {
          $newKey = trim(substr($value, 0, strpos($value, '-')));
          $response[$key]['extra'][$newKey] = trim(substr($value, strpos($value, '-')+1));
        }
      }
    }
    return $response;
  }

  private function get_longest_common_subarray($array_1, $array_2) {
    $array_1_length = count($array_1);
    $array_2_length = count($array_2);
    $result = array();

    if ($array_1_length === 0 || $array_2_length === 0) {
  		// No similarities
  		return $result;
  	}

    $longest_common_subarray = array();
    $largest_size = 0;

  	// Initialize the CSL array to assume there are no similarities
  	$longest_common_subarray = array_fill(0, $array_1_length, array_fill(0, $array_2_length, 0));

    for ($i = 0; $i < $array_1_length; $i++) {
      for ($j = 0; $j < $array_2_length; $j++) {
        if ($array_1[$i] === $array_2[$j]) {
          if ($i === 0 || $j === 0) {
  					// It's the first character, so it's clearly only 1 character long
  					$longest_common_subarray[$i][$j] = 1;
  				} else {
            $longest_common_subarray[$i][$j] = $longest_common_subarray[$i - 1][$j - 1] + 1;
          }

          if ($longest_common_subarray[$i][$j] > $largest_size) {
  					// Remember this as the largest
  					$largest_size = $longest_common_subarray[$i][$j];
  					// Wipe any previous results
  					$result = array();
  					// And then fall through to remember this new value
  				}

          if ($longest_common_subarray[$i][$j] === $largest_size) {
  					// Remember the largest string(s)
  					$result = array_slice($array_1, $i - $largest_size + 1, $largest_size);
  				}
        }
        // Else, $CSL should be set to 0, which it was already initialized to
      }
    }
    // dump($longest_common_subarray );
    // dump($result);
    return $result;
  }
}
