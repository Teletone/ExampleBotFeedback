<?php

require 'vendor/autoload.php';

use Teletone\Types\ReplyKeyboardMarkup;

$dotenv = Dotenv\Dotenv::createImmutable('./');
$dotenv->load();

define('BOT_TOKEN', $_SERVER['BOT_TOKEN']);
define('BOT_ADMIN_ID', $_SERVER['BOT_ADMIN_ID']);
define('DB_FILE', $_SERVER['DB_FILE']);

if (!is_file(DB_FILE))
    file_put_contents(DB_FILE, '{}');

function write_in_db($data)
{
    $fd = json_decode(file_get_contents(DB_FILE), true);
    if (!isset($fd['feedbacks']))
        $fd['feedbacks'] = [];
    $fd['feedbacks'][] = $data;
    file_put_contents(DB_FILE, json_encode($fd));
}

function load_from_db()
{
    return json_decode(file_get_contents(DB_FILE), true);
}

$bot = new \Teletone\Bot(BOT_TOKEN, [
    'parse_mode' => 'html',
    'debug' => true
]);

$r = $bot->getRouter();

$r->command('start', 'start');
function start($update)
{
    $params = [];
    if ($update->message->from->id == BOT_ADMIN_ID)
        $params['reply_markup'] = new ReplyKeyboardMarkup([
            [ 'Feedbacks' ]
        ], [ 'resize_keyboard' => true ]);
    $update->answer("Welcome, <b>{$update->message->from->first_name}</b>!\n\nWrite us your message and we will answer you as soon as possible", $params);
}

$r->message('Feedbacks', 'feedbacks');
function feedbacks($update)
{
    if ($update->message->from->id == BOT_ADMIN_ID)
    {
        $db = load_from_db();
        $text = '';
        if (is_null($db['feedbacks']))
            $text = 'Empty';
        foreach ($db['feedbacks'] as $feedback)
        {
            $text .= "User: <a href='tg://user?id=$feedback[user_id]'>$feedback[first_name]</a>\nDate: $feedback[date]\nMessage: $feedback[message]\n\n";
        }
        $update->answer($text);
    }
}

$r->message(NULL, 'all');
function all($update)
{
    write_in_db([
        'user_id' => $update->message->from->id,
        'date' => date('Y-m-d H:i:s'),
        'first_name' => $update->message->from->first_name,
        'message' => $update->message->text
    ]);
    $update->answer('Message sent!');
}

$bot->polling();