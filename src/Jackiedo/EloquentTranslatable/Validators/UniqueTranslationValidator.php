<?php

namespace Jackiedo\EloquentTranslatable\Validators;

use Illuminate\Support\Facades\DB;

/**
 * The UniqueTranslationValidator class.
 *
 * @package Jackiedo\EloquentTranslatable
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class UniqueTranslationValidator
{
    /**
     * Store rule name.
     *
     * @var string
     */
    protected $rule = 'unique_translation';

    /**
     * Check if the translated value is unique in the database.
     *
     * @param string                           $attribute
     * @param string                           $value
     * @param array                            $parameters
     * @param \Illuminate\Validation\Validator $validator
     *
     * @return bool
     */
    public function validate($attribute, $value, $parameters, $validator)
    {
        $attributeParts = explode('.', $attribute);
        $name           = $attributeParts[0];
        $locale         = isset($attributeParts[1]) ? $attributeParts[1] : app()->getLocale();
        $table          = isset($parameters[0]) ? $parameters[0] : null;
        $column         = $this->filterNullValues(isset($parameters[1]) ? $parameters[1] : null) ?: $name;
        $ignoreValue    = $this->filterNullValues(isset($parameters[2]) ? $parameters[2] : null);
        $ignoreColumn   = $this->filterNullValues(isset($parameters[3]) ? $parameters[3] : null);
        $extraWhere     = $this->getUniqueExtra($parameters);

        $isUnique = $this->isUnique($value, $locale, $table, $column, $ignoreValue, $ignoreColumn, $extraWhere);

        if (!$isUnique) {
            $this->addErrorsToValidator($validator, $parameters, $name, $locale);
        }

        return $isUnique;
    }

    /**
     * Get the extra conditions for a unique rule.
     *
     * @return array
     */
    protected function getUniqueExtra(array $parameters = [])
    {
        if (isset($parameters[4])) {
            return $this->getExtraConditions(array_slice($parameters, 4));
        }

        return [];
    }

    /**
     * Get the extra conditions for a unique / exists rule.
     *
     * @return array
     */
    protected function getExtraConditions(array $segments = [])
    {
        $extra = [];

        $count = count($segments);

        for ($i = 0; $i < $count; $i += 2) {
            $extra[$segments[$i]] = $segments[$i + 1];
        }

        return $extra;
    }

    /**
     * Filter NULL values.
     *
     * @param string|null $value
     *
     * @return string|null
     */
    protected function filterNullValues($value)
    {
        $nullValues = ['null', 'NULL'];

        if (in_array($value, $nullValues)) {
            return null;
        }

        return $value;
    }

    /**
     * Check if a translation is unique.
     *
     * @param mixed       $value
     * @param string      $locale
     * @param string      $table
     * @param string      $column
     * @param mixed       $ignoreValue
     * @param string/null $ignoreColumn
     *
     * @return bool
     */
    protected function isUnique($value, $locale, $table, $column, $ignoreValue = null, $ignoreColumn = null, array $extraWhere = [])
    {
        $query = DB::table($table);
        $query = $this->queryIgnore($query, $ignoreColumn, $ignoreValue);
        $query = $this->queryExtraWhere($query, $extraWhere);

        $filtered = $query->get()->filter(function ($item, $key) use ($locale, $value, $column) {
            $valueToArray = json_decode($item->{$column}, true);

            if (JSON_ERROR_NONE == json_last_error() && is_array($valueToArray)) {
                if (array_key_exists($locale, $valueToArray) && $valueToArray[$locale] == $value) {
                    return true;
                }
            }

            return false;
        });

        $isUnique = 0 === $filtered->count();

        return $isUnique;
    }

    /**
     * Build query for ignore the column with the given value.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string|null                        $column
     * @param mixed                              $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function queryIgnore($query, $column = null, $value = null)
    {
        if (null !== $value && null === $column) {
            $column = 'id';
        }

        if (null !== $column) {
            $query = $query->where($column, '!=', $value);
        }

        return $query;
    }

    /**
     * Build query for extra where clauses.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function queryExtraWhere($query, array $conditions = [])
    {
        foreach ($conditions as $column => $value) {
            $query = $query->where($column, '=', $value);
        }

        return $query;
    }

    /**
     * Add error messages to the validator.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param array                            $parameters
     * @param string                           $name
     * @param string                           $locale
     *
     * @return void
     */
    protected function addErrorsToValidator($validator, $parameters, $name, $locale)
    {
        $message = trans('validation.unique');

        $validator->setCustomMessages([
            $this->rule => $message,
        ]);
    }
}
