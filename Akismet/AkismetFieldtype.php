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
        return [
            'form' => null,
            'author_field' => null,
            'email_field' => null,
            'content_field' => null
        ];
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
        $data['author_field'] = isset($data['author_field']) ? [$data['author_field']] : '';
        $data['email_field'] = isset($data['email_field']) ? [$data['email_field']] : '';
        $data['content_field'] = isset($data['content_field']) ? [$data['content_field']] : '';

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
        $data['author_field'] = isset($data['author_field']) ? reset($data['author_field']) : '';
        $data['email_field'] = isset($data['email_field']) ? reset($data['email_field']) : '';
        $data['content_field'] = isset($data['content_field']) ? reset($data['content_field']) : '';

        return $data;
    }
}
