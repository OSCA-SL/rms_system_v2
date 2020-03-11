<?php

namespace App\Listeners;

use App\Fingerprint;
use App\Events\SongUploaded;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class SendHashRequest implements ShouldQueue
{

    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public $connection = 'database';

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'songhash';

    /**
     * The time (seconds) before the job should be processed.
     *
     * @var int
     */
    public $delay = 20;

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
     * @param  SongUploaded  $event
     * @return void
     */
    public function handle(SongUploaded $event)
    {
        $song = $event->song;
        $file_path = $event->file_path;

        $register_url = config('app.song_register_url');

        DB::connection('mysql_system')
            ->table('fingerprints')
            ->where('song_id', '=', $song->id)
            ->delete();

        $client = new Client();
        $promise = $client->postAsync($register_url, [
            'json' => [
                'songId' => $song->id,
                'path' => $file_path
            ]
        ]);
        $promise->then(
            function (ResponseInterface $res) use ($song){
                $song->refresh();
                $response_status = $res->getStatusCode();

                if ($response_status >= 200 && $response_status < 300)
                {
                    $fp_count = Fingerprint::where('song_id', $song->id)->count();

                    if ($fp_count > 0){
                        $song->hash_status = 3;
                        Log::channel('songhash')->info('Hash Success', [
                            'song'=>$song,
                            'response'=>$res
                        ]);
                    }
                    else{
                        Log::channel('songhash')->error('Request Success Hash Failed', [
                            'song'=>$song,
                            'response'=>$res
                        ]);
                        $song->hash_status = 2;
                    }
                }
                else
                {
                    Log::channel('songhash')->error('Request Failed', [
                        'song'=>$song,
                        'response'=>$res
                    ]);
                    $song->hash_status = 2;
                }
                $song->save();
            },
            function (RequestException $e) use ($song){
                $song->refresh();
                Log::channel('songhash')->error('Request Exception', [
                    'song'=>$song,
                    'exception'=>$e
                ]);
                if ($song->hash_status > 2){
                    $song->hash_status = 2;
                    $song->save();
                }
            }
        );
    }

    /**
     * Handle a job failure.
     *
     * @param  \App\Events\SongUploaded  $event
     * @param  \Exception  $exception
     * @return void
     */

    public function failed(SongUploaded $event, $exception)
    {
        $song = $event->song;
        Log::channel('songhash')->error('Listener Job Exception', [
            'song'=>$song,
            'exception'=>$exception
        ]);
    }
}
