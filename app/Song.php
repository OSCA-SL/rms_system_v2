<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Song extends Model
{

    use SoftDeletes;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['song_status'];

    public function matches()
    {
        return $this->hasMany('App\Match', 'song_id');
    }

    public function addedUser(){
        return $this->belongsTo('App\User', 'added_by');
    }

    public function approvedUser(){
        return $this->belongsTo('App\User', 'approved_by');
    }

    public function artists(){
        return $this->belongsToMany('App\Artist', 'song_artists')->withPivot('type');
    }

    public function singers(){
        return $this->belongsToMany('App\Artist', 'song_artists')
            ->withPivot('type')
            ->wherePivot('type', '=', '1');
//        return $this->artists()->wherePivot('type', '=', '1')->get();
    }

    public function musicians(){
        return $this->belongsToMany('App\Artist', 'song_artists')
            ->withPivot('type')
            ->wherePivot('type', '=', '2');
//        return $this->artists()->wherePivot('type', '=', '2')->get();
    }


    public function writers(){
        return $this->belongsToMany('App\Artist', 'song_artists')
            ->withPivot('type')
            ->wherePivot('type', '=', '3');
//        return $this->artists()->wherePivot('type', '=', '3')->get();
    }

    public function producers(){
        return $this->belongsToMany('App\Artist', 'song_artists')
            ->withPivot('type')
            ->wherePivot('type', '=', '4');
//        return $this->artists()->wherePivot('type', '=', '4')->get();
    }

    public function isSinger($artist_id)
    {
        return $this->singers->contains($artist_id);
    }

    public function isMusician($artist_id)
    {
        return $this->musicians->contains($artist_id);
    }

    public function isWriter($artist_id)
    {
        return $this->writers->contains($artist_id);
    }

    public function isProducer($artist_id)
    {
        return $this->producers->contains($artist_id);
    }

    public function isArtist($artist_id)
    {
        return $this->artists->contains($artist_id);
    }

    public function fileName()
    {
        return pathinfo(public_path($this->file_path))['basename'];
    }

    public function fileSize()
    {
        return Storage::disk('public')->size("songs/{$this->fileName()}");
    }

    /**
     * Get the hash status for the song.
     *
     * @return bool
     */
    public function getSongStatusAttribute()
    {
        if ($this->hash_status == 0){
            return "Uploading to Main server failed!";
        }
        elseif ($this->hash_status == 1){
            return "Uploaded to Main server";
        }
        elseif ($this->hash_status == 2){
            return "Uploaded, but hashing failed!";
        }
        elseif ($this->hash_status == 3){
            return "Uploaded & Hashed!";
        }
        else{
            return "Unknown state!";
        }
    }
}
