<?php

namespace Jackiedo\EloquentTranslatable\Traits;

use Illuminate\Support\Str;
use Jackiedo\EloquentTranslatable\Events\TranslationHasBeenForgotten;
use Jackiedo\EloquentTranslatable\Events\TranslationHasBeenSet;
use Jackiedo\EloquentTranslatable\Events\TranslationsHaveBeenForgotten;
use Jackiedo\EloquentTranslatable\Exceptions\AttributeIsNotTranslatable;

/**
 * The Translatable trait.
 *
 * @package Jackiedo\EloquentTranslatable
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
trait Translatable
{
    protected $translableSuffix = '_translation';

    /**
     * Hijack parent's getAttributeValue to get the translation of the given attribute instead of its value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        if (!$this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        // pass arrays and untranslatable attributes to the parent method
        if (!$this->isTranslatableAttribute($key)) {
            return parent::setAttribute($key, $value);
        }

        // if the attribute is translatable and value is array,
        // we loop all value and set a translation for each locale in value
        if (is_array($value)) {
            foreach ($value as $locale => $translation) {
                $this->setTranslation($key, $locale, $translation);
            }

            return $this;
        }

        // if the attribute is translatable and not is array,
        // set a translation for the current app locale
        return $this->setTranslation($key, $this->getLocale(), $value);
    }

    /**
     * Alias of getTranslation method.
     *
     * @param string      $key
     * @param string|null $locale
     * @param mixed       $useFallbackValue
     * @param bool        $useRawValue
     *
     * @return mixed
     */
    public function translate($key, $locale = null, $useFallbackValue = true, $useRawValue = false)
    {
        return $this->getTranslation($key, $locale, $useFallbackValue, $useRawValue);
    }

    /**
     * Get translation for a given attribute.
     *
     * @param string      $key
     * @param string|null $locale
     * @param mixed       $useFallbackValue
     * @param bool        $useRawValue
     *
     * @return mixed
     */
    public function getTranslation($key, $locale = null, $useFallbackValue = true, $useRawValue = false)
    {
        $locale       = (isset($locale) && is_string($locale)) ? $locale : $this->getLocale();
        $translations = $this->getTranslations($key, $useRawValue);

        if (array_key_exists($locale, $translations)) {
            return $translations[$locale];
        }

        switch (true) {
            case is_bool($useFallbackValue) && $useFallbackValue:
                $value = $this->getFallbackTranslation($key, $locale, $translations);
                break;

            case is_callable($useFallbackValue):
                $value = call_user_func($useFallbackValue, $this, $locale, $translations);
                break;

            case is_string($useFallbackValue):
                $value = $useFallbackValue;
                break;

            default:
                $value = null;
                break;
        }

        return $value;
    }

    /**
     * Get all translations for given attribute or for all translatable attributes.
     *
     * @param string|null $key
     * @param bool        $useRawValue
     *
     * @return array
     */
    public function getTranslations($key = null, $useRawValue = false)
    {
        // Get all translations for given key in model
        if (null !== $key) {
            $this->guardAgainstUntranslatableAttribute($key);

            $rawTranslations = json_decode((isset($this->attributes[$key]) ? $this->attributes[$key] : '') ?: '{}', true) ?: [];

            if ($useRawValue) {
                return $rawTranslations;
            }

            if ($this->hasGetTranslationModifier($key)) {
                $translations = [];

                foreach ($rawTranslations as $locale => $translation) {
                    $translations[$locale] = $this->mutateGetTranslation($key, $translation, $locale);
                }

                return $translations;
            }

            if ($this->hasCast($key . $this->translableSuffix)) {
                $translations = [];

                foreach ($rawTranslations as $locale => $translation) {
                    $translations[$locale] = $this->castAttribute($key . $this->translableSuffix, $translation);
                }

                return $translations;
            }

            if (in_array($key . $this->translableSuffix, $this->getDates())) {
                $translations = [];

                foreach ($rawTranslations as $locale => $translation) {
                    if (!is_null($translation)) {
                        $translations[$locale] = $this->asDateTime($translation);
                    }
                }

                return $translations;
            }

            return $rawTranslations;
        }

        // Get all translations for all translatable keys of model
        return array_reduce($this->getTranslatableAttributes(), function ($carry, $item) use ($useRawValue) {
            $carry[$item] = $this->getTranslations($item, $useRawValue);

            return $carry;
        }, []);
    }

    /**
     * Set translation for a given translatable attribute by a locale.
     *
     * @param string $key
     * @param string $locale
     * @param mixed  $value
     *
     * @return $this
     */
    public function setTranslation($key, $locale, $value)
    {
        $this->guardAgainstUntranslatableAttribute($key);

        $translations = $this->getTranslations($key, true);
        $oldValue     = isset($translations[$locale]) ? $translations[$locale] : '';

        if ($this->hasSetTranslationModifier($key)) {
            $value = $this->mutateSetTranslation($key, $value, $locale);
        } elseif ($value && (in_array($key . $this->translableSuffix, $this->getDates()) || $this->isDateCastable($key . $this->translableSuffix))) {
            $value = $this->fromDateTime($value);
        } elseif ($this->isJsonCastable($key . $this->translableSuffix) && !is_null($value)) {
            $value = $this->castAttributeAsJson($key . $this->translableSuffix, $value);
        }

        $translations[$locale]  = $value;
        $this->attributes[$key] = $this->asJson($translations);

        event(new TranslationHasBeenSet($this, $key, $locale, $oldValue, $value));

        return $this;
    }

    /**
     * Set multiple translations for a given translatable attribute.
     *
     * @param string $key
     *
     * @return $this
     */
    public function setTranslations($key, array $translations)
    {
        $this->guardAgainstUntranslatableAttribute($key);

        foreach ($translations as $locale => $translation) {
            $this->setTranslation($key, $locale, $translation);
        }

        return $this;
    }

    /**
     * Remove a given translation for attribute.
     *
     * @param string $key
     * @param string $locale
     *
     * @return $this
     */
    public function forgetTranslation($key, $locale)
    {
        $translations = $this->getTranslations($key, true);

        unset($translations[$locale]);

        $this->attributes[$key] = empty($translations) ? null : $this->asJson($translations);

        event(new TranslationHasBeenForgotten($this, $key, $locale));

        return $this;
    }

    /**
     * Remove some translations for attribute.
     *
     * @param string $key
     *
     * @return $this
     */
    public function forgetTranslations($key, array $locales = [])
    {
        $translations = $this->getTranslations($key, true);

        if (empty($locales)) {
            $this->attributes[$key] = null;

            event(new TranslationsHaveBeenForgotten($this, $key, array_keys($translations)));

            return $this;
        }

        foreach ($locales as $locale) {
            unset($translations[$locale]);
        }

        $this->attributes[$key] = empty($translations) ? null : $this->asJson($translations);

        event(new TranslationsHaveBeenForgotten($this, $key, $locales));

        return $this;
    }

    public function forgetAllTranslations($locale)
    {
        collect($this->getTranslatableAttributes())->each(function ($attribute) use ($locale) {
            $this->forgetTranslation($attribute, $locale);
        });

        return $this;
    }

    public function mayHaveBeenTranslated($key)
    {
        return $this->isTranslatableAttribute($key) && $this->isJsonAttribute($key);
    }

    public function isTranslatableAttribute($key)
    {
        return in_array($key, $this->getTranslatableAttributes());
    }

    public function isJsonAttribute($key)
    {
        $result = @json_decode($this->getOriginal($key));

        return JSON_ERROR_NONE == json_last_error() && is_array($result);
    }

    public function getTranslatedLocales($key)
    {
        return array_keys($this->getTranslations($key, true));
    }

    public function hasTranslation($key, $locale)
    {
        return array_key_exists($locale, $this->getTranslations($key, true));
    }

    public function getTranslatableAttributes()
    {
        return is_array($this->translatable) ? $this->translatable : [];
    }

    public function getCasts()
    {
        return array_merge(
            parent::getCasts(),
            array_fill_keys($this->getTranslatableAttributes(), 'array')
        );
    }

    /**
     *  Extend parent's attributesToArray so that translatable attributes are translated.
     *
     *  @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        $translatable = array_diff(
            $this->getTranslatableAttributes(),
            $this->jsonResponsesUntranslatable()
        );

        foreach ($translatable as $key) {
            $attributes[$key] = $this->getTranslation($key);
        }

        return $attributes;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasSetTranslationModifier($key)
    {
        return method_exists($this, 'set' . Str::studly($key) . 'TranslationModifier');
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasGetTranslationModifier($key)
    {
        return method_exists($this, 'get' . Str::studly($key) . 'TranslationModifier');
    }

    protected function guardAgainstUntranslatableAttribute($key)
    {
        if (!$this->isTranslatableAttribute($key)) {
            throw AttributeIsNotTranslatable::make($key, $this);
        }
    }

    protected function getLocale()
    {
        return config('app.locale');
    }

    protected function getFallbackTranslation($key, $locale, array $translations)
    {
        if (method_exists($this, $fallbackMethod = 'get' . Str::studly($key) . 'FallbackTranslation')) {
            return $this->{$fallbackMethod}($locale, $translations);
        }

        if (!is_null($fallbackLocale = config('eloquent-translatable.fallback_locale'))) {
            if (array_key_exists($fallbackLocale, $translations)) {
                return $translations[$fallbackLocale];
            }
        }

        return config('eloquent-translatable.fallback_value', null);
    }

    protected function jsonResponsesUntranslatable()
    {
        return [];
    }

    /**
     * Get the translation of an attribute using its mutator.
     *
     * @param string $key
     * @param mixed  $value
     * @param string $locale
     *
     * @return mixed
     */
    protected function mutateSetTranslation($key, $value, $locale)
    {
        return $this->{'set' . Str::studly($key) . 'TranslationModifier'}($value, $locale);
    }

    /**
     * Get the translation of an attribute using its mutator.
     *
     * @param string $key
     * @param mixed  $value
     * @param string $locale
     *
     * @return mixed
     */
    protected function mutateGetTranslation($key, $value, $locale)
    {
        return $this->{'get' . Str::studly($key) . 'TranslationModifier'}($value, $locale);
    }
}
