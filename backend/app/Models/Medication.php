<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    use HasFactory;

    protected $table = 'medication';
    protected $primaryKey = 'medication_id';

    protected $fillable = [
        'item_name',
    ];

    protected $dates = ['deleted_at'];
    public $incrementing = true;
    public $timestamps = true;

    // Removed the patients() relationship since we're not using pivot table
}