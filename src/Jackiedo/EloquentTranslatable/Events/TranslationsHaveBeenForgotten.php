<?php namespace Jackiedo\EloquentTranslatable\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * The TranslationsHaveBeenForgotten class.
 *
 * @package Jackiedo\EloquentTranslatable
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class TranslationsHaveBeenForgotten
{
    /**
     * Store model
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * Store attribute
     *
     * @var string
     */
    public $key;

    /**
     * Store locale
     *
     * @var array
     */
    public $locales;

    /**
     * Create a new event instance.
     *
     * @param  Model   $model
     * @param  string  $key
     * @param  array   $locales
     *
     * @return void
     */
    public function __construct(Model $model, $key, $locales)
    {
        $this->model   = $model;
        $this->key     = $key;
        $this->locales = $locales;
    }
}
