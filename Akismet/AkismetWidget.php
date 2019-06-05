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
        $spam = [];
        $forms = $this->getConfig('forms');

        collect(Form::getAllFormsets())
            ->filter(function ($form) use ($forms) {
                return collect($forms)->contains(function ($ignore, $value) use ($form) {
                    return $form['name'] == array_get($value, 'form_and_fields.form');
                });
            })->each(function ($form) use (&$spam) {
                $name = $form['name'];
                $path = Path::assemble('addons', $this->getAddonName(), $name);
                $count = count(Folder::disk('storage')->getFilesByType($path, 'php'));

                $spam[$name]['title'] = $form['title'];
                $spam[$name]['count'] = $count;
                $spam[$name]['route'] = route('queue', 'form=' . $name);
            });

        return $this->view('widget', compact('spam'))->render();
    }
}
