<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Form;
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
        $spam = [];

        // don't use collection and map, etc as `getFilesByType` retains the array keys so it can return
        // an array that starts at '1' which mucks up the front-end because it gets converted to an Object
        // instead of an array
        foreach (Folder::disk('storage')->getFilesByType("addons/{$this->getAddonName()}", 'php') as $file)
        {
            $filename = pathinfo($file)['filename'];
            $submission = $this->storage->getSerialized($filename)->toArray();

            // add the id so that Dossier can remove it from the view after approve/discard
            $submission['id'] = $filename;

            // gotta set the items to not checked...kinda weird it's not a default but whatever
            // if this isn't set the checkboxes don't work
            $submission['checked'] = false;

            $spam[] = $submission;
        }

        return [
            'columns' => $this->akismet->getFields(),
            'items' => $spam
        ];

    }

    public function getForms()
    {
        return collect(Form::all())->map(function ($form) {
            $fields = collect(array_keys(Form::get($form['name'])->formset()->data()['fields']));

            $fields = $fields->map(function ($field) {
                return [
                    'text' => ucfirst($field),
                    'value' => $field,
                ];
            });

            return [
                'text' => $form['title'],
                'value' => $form['name'],
                'fields' => $fields
            ];
        });
    }
}
