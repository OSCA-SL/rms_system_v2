<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Artist extends Model
{
    public function user(){
        return $this->belongsTo('App\User');
    }

    public function songs(){
        return $this->belongsToMany('App\Artist', 'song_artists')->withPivot('type');
    }

    public function sang()
    {
        return $this->songs()->wherePivot('type', '=', '1')->get();
    }

    public function music()
    {
        return $this->songs()->wherePivot('type', '=', '2')->get();
    }

    public function wrote()
    {
        return $this->songs()->wherePivot('type', '=', '3')->get();
    }

    public function getType()
    {
        if ($this->type == 1){
            return "Singer";
        }
        elseif ($this->type == 2){
            return "Music Director";
        }
        elseif ($this->type == 3){
            return "Song Writer";
        }
        elseif ($this->type == 4){
            return "Producer";
        }
        elseif ($this->type == 5){
            return "Unknown";
        }
        else{
            return "Undefined Type";
        }

    }

    public function getStatus()
    {
        if ($this->status == 1){
            return "Active Member";
        }
        elseif ($this->status == 2){
            return "Consented Member";
        }
        elseif ($this->status == 3){
            return "Non Member";
        }
        elseif ($this->status == 4){
            return "Deceased, but was an Active member";
        }
        elseif ($this->status == 5){
            return "Deceased, and Consent given member";
        }
        elseif ($this->status == 6){
            return "Deceased, and not consented member";
        }
        else{
            return "Undefined";
        }
    }
}
