<?php

namespace App\Listeners;

use App\Events\FileWanted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessFiles
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  FileWanted  $event
     * @return void
     */
    public function handle(FileWanted $event)
    {
        //
    }
}
