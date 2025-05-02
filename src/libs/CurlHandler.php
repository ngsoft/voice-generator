<?php
/** @noinspection ALL */
namespace {

if (!interface_exists("Lockable", false)) {
    interface Lockable
    {
        /**
         * Lock the object.
         * @return void
         */
        public function lock();

        /**
         * Unlock the object.
         * @return void
         */
        public function unlock();


        /**
         * Get the lock status.
         * @return bool
         */
        public function isLocked();
    }
}

}
namespace {

class CurlHandler
{

    /**
     * Experimental technology to fetch long list of urls faster
     * Only supports GET method with no header parsing
     *
     * @param string[]|Stringable[] $urls
     * @return HttpClient\CurlResponse[] returns responses in order of urls
     */
    public static function makeMultiGetRequests(array $urls)
    {
        $multi = new HttpClient\CurlMultiRequest();
        $cookies = tempnam(sys_get_temp_dir(), 'curl_multi');
        foreach ($urls as $url) {

            $req = (new HttpClient\CurlRequest());
            $multi->add(
                $req
                    // prevent multi handler to follow using synchronous request
                    ->setOpt(CURLOPT_FOLLOWLOCATION, true)
                    // as headers cannot be defined in that function
                    // make believe we are in the last firefox version
                    ->setUserAgent(self::generateUserAgent())
                    // cookie support if needed
                    ->setCookieFile($cookies)
                    ->prepare(self::METHOD_GET, $url)
            );
        }

        // make request
        return $multi->execute()->getResults();
    }


    /**
     * @param string|Stringable $url
     * @param null|string|array<string, string> $params
     * @param string|Stringable $method
     * @param ?array<string, string|string[]> $headers
     * @param int $timeout
     *
     * @return HttpClient\CurlResponse
     */
    public static function makeHttpRequest($url, $params = null, $method = 'GET', $headers = null, $timeout = 0)
    {


        $req = new HttpClient\CurlRequest();
        $req->enableHeaderParsing();

        if (is_int($headers)) {
            $timeout = $headers;
            $headers = null;
        }

        if (is_array($method)) {
            $headers = $method;
            $method = "GET";
        }

        if (is_array($headers)) {

            $usable = [];

            foreach ($headers as $name => $val) {
                if (strtolower($name) === "cookie-file") {
                    $req->setCookieFile($val);
                    continue;
                }
                $usable[$name] = $val;
            }
            $req->setHeaders($usable);
        }


        if ($timeout > 0) {
            $req->setTimeout($timeout);
        }

        try {
            return $req->fetch($method, $url, $params);
        } finally {
            $req->closeHandle();
        }


    }

    /**
     * @param string $method
     * @param bool $normalize
     * @return bool
     */
    public static function isValidMethod($method, $normalize = true)
    {
        if ($normalize) {
            $method = strtoupper($method);
        }
        return in_array($method, self::$VALID_METHODS);
    }

    /**
     * @param string|int|null|bool $version true => random, null|false => latest, int => "$version.0"
     * @return string
     */
    public static function generateUserAgent($version = null)
    {

        /**
         * @link https://wiki.mozilla.org/Release_Management/Product_details
         */
        static $ffListApi = "https://product-details.mozilla.org/1.0/firefox_history_major_releases.json",
        $ffLastApi = "https://product-details.mozilla.org/1.0/firefox_versions.json",
        $template = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:{version}) Gecko/20100101 Firefox/{version}";


