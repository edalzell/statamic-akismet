<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Nav;
use Statamic\API\Path;
use Statamic\API\Folder;
use Statamic\Extend\Listener;
use Statamic\Exceptions\SilentFormFailureException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AkismetListener extends Listener
{
    use Akismet, AuthorizesRequests;

    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        'Form.submission.creating' => 'checkSubmission',
        'user.registering' => 'checkUser',
        'cp.nav.created' => 'nav',
        'cp.add_to_head' => 'addToHead',
    ];

    /**
     * Checks whether the content is considered spam as far as Akismet is concerned
     *
     * @param \Statamic\Forms\Submission $submission The submission containing the key/value pairs to validate
     *
     * @example
     * $data        = array(
     *    'email'    => 'john@smith.com',
     *    'author'   => 'John Smith',
     *    'content'  => 'We are Smith & Co, one of the best companies in the world.'
     * )
     *
     * @note $data[content] is required
     *
     * @throws SilentFormFailureException
     *
     * @return \Statamic\Forms\Submission|array
     */
    public function checkSubmission($submission)
    {
        // only do something if we're on the right formset & it's spam
        if ($this->shouldProcessForm($submission) &&
            ($spam = $this->detectSpam($submission))) {
            // if the discard thingy is not set, put in spam queue
            if (!$spam !== 'discard') {
                //TODO: workaround for https://github.com/statamic/v2-hub/issues/984
                $submission_id = $submission->id();
                $submission->id($submission_id);

                $this->addToQueue($submission);
            }

            // throw error that Statamic will treat same as honeypot. i.e. the form will
            // look like it succeeded
            throw new SilentFormFailureException('Spam submitted');
        }

        return $submission;
    }

    /**
     * Check if user is likely a big fat spammer
     *
     * @param \Statamic\Data\Users\User $user
     * @return void|array
     */
    public function checkUser($user)
    {
        if ($this->getConfigBool('user_check_registrations') &&
            ($spam = $this->detectSpam($user))) {
            // if the discard thingy is not set, it's spam
            if (!$spam !== 'discard') {
                $error = 'User is likely a big fat spammer';

                return ['errors' => [$error]];
            }
        }
    }

    /**
     * @param \Statamic\Forms\Submission $submission
     *
     * @return bool
     *
     * Only process the form if the submitted form is the formset in the config
     */
    private function shouldProcessForm($submission)
    {
        $formsetName = $submission->formset()->name();

        return collect($this->getConfig('forms'))->contains(function ($ignore, $value) use ($formsetName) {
            return $formsetName == array_get($value, 'form_and_fields.form');
        });
    }

    /**
     * Validates potential spam against the Akismet API
     *
     * @param \Statamic\Data\Users\User|\Statamic\Forms\Submission $data
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
    public function detectSpam($data)
    {
        if (!$this->isKeyValid()) {
            throw new AkismetInvalidKeyException();
        }

        $segments = explode('\\', get_class($data));
        $method = 'convert' . end($segments) . 'toAkismetData';

        $response = $this->httpClient->post(
                $this->contentEndpoint(),
                ['form_params' => $this->$method($data)]
            );
        $body = (string) $response->getBody();

        if ($response->hasHeader('X-akismet-pro-tip')) {
            return 'discard';
        }

        return ('true' == $body) ? 'spam' : false;
    }

    /**
     * @param \Statamic\Data\Users\User $data
     *
     * @return array
     */
    protected function convertUserToAkismetData($user)
    {
        $fields = $this->userConfig();
        $name = $user->get($fields['first_name_field']) . ' ' . $user->get($fields['last_name_field']);

        return [
                'blog' => $this->siteUrl,
                'user_ip' => $this->requestingIp(),
                'user_agent' => $this->userAgent(),
                'comment_type' => 'signup',
                'comment_author' => $name,
                'comment_author_email' => $user->email(),
                'comment_content' => $user->get($fields['content_field']),
        ];
    }

    /**
     * @param \Statamic\Forms\Submission $submission
     *
     * @return array
     */
    protected function convertSubmissionToAkismetData($submission)
    {
        $data = array_only(
            $submission->toArray(),
            $this->formConfig($submission->formset()->name())
        );

        $keys = [
            'comment_author',
            'comment_author_email',
            'comment_content',
        ];

        return array_merge(
            [
                'blog' => $this->siteUrl,
                'user_ip' => $this->requestingIp(),
                'user_agent' => $this->userAgent(),
                'comment_type' => 'content-form',
            ],
            array_combine($keys, $data)
        );
    }

    private function userConfig()
    {
        return [
            'first_name_field' => $this->getConfig('user_first_name_field'),
            'last_name_field' => $this->getConfig('user_last_name_field'),
            'content_field' => $this->getConfig('user_content_field'),
        ];
    }

    /**
     * Add Akismet to the side nav
     * @param  \Statamic\CP\Navigation\Nav $nav [description]
     * @return void
     */
    public function nav($nav)
    {
        if ($this->canAccessQueue()) {
            // Create the first level navigation item
            $spamMenu = Nav::item('Spam Queue')->route('akismet')->icon('untag');
            $totalSpamCount = 0;

            $this->getForms()->each(function ($form) use ($spamMenu, &$totalSpamCount) {
                $path = Path::assemble('addons', $this->getAddonName(), $form['value']);
                $count = count(Folder::disk('storage')->getFilesByType($path, 'php'));
                $totalSpamCount += $count;

                $spamMenu->add(
                        Nav::item($form['text'])
                            ->route('queue', 'form=' . $form['value'])
                            ->badge($count)
                    );
            });

            $spamMenu->badge($totalSpamCount);

            $nav->addTo('tools', $spamMenu);
        }
    }

    public function addToHead()
    {
        if ((request()->segment(2) == 'forms') && (request()->segment(3))) {
            return $this->js->tag('cp.js');
        }
    }
}
