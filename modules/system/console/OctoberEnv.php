<?php namespace System\Console;

use Illuminate\Console\Command;

/**
 * Console command to convert configuration to use .env files.
 *
 * This creates an .env file with some default configuration values, it also converts
 * the existing PHP-based configuration files to use the `env` function for values.
 *
 * @package october\system
 * @author Alexey Bobkov, Samuel Georges
 */
class OctoberEnv extends Command
{

    /**
     * The console command name.
     */
    protected $name = 'october:env';

    /**
     * The console command description.
     */
    protected $description = 'Creates .env file with default configuration values.';

    /**
     * @var array The env keys that need to have their original values removed from the config files
     */
    protected $protectedKeys = [
        'APP_KEY',
        'DB_USERNAME',
        'DB_PASSWORD',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'REDIS_PASSWORD',
    ];

    /**
     * The current config cursor.
     */
    protected $config;

    /**
     * The current database connection cursor.
     */
    protected $connection;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (file_exists('.env')) {
            return $this->error('.env file already exists.');
        }

        $this->overwriteConfig();

        $this->info('.env configuration file has been created.');
    }

    /**
     * Overwrite config file
     */
    private function overwriteConfig()
    {
        foreach (array_keys($this->config()) as $config) {
            $this->config = $config;

            $this->configToEnv();
        }
    }

    /**
     * Replace config values with env() syntax
     */
    private function configToEnv()
    {
        $content = $this->parseConfigFile();

        $this->writeToConfigFile($content);
    }

    /**
     * Parse config file line by line
     *
     * @return string
     */
    private function parseConfigFile()
    {
        $lines = [];

        $this->writeToEnv("# " . $this->config . ".php\n");

        foreach ($this->lines() as $line) {
            $keys = $this->config()[$this->config];

            $lines[] = $this->parseLine($line, $keys);
        }

        $this->writeToEnv("\n");

        return implode('', $lines);
    }

    /**
     * @param $keys
     * @param $line
     * @return mixed
     */
    private function parseLine($line, $keys)
    {
        $line = $this->replaceConfigLine($line, $keys);

        $line = $this->replaceCmsStorageConfigLine($line);

        $line = $this->replaceDbConfigLine($line);

        return $line;
    }

    /**
     * @param $line
     * @param $keys
     * @return mixed
     */
    private function replaceConfigLine($line, $keys)
    {
        foreach ($keys as $envKey => $configKey) {
            $pattern = $this->buildPattern($configKey);

            $callback = $this->buildCallback($envKey, $configKey);

            if (preg_match($pattern, $line)) {
                $line = preg_replace_callback($pattern, $callback, $line);
            }
        }

        return $line;
    }

    /**
     * @param $line
     * @return mixed
     */
    private function replaceDbConfigLine($line)
    {
        if ($this->config == 'database') {
            foreach ($this->dbConfig() as $connection => $settings) {
                $this->setCurrentConnection($line, $connection);

                if ($this->connection == $connection) {
                    $line = $this->replaceConfigLine($line, $settings);
                }
            }
        }

        return $line;
    }

    /**
     * @param $line
     * @return mixed
     */
    private function replaceCmsStorageConfigLine($line)
    {
        if ($this->config == 'cms') {
            foreach ($this->cmsStorageConfig() as $connection => $settings) {
                $this->setCurrentConnection($line, $connection);

                if ($this->connection == $connection) {
                    $line = $this->replaceConfigLine($line, $settings);
                }
            }
        }

        return $line;
    }

    /**
     * @param $line
     * @param $connection
     */
    private function setCurrentConnection($line, $connection)
    {
        if (preg_match("/['\"]" . $connection . "['\"]" . "\s*=>/", $line)) {
            $this->connection = $connection;
        }
    }

    /**
     * @param $configKey
     * @return string
     */
    private function buildPattern($configKey)
    {
        return "/['\"]" . $configKey . "['\"]" . "\s*=>\s*[^,\[]+,/";
    }

    /**
     * @param $envKey
     * @param $configKey
     * @return \Closure
     */
    private function buildCallback($envKey, $configKey)
    {
        return function ($matches) use ($envKey, $configKey) {
            $value = $this->envValue($configKey);

            $this->saveEnvSettings($envKey, $value);

            // Remove protected values from the config files
            if (in_array($envKey, $this->protectedKeys) && !empty($value)) {
                $value = "''";
            }

            return $this->isEnv($matches[0]) ? $matches[0] : "'$configKey' => env('$envKey', {$value}),";
        };
    }

    /**
     * @param $key
     * @param $value
     */
    private function saveEnvSettings($key, $value)
    {
        if (!$this->envKeyExists($key)) {
            $line = sprintf("%s=%s\n", $key, $this->stripQuotes($value));

            if ($this->config == 'database' && $key != 'DB_CONNECTION') {
                $this->writeDbEnvSettings($line);
            } else {
                $this->writeToEnv($line);
            }
        }
    }

    /**
     * @param $line
     */
    private function writeDbEnvSettings($line)
    {
        if ($this->connection == config('database.default') || $this->connection == 'redis') {
            $this->writeToEnv($line);
        }
    }

    /**
     * @param $configKey
     * @return string
     */
    private function envValue($configKey)
    {
        $value = config("$this->config.$configKey");

        if ($this->config == 'cms') {
            $value = $this->cmsConfigValue($configKey);
        }

        if ($this->config == 'database') {
            $value = $this->databaseConfigValue($configKey);
        }

        return $this->normalize($value);
    }

    /**
     * @param $configKey
     * @return string
     */
    private function cmsConfigValue($configKey)
    {
        if (!$this->connection) {
            return config("cms.$configKey");
        }

        return config("cms.storage.$this->connection.$configKey");
    }

    /**
     * @param $configKey
     * @return string
     */
    private function databaseConfigValue($configKey)
    {
        if ($configKey == 'default') {
            return config('database.default');
        }

        if ($configKey == 'useConfigForTesting') {
            return config('database.useConfigForTesting');
        }

        if ($this->connection == 'redis') {
            return config("database.redis.default.$configKey");
        }

        return config("database.connections.$this->connection.$configKey");
    }

    /**
     * @param $value
     * @return string
     */
    private function normalize($value)
    {
        if (is_string($value)) {
            return "'$value'";
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif ($value === null) {
            return 'null';
        }

        return $value;
    }

    /**
     * @param $string
     * @return string
     */
    private function stripQuotes($string)
    {
        return strtr($string, ['"' => '', "'" => '']);
    }

    /**
     * @param $matches
     * @return bool
     */
    private function isEnv($matches)
    {
        return strpos($matches, 'env') !== false;
    }

    /**
     * @param $content
     */
    private function writeToEnv($content)
    {
        file_put_contents('.env', $content, FILE_APPEND);
    }

    /**
     * @return string
     */
    private function readEnvFile()
    {
        return file_exists('.env') ? file_get_contents('.env') : '';
    }

    /**
     * @param $content
     */
    private function writeToConfigFile($content)
    {
        file_put_contents(config_path($this->config . '.php'), $content);
    }

    /**
     * @return array
     */
    private function lines()
    {
        return file(config_path($this->config . '.php'));
    }

    /**
     * @param $key
     * @return bool
     */
    private function envKeyExists($key)
    {
        return strpos($this->readEnvFile(), $key) !== false;
    }

    /**
     * @return array
     */
    private function config()
    {
        return [
            'app' => [
                'APP_DEBUG' => 'debug',
                'APP_URL' => 'url',
                'APP_KEY' => 'key',
                'APP_TIMEZONE' => 'timezone',
                'APP_LOCALE' => 'locale',
                'APP_FALLBACK_LOCALE' => 'fallback_locale',
                'APP_LOG' => 'log',
                'APP_LOG_MAX_FILES' => 'log_max_files',
            ],
            'cache' => [
                'CACHE_DRIVER' => 'default',
                'CACHE_PREFIX' => 'prefix',
            ],
            'cms' => [
                'CMS_ACTIVE_THEME' => 'activeTheme',
                'CMS_BACKEND_URI' => 'backendUri',
                'CMS_BACKEND_FORCE_SECURE' => 'backendForceSecure',
                'CMS_BACKEND_FORCE_REMEMBER' => 'backendForceRemember',
                'CMS_BACKEND_TIMEZONE' => 'backendTimezone',
                'CMS_ROUTES_CACHE' => 'enableRoutesCache',
                'CMS_ASSET_CACHE' => 'enableAssetCache',
                'CMS_ASSET_MINIFY' => 'enableAssetMinify',
                'CMS_PLUGINS_PATH' => 'pluginsPath',
                'CMS_THEMES_PATH' => 'themesPath',
                'CMS_LINK_POLICY' => 'linkPolicy',
                'CMS_ENABLE_CSRF' => 'enableCsrfProtection',
                'CMS_DATABASE_TEMPLATES' => 'databaseTemplates'
            ],
            'database' => [
                'DB_CONNECTION' => 'default',
                'DB_USE_CONFIG_FOR_TESTING' => 'useConfigForTesting',
            ],
            'mail' => [
                'MAIL_DRIVER' => 'driver',
                'MAIL_HOST' => 'host',
                'MAIL_PORT' => 'port',
                'MAIL_USERNAME' => 'username',
                'MAIL_PASSWORD' => 'password',
                'MAIL_ENCRYPTION' => 'encryption',
            ],
            'queue' => [
                'QUEUE_DRIVER' => 'default',
            ],
            'session' => [
                'SESSION_DRIVER' => 'driver',
                'SESSION_COOKIE' => 'cookie',
                'SESSION_COOKIE_PATH' => 'path',
                'SESSION_COOKIE_DOMAIN' => 'domain',
                'SESSION_COOKIE_SECURE' => 'secure',
            ]
        ];
    }

    /**
     * @return array
     */
    private function dbConfig()
    {
        return [
            'sqlite' => [
                'DB_DATABASE' => 'database',
            ],
            'mysql' => [
                'DB_HOST' => 'host',
                'DB_PORT' => 'port',
                'DB_DATABASE' => 'database',
                'DB_USERNAME' => 'username',
                'DB_PASSWORD' => 'password',
            ],
            'pgsql' => [
                'DB_HOST' => 'host',
                'DB_PORT' => 'port',
                'DB_DATABASE' => 'database',
                'DB_USERNAME' => 'username',
                'DB_PASSWORD' => 'password',
            ],
            'redis' => [
                'REDIS_HOST' => 'host',
                'REDIS_PASSWORD' => 'password',
                'REDIS_PORT' => 'port',
            ],
        ];
    }

    private function cmsStorageConfig()
    {
        return [
            'uploads' => [
                'CMS_UPLOADS_PATH' => 'path',
            ],
            'media' => [
                'CMS_MEDIA_PATH' => 'path',
            ]
        ];
    }
}