        if (!isset(self::$firefoxVersions)) {

            $cachedFile = sys_get_temp_dir() . "/curl_firefox_versions.json";
            $cachedData = false;
            @mkdir(dirname($cachedFile), 0777, true);

            if (@filemtime($cachedFile) > time() - 3600) {

                $cachedData = @file_get_contents($cachedFile);

                if (is_string($cachedData)) {
                    $cachedData = json_decode($cachedData, true);
                }
            }

            if (!$cachedData) {
                $versions = [];

                if ($list = self::makeSimpleGetHttpRequest($ffListApi)) {
                    foreach (array_reverse($list) as $ver => $date) {

                        if (strtotime($date) < strtotime("-3 years")) {
                            continue;
                        }
                        if (!preg_match("#^\d+\.\d+$#", $ver)) {
                            continue;
                        }
                        if (strtotime($date) < time()) {
                            $versions[] = $ver;
                        }
                    }
                }

                $latest = self::$latestFirefoxVersion;

                if (!empty($versions)) {
                    $latest = $versions[0];
                }

                $data = self::makeSimpleGetHttpRequest($ffLastApi);
                if ($data) {
                    $latest = $data['LATEST_FIREFOX_VERSION'];
                }

                if (!empty($versions)) {
                    $cachedData = [$versions, $latest];
                    @file_put_contents($cachedFile, json_encode($cachedData));

                }
            }


            if ($cachedData) {
                list(self::$firefoxVersions, self::$latestFirefoxVersion) = $cachedData;
            }
        }


        if (!empty($version)) {

            if (is_int($version)) {
                $version = "$version.0";
            } elseif (true === $version) {
                $version = self::$firefoxVersions[array_rand(self::$firefoxVersions)];
            }


        } else {
            $version = self::$latestFirefoxVersion;
        }

        $version = preg_replace('#^(\d+\.\d+)\D*.*$#', '$1', $version);

        return str_replace("{version}", $version, $template);


    }

    public static function makeSimpleGetHttpRequest($url)
    {
        $json = @file_get_contents(
            $url,
            false,
            stream_context_create([
                'http' => ['method' => 'GET'],
                'ssl' => [
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ])
        ) ?: "";
        if (null === $decoded = @json_decode($json, true)) {
            return $json;
        }
        return $decoded;
    }

    public static function getReasonPhrase($statusCode)
    {
        return isset(self::$REASON_PHRASES[$statusCode]) ? self::$REASON_PHRASES[$statusCode] : self::$REASON_PHRASES[0];
    }

    protected static $firefoxVersions = null;
    protected static $latestFirefoxVersion = "132.0";
    /**
     * @link https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     */
    protected static $REASON_PHRASES = [
        "Unassigned",
        100 => "Continue",
        101 => "Switching Protocols",
        102 => "Processing",
        103 => "Early Hints",
        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        203 => "Non-Authoritative Information",
        204 => "No Content",
        205 => "Reset Content",
        206 => "Partial Content",
        207 => "Multi-Status",
        208 => "Already Reported",
        226 => "IM Used",
        300 => "Multiple Choices",
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        304 => "Not Modified",
        305 => "Use Proxy",
        307 => "Temporary Redirect",
        308 => "Permanent Redirect",
        400 => "Bad Request",
        401 => "Unauthorized",
        402 => "Payment Required",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        406 => "Not Acceptable",
        407 => "Proxy Authentication Required",
        408 => "Request Timeout",
        409 => "Conflict",
        410 => "Gone",
        411 => "Length Required",
        412 => "Precondition Failed",
        413 => "Payload Too Large",
        414 => "URI Too Long",
        415 => "Unsupported Media Type",
        416 => "Range Not Satisfiable",
        417 => "Expectation Failed",
        421 => "Misdirected Request",
        422 => "Unprocessable Entity",
        423 => "Locked",
        424 => "Failed Dependency",
        425 => "Too Early",
        426 => "Upgrade Required",
        428 => "Precondition Required",
        429 => "Too Many Requests",
        431 => "Request Header Fields Too Large",
        451 => "Unavailable For Legal Reasons",
        500 => "Internal Server Error",
        501 => "Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
        505 => "HTTP Version Not Supported",
        506 => "Variant Also Negotiates",
        507 => "Insufficient Storage",
        508 => "Loop Detected",
        510 => "Not Extended",
        511 => "Network Authentication Required",
    ];


    const METHOD_GET = "GET";
    const METHOD_HEAD = "HEAD";
    const METHOD_POST = "POST";
    const METHOD_PUT = "PUT";
    const METHOD_DELETE = "DELETE";
    const METHOD_CONNECT = "CONNECT";
    const METHOD_OPTIONS = "OPTIONS";
    const METHOD_TRACE = "TRACE";
    const METHOD_PATCH = "PATCH";


    /**
     * Valid Methods
     */
    protected static $VALID_METHODS = [
        self::METHOD_GET,
        self::METHOD_HEAD,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_DELETE,
        self::METHOD_CONNECT,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_PATCH,
    ];

}

}
namespace HttpClient{



use Lockable;


class CurlMultiRequest implements Lockable, \IteratorAggregate, \Countable
{

