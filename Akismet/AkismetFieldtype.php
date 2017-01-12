<?php

namespace Statamic\Addons\Akismet;

use Statamic\Extend\Fieldtype;

class AkismetFieldtype extends Fieldtype
{
    /**
     * The blank/default value
     *
     * @return array
     */
    public function blank()
    {
        return null;
    }

    /**
     * Pre-process the data before it gets sent to the publish page
     *
     * @param mixed $data
     * @return array|mixed
     */
    public function preProcess($data)
    {
        // Only have one of each field so it's stored as a simple string value.
        // However, the selectize field needs an array to convert to array
        $data['form'] = isset($data['form']) ? [$data['form']] : '';
        $data['author'] = isset($data['author']) ? [$data['author']] : '';
        $data['email'] = isset($data['email']) ? [$data['email']] : '';
        $data['content'] = isset($data['content']) ? [$data['content']] : '';

        return $data;
    }

    /**
     * Process the data before it gets saved
     *
     * @param mixed $data
     * @return array|mixed
     */
    public function process($data)
    {
        // As the data comes from a selectize field, it's in an array.
        // We only have one of everything so get rid of all the arrays
        $data['form'] = isset($data['form']) ? reset($data['form']): '';
        $data['author'] = isset($data['author']) ? reset($data['author']) : '';
        $data['email'] = isset($data['email']) ? reset($data['email']) : '';
        $data['content'] = isset($data['content']) ? reset($data['content']) : '';

        return $data;
    }
}
