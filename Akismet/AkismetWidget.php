<?php

namespace Statamic\Addons\Akismet;

use Statamic\API\Form;
use Statamic\API\Path;
use Statamic\API\Folder;
use Statamic\Extend\Widget;

class AkismetWidget extends Widget
{
    /**
     * The HTML that should be shown in the widget
     *
     * @return string
     */
    public function html()
    {
        $spam = null;

        collect(Form::getAllFormsets())->each(function ($form) use (&$spam) {
            $formset = $form['name'];
            $path = Path::assemble('addons', $this->getAddonName(), $formset);
            $count = count(Folder::disk('storage')->getFilesByType($path, 'php'));

            $spam[$formset]['title'] = $form['title'];
            $spam[$formset]['count'] = $count;
            $spam[$formset]['route'] = route('queue', 'form=' . $formset);
        });

        return $this->view('widget', compact('spam'))->render();
    }
}
