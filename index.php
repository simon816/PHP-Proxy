<?php

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    file_exists($file) ? require $file : 0;
});

use io\URL;
use io\URLUtils;

use response\CookieHandler;
use response\html\HTMLResponseHandler;
use response\html\DocumentURLRemapper;
use response\html\AddHeaderForm;
use response\html\HTMLPageObfuscator;
use response\css\CSSRemapper;

class ProxyController
{
    const MODE_HTML = 1;
    const MODE_DISABLE_HEADER_FORM = 2;
    const MODE_POST_DATA = 4;

    private $rootUrl;
    private $urlRemapper;
    private $requestedUrl = null;
    private $modeBits = 0;

    public function __construct()
    {
        $this->rootUrl = URLUtils::urlOf($_SERVER['REQUEST_SCHEME'], $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['PHP_SELF']);
        $this->urlRemapper = new ProxyURLRemapper($this->rootUrl, 'page', 'raw');
        $this->initializeSession();
        $this->readInputParams();
    }

    private function initializeSession()
    {
        if (isset($_SESSION)) {
            return;
        }
        ini_set('session.use_only_cookies', 1);
        $host = $this->rootUrl->getHost();
        if ($host === 'localhost') {
            $host = null;
        }
        session_name('PROXYSESSION');
        session_set_cookie_params(0, dirname($this->rootUrl->getPath()), $host);
        session_start();
    }

    private function readInputParams()
    {
        $encodedUrl = null;

        if (isset($_SERVER['REDIRECT_URL'], $_GET['__redir__'], $_SERVER['HTTP_REFERER'])) {
            $referer = new URL($_SERVER['HTTP_REFERER']);
            $query = URLUtils::queryAsArray($referer->getQuery());
            $refUrl = isset($query['page']) ? $query['page'] : isset($query['raw']) ? $query['raw'] : null;
            if (!$refUrl) {
                if (!isset($_SESSION['requestPage'])) {
                    header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
                    exit;
                } else {
                    $pageUrl = new URL($_SESSION['requestPage']);
                }
            } else {
                $pageUrl = new URL($this->urlRemapper->decodeUrl($refUrl));
            }
            $url = URLUtils::inheritBase($pageUrl, $_GET['__redir__']);
            $loc = $this->urlRemapper->toRawUrl($url);
            header("Location: $loc");
            exit;
        } elseif (isset($_SERVER['REDIRECT_URL'])) {
            // htaccess redirected us but required data is missing
            header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
            exit;
        }

        $form = false;
        if (isset($_GET['__formurl__'])) {
            $encodedUrl = $_GET['__formurl__'];
            unset($_GET['__formurl__']);
            $form = true;
        } elseif (isset($_POST['__formurl__'])) {
            $encodedUrl = $_POST['__formurl__'];
            unset($_POST['__formurl__']);
            $this->modeBits |= self::MODE_POST_DATA;
            $this->modeBits |= self::MODE_HTML;
        } elseif(isset($_GET['page'])) {
            $encodedUrl = $_GET['page'];
            $this->modeBits |= self::MODE_HTML;
            if (array_key_exists('noHeader', $_GET)) {
                $this->modeBits |= self::MODE_DISABLE_HEADER_FORM;
            }
        } elseif (isset($_GET['raw'])) {
            $encodedUrl = $_GET['raw'];
        } elseif (isset($_POST['url'])) {
            $partialUrl = $_POST['url'];
            $url = URLUtils::completeUrl($partialUrl);
            $loc = $this->urlRemapper->toPageUrl($url);
            header("Location: $loc");
            exit;
        }

        if ($encodedUrl) {
            $this->requestedUrl = new URL($this->urlRemapper->decodeUrl($encodedUrl));
        }

        if ($form) {
            $newTarget = URLUtils::appendQuery($this->requestedUrl, $_GET);
            $loc = $this->urlRemapper->toPageUrl($newTarget);
            header("Location: $loc");
            exit;
        }
    }

    private function isModeEnabled($modeBit)
    {
        return ($this->modeBits & $modeBit) === $modeBit;
    }

    public function run()
    {
        if ($this->requestedUrl == null) {
            $this->showHomepage();
            return;
        }
        $proxy = new Proxy();
        $proxy->setOption(Proxy::OPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

        $cssRemapper = new CSSRemapper($this->urlRemapper, $this->requestedUrl);
        $proxy->registerResponseHandler($cssRemapper);

        // HTML response mode
        if ($this->isModeEnabled(self::MODE_HTML)) {
            $_SESSION['requestPage'] = (string) $this->requestedUrl;
            $htmlHandler = new HTMLResponseHandler();
            $urlRemapper = new DocumentURLRemapper($this->requestedUrl, $this->urlRemapper);
            $urlRemapper->setCssRemapper($cssRemapper);
            $htmlHandler->registerDomHandler($urlRemapper);
            $proxy->registerResponseHandler($htmlHandler);

            // Add header form
            if (!$this->isModeEnabled(self::MODE_DISABLE_HEADER_FORM)) {
                $htmlHandler->registerDomHandler(new AddHeaderForm($this->rootUrl, $this->requestedUrl, dirname($this->rootUrl->getPath())));
            }

            // Obfuscate the output. Uncomment to enable.
            // $proxy->registerResponseHandler(new HTMLPageObfuscator());
        }

        $cookieHandler = new CookieHandler($this->rootUrl, $this->requestedUrl);

        $proxy->registerResponseHandler($cookieHandler);

        $data = null;
        if ($this->isModeEnabled(self::MODE_POST_DATA)) {
            $data = $_POST;
        }
        $requestHeaders = array();
        $cookieHeader = $cookieHandler->getCookieHeader();
        if ($cookieHeader != null) {
            $requestHeaders[] = "Cookie: {$cookieHeader}";
        }
        $response = $proxy->proxyURL($this->requestedUrl, $data, $requestHeaders);

        $headers = $response->getHeaders();

        // TODO Clean this up. Should make a LocationHeaderHandler
        if ($headers->hasHeader('Location')) {
            $loc = $headers->getFirstHeader('Location');
            $newLoc = (string) URLUtils::inheritBase($this->requestedUrl, $loc);
            if ($this->isModeEnabled(self::MODE_HTML)) {
                $extra = '';
                if ($this->isModeEnabled(self::MODE_DISABLE_HEADER_FORM)) {
                    $extra = 'noHeader';
                }
                $newLoc = $this->urlRemapper->toPageUrl($newLoc, $extra);
            } else {
                $newLoc = $this->urlRemapper->toRawUrl($newLoc);
            }
            $headers = $headers->setHeader('Location', array($newLoc));
        }

        // Note: Set-Cookie is manipulated by CookieHandler
        $mirroredHeaders = array(''/*(status header)*/, 'Location', 'Content-Encoding', 'Accept-Ranges', 'Content-Type', 'Set-Cookie');
        // Download related
        $mirroredHeaders = array_merge($mirroredHeaders, array('Content-Transfer-Encoding', 'Content-Description', 'Content-Disposition', 'Content-Transfer-Encoding'));
        foreach ($mirroredHeaders as $headerName) {
            $headerValues = $headers->getHeader($headerName);
            if ($headerValues != null) {
                foreach ($headerValues as $headerValue) {
                    if ($headerName === '') { // Status header
                        header($headerValue);
                    } else {
                        header("{$headerName}: {$headerValue}", false);
                    }
                }
            }
        }
        echo $response->getBody();
    }

    private function showHomepage()
    {
        $homepage = file_get_contents('homepage.html');
        echo str_replace('${ROOT}', $this->rootUrl, $homepage);
    }
}

(new ProxyController())->run();
