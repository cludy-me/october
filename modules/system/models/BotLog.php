<?php namespace System\Models;

use App;
use Model;
use Request;
use DeviceDetector\DeviceDetector;

/**
 * Model for visited bots
 *
 * @package october\system
 * @author Alexey Bobkov, Samuel Georges
 */
class BotLog extends Model
{
    /**
     * @var string The database table used by the model.
     */
    protected $table = 'system_bot_logs';

    /**
     * @var array The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * Creates a log record
     * @return self
     */
    public static function add($userAgent = null)
    {
        if (!App::hasDatabase())
            return;

        if (!LogSetting::get('log_bots'))
            return;

        $userAgent = $userAgent ?? Request::userAgent();

        $dd = new DeviceDetector($userAgent);
        $dd->parse();

        if (!$dd->isBot())
            return;

        $bot = $dd->getBot();

        return static::create([
            'url' => substr(Request::fullUrl(), 0, 191),
            'bot' => $bot['name']
        ]);
    }

    public static function isBot($userAgent = null)
    {
        $userAgent = $userAgent ?? Request::userAgent();

        $dd = new DeviceDetector($userAgent);
        $dd->parse();

        return $dd->isBot();
    }
}
