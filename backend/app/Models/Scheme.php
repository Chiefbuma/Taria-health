<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scheme extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'scheme_id';

    protected $fillable = [
        'name',
    ];

    protected $dates = ['deleted_at'];
}