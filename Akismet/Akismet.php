<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Str;
use Statamic\API\URL;
use GuzzleHttp\Client;
use Statamic\API\File;
use Statamic\API\Form;
use Statamic\API\Path;
use Statamic\API\User;
use Statamic\API\Config;
use Statamic\API\Helper;
use Statamic\Addons\Akismet\Exceptions\AkismetInvalidKeyException;

trait Akismet
{
    public static $endpoint = 'rest.akismet.com/1.1';

    /**
     * Akismet API key, get one here: https://akismet.com/plans/
     *
     * @var string
     */
    private $apiKey;

    /** @var \GuzzleHttp\Client */
    private $httpClient;

    private $siteUrl;

    private $submissionType;

    public function __construct()
    {
        $this->apiKey = $this->getConfig('akismet_key');
        $this->siteUrl = URL::makeAbsolute(Config::getSiteUrl());
        $this->httpClient = new Client();
    }

    protected function userAgent()
    {
        $meta = $this->getMeta();

        return sprintf('Statamic %s | %s %s', STATAMIC_VERSION, $meta['name'], $meta['version']);
    }

    /**
     * Ensures that we get the right IP address even if behind CloudFlare
     *
     * @return    string
     */
    private function requestingIp()
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
        /*
         * Akismet expects the params to be passed like a form submission
         * (https://akismet.com/development/api/#detailed-docs)
         * so as per Guzzle: http://docs.guzzlephp.org/en/latest/request-options.html#form-params
         */
        $response = $this->httpClient->post(
            $this->keyEndpoint(),
            [
                'form_params' => [
                        'key' => $this->apiKey,
                        'blog' => $this->siteUrl,
                    ],
            ]
        );

        $body = (string) $response->getBody();

        return ('valid' === $body);
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
                'fields' => $fields,
            ];
        });
    }

    private function formConfig($formsetName)
    {
        return array_get(
            collect($this->getConfig('forms'))->first(function ($ignored, $data) use ($formsetName) {
                return $formsetName == array_get($data, 'form_and_fields.form');
            }),
            'form_and_fields',
            []
        );
    }

    protected function canAccessQueue()
    {
        $user = User::getCurrent();

        return $user->isSuper() ||
               !empty(array_intersect(
                    $user->roles()->keys()->all(),
                    $this->getConfig('roles', [])
                )
        );
    }

    public function removeFromQueue($formset, $id)
    {
        // @todo replace when https://github.com/statamic/v2-hub/issues/629 is fixed
        File::disk('storage')->delete(Path::assemble(
            'addons',
            $this->getAddonClassName(),
            $formset,
            Str::ensureRight($id, '.php')
        ));
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
    public function submitHam($data = null)
    {
        $author_key = $this->getConfig('author', 'author');
        $email_key = $this->getConfig('email', 'email');
        $content_key = $this->getConfig('content', 'content');

        $data = Helper::ensureArray($data);

        $params = $this->mergeWithDefaultParams(
            [
                'comment_author' => array_get($data, $author_key),
                'comment_content' => array_get($data, $content_key),
                'comment_author_email' => array_get($data, $email_key),
            ]
        );

        if ($this->isKeyValid()) {
            $response = $this->httpClient->post(
                $this->hamEndpoint(),
                ['form_params' => $params]
            );
            $body = (string) $response->getBody();

            return (bool) ('Thanks for making the web a better place.' == $body);
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
    public function submitSpam($data, $testing = false)
    {
        $author_key = $this->getConfig('author', 'author');
        $email_key = $this->getConfig('email', 'email');
        $content_key = $this->getConfig('content', 'content');

        $data = Helper::ensureArray($data);

        $params = $this->mergeWithDefaultParams(
            [
                'comment_author' => array_get($data, $author_key),
                'comment_content' => array_get($data, $content_key),
                'comment_author_email' => array_get($data, $email_key),
                'is_test' => $testing,
            ]
        );

        if ($this->isKeyValid()) {
            $response = $this->httpClient->post(
                $this->spamEndpoint(),
                ['form_params' => $params]
            );
            $body = (string) $response->getBody();

            return (bool) ('Thanks for making the web a better place.' == $body);
        }

        throw new AkismetInvalidKeyException;
    }

    /**
     * @param array $params The parameters to be merged with defaults
     *
     * @return array
     */
    protected function mergeWithDefaultParams($params = null)
    {
        return array_merge(
            [
                'blog' => $this->siteUrl,
                'user_ip' => $this->requestingIp(),
                'user_agent' => $this->userAgent(),
                'comment_type' => 'contact-form',
            ],
            Helper::ensureArray($params)
        );
    }

    protected function contentEndpoint()
    {
        return sprintf('https://%s.%s/comment-check', $this->apiKey, self::$endpoint);
    }

    protected function keyEndpoint()
    {
        return sprintf('https://%s/verify-key', self::$endpoint);
    }

    protected function hamEndpoint()
    {
        return sprintf('https://%s.%s/submit-ham', $this->apiKey, self::$endpoint);
    }

    protected function spamEndpoint()
    {
        return sprintf('https://%s.%s/submit-spam', $this->apiKey, self::$endpoint);
    }
}
