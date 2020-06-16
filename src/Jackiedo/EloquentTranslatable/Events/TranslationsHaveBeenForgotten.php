<?php

namespace Jackiedo\EloquentTranslatable\Events;

use App\Events\Event;
use Illuminate\Database\Eloquent\Model;

/**
 * The TranslationsHaveBeenForgotten class.
 *
 * @package Jackiedo\EloquentTranslatable
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class TranslationsHaveBeenForgotten extends Event
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
     * @var array
     */
    public $locales;

    /**
     * Create a new event instance.
     *
     * @param string $key
     * @param array  $locales
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
