<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChannelFee extends Model
{
    public function channel()
    {
        return $this->belongsTo('App\Channel', 'channel_id');
    }
}
