<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    public function matches()
    {
        return $this->hasMany('App\Match', 'song_id');
    }

    public function contactUser(){
        return $this->belongsTo('App\User', 'contact_user');
    }

    public function addedUser(){
        return $this->belongsTo('App\User', 'added_by');
    }

    public function fee()
    {
        return $this->hasMany('App\Fee', 'channel_id');
    }
}