    /** @var \CurlMultiHandle|resource */
    protected $handle;
    protected $closed = true;
    protected $ready = false;
    protected $locked = false;
    /** @var array<string,CurlRequest> */
    protected $curlHandles = [];
    /** @var ?array<string,CurlResponse> */
    protected $results = null;
    /** @var ?array<string,CurlRequest> */
    protected $resultRequests = null;

    /**
     * @return static
     */
    public function execute()
    {
        if ($this->isLocked() || !$this->ready) {
            throw new \RuntimeException('CurlMultiRequest is locked or requests are not ready yet.');
        }

        $this->ready = false;
        $this->lock();


        $this->results = $this->resultRequests = [];
        $results = [];
        $handles = [];
        $mh = $this->getHandle();

        $n = 0;

        foreach ($this->curlHandles as $curlHandle) {
            if ($curlHandle->isReady()) {
                $n++;
                @curl_multi_add_handle(
                    $mh,
                    $handles[$curlHandle->getUid()] = $curlHandle->getHandle()
                );
            }
        }

        if (!$n) {
            throw new \RuntimeException('no requests are ready.');
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);


        while ($active && $mrc == CURLM_OK) {
            curl_multi_select($mh);
            usleep(90);
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($info = curl_multi_info_read($mh)) {

                $ch = $info["handle"];
                curl_multi_remove_handle($mh, $ch);
                $uid = array_search($ch, $handles, true);
                $req = $this->curlHandles[$uid];

                $result = $req->getResult();
                // redirection
                if ($req->isReady()) {
                    $result = $req->execute();
                }
                $results[$uid] = $result;
            }
        }

        $this->unlock();
        // sort results by request order
        foreach (array_keys($this->curlHandles) as $uid) {
            if (isset($results[$uid])) {
                $this->results[$uid] = $results[$uid];
                $this->resultRequests[$uid] = $this->curlHandles[$uid];
                $this->remove($uid);
            }
        }


        return $this->closeHandle();

    }

    /**
     * @return CurlResponse[]
     */
    public function getResults()
    {

        if (empty($this->results)) {
            return [];
        }


        return $this->results;
    }


    /**
     * @param CurlRequest|string $request
     * @return static
     */
    public function remove($request)
    {

        if (!$this->isLocked()) {
            if ($request instanceof CurlRequest) {
                $request = $request->getUid();
            }

            if (is_string($request)) {
                unset($this->curlHandles[$request]);
            }

            $this->ready = array_any($this->curlHandles, function ($request) {
                return $request->isReady();
            });
        }


        return $this;
    }


    public function add(CurlRequest $curlRequest)
    {
        if (!$this->isLocked()) {
            $this->curlHandles[$curlRequest->getUid()] = $curlRequest;
            if ($curlRequest->isReady()) {
                $this->ready = true;
            }
        }

        return $this;
    }

    /**
     * @param CurlRequest[] $requests
     * @return $this
     */
    public function addMany(array $requests)
    {
        foreach ($requests as $request) {
            if (!($request instanceof CurlRequest)) {
                throw new \InvalidArgumentException('$requests must be of type CurlRequest[]');
            }
            $this->add($request);

        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isReady()
    {
        return $this->ready;
    }


    public function __destruct()
    {
        if (!$this->closed) {
            @curl_multi_close($this->handle);
        }
    }

    /**
     * @return \CurlMultiHandle|resource
     */
    public function getHandle()
    {
        if ($this->closed) {
            $this->handle = @curl_multi_init();
            $this->closed = false;
        }

        return $this->handle;
    }


    /**
     * @return static
     */
    public function closeHandle()
    {
        if (!$this->closed) {
            @curl_multi_close($this->handle);
            $this->closed = true;
            $this->ready = false;
            $this->handle = null;
        }

        return $this;
    }


    public function lock()
    {
        $this->locked = true;
    }

    public function unlock()
    {
        $this->locked = false;
    }

    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * @return \Traversable<CurlRequest,CurlResponse>
     */
    public function getIterator()
    {
        if (is_array($this->results)) {
            foreach ($this->results as $uid => $response) {
                yield $this->resultRequests[$uid] => $response;
            }
        }

    }

    public function count()
    {
        return is_array($this->results) ? count($this->results) : 0;
    }
}

}
namespace HttpClient{



use CurlHandler;

/**
 * @property-read ?resource $file
 * @property-read string $uid
 * @property-read int $requestCount
 */
class CurlRequest
{

