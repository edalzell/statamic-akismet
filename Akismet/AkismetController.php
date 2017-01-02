<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Folder;
use Statamic\API\Helper;
use Statamic\Extend\Controller;

class AkismetController extends Controller
{
    /** @var  Akismet */
    private $akismet;

    public function init()
    {
        $this->akismet = new Akismet();
    }

    /**
     * Maps to your route definition in routes.yaml
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return $this->view('index', ['title' => 'Spam Queue']);
    }

    public function discardSpam()
    {
        // the ids will be in the request
        collect(Helper::ensureArray(request('ids', [])))->each(function($id, $ignored)
        {
            $this->akismet->removeFromQueue($id);
        });
    }

    public function approveHam()
    {
        collect(Helper::ensureArray(request('ids', [])))->each(function($id, $ignored)
        {
            /** @var \Statamic\Forms\Submission $submission */
            $submission = $this->storage->getSerialized($id);

            // @todo should validation happen?
            $submission->unguard();

            $submission->save();

            // remove it from queue
            $this->akismet->removeFromQueue($id);

            //@todo submit to Akismet as ham
            $this->akismet->submitHam($submission->data());
        });
    }

    public function getSpam()
    {
        // @todo replace when https://github.com/statamic/v2-hub/issues/629 is fixed
        $spam = collect(Folder::disk('storage')->getFilesByType("addons/{$this->getAddonName()}", 'php'))
            ->map(function($file, $ignored)
            {
                $filename = pathinfo($file)['filename'];
                $content = $this->storage->getSerialized($filename)->toArray();

                // add the id so that Dossier can remove it from the view after approve/discard
                $content['id'] = $filename;

                // gotta set the items to not checked...kinda weird it's not a default but whatever
                // if this isn't set the checkboxes don't work
                $content['checked'] = false;

                return $content;
            })
            ->all();

        return [
            'columns' => ['author', 'email', 'content'],
            'items' => $spam
        ];

    }
}
