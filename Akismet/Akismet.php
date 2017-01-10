<?php

namespace Statamic\Addons\Akismet;

use GuzzleHttp\Client;
use Statamic\Addons\Akismet\Exceptions\AkismetInvalidKeyException;
use Statamic\API\Config;
use Statamic\API\File;
use Statamic\API\Path;
use Statamic\API\Str;
use Statamic\Extend\Extensible;

class Akismet
{
    use Extensible;

    const ENDPOINT = 'rest.akismet.com/1.1';

    /**
     * Akismet API key, get one here: https://akismet.com/plans/
     *
     * @var string
     */
    private $api_key;

    /**
     * The HTTP client to user for all requests
     *
     * @var Client
     */
    private $httpClient;

    /**
     * The User Agent string
     *
     * @var string
     */
    private $ua;

    /**
     * the site url
     *
     * @var string
     */
    private $site_url;

    /**
     * addon config params
     *
     * @var array
     */
    private $config;

    public function __construct()
    {
        $this->api_key = $this->getConfig('akismet_key');
        $this->site_url = Config::getSiteUrl();
        $this->httpClient = new Client();
        $this->ua = $this->getUserAgent();
        $this->config = $this->getConfig();
    }

    protected function getUserAgent()
    {
        $meta = $this->getMeta();

        return sprintf('Statamic %s | %s %s', STATAMIC_VERSION, $meta['name'], $meta['version']);
    }

    /**
     * @param Client $httpClient
     */
    public function setHttpClient(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Ensures that a valid origin URL is set and returns it
     *
     * @return string
     */
    public function getOriginUrl()
    {
        return Config::getSiteUrl();
    }

    /**
     * Get the form we are checking
     *
     * @return string
     */
    public function getForm()
    {
        return $this->config['form_and_fields']['form'];
    }

    /**
     * Get the form fields as an array
     *
     * @return array
     */
    public function getFields()
    {
        return [
            array_get($this->config, 'form_and_fields:author', 'author'),
            array_get($this->config, 'form_and_fields:email', 'email'),
            array_get($this->config, 'form_and_fields:content', 'content')
            ];
    }

    /**
     * Validates potential spam against the Akismet API
     *
     * @param array $data
     *
     * @example
     * $data        = array(
     *    'email'        => 'john@smith.com',
     *    'author'    => 'John Smith',
     *    'content'    => 'We are Smith & Co, one of the best companies in the world.'
     * )
     *
     * @note $data[content] is required
     * @throws Exceptions\AkismetInvalidKeyException
     * @return bool
     */
    public function detectSpam(array $data = [])
    {
        $author_key = array_get($this->config, 'form_and_fields:author', 'author');
        $email_key = array_get($this->config, 'form_and_fields:email', 'email');
        $content_key = array_get($this->config, 'form_and_fields:content', 'content');

        $params = $this->mergeWithDefaultParams(
            [
                'comment_author' => $data[$author_key] ?? null,
                'comment_content' => $data[$content_key] ?? null,
                'comment_author_email' => $data[$email_key] ?? null,
            ]
        );

        if ($this->isKeyValid())
        {
            $response = $this->httpClient->post($this->getContentEndpoint(), ['form_params' => $params]);
            $body = (string)$response->getBody();

            if ($response->hasHeader('X-akismet-pro-tip'))
            {
                return 'discard';
            }
            return ('true' == $body) ? 'spam' : false;
        }

        throw new AkismetInvalidKeyException();
    }

    /**
     * @param array $params The parameters to be merged with defaults
     *
     * @return array
     */
    protected function mergeWithDefaultParams(array $params = [])
    {
        return array_merge(
            [
                'blog' => $this->site_url,
                'user_ip' => $this->getRequestingIp(),
                'user_agent' => $this->ua,
                'comment_type' => 'contact-form'
            ],
            $params
        );
    }

    /**
     * Ensures that we get the right IP address even if behind CloudFlare
     *
     * @return    string
     */
    public function getRequestingIp()
    {
        return isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Checks whether the API key is valid
     *
     * @return bool
     */
    private function isKeyValid()
    {
        $params = [
            'key' => $this->api_key,
            'blog' => Config::getSiteUrl()
        ];

        /*
         * Note, Akismet expects the params to be passed like a form submission (https://akismet.com/development/api/#detailed-docs)
         * so as per Guzzle: http://docs.guzzlephp.org/en/latest/request-options.html#form-params
         */
        $response = $this->httpClient->post($this->getKeyEndpoint(), ['form_params' => $params]);

        $body = (string)$response->getBody();

        return ('valid' === $body);
    }

    protected function getKeyEndpoint()
    {
        return sprintf('https://%s/verify-key', self::ENDPOINT);
    }

    protected function getContentEndpoint()
    {
        return sprintf('https://%s.%s/comment-check', $this->api_key, self::ENDPOINT);
    }

    public function removeFromQueue($id)
    {
        // @todo replace when https://github.com/statamic/v2-hub/issues/629 is fixed
        File::disk('storage')->delete(Path::assemble(
            'addons',
            $this->getAddonClassName(),
            Str::ensureRight($id, '.php')));
    }

    /**
     * @param \Statamic\Forms\Submission $submission
     */
    public function addToQueue($submission)
    {
        $this->storage->putSerialized($submission->id(), $submission);
    }

    /**
     * Sends ham (not spam) to Akismet so it can learn
     *
     * @param array $data
     *
     * @throws AkismetInvalidKeyException
     * @return bool
     */
    public function submitHam(array $data = [])
    {
        $author_key = $this->getConfig('author', 'author');
        $email_key = $this->getConfig('email', 'email');
        $content_key = $this->getConfig('content', 'content');

        $params = $this->mergeWithDefaultParams(
            [
                'comment_author' => $data[$author_key] ?? null,
                'comment_content' => $data[$content_key] ?? null,
                'comment_author_email' => $data[$email_key] ?? null,
            ]
        );

        if ($this->isKeyValid())
        {
            $response = $this->httpClient->post($this->getHamEndpoint(), ['form_params' => $params]);
            $body = (string)$response->getBody();

            return (bool)('Thanks for making the web a better place.' == $body);
        }

        throw new AkismetInvalidKeyException;
    }

    protected function getHamEndpoint()
    {
        return sprintf('https://%s.%s/submit-ham', $this->api_key, self::ENDPOINT);
    }

    protected function getSpamEndpoint()
    {
        return sprintf('https://%s.%s/submit-spam', $this->api_key, self::ENDPOINT);
    }
}