    /** @var \CurlHandle|resource */
    protected $handle;
    protected $closed = true;
    protected $ready = false;
    /** @var string */
    protected $uid;

    protected $options = [];

    /** @var ?resource */
    protected $file = null;
    protected $initialCount = 0;

    protected $requestHeaders = [];
    protected $requestCount = 0;


    protected $parseHeaders = false;
    protected $rawHeaders = "";
    protected $responseHeaders = [];

    /** @var null|CurlResponse */
    protected $previous = null;

    /**
     * @param string|\Stringable $method
     * @param string|\Stringable $url
     * @param string|array|null $params
     * @return CurlResponse
     */

    public function fetch($method, $url, $params = null)
    {
        return $this
            ->prepare($method, $url, $params)
            ->execute();

    }


    /**
     * Make a GET request
     * @param string $url
     * @param null|string|array $params
     * @param ?array $headers
     * @return CurlResponse
     */
    public function get($url, $params = null, $headers = null)
    {
        if (is_array($headers)) {
            $this->setHeaders($headers);
        }
        return $this->fetch(self::GET, $url, $params);
    }


    /**
     * Make a POST request
     * if params are json please set header: "content-type" => "application/json"
     * @param string $url
     * @param null|string|array $params
     * @param ?array $headers
     * @return CurlResponse
     */
    public function post($url, $params = null, $headers = null)
    {
        if (is_array($headers)) {
            $this->setHeaders($headers);
        }

        return $this->fetch(self::POST, $url, $params);

    }


    /**
     * @param string|\Stringable $method
     * @param string|\Stringable $url
     * @param string|array|null $params
     * @return static
     */
    public function prepare($method, $url, $params = null)
    {

        $url = (string)$url;
        $method = (string)$method;

        $this->previous = null;
        $this->responseHeaders = [];
        $this->rawHeaders = "";
        $this->initialCount = $this->requestCount;


        $json = false;
        $requestMethod = strtoupper($method);
        if (preg_match("#^(.+)JSON$#", $requestMethod, $matches)) {
            $requestMethod = $matches[1];
            $json = true;
        }

        if (!CurlHandler::isValidMethod($requestMethod)) {
            throw new \InvalidArgumentException("Invalid method $requestMethod");
        }

        // for faster requests
        $this->unsetOpt(\CURLOPT_HEADERFUNCTION);
        if ($this->parseHeaders) {
            $this->setOpt(\CURLOPT_HEADERFUNCTION, $this->generateHeaderFunction());
        }

        $this->setOpt(\CURLOPT_CUSTOMREQUEST, $requestMethod);

        if ($json && !$this->getHeader("content-type")) {
            $this->addHeader("content-type", "application/json");
        } elseif ($requestMethod !== "GET") {
            $this->addHeader("content-type", "application/x-www-form-urlencoded");
        }

        if (is_array($params)) {
            $params = $json ? json_encode($params) : http_build_query($params);
        }


        $this->unsetOpt(\CURLOPT_POSTFIELDS);

        if ($method === "GET" && !$json) {
            $this->unsetOpt(\CURLOPT_CUSTOMREQUEST);
            if (!empty($params)) {
                $url .= false !== strpos($url, "?") ? "&" : "?";
                $url .= $params;
            }
        } elseif (is_string($params)) {
            $this->setOpt(\CURLOPT_POSTFIELDS, $params);
        }

        $this->unsetOpt(\CURLOPT_HTTPHEADER);


        if (!empty($this->requestHeaders)) {
            $this->setOpt(\CURLOPT_HTTPHEADER, $this->makeHeaders());
        }

        $this->setOpt(\CURLOPT_URL, $url);
        $this->setOpt(\CURLOPT_FILE, $this->createFileHandle());
        $ch = $this->getHandle();
        curl_reset($ch);
        foreach ($this->options as $name => $value) {
            curl_setopt($this->getHandle(), $name, $value);
        }
        $this->ready = true;
        return $this;
    }

