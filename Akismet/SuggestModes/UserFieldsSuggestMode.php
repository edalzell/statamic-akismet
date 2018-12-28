<?php

namespace Statamic\Addons\Akismet\SuggestModes;

use Statamic\API\Str;
use Statamic\API\Fieldset;
use Statamic\Addons\Suggest\Modes\AbstractMode;

class UserFieldsSuggestMode extends AbstractMode
{
    public function suggestions()
    {
        return collect(Fieldset::get('user')->fields())->map(function ($field, $key) {
            return [
                'value' => $key,
                'text' => array_get($field, 'display', Str::title($key)),
            ];
        })->values()->all();
    }
}
