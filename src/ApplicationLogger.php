<?php

/** @noinspection PhpMissingReturnTypeInspection */

/** @noinspection PhpMissingFieldTypeInspection */
/** @noinspection PhpMultipleClassDeclarationsInspection */

/** @noinspection PhpMissingParamTypeInspection */

/**
 * An application logger than can dynamically write to log file defined as channel
 * Can prefix INFO:, WARN:, ERR: to logs using the right method
 * Can use variadic or array replacements on every php versions between 5.5 and 8.4
 * eg: ApplicationLogger(APP_ID)->info('app started on %s', date('Y-m-d H:i:s'));.
 *
 * @author Aymeric Anger
 */
class ApplicationLogger
{
    public const DEFAULT_CHANNEL      = 'app';

    protected $channel                = '';

    protected $prefix                 = '';

    protected $logs                   = [];

    protected $rotationDone           = false;

    protected static $instances       = [];
    protected static $logRoot         = '';
    protected static $rotate          = 0;
    protected static $archiveLocation = '';
    protected static $logDays         = false;

    protected static $backTrace       = true;

    protected static $logOutput       = false;

    protected static $defaultChannel;

    public function __construct($channel = self::DEFAULT_CHANNEL)
    {
        $this->channel = $channel;
    }

    /**
     * @return bool
     */
    public static function getLogDays()
    {
        return self::$logDays;
    }

    /**
     * @param bool $logDays
     */
    public static function setLogDays($logDays)
    {
        self::$logDays = (bool) $logDays;
    }

    /**
     * @param string $archiveLocation
     */
    public static function setArchiveLocation($archiveLocation)
    {
        $archiveLocation       = self::normalizePath($archiveLocation) . DIRECTORY_SEPARATOR;
        $umask                 = @umask(0);
        @mkdir($archiveLocation, 0777, true);
        @umask($umask);
        self::$archiveLocation = $archiveLocation;
    }

    /**
     * @return string
     */
    public static function getArchiveLocation()
    {
        if ( ! self::$archiveLocation)
        {
            $pth = self::getConstant(
                'LOG_PATH_ARCHIVE',
                self::getLogRoot() . 'archives' . DIRECTORY_SEPARATOR
            );
            self::setArchiveLocation($pth);
        }

        return self::$archiveLocation;
    }

    /**
     * @return int
     */
    public static function getRotate()
    {
        return self::$rotate;
    }

    /**
     * @param int $rotate
     */
    public static function setRotate($rotate)
    {
        self::$rotate = max($rotate, 0);
    }

