<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Form;
use Statamic\API\Path;
use Statamic\API\Folder;
use Statamic\API\Helper;
use Illuminate\Http\Request;
use Statamic\Extend\Controller;
use Statamic\Exceptions\UnauthorizedHttpException;

class AkismetController extends Controller
{
    use Akismet;

    /**
     * Maps to your route definition in routes.yaml
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // get the first form if none chosen
        $form = $this->getForms()->first();

        return redirect()->route('queue', 'form=' . $form['value']);
    }

    /**
     * Show the spam queue
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\View\View
     */
    public function queue(Request $request)
    {
        $this->authorized();

        $form = Form::get($request->input('form'));

        return $this->view(
            'queue',
            [
                'title' => 'Spam Queue - ' . $form->title(),
                'formset' => $form->name(),
            ]
        );
    }

    /**
     * Discard spam
     *
     * @param \Illuminate\Http\Request $request
     */
    public function discardSpam(Request $request)
    {
        $formset = $request->input('formset');

        // the ids will be in the request
        collect(Helper::ensureArray(request('ids', [])))->each(function ($id, $ignored) use ($formset) {
            $this->removeFromQueue($formset, $id);
        });
    }

    /**
     * Approve ham
     *
     * @param \Illuminate\Http\Request $request
     */
    public function approveHam(Request $request)
    {
        $formset = $request->input('formset');

        collect(Helper::ensureArray(request('ids', [])))->each(function ($id, $ignored) use ($formset) {
            /** @var \Statamic\Forms\Submission $submission */
            $submission = $this->storage->getSerialized(Path::assemble($formset, $id));

            // @todo should validation happen?
            $submission->unguard();

            $submission->save();

            // remove it from queue
            $this->removeFromQueue($formset, $id);

            // submit to Akismet as ham
            $this->submitHam($submission->data());
        });
    }

    /**
     * Submit spam
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getSubmitSpam(Request $request)
    {
        /** @var \Statamic\Forms\Submission $submission */
        $submission = Form::get($request->input('form'))->submission(request('id'));

        // add it to spam queue
        $this->addToQueue($submission);

        // send to Akismet
        $this->submitSpam($submission->data(), $this->getConfig('testing', false));

        //delete it
        $submission->delete();

        // go back so it refreshes the list of submissions
        return back();
    }

    /**
     * Get all the spam
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getSpam(Request $request)
    {
        $formset = $request->input('form');

        // @todo replace when https://github.com/statamic/v2-hub/issues/629 is fixed
        // `getFilesByType` retains the array keys so it can return an array that starts at
        // '1' which mucks up the front-end because it gets converted to an Object instead
        // of an array. Which is why we need to use `values`

        $path = Path::assemble('addons', $this->getAddonName(), $formset);
        $spam = collect(Folder::disk('storage')->getFilesByType($path, 'php'))
            ->map(function ($file) use ($formset) {
                $filename = pathinfo($file)['filename'];

                // init the array w/ empty strings just in case the submission is a bit funky
                $submission = array_merge(
                    array_fill_keys(
                        $this->fields($formset),
                        ''
                    ),
                    array_filter(
                        $this->storage
                            ->getSerialized(Path::assemble($formset, $filename))
                            ->toArray()
                    )
                );

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
            'columns' => $this->fields($formset),
            'items' => $spam,
        ];
    }

    /**
     * Get the form fields as an array
     *
     * @param $formsetName string which form are we interested in?
     *
     * @return array
     */
    private function fields($formsetName)
    {
        $config = $this->formConfig($formsetName);

        return [
            array_get($config, 'author_field', 'author'),
            array_get($config, 'email_field', 'email'),
            array_get($config, 'content_field', 'content'),
        ];
    }

    private function authorized()
    {
        if (!$this->canAccessQueue()) {
            throw new UnauthorizedHttpException(403, 'This action is unauthorized.');
        }
    }
}
