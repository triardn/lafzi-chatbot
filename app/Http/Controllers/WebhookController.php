<?php

namespace App\Http\Controllers;

class WebhookController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    private $bot;
    private $events;
    private $signature;
    private $user;

    public function __construct()
    {
        // create bot object
        $httpClient = new CurlHTTPClient(env('CHANNEL_ACCESS_TOKEN'));
        $this->bot = new LINEBot($httpClient, ['channelSecret' => env('CHANNEL_SECRET')]);
    }

    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo 'Hello Coders!';
            header('HTTP/1.1 400 Only POST method allowed');
            exit;
        }

        // get request
        $body = file_get_contents('php://input');
        $this->signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : '-';
        $this->events = json_decode($body, true);

        $this->saveEventLog($this->signature, $body);

        if (is_array($this->events['events'])) {
            foreach ($this->events['events'] as $event) {
                // skip group and room event
                if (!isset($event['source']['userId'])) {
                    continue;
                }

                // get user data from database
                $this->user = User::where('user_id', $event['source']['userId'])->first();

                // if user not registered
                if (empty($this->user)) {
                    $this->followCallback($event);
                } else {
                    // respond event
                    if ($event['type'] == 'message') {
                        // $this->play($event);
                    } else {
                        $this->followCallback($event);
                    }
                }
            } // end of foreach
        }
    }

    private function saveEventLog($signature, $body)
    {
        $eventLog = new EventLog;
        $eventLog->signature = $signature;
        $eventLog->events = $body;
        $eventLog->save();
    }

    private function saveUserData($profile)
    {
        $user = new User;
        $user->user_id = $profile['userId'];
        $user->display_name = $profile['displayName'];
        $user->picture_url = $profile['pictureUrl'];
        $user->save();
    }

    private function followCallback($event)
    {
        $res = $this->bot->getProfile($event['source']['userId']);
        if ($res->isSucceeded()) {
            $profile = $res->getJSONDecodedBody();

            // create welcome message
            $welcomingMessage = 'Assalamu`alaikum Warahmatullahi Wabarakatuh. Kak ' . $profile['displayName'] . ', Terima kasih sudah menambahkan Lafzi sebagai teman kakak yaa' . "\n";

            $welcomingMessage1 = 'Aku bisa bantu kakak cari tahu ayat di Al-qur`an yang kakak dengar, tapi kakak gatau itu ada di surat apa dan ayat berapa.' . "\n";

            $welcomingMessage2 = 'Kakak bisa langsung ketikan aja potongan ayat yang kakak mau cari, nanti aku akan kasih tau ada dimana aja ayat tersebut.' . "\n";

            $textMessageBuilder = new TextMessageBuilder($welcomingMessage);
            $textMessageBuilder1 = new TextMessageBuilder($welcomingMessage1);
            $textMessageBuilder2 = new TextMessageBuilder($welcomingMessage2);

            // create sticker message
            $stickerMessageBuilder = new StickerMessageBuilder(2, 161);

            // merge all message
            $multiMessageBuilder = new MultiMessageBuilder();
            $multiMessageBuilder->add($textMessageBuilder);
            $multiMessageBuilder->add($textMessageBuilder1);
            $multiMessageBuilder->add($textMessageBuilder2);
            $multiMessageBuilder->add($stickerMessageBuilder);

            // send reply message
            $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);

            // save user data
            $this->saveUserData($profile);
        }
    }
}
