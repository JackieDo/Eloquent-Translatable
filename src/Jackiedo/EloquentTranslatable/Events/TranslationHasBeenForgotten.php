<?php

namespace Jackiedo\EloquentTranslatable\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * The TranslationHasBeenForgotten class.
 *
 * @package Jackiedo\EloquentTranslatable
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class TranslationHasBeenForgotten
{
    /**
     * Store model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * Store attribute.
     *
     * @var string
     */
    public $key;

    /**
     * Store locale.
     *
     * @var string
     */
    public $locale;

    /**
     * Create a new event instance.
     *
     * @param string $key
     * @param string $locale
     *
     * @return void
     */
    public function __construct(Model $model, $key, $locale)
    {
        $this->model  = $model;
        $this->key    = $key;
        $this->locale = $locale;
    }
}
