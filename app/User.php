<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
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
//    protected $fillable = [
//        'name', 'email', 'password',
//    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function artists(){
        return $this->hasMany('App\Artist');
    }

    public function channelContacts(){
        return $this->hasMany('App\Channel', 'contact_user');
    }

    public function channelAdds(){
        return $this->hasMany('App\Channel', 'added_by');
    }

    public function isArtist()
    {
        return count($this->artists) > 0;
    }

    public function isAdmin()
    {
        return $this->role < 3;
    }
}
