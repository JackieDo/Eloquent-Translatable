<?php

namespace Jackiedo\EloquentTranslatable\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jackiedo\EloquentTranslatable\Traits\Translatable;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * The CheckRepairCommand class.
 *
 * @package Jackiedo\EloquentTranslatable
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class CheckRepairCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translatable:check-repair
                            {class_name : The eloquent translatable class name that you want to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the preparation for storing the translations and correcting database values if needed';

    /**
     * Store the eloquent translatable class name.
     *
     * @var string
     */
    protected $class_name;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Alias of the handle() method.
     *
     * @return mixed
     */
    public function fire()
    {
        $this->handle();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->setFormatStyles();

        $className = $this->argument('class_name');

        if (!class_exists($className)) {
            $this->info(PHP_EOL . 'Class "' . $className . '" not found');

            return;
        }

        $this->class_name = $className;

        $step = 0;

        $this->line(PHP_EOL . '[' . ++$step . ']. Checking for using required traits...');
        if (!$this->isUsedTranslatableTrait()) {
            return;
        }

        $this->line(PHP_EOL . '[' . ++$step . ']. Checking for declaring translatables attributes...');
        if (!$this->hasTranslatableAttributes()) {
            return;
        }

        $this->line(PHP_EOL . '[' . ++$step . ']. Checking the compatibility of columns in the database...');
        if (!$this->isCompatibledDataType()) {
            return;
        }

        $this->line(PHP_EOL . '[' . ++$step . ']. Checking the compatibility of value...');
        if (!$this->isCompatibledDataValue()) {
            $this->line(PHP_EOL . '<label> FINAL RESULT: </label>');
            $this->line('<success> OK. Now, your model is a translatable, but some values in database have yet to determine the locale. </success>');

            return;
        }

        $this->line(PHP_EOL . '<label> FINAL RESULT: </label>');
        $this->line('<success> Congratulations!. Everything is good. Now, your model is a translatable. </success>');
    }

    /**
     * Initialize some styles for the formatter.
     *
     * @return void
     */
    protected function setFormatStyles()
    {
        $labelStyle = new OutputFormatterStyle('black', 'white');
        $this->output->getFormatter()->setStyle('label', $labelStyle);

        $warningStyle = new OutputFormatterStyle('black', 'yellow');
        $this->output->getFormatter()->setStyle('warning', $warningStyle);

        $successStyle = new OutputFormatterStyle('black', 'green');
        $this->output->getFormatter()->setStyle('success', $successStyle);
    }

    /**
     * Check if eloquent model used Translatable trait.
     *
     * @return bool
     */
    protected function isUsedTranslatableTrait()
    {
        $usedTraits = class_uses_recursive($this->class_name);

        if (array_key_exists(Translatable::class, $usedTraits)) {
            $this->info('Good. Your model used required trait.');

            return true;
        }

        $this->error(' Failed. Your model is not yet using required trait. ');

        return false;
    }

    /**
     * Check if eloquent model has translatable attributes.
     *
     * @return bool
     */
    protected function hasTranslatableAttributes()
    {
        $model = new $this->class_name;

        if (!empty($model->getTranslatableAttributes())) {
            $this->info('Good. Your model has declared translatables attributes.');

            return true;
        }

        $this->error(' Not good. Your model has not yet declared translatables attributes. ');

        return false;
    }

    /**
     * Check if translatable columns in table is compatibled data type.
     *
     * @return bool
     */
    protected function isCompatibledDataType()
    {
        $model = new $this->class_name;
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            $this->error(' Sorry! Table "' . $table . '" of your model doesn\'t exists. Processing stop here. ');

            return false;
        }

        $column_info = DB::select(DB::raw('SHOW COLUMNS FROM ' . $table));
        $isSuccess   = true;

        $type_info = array_reduce($column_info, function ($carry, $column) use ($model, &$isSuccess) {
            $name = $column->Field;
            $type = preg_replace('/^(\w+).*$/', '${1}', $column->Type);

            if ($model->isTranslatableAttribute($name)) {
                $carry[$name] = [
                    'column'   => $name,
                    'type'     => $type,
                ];

                if ('text' == $type || 'longtext' == $type) {
                    $carry[$name]['result'] = 'true';
                } else {
                    $carry[$name]['result'] = '<error> false </error>';
                    $isSuccess = $isSuccess && false;
                }
            }

            return $carry;
        }, []);

        $this->table(['Column name', 'Column type', 'Compatible ?'], $type_info);

        if ($isSuccess) {
            $this->info(PHP_EOL . 'Good. The required columns in your database already have compatible data types.');
        } else {
            $this->error(PHP_EOL . ' Not good. Some your database columns have not yet compatible type to store translations. ');

            $this->comment(PHP_EOL . 'The compatible data type to store translations is "text" or "longtext".');
            $this->comment('Please change the incompatible column type in your database to compatibility type.');
        }

        return $isSuccess;
    }

    /**
     * Check if values of translatable columns in table is compatibled.
     *
     * @return bool
     */
    protected function isCompatibledDataValue()
    {
        $model        = new $this->class_name;
        $keyName      = $model->getKeyName();
        $table        = $model->getTable();
        $verifyFields = $model->getTranslatableAttributes();
        $records      = DB::table($table)->get();
        $needRepair   = [];

        if ($records->isEmpty()) {
            $this->info('Passed. Your table have not any record now.');

            return true;
        }

        foreach ($records as $record) {
            foreach ($verifyFields as $field) {
                $value = json_decode($record->{$field}, true);

                if (JSON_ERROR_NONE !== json_last_error() && !is_array($value)) {
                    $needRepair[$record->{$keyName}][$field] = json_encode([
                        app()->getLocale() => $record->{$field},
                    ], JSON_UNESCAPED_UNICODE);
                }
            }
        }

        $fieldsConcat = 'the "' . implode('", "', $verifyFields) . '" ' . (count($verifyFields) >= 2 ? 'fields' : 'field');

        if (!empty($needRepair)) {
            $this->line('<warning> Not good. Perhaps a few values in ' . $fieldsConcat . ' have yet to determine the locale. </warning>');

            $this->comment(PHP_EOL . 'Don\'t worry, this does not affect the performance of your Model.');
            $this->comment('However you should solve this issue right now. That makes your model more perfect.');

            if ($this->confirm('So! Do you want to assign these values to your locale (current is "' . app()->getLocale() . '")', true)) {
                foreach ($needRepair as $id => $updates) {
                    DB::table($table)->where($keyName, $id)->update($updates);
                }

                return true;
            }

            return false;
        }

        $this->info('Good. All your values in ' . $fieldsConcat . ' may have been translated.');

        return true;
    }
}