    /**
     * @return CurlResponse
     */
    public function getResult()
    {

        $ch = $this->getHandle();
        $info = curl_getinfo($ch);
        $statusCode = intval($info['http_code']);
        $success = 0 !== $statusCode;
        $statusText = CurlHandler::getReasonPhrase($statusCode);
        $info["status"] = $statusCode;
        $info["statusText"] = $statusText;
        $info["error"] = [
            curl_errno($ch) => curl_error($ch)
        ];


        $redirections = ($this->requestCount - $this->initialCount) - 1;
        if (!empty($info["redirect_count"])) {
            $redirections = $info["redirect_count"];
        }

        $resp = CurlResponse::make([
            "success" => $success,
            "info" => $info,
            "stream" => $this->file,
            "headers" => $this->responseHeaders,
            "previous" => $this->previous,
            "redirections" => $redirections
        ]);

        // prevent infinite loop in execute on multi redirects
        $this->ready = false;

        // auto redirect (301,302)
        if (!empty($info["redirect_url"])) {
            $this->previous = $resp;
            // reset data for new request
            $this->rawHeaders = "";
            $this->responseHeaders = [];
            curl_setopt($ch, \CURLOPT_FILE, $this->file = @fopen("php://temp", "r+"));
            curl_setopt($ch, \CURLOPT_URL, $info["redirect_url"]);
            $this->ready = true;
        }
        return $resp;
    }


    /**
     * @return CurlResponse|null
     */
    public function execute()
    {
        if ($this->ready) {


            $ch = $this->getHandle();
            while (1) {
                @set_time_limit(120);
                @curl_exec($ch);

                $resp = $this->getResult();
                // redirection
                if ($this->ready) {
                    continue;
                }
                return $resp;
            }
        }
        return null;
    }


    public function __construct()
    {
        $this->uid = \generate_uid();
        $this->options = [
            \CURLOPT_ENCODING => 'gzip,deflate',
            \CURLOPT_AUTOREFERER => true,
            \CURLOPT_SSL_VERIFYPEER => 0,
        ];

    }


    public function __destruct()
    {
        if (!$this->closed) {
            @curl_close($this->handle);
        }

    }

    /**
     * @return \CurlHandle|resource
     */
    public function getHandle()
    {
        if ($this->closed) {
            $this->handle = curl_init();
            $this->closed = false;
        }
        return $this->handle;
    }

