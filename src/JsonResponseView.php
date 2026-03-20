<?php

/** @noinspection PhpHierarchyChecksInspection */

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonResponseView extends ResponseView implements JsonSerializable
{
    private $success    = true;
    private $message    = '';

    /** @var ?string */
    private $error      = null;

    /** @var mixed */
    private $data       = null;

    private $attributes = [];

    public function __construct()
    {
        parent::__construct();
        $this->setContentType('application/json; charset=utf-8');

        if (env_get('CORS_ENABLE'))
        {
            $methods = env_get('CORS_METHODS', '');

            if ( ! is_array($methods))
            {
                $methods = array_filter(
                    array_map(
                        function ($method)
                        {
                            return trim(strtoupper($method));
                        },
                        preg_split('#,\h*#', $methods)
                    )
                );
            }

            if ( ! empty($methods))
            {
                if ( ! in_array('OPTIONS', $methods))
                {
                    $methods[] = 'OPTIONS';
                }

                $this->addHeader('Access-Control-Allow-Origin', '*');
                $this->addHeader('Access-Control-Allow-Headers', '*');
                $this->addHeader(
                    'Access-Control-Allow-Methods',
                    implode(', ', array_values(array_unique($methods)))
                );
            }
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getContent();
    }

    /**
     * @return static
     */
    public static function newResponse()
    {
        return new static();
    }

    /**
     * @param array|JsonSerializable $data
     * @param int                    $status
     *
     * @return static
     */
    public static function from(array|JsonSerializable $data, int $status = 200)
    {
        return static::newResponse()
            ->setAttributes($data instanceof JsonSerializable ? $data->jsonSerialize() : $data)->setStatusCode($status);
    }

    /**
     * @param null|string $message
     *
     * @return static
     */
    public static function newNotFound($message = null)
    {
        if ( ! isset($message))
        {
            $message = CurlHandler::getReasonPhrase(404);
        }

        return (new static())->setStatusCode(404)->setError(
            $message
        );
    }

    /**
     * @param null|string $message
     *
     * @return static
     */
    public static function newBadRequest($message = null)
    {
        if ( ! isset($message))
        {
            $message = CurlHandler::getReasonPhrase(400);
        }
        return (new static())->setStatusCode(400)->setError(
            $message
        );
    }

    /**
     * @param null|string $message
     *
     * @return static
     */
    public static function newBadMethod($message = null)
    {
        if ( ! isset($message))
        {
            $message = CurlHandler::getReasonPhrase(405);
        }
        return (new static())->setStatusCode(405)->setError(
            $message
        );
    }

    /**
     * @param null|string $message
     *
     * @return static
     */
    public static function newForbidden($message = null)
    {
        if ( ! isset($message))
        {
            $message = CurlHandler::getReasonPhrase(403);
        }
        return (new static())->setStatusCode(403)->setError(
            $message
        );
    }

    /**
     * @param null|string $message
     *
     * @return static
     */
    public static function newInternalError($message = null)
    {
        if ( ! isset($message))
        {
            $message = CurlHandler::getReasonPhrase(500);
        }
        return (new static())->setStatusCode(500)->setError(
            $message
        );
    }

    /**
     * @param null|string $message
     *
     * @return static
     */
    public static function newUnauthorized($message = null)
    {
        if ( ! isset($message))
        {
            $message = CurlHandler::getReasonPhrase(401);
        }
        return (new static())->setStatusCode(401)->setError(
            $message
        );
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     *
     * @return static
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param array $data
     *
     * @return static
     */
    public function addData(array $data)
    {
        if ( ! empty($data))
        {
            $newData    = $this->data;

            if ( ! is_array($newData))
            {
                $newData = [];
            }

            foreach ($data as $prop => $value)
            {
                $newData[$prop] = $value;
            }
            $this->data = $newData;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @param bool $success
     *
     * @return static
     */
    public function setSuccess($success)
    {
        $this->success = $success;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param string $message
     * @param mixed  ...$replacements
     *
     * @return static
     */
    public function setError($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $replacements = array_slice(func_get_args(), 1);
        }

        if ( ! empty($replacements))
        {
            $message = vsprintf($message, $replacements);
        }
        $this->error = $message;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     *
     * @return static
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $message = empty($this->error) ? $this->message : $this->error;
        $resp    = ['success' => $this->success && empty($this->error)];

        if ('' !== $message)
        {
            $resp['message'] = $message;
        }

        if (empty($this->error))
        {
            if (isset($this->data))
            {
                $resp['result'] = $this->data;
            }

            $resp = array_replace($resp, $this->attributes);
        }

        return $resp;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function removeAttribute($name)
    {
        unset($this->attributes[$name]);
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return static
     */
    public function addAttribute($name, $value)
    {
        if ( ! $this->hasAttribute($name))
        {
            $this->setAttribute($name, $value);
        }

        return $this;
    }

    /**
     * @param array<string,mixed> $attributes
     *
     * @return static
     */
    public function addAttributes($attributes)
    {
        foreach ($attributes as $name => $value)
        {
            $this->addAttribute($name, $value);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return static
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * @param array<string,mixed> $attributes
     *
     * @return static
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        if ( ! $this->hasAttribute($name))
        {
            return value($default);
        }
        return $this->attributes[$name];
    }

    /**
     * @return JsonResponse
     */
    public function toResponse()
    {
        return new JsonResponse(
            $this->getContent(),
            $this->getStatusCode(),
            $this->getAllHeaders(),
            true
        );
    }
}
