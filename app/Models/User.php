<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'site', 'nick', 'email', 'password' // Добавил email и password для Filament
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['avatar_url'];

    /**
     * Автоматическое хеширование пароля (очень полезно для Laravel 11+)
     */
    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Проекты пользователя
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'users_to_projects')->withPivot('role');
    }

    /**
     * URL аватарки пользователя
     */
    public function getAvatarUrlAttribute(): string
    {
        return "https://pashaman.dev/images/authors/{$this->id}.jpg";
    }
}
