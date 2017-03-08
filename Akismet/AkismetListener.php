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
     * @return \Statamic\Forms\Submission|array
     */
    public function checkForSpam($submission)
    {
        // only do something if we're on the right formset
        if ($submission->formset()->name() == $this->akismet->getForm())
        {
            if ($spam = $this->akismet->detectSpam($submission->data()))
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
        }

        return $submission;
    }

    /**
     * Add Akismet to the side nav
     * @param  Nav $nav [description]
     * @return void
     */
    public function nav(Nav $nav)
    {
        $charge = (new NavItem)->name('Spam Queue')->route('akismet')->icon('untag');
        $nav->addTo('tools', $charge);
    }
}
