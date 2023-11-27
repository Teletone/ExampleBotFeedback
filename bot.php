<?php

require 'vendor/autoload.php';

use Teletone\Types\InlineKeyboardMarkup;
use Teletone\Types\ReplyKeyboardMarkup;

$dotenv = Dotenv\Dotenv::createImmutable('./');
$dotenv->load();

define('BOT_TOKEN', $_SERVER['BOT_TOKEN']);
define('BOT_ADMIN_ID', $_SERVER['BOT_ADMIN_ID']);
define('DB_FILE', $_SERVER['DB_FILE']);

if (!is_file(DB_FILE))
    file_put_contents(DB_FILE,  json_encode([ 'feedbacks' => [] ]));

function write_msg_in_db($user_id, $data)
{
    $fd = json_decode(file_get_contents(DB_FILE), true);
    if (!isset($fd['feedbacks'][$user_id]))
        $fd['feedbacks'][$user_id] = [];
    $fd['feedbacks'][$user_id][] = $data;
    file_put_contents(DB_FILE, json_encode($fd));
}

function load_from_db()
{
    return json_decode(file_get_contents(DB_FILE), true);
}

function write_in_db($db)
{
    file_put_contents(DB_FILE, json_encode($db));
}

$bot = new \Teletone\Bot(BOT_TOKEN, [
    'parse_mode' => 'html',
    'debug' => true
]);

$r = $bot->getRouter();

$r->command('start', 'start');
function start($u)
{
    $params = [];
    if ($u->from->id == BOT_ADMIN_ID)
        $params['reply_markup'] = new ReplyKeyboardMarkup([
            [ 'Feedbacks' ]
        ], [ 'resize_keyboard' => true ]);
    $u->answer("Welcome, <b>{$u->from->first_name}</b>!\n\nWrite us your message and we will answer you as soon as possible", $params);
}

$r->message('Cancel', 'cancel');
function cancel($u)
{
    $db = load_from_db();
    unset($db['dialog']);
    write_in_db($db);
    start($u);
}

$r->message('Feedbacks', 'feedbacks');
function feedbacks($u)
{
    if ($u->from->id != BOT_ADMIN_ID)
        return;
    $keyboard = [];
    $db = load_from_db();
    $text = '';
    if (empty($db['feedbacks']))
        $text = 'Empty';
    else
    {
        $text = 'List of dialogs';
        foreach ($db['feedbacks'] as $feedback)
        {
            $keyboard[] = [ [ 'text' => "{$feedback[0]['user_id']} : {$feedback[0]['first_name']}", 'callback_data' => "messages_{$feedback[0]['user_id']}" ] ];
        }
    }
    $u->answer($text, [
        'reply_markup' => new InlineKeyboardMarkup($keyboard)
    ]);
}

$r->callbackQuery('/^messages_[0-9]+$/', 'messages', true);
function messages($u)
{
    $user_id = explode('_', $u->data)[1];
    $db = load_from_db();
    $list = $db['feedbacks'][$user_id];
    if (count($list) > 10)
        $list = array_slice($list, count($list)-10);
    $text = "Messages:\n\n";
    foreach ($list as $item)
    {
        $text .= "<a href='tg://user?id={$item['user_id']}'>{$item['first_name']}</a> | {$item['date']}\n{$item['message']}\n\n";
    }
    $u->edit($text, [
        'reply_markup' => new InlineKeyboardMarkup([
            [ [ 'text' => 'Reply', 'callback_data' => 'reply_'.$user_id ] ]
        ])
    ]);
}

$r->callbackQuery('/^reply_[0-9]+$/', 'reply', true);
function reply($u)
{
    $user_id = explode('_', $u->data)[1];
    $db = load_from_db();
    $db['dialog'] = $user_id;
    write_in_db($db);
    $u->delete();
    $u->answer('Enter your reply:', [
        'reply_markup' => new ReplyKeyboardMarkup([
            [ 'Cancel' ]
        ], [ 'resize_keyboard' => true ])
    ]);
}

$r->message(NULL, 'all');
function all($u)
{
    global $bot;
    $db = load_from_db();
    if ($u->from->id == BOT_ADMIN_ID && isset($db['dialog']))
    {
        try {
            $bot->sendMessage([
                'chat_id' => $db['dialog'],
                'text' => $u->text
            ]);
        }
        catch(Exception $e) {
            return $u->answer('The user blocked the bot');
        }
        write_msg_in_db($db['dialog'], [
            'user_id' => $u->from->id,
            'date' => date('Y-m-d H:i:s'),
            'first_name' => $u->from->first_name,
            'username' => (!empty($u->from->username)) ? $u->from->username : NULL,
            'message' => $u->text
        ]);
        $u->answer('Reply sent', [
            'reply_markup' => new InlineKeyboardMarkup([
                [ [ 'text' => 'Back to messages', 'callback_data' => 'messages_'.$db['dialog'] ] ]
            ])
        ]);
        $db = load_from_db();
        unset($db['dialog']);
        write_in_db($db);
    }
    else
    {
        write_msg_in_db($u->from->id, [
            'user_id' => $u->from->id,
            'date' => date('Y-m-d H:i:s'),
            'first_name' => $u->from->first_name,
            'username' => (!empty($u->from->username)) ? $u->from->username : NULL,
            'message' => $u->text
        ]);
        $u->answer('Message sent');
    }
}

if ($bot->run_type == 'polling')
    $bot->polling();
else
    $bot->handleWebhook();