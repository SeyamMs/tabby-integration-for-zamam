<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
	protected $guarded = [];

	protected $casts = [
		'hotel_id' => 'integer',
		'package_id' => 'integer',
		'user_id' => 'integer',
		'is_paid' => 'integer',
		'rooms' => 'integer'
	];

	public function User()
	{
		return $this->belongsTo('App\User')->select('id', 'phone', 'name', 'code', 'email');
	}

	public function Hotel()
	{
		return $this->belongsTo('App\Hotel')->select('id', 'title_ar')->with('Image');
	}

	public function Package()
	{
		return $this->belongsTo('App\Package');
	}

	public function PackageFeatures()
	{
		return $this->hasMany('App\BookingPackageFeature')->with('Details');
	}
}
