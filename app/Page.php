<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Page extends Model {

    const PAGE_DEFAULT = 'works';

    protected $fillable = [
        'active', 'text', 'url', 'sort', 'name'
    ];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships

}