    /**
     * @return static
     */
    public function closeHandle()
    {
        if (!$this->closed) {
            @curl_close($this->handle);
            $this->closed = true;
            $this->ready = false;
            $this->uid = \generate_uid();
            $this->handle = null;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @return int
     */
    public function getRequestCount()
    {
        return $this->requestCount;
    }


    /**
     * @return bool
     */
    public function isReady()
    {
        return $this->ready;
    }


    /**
     * @param int $option
     * @param mixed $value
     * @return static
     */
    public function setOpt($option, $value)
    {
        $this->options[$option] = $value;
        return $this;

    }

    /**
     * @param array<int,mixed> $options
     * @return static
     */
    public function setOpts(array $options)
    {
        foreach ($options as $option => $value) {
            $this->setOpt($option, $value);
        }
        return $this;
    }

    /**
     * @param int $option
     * @return static
     */
    public function unsetOpt($option)
    {
        unset($this->options[$option]);
        return $this;
    }


    /**
     * @param string $file
     * @return static
     */
    public function setCookieFile($file)
    {
        $umask = @umask(0);
        @mkdir(dirname($file), true, 0777);
        @umask($umask);
        if (!is_writable(dirname($file))) {
            throw new \RuntimeException("Cookie file $file cannot be created.");
        }
        $this->setOpt(\CURLOPT_COOKIEFILE, $file);
        return $this->setOpt(\CURLOPT_COOKIEJAR, $file);
    }

    /**
     * @param int $timeout
     * @return static
     */
    public function setTimeout($timeout)
    {

        if (is_int($timeout) && $timeout > 0) {
            $this->setOpts([
                \CURLOPT_CONNECTTIMEOUT => $timeout,
                \CURLOPT_TIMEOUT => $timeout,
            ]);
        }

        return $this;
    }


    /**
     * @param string|bool|null $userAgent
     * @return static
     */
    public function setUserAgent($userAgent = null)
    {
        if (is_int($userAgent) || true === $userAgent || null === $userAgent) {
            $userAgent = CurlHandler::generateUserAgent($userAgent);
        }

        unset($this->requestHeaders["user-agent"]);

        if (false === $userAgent) {
            unset($this->options[\CURLOPT_USERAGENT]);
            return $this;
        }

        return $this->setOpt(\CURLOPT_USERAGENT, $userAgent);

    }

    /**
     * @return bool
     */
    public function canParseHeaders()
    {
        return $this->parseHeaders;
    }

    /**
     * @param bool $parseHeaders
     * @return static
     */
    public function enableHeaderParsing($parseHeaders = true)
    {
        $this->parseHeaders = $parseHeaders !== false;
        return $this;
    }


    /**
     * @param string $name
     * @return string
     */
    public function getHeader($name)
    {
        if (!isset($this->requestHeaders[strtolower($name)])) {
            return "";
        }
        return $this->requestHeaders[strtolower($name)];
    }


    /**
     * Erases previous headers and replaces them with provided values
     * @param array<string,string|string[]> $headers
     * @return static
     */
    public function setHeaders(array $headers)
    {
        $this->requestHeaders = [];
        return $this->addHeaders($headers);
    }


    /**
     * @param array<string,string|string[]> $headers
     * @return static
     */
    public function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string|string[] $value
     * @return $this
     */
    public function addHeader($name, $value)
    {

        if (!is_array($value)) {
            $value = array_slice(func_get_args(), 1);
        }
        if (is_string($name)) {
            $name = strtolower($name);
            if ($name === "user-agent") {
                return $this->setOpt(\CURLOPT_USERAGENT, $value[0]);
            }
            $this->requestHeaders[$name] = implode(", ", $value);

            if ($name === "referer") {
                unset($this->options[\CURLOPT_AUTOREFERER]);
            }

        }
        return $this;
    }

    public function removeHeader($name)
    {
        unset($this->requestHeaders[strtolower($name)]);
        return $this;
    }


    /**
     * @param string $name
     * @return string
     */
    protected function getHeaderName($name)
    {
        return ucfirst(preg_replace_callback('#-([a-z])#', function ($matches) {
            return strtoupper($matches[0]);
        }, strtolower($name)));
    }

    /**
     * @return string[]
     */
    protected function makeHeaders()
    {
        $headers = [];
        foreach ($this->requestHeaders as $name => $value) {
            $headers[] = sprintf('%s: %s', $this->getHeaderName($name), $value);
        }
        return $headers;
    }

    /**
     * @return resource
     */
    protected function createFileHandle()
    {
        if ($this->file) {
            @fclose($this->file);
        }
        return $this->file = @fopen("php://temp", "r+");
    }


    protected function generateHeaderFunction()
    {
        return function () {
            $doNotSplit = ["set-cookie"];
            $this->rawHeaders .= $raw = func_get_arg(1);
            $len = strlen($raw);

            if (!empty($line = rtrim($raw)) && preg_match("#^(\H+):\h+(.+)$#", $line, $matches)) {


                $responseHeaders = &$this->responseHeaders;
                list(, $name, $values) = $matches;
                $name = strtolower($name);
                if (!isset($responseHeaders[$name])) {
                    $responseHeaders[$name] = [];
                }

                // dates and others
                if (in_array($name, $doNotSplit) || false !== strtotime($values)) {
                    $responseHeaders[$name][] = trim($values);
                    return $len;
                }


                foreach (explode(",", $values) as $value) {
                    $responseHeaders[$name][] = trim($value);
                }

            } elseif (0 === strpos($raw, "HTTP/")) {
                // detects a new request
                $this->responseHeaders = [];
                $this->rawHeaders = $raw;
                $this->requestCount++;
            }

            return $len;
        };

    }

    public function __get($name)
    {
        if (!$this->__isset($name)) {
            return null;
        }
        return $this->{$name};
    }


    public function __isset($name)
    {
        return property_exists($this, $name) && $this->{$name} !== null;
    }


    public function __set($name, $value)
    {

    }

    public function __unset($name)
    {
    }


    /**
     * Methods
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods
     */
    const GET = "GET";
    const HEAD = "HEAD";
    const POST = "POST";
    const PUT = "PUT";
    const DELETE = "DELETE";
    const CONNECT = "CONNECT";
    const OPTIONS = "OPTIONS";
    const TRACE = "TRACE";
    const PATCH = "PATCH";

}

}
namespace HttpClient{




/**
 * @property-read string $body
 * @property-read int $status
 * @property-read string $statusText
 * @property-read array<int,string> $error
 */
class CurlResponse
{


