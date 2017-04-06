<?php

namespace Statamic\Addons\Akismet;

use Statamic\Extend\Listener;
use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;
use Statamic\Exceptions\SilentFormFailureException;

class AkismetListener extends Listener
{
    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        'Form.submission.creating' => 'checkForSpam',
        'cp.nav.created' => 'nav'
    ];

    /** @var  Akismet */
    private $akismet;

    public function init()
    {
        $this->akismet = new Akismet();
    }

    /**
     * Checks whether the content is considered spam as far as akismet is concerned
     *
     * @param \Statamic\Forms\Submission $submission The submission containing the key/value pairs to validate
     *
     * @example
     * $data        = array(
     *    'email'        => 'john@smith.com',
     *    'author'    => 'John Smith',
     *    'content'    => 'We are Smith & Co, one of the best companies in the world.'
     * )
     *
     * @note $data[content] is required
     *
     * @throws SilentFormFailureException
     *
     * @return \Statamic\Forms\Submission|array
     */
    public function checkForSpam($submission)
    {
        $formset_name = $submission->formset()->name();

        // only do something if we're on the right formset & it's spam
        if ($this->shouldProcessForm($formset_name) &&
            ($spam = $this->akismet->detectSpam($submission->data(), $formset_name)))
        {
            // if the discard thingy is not set, put in spam queue
            if (!$spam !== 'discard')
            {
                //TODO: workaround for https://github.com/statamic/v2-hub/issues/984
                $submission_id = $submission->id();
                $submission->id($submission_id);

                $this->akismet->addToQueue($submission);
            }

            // throw error that Statamic will treat same as honeypot. i.e. the form will
            // look like it succeeded
            throw new SilentFormFailureException('Spam submitted');
        }

        return $submission;
    }

    /**
     * @param $formset_name string
     *
     * @return bool
     *
     * Only process the form if the submitted form is the formset in the config
     *
     */
    private function shouldProcessForm($formset_name)
    {
        return collect($this->getConfig('forms'))->contains(function($ignore, $value) use ($formset_name)
        {
            return $formset_name == array_get($value, 'form_and_fields.form');
        });
    }

    /**
     * Add Akismet to the side nav
     * @param  Nav $nav [description]
     * @return void
     */
    public function nav(Nav $nav)
    {
        $spam = (new NavItem)->name('Spam Queue')->route('akismet')->icon('untag');
        $nav->addTo('tools', $spam);
    }
}
