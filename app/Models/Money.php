<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Money extends Model {

    public $timestamps = false;

    protected $fillable = [
        'year', 'month', 'type', 'sum', 'project', 'date_payed', 'is_payed', 'status', 'comment'
    ];

    protected $casts = [
        'is_payed' => 'boolean',
    ];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships

}