    /**
     * @var array<string,mixed>
     */
    public $info = null;

    public $success = false;
    protected $contents = null;
    protected $stream = null;
    protected $headers = [];

    public $redirections = 0;

    /**
     * @var array<string,string>
     */
    protected $headerNames = [];


    protected $previous = null;

    /**
     * @return ?static
     */
    public function getPrevious()
    {
        return $this->previous;
    }


    protected function fixHeaders()
    {


        if ($this->stream) {
            $this->contents = null;
        }

        $this->headerNames = [];
        foreach (array_keys($this->headers) as $lowercased) {
            $lowercased = strtolower($lowercased);
            $name = preg_replace_callback("#-\w#", function ($matches) {
                return strtoupper($matches[0]);
            }, ucfirst($lowercased));
            $this->headerNames[$lowercased] = $name;
        }
        $headers = $this->headers;
        $this->headers = [];
        foreach ($this->headerNames as $lower => $name) {
            $this->headers[$name] = $headers[$lower];
        }
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param string $header
     * @return bool
     */
    public function hasHeader($header)
    {
        return isset($this->headerNames[strtolower($header)]);
    }


    /**
     * @param string $header
     * @return array
     */
    public function getHeader($header)
    {
        $header = strtolower($header);

        if (!isset($this->headerNames[$header])) {
            return [];
        }

        $header = $this->headerNames[$header];
        return $this->headers[$header];
    }

    /**
     * @param string $header
     * @return string
     */
    public function getHeaderLine($header)
    {
        return implode(', ', $this->getHeader($header));
    }


    /**
     * @return string
     */
    public function getRawHeaders()
    {
        $str = "";
        foreach (array_keys($this->headerNames) as $name) {
            $str .= sprintf("%s: %s\n", $this->headerNames[$name], $this->getHeaderLine($name));
        }


        return rtrim($str);
    }


    public function __destruct()
    {
        if ($this->stream) {
            @fclose($this->stream);
        }
    }

    /**
     * @param array $data
     * @param ?static $instance
     * @return static
     */
    public static function make(array $data, $instance = null)
    {
        if (!isset($instance)) {
            $instance = new static();
        }
        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }
        $instance->fixHeaders();
        return $instance;
    }

    /**
     * @return ?string
     */
    public function getContents()
    {

        if (!isset($this->contents)) {
            $this->contents = "";
            if ($this->stream) {
                if (-1 !== @fseek($this->stream, 0)) {
                    $this->contents = stream_get_contents($this->stream);
                }
                @fclose($this->stream);
                $this->stream = null;
            }
        }
        return $this->contents;
    }


    /**
     * @return mixed
     */
    public function getDecodedContents()
    {
        $contents = $this->getContents();

        if (null === ($value = @json_decode($contents, true))) {
            $value = $contents;
        }

        return $value;
    }


    public function __get($name)
    {
        if ($name === "body") {
            return $this->getContents();
        }

        if ($this->__isset($name)) {
            return $this->info[$name];
        }

        return null;
    }


    public function __isset($name)
    {
        if ($name === "body") {
            return true;
        }

        return is_array($this->info) && isset($this->info[$name]);
    }


    public function __set($name, $value)
    {
    }

    public function __unset($name)
    {
    }

}

}