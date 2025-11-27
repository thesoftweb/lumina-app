<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $casts = [
		'start_at' => 'date',
		'end_at' => 'date',
		'year' => 'integer',
		'is_defaul' => 'boolean'
	];
	
	protected $fillable = [
		'description',
		'year',
		'start_at',
		'end_at',
		'is_default'
	];

	public function enrollments()
	{
		return $this->hasMany(Enrollment::class);
	}
}
