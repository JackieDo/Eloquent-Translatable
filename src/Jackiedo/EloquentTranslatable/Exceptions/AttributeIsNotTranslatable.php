<?php namespace Jackiedo\EloquentTranslatable\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * The AttributeIsNotTranslatable class.
 *
 * @package Jackiedo\EloquentTranslatable
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class AttributeIsNotTranslatable extends Exception
{
    /**
     * Make an exception
     *
     * @param  string  $key
     * @param  Model   $model
     *
     * @return Exception
     */
    public static function make($key, Model $model)
    {
        $translatable = implode(', ', $model->getTranslatableAttributes());

        return new static("Cannot translate attribute `{$key}` as it's not one of the translatable attributes: `$translatable`");
    }
}
