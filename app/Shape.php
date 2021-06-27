<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Shape extends Model
{
    protected $table = "shapes";
    protected $primaryKey = "shape_id";

    public static function getShapesList() {
        return DB::table('shapes')->select('shape_id')->distinct()->get();
    }

    public static function getShape($shape_id) {
        $cords = DB::table('shapes')->where('shape_id', $shape_id)->orderBy('shape_pt_sequence')->get(['shape_pt_lat', 'shape_pt_lon'])->toArray();
        $cords = array_map(function($value) {
            return [$value->shape_pt_lat, $value->shape_pt_lon];
        }, $cords);
        return array(
            'shape_id' => $shape_id,
            'path' => $cords
        );
    }
}
