<?php

namespace Statamic\Addons\Akismet;

use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;
use Statamic\Extend\Listener;

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
        if (in_array($submission->formset()->name(), $this->getConfig('forms')))
        {
            if ($spam = $this->akismet->detectSpam($submission->data()))
            {
                // @todo want to stop submission but not give any indication
                // if the discard thingy is not set, put in spam queue
                if (!$spam !== 'discard')
                {
                    //TODO: workaround for https://github.com/statamic/v2-hub/issues/984
                    $submission_id = $submission->id();
                    $submission->id($submission_id);

                    $this->akismet->addToQueue($submission);
                }

                // @todo remove this when https://github.com/statamic/v2-hub/issues/1165 is fixed
                // remove the existing input from the request so that there are no `old` values in the form,
                // because we are pretending to have submitted the form

                // @todo pretty sure this will mess up any form listeners called after me so....
                request()->replace([]);

                return ['errors' => ['is_spam' => true]];
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
        $charge = (new NavItem)->name('Akismet')->route('akismet')->icon('untag');
        $nav->addTo('tools', $charge);
    }
}
