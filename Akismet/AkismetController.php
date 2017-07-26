<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Form;
use Statamic\API\Path;
use Statamic\API\Folder;
use Statamic\API\Helper;
use Statamic\Extend\Controller;

class AkismetController extends Controller
{
    /** @var  Akismet */
    private $akismet;

    public function __construct()
    {
        $this->akismet = new Akismet();
    }

    /**
     * @deprecated not used in Statamic 2.6
     */
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
        $this->authorize('super');

        // get the first form if none chosen
        $form = $this->akismet->getForms()->first();

        return redirect()->route('queue',"form=".$form['value']);
    }

    /**
     * Maps to your route definition in routes.yaml
     *
     * @return \Illuminate\View\View
     */
    public function queue()
    {
        $this->authorize('super');

        $form = Form::get(request('form'));

        return $this->view('queue', [
            'title' => 'Spam Queue - ' . $form->title(),
            'formset' => $form->name()
        ]);
    }

    public function discardSpam()
    {
        $formset = request('formset');

        // the ids will be in the request
        collect(Helper::ensureArray(request('ids', [])))->each(function($id, $ignored) use ($formset)
        {
            $this->akismet->removeFromQueue($formset, $id);
        });
    }

    public function approveHam()
    {
        $formset = request('formset');

        collect(Helper::ensureArray(request('ids', [])))->each(function($id, $ignored) use ($formset)
        {
            /** @var \Statamic\Forms\Submission $submission */
            $submission = $this->storage->getSerialized(Path::assemble($formset, $id));

            // @todo should validation happen?
            $submission->unguard();

            $submission->save();

            // remove it from queue
            $this->akismet->removeFromQueue($formset, $id);

            //@todo submit to Akismet as ham
            $this->akismet->submitHam($submission->data());
        });
    }

    public function getSubmitSpam()
    {
        /** @var \Statamic\Forms\Submission $submission */
        $submission = Form::get(request('form'))->submission(request('id'));

        // add it to spam queue
        $this->akismet->addToQueue($submission);

        // send to Akismet
        $this->akismet->submitSpam($submission->data(), $this->getConfig('testing', false));

        //delete it
        $submission->delete();

        // go back so it refreshes the list of submissions
        return back();
    }

    public function getSpam()
    {
        $formset = request('form');

        // @todo replace when https://github.com/statamic/v2-hub/issues/629 is fixed
        // `getFilesByType` retains the array keys so it can return an array that starts at '1' which mucks
        // up the front-end because it gets converted to an Object instead of an array.
        // Which is why we need to use `values`

        $path = Path::assemble("addons", $this->getAddonName(), $formset);
        $spam = collect(Folder::disk('storage')->getFilesByType($path, 'php'))
            ->map(function($file ) use ($formset) {
                $filename = pathinfo($file)['filename'];

                $submission = $this->storage->getSerialized(Path::assemble($formset, $filename))->toArray();

                // add the id so that Dossier can remove it from the view after approve/discard
                $submission['id'] = $filename;

                // gotta set the items to not checked...kinda weird it's not a default but whatever
                // if this isn't set the checkboxes don't work
                $submission['checked'] = false;

                return $submission;
            })
            ->values() // this resets the array indexes to 0, see comment above
            ->all();

        return [
            'columns' => $this->akismet->getFields($formset),
            'items' => $spam
        ];

    }

    public function getForms()
    {
        return $this->akismet->getForms();
    }
}
