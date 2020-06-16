<?php

namespace Jackiedo\EloquentTranslatable\Events;

use App\Events\Event;
use Illuminate\Database\Eloquent\Model;

/**
 * The TranslationHasBeenSet class.
 *
 * @package Jackiedo\EloquentTranslatable
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class TranslationHasBeenSet extends Event
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
     * Store old value.
     *
     * @var mixed
     */
    public $oldValue;

    /**
     * Store new value.
     *
     * @var mixed
     */
    public $newValue;

    /**
     * Create a new event instance.
     *
     * @param string $key
     * @param string $locale
     * @param mixed  $oldValue
     * @param mixed  $newValue
     *
     * @return void
     */
    public function __construct(Model $model, $key, $locale, $oldValue, $newValue)
    {
        $this->model    = $model;
        $this->key      = $key;
        $this->locale   = $locale;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
    }
}
