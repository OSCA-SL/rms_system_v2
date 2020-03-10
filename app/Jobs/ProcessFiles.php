<?php

namespace App\Jobs;

use App\Channel;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use Psr\Http\Message\ResponseInterface;

class ProcessFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public $queue = 'processfiles';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $post_data = [
            "folder_path" => Storage::disk('clips')->path('merged'),
            "channels" => [],
        ];

        $channels = Channel::all();

        /*
         * For each channels
         * */
        foreach ($channels as $channel){
            $channel_data = [];
            $now = Carbon::now();

            if ($channel->last_fetch_at == null){
                $channel->last_fetch_at = Carbon::now()->subMinutes(20)->toDateTimeString();
                $channel->save();
                $channel->refresh();
            }

            if ($channel->aired_time == null){
                $channel->aired_time = Carbon::now()->subMinutes(20)->toDateTimeString();
                $channel->save();
                $channel->refresh();
            }

            if ($channel->isMatchRequestOk()){
                $nextFetch = $channel->getNextFetch();

            }
            else{
                $nextFetch = $channel->getCurrentFetch();
            }

            $ftp_path = "FM/logger{$channel->logger}/{$nextFetch['day']}/{$nextFetch['hour']}/{$nextFetch['minute']}.wma";

            if (Storage::disk('ftp')->exists($ftp_path)) {
                $lastModified = Storage::disk('ftp')->lastModified($ftp_path);
                $lastModified = date('Y-m-d H:i:s', $lastModified);

                $difference = Carbon::parse($lastModified)->diffInMinutes($now);

                if ($now->gt(Carbon::parse($lastModified)) && $difference >= 4) {

                    $fetches = $difference % 2 == 0 ? ($difference - 2) / 2 : ($difference - 3) / 2;

                    for ($i = 0; $i < $fetches; $i++) {

                        $lastModified = Storage::disk('ftp')->lastModified($ftp_path);

                        $lastModified = date('Y-m-d H:i:s', $lastModified);

                        try{
                            $ftp_clip1 = Storage::disk('ftp')->get($ftp_path);
                        }
                        catch (FileNotFoundException $e) {
                            Log::channel('clipsmerge')->error('File Not Found Clip 1', [
                                'path'=>$ftp_path,
                                'exception'=>$e
                            ]);
                            continue;
                        }

                        $clip1_name = "{$channel->id}_{$nextFetch['day']}_{$nextFetch['hour']}_{$nextFetch['minute']}";
                        $clip1_path = "fetched/{$clip1_name}.wma";
                        Storage::disk('clips')->put($clip1_path, $ftp_clip1);

                        if (Storage::disk('clips')->exists($clip1_path)) {

                            $channel->last_fetch_at = Carbon::now()->toDateTimeString();
                            $channel->aired_time = $lastModified;
                            $channel->fetch_status = $channel->setFirstClipOk();
                            $channel->save();
                            $channel->setFetched($nextFetch);

                            $nextFetch = $channel->getNextFetch();

                            $ftp_path = "FM/logger{$channel->logger}/{$nextFetch['day']}/{$nextFetch['hour']}/{$nextFetch['minute']}.wma";

                            if (Storage::disk('ftp')->exists($ftp_path)) {

                                try {
                                    $ftp_clip2 = Storage::disk('ftp')->get($ftp_path);
                                }
                                catch (FileNotFoundException $e) {
                                    Log::channel('clipsmerge')->error('File Not Found Clip 2', [
                                        'path'=>$ftp_path,
                                        'exception'=>$e
                                    ]);
                                    continue;
                                }

                                $clip2_name = "{$channel->id}_{$nextFetch['day']}_{$nextFetch['hour']}_{$nextFetch['minute']}";
                                $clip2_path = "fetched/{$clip2_name}.wma";

                                Storage::disk('clips')->put($clip2_path, $ftp_clip2);

                                if (Storage::disk('clips')->exists($clip2_path)) {

                                    $merged_file_name = "{$clip1_name}-{$clip2_name}";

                                    $channel->clip_path = $merged_file_name;

                                    $channel->last_fetch_at = Carbon::now()->toDateTimeString();
                                    $channel->fetch_status = $channel->setSecondClipOk();
                                    $channel->save();
                                    $channel->setFetched($nextFetch);

                                    $merged_file_path = "merged/{$merged_file_name}.wma";
                                    $final_file_path = "merged/{$merged_file_name}.wav";
                                    $output = Storage::disk('clips')->path($merged_file_path);

                                    $ffmpeg = FFMpeg::create();
                                    $clip1 = $ffmpeg->open(Storage::disk('clips')->path($clip1_path));

                                    if (Storage::disk('clips')->exists($merged_file_path)){
                                        Log::channel('clipsmerge')->error('Merged File Already Exists ', [
                                            'path'=>$merged_file_path
                                        ]);
                                        Storage::disk('clips')->delete($merged_file_path);
                                    }

                                    $clip1->concat(
                                            [
                                                Storage::disk('clips')->path($clip1_path),
                                                Storage::disk('clips')->path($clip2_path)
                                            ]
                                        )->saveFromSameCodecs($output, TRUE);

                                    if (Storage::disk('clips')->exists($merged_file_path)) {

                                        /*
                                         * If merged & converted (final) file already exists, delete it before converting using FFMPEG
                                         * */

                                        if (Storage::disk('clips')->exists($final_file_path)) {
                                            Log::channel('clipsmerge')->error('Converted File Already Exists ', [
                                                'path'=>$final_file_path
                                            ]);
                                            Storage::disk('clips')->delete($final_file_path);
                                        }

                                        $final_clip = $ffmpeg->open(Storage::disk('clips')->path($final_file_path));
                                        $final_clip
                                            ->save(new \FFMpeg\Format\Audio\Wav(), Storage::disk('clips')->path($final_file_path));

                                        if (Storage::disk('clips')->exists($final_file_path)){

                                            $channel->fetch_status = $channel->setMergingOk();
                                            $channel->save();

                                            $channel_data[] =  [
                                                'file_name' => $merged_file_name.".wav",
                                                'timestamp' => $channel->aired_time,
                                            ];
                                        }

                                        else{
                                            Log::channel('clipsmerge')->error('Converting Failed ', [
                                                'path'=>$final_file_path
                                            ]);
                                            $channel->fetch_status = $channel->setMergingFailed();
                                            $channel->save();
                                        }

                                    }
                                    else{
                                        Log::channel('clipsmerge')->error('Merging Failed ', [
                                            'path'=>$merged_file_path
                                        ]);
                                        $channel->fetch_status = $channel->setMergingFailed();
                                        $channel->save();
                                    }
                                }
                                else{
                                    Log::channel('clipsmerge')->error('Second Clip Failed ', [
                                        'path'=>$clip2_path
                                    ]);
                                    $channel->fetch_status = $channel->setSecondClipFailed();
                                    $channel->save();
                                }

                            }
                        }
                        else{
                            Log::channel('clipsmerge')->error('First Clip Failed ', [
                                'path'=>$clip1_path
                            ]);
                            $channel->fetch_status = $channel->setFirstClipFailed();
                            $channel->save();
                        }

                        $channel->refresh();
                        $nextFetch = $channel->getNextFetch();

                        $ftp_path = "FM/logger{$channel->logger}/{$nextFetch['day']}/{$nextFetch['hour']}/{$nextFetch['minute']}.wma";
                    }

                    if (count($channel_data) > 0){
                        $post_data['channels'][$channel->id] = $channel_data;
                    }
                }
            }
        }
        /*
         * End for each channels
         * */

        if (count($post_data['channels']) > 0) {
            $match_url = config('app.match_url');

            $client = new Client();
            $promise = $client->postAsync($match_url, [
                'json'=>$post_data
            ]);

            $promise->then(
                function (ResponseInterface $res) use ($post_data){
                    $response_status = $res->getStatusCode();
                    if ($response_status >= 200 && $response_status < 300)
                    {
                        $channel_ids = array_keys($post_data['channels']);
                        if (count($channel_ids) > 0){
                            Channel::whereIn('id', $channel_ids)
                                ->update(['fetch_status' => 7]);
                        }
                    }
                    else{
                        Log::channel('clipsmerge')->error('Process Request Error ', [
                            'post_data'=>$post_data
                        ]);
                    }

            },
                function (RequestException $exception) use ($post_data){
                    Log::channel('clipsmerge')->error('Process Request Exception ', [
                        'post_data'=>$post_data,
                        'exception'=>$exception
                    ]);
                    $channel_ids = array_keys($post_data['channels']);
                    if (count($channel_ids) > 0){
                        Channel::whereIn('id', $channel_ids)
                            ->update(['fetch_status' => 6]);
                    }
                    /*foreach ($channels as $channel) {
                        $channel->fetch_status = $channel->setMatchRequestFailed();
                        $channel->save();
                    }*/
                }
            );
        }

    }
}
