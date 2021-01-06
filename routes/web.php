<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('api')->group(function () {

    Route::get('/agencies', "AgencyController@index");
    Route::get('/agencies/{agency_id}', "AgencyController@show");

    Route::get('/calendar', "CalendarController@index");
    Route::get('/calendar/{service_id}', "CalendarController@show");
    Route::get('/calendar/key=day', "CalendarController@indexByDay");

    Route::get('/routes', "RouteController@index");
    Route::get('/routes/{route_id}', "RouteController@show");

    Route::get('/shapes', "ShapeController@index");
    Route::get('/shapes/{shape_id}', "ShapeController@show");
    Route::get('/shapes/list', "ShapeController@list");

    Route::get('/stops', "StopController@index");
    Route::get('/stops/{stop_id}', "StopController@show");

    Route::get('/stop_times', "StopTimeController@index");
    Route::get('/stop_times/{trip_id}', "StopTimeController@show");

    Route::get('/trips', "TripController@index");
    Route::get('/trips/{trip_id}', "TripController@show");

    Route::get('/vehicles', "VehicleController@index");
    Route::get('/vehicles/{vehicle_id}', "VehicleController@show");

});
