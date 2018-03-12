<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Project extends Model {

    protected $fillable = [
        'name', 'site', 'info', 'link', 'active', 'tags', 'date'
    ];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships

    public function users()
    {
        return $this->hasMany('App\User');
    }

}
