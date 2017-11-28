<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Helper;
use Statamic\API\Str;
use Statamic\API\URL;
use GuzzleHttp\Client;
use Statamic\API\File;
use Statamic\API\Form;
use Statamic\API\Path;
use Statamic\API\Config;
use Statamic\Addons\Akismet\Exceptions\AkismetInvalidKeyException;
use Statamic\API\User;

trait Akismet
{
    static $endpoint = 'rest.akismet.com/1.1';

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
     * Get the form fields as an array
     *
     * @param $formset_name string which form are we interested in?
     *
     * @return array
     */
    public function getFields($formset_name)
    {
         $config = collect($this->getConfig('forms'))->first(function($ignored, $data) use ($formset_name) {
            return $formset_name == array_get($data, 'form_and_fields.form');
         });

        return [
            array_get($config, 'form_and_fields:author_field', 'author'),
            array_get($config, 'form_and_fields:email_field', 'email'),
            array_get($config, 'form_and_fields:content_field', 'content')
            ];
    }

    /**
     * Validates potential spam against the Akismet API
     *
     * @param array $data
     * @param string $formset_name
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
    public function detectSpam(array $data = [], $formset_name)
    {
        list($author_key, $email_key, $content_key) = $this->getFields($formset_name);

        $params = $this->mergeWithDefaultParams(
            [
                'comment_author' => array_get($data, $author_key),
                'comment_content' => array_get($data, $content_key),
                'comment_author_email' => array_get($data, $email_key),
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
            'blog' => URL::makeAbsolute(Config::getSiteUrl())
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
        return sprintf('https://%s/verify-key', self::$endpoint);
    }

    protected function getContentEndpoint()
    {
        return sprintf('https://%s.%s/comment-check', $this->api_key, self::$endpoint);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getForms()
    {
        return collect(Form::getAllFormsets())->map(function ($form) {
            $fields = collect(Form::fields($form['name']))->map(function ($form_field) {
                return [
                    'text' => array_get($form_field, 'display', ucfirst($form_field['name'])),
                    'value' => $form_field['field'],
                ];
            });

            return [
                'text' => $form['title'],
                'value' => $form['name'],
                'fields' => $fields
            ];
        });
    }

    protected function canAccessQueue()
    {
        $user = User::getCurrent();

        return $user->isSuper() ||
            !empty(array_intersect($user->roles()->keys()->all(), array_get($this->getConfig(), 'roles', [])));
    }

    public function removeFromQueue($formset, $id)
    {
        // @todo replace when https://github.com/statamic/v2-hub/issues/629 is fixed
        File::disk('storage')->delete(Path::assemble(
            'addons',
            $this->getAddonClassName(),
            $formset,
            Str::ensureRight($id, '.php')));
    }

    /**
     * @param \Statamic\Forms\Submission $submission
     */
    public function addToQueue($submission)
    {
        $formset = $submission->form()->name();
        $id = $submission->id();

        $this->storage->putSerialized(Path::assemble($formset, $id), $submission);
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
                'comment_author' => array_get($data, $author_key),
                'comment_content' => array_get($data, $content_key),
                'comment_author_email' => array_get($data, $email_key),            ]
        );

        if ($this->isKeyValid())
        {
            $response = $this->httpClient->post($this->getHamEndpoint(), ['form_params' => $params]);
            $body = (string)$response->getBody();

            return (bool)('Thanks for making the web a better place.' == $body);
        }

        throw new AkismetInvalidKeyException;
    }

    /**
     * Sends spam (not ham) to Akismet so it can learn
     *
     * @param array $data
     * @param bool  $testing let Akismet know if this a test call
     *
     * @throws AkismetInvalidKeyException
     * @return bool
     */
    public function submitSpam(array $data = [], bool $testing = false)
    {
        $author_key = $this->getConfig('author', 'author');
        $email_key = $this->getConfig('email', 'email');
        $content_key = $this->getConfig('content', 'content');

        $params = $this->mergeWithDefaultParams(
            [
                'comment_author' => array_get($data, $author_key),
                'comment_content' => array_get($data, $content_key),
                'comment_author_email' => array_get($data, $email_key),
                'is_test' => $testing,
            ]
        );

        if ($this->isKeyValid())
        {
            $response = $this->httpClient->post($this->getSpamEndpoint(), ['form_params' => $params]);
            $body = (string)$response->getBody();

            return (bool)('Thanks for making the web a better place.' == $body);
        }

        throw new AkismetInvalidKeyException;
    }

    protected function getHamEndpoint()
    {
        return sprintf('https://%s.%s/submit-ham', $this->api_key, self::$endpoint);
    }

    protected function getSpamEndpoint()
    {
        return sprintf('https://%s.%s/submit-spam', $this->api_key, self::$endpoint);
    }
}