    /**
     * @return string
     */
    public static function getLogRoot()
    {
        if ( ! self::$logRoot)
        {
            $pth = self::getConstant('LOG_PATH', getcwd() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
            self::setLogRoot($pth);
        }
        return self::$logRoot;
    }

    /**
     * @param string $dir
     */
    public static function setLogRoot($dir)
    {
        $dir           = self::normalizePath($dir) . DIRECTORY_SEPARATOR;
        $umask         = @umask(0);
        @mkdir($dir, 0777, true);
        @umask($umask);
        self::$logRoot = $dir;
    }

    /**
     * @param null|string $channel
     *
     * @return static
     */
    public static function getLogger($channel = null)
    {
        if (empty($channel))
        {
            $channel = self::getDefaultChannel();
        }

        if ( ! isset(self::$instances[$channel]))
        {
            self::$instances[$channel] = new ApplicationLogger($channel);
        }
        return self::$instances[$channel];
    }

    /**
     * @param ?string $defaultChannel
     */
    public static function setDefaultChannel($defaultChannel)
    {
        self::$defaultChannel = $defaultChannel;
    }

    /**
     * @return bool
     */
    public static function hasBackTrace()
    {
        return self::$backTrace;
    }

    /**
     * @param bool $backTrace
     */
    public static function setBackTrace($backTrace)
    {
        self::$backTrace = $backTrace;
    }

    /**
     * @return bool
     */
    public static function canLogOutput()
    {
        return self::$logOutput;
    }

    /**
     * @param bool $logOutput
     */
    public static function setLogOutput($logOutput)
    {
        self::$logOutput = $logOutput;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return rtrim($this->prefix);
    }

    /**
     * @param string $prefix
     *
     * @return static
     */
    public function setPrefix($prefix)
    {
        if (empty($prefix))
        {
            $this->prefix = '';
            return $this;
        }

        if (rtrim($prefix, ' ') !== $prefix)
        {
            $prefix = rtrim("{$prefix}") . ' ';
        }

        $this->prefix = $prefix;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param string $channel
     *
     * @return static
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function log($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }

        if (count($replacements) > 0 && ! empty($message))
        {
            $newMessage = @vsprintf($message, $replacements);

            if (empty($newMessage))
            {
                $newMessage = 'ERR: invalid argument count (' . count($replacements) . "): `{$message}`";
            }
            $message    = $newMessage;
        }

        $file         = self::getFilename();
        $dir          = dirname($file);
        $umask        = @umask(0);

        if ( ! is_dir($dir))
        {
            @mkdir($dir, 0777, true);
        }

        $metadata     = date('Y/m/d H:i:s');

        if (self::hasBackTrace())
        {
            foreach (@debug_backtrace() as $trace)
            {
                if (__FILE__ !== $trace['file'])
                {
                    $metadata .= sprintf(' %s:%d', basename($trace['file']), $trace['line']);
                    break;
                }
            }
        }

        $this->logs[] = $msg = sprintf("%s %s%s\n", $metadata, $this->prefix, $message);

        @file_put_contents(
            $file,
            $msg,
            FILE_APPEND
        );

        @chmod($file, 0777);

        @umask($umask);

        if (self::canLogOutput())
        {
            echo $msg;
        }

        return $this->rotateLogs();
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function info($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }
        return $this->log("INFO: {$message}", $replacements);
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function error($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }
        return $this->log("ERR: {$message}", $replacements);
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function warn($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }

        return $this->log("WARN: {$message}", $replacements);
    }

    /**
     * Normalize pathname.
     *
     * @param string $path
     *
     * @return string
     */
    protected static function normalizePath($path)
    {
        if (empty($path))
        {
            return $path;
        }

        return rtrim(
            preg_replace('#[\\\/]+#', DIRECTORY_SEPARATOR, $path),
            DIRECTORY_SEPARATOR
        );
    }

    /**
     * @return string
     */
    protected static function getDefaultChannel()
    {
        $channel = self::getConstant('APP_ID', self::DEFAULT_CHANNEL);

        if (is_string(self::$defaultChannel))
        {
            $channel = self::$defaultChannel;
        }

        if (self::getConstant('DEV_ENV', false))
        {
            $channel .= '-dev';
        }

        return $channel;
    }

    /**
     * @param Closure|mixed $value
     * @param array         $arguments
     *
     * @return mixed
     */
    protected static function getValue($value, $arguments = [])
    {
        if ($value instanceof Closure)
        {
            if ( ! is_array($arguments))
            {
                $args      = func_get_args();
                array_splice($args, 0, 1);
                $arguments = $args;
            }

            $value = call_user_func_array($value, $arguments);
        }
        return $value;
    }

    /**
     * @param string        $name
     * @param Closure|mixed $defaultValue
     *
     * @return mixed
     */
    protected static function getConstant($name, $defaultValue = null)
    {
        if ( ! defined($name))
        {
            return self::getValue($defaultValue, [$name]);
        }

        return constant($name);
    }

    /**
     * @param null|string $channel
     *
     * @return string
     */
    protected function getFilename($channel = null)
    {
        static $filenames = [];

        if ( ! isset($channel))
        {
            $channel = $this->getChannel();

            if (empty($channel))
            {
                $channel = self::getDefaultChannel();
            }
        }

        if ( ! isset($filenames[$channel]))
        {
            $filenames[$channel] = sprintf(
                '%s%s-%s.log',
                self::getLogRoot(),
                self::getLogDays() ? date('ymd') : date('ym'),
                $channel
            );
        }

        return $filenames[$channel];
    }

    /**
     * @return static
     */
    protected function rotateLogs()
    {
        if ($this->rotationDone)
        {
            return $this;
        }
        $this->rotationDone = true;
        $keep               = self::getRotate();

        if ( ! $keep)
        {
            return $this;
        }
        $orig               = self::getLogRoot();
        $dest               = self::getArchiveLocation();
        $chan               = $this->channel;
        $list               = [];

        foreach (glob($orig . '[0-9][0-9]*.log') as $file)
        {
            if ( ! is_file($file))
            {
                continue;
            }

            if (preg_match('#^\d+(.+)\.log#', basename($file), $matches))
            {
                @list(, $name)          = $matches;
                $name                   = trim($name, '-_');

                if ($name !== $chan)
                {
                    continue;
                }

                $list[filemtime($file)] = $file;
            }
        }

        ksort($list);

        while (count($list) > $keep)
        {
            $file     = array_shift($list);
            $basename = basename($file);
            @rename($file, $dest . $basename);
        }

        return $this;
    }
}
