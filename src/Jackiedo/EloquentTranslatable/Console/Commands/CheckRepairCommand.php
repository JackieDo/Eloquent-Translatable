<?php namespace Jackiedo\EloquentTranslatable\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jackiedo\EloquentTranslatable\Traits\Translatable;

/**
 * The CheckRepairCommand class.
 *
 * @package Jackiedo\EloquentTranslatable
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
     * Store the eloquent translatable class name
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
     * Alias of the handle() method
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
        $className = $this->argument('class_name');

        if (! class_exists($className)) {
            $this->info(PHP_EOL.'Class "' . $className . '" not found');

            return;
        }

        $this->class_name = $className;

        $step = 0;

        $this->line(PHP_EOL.'['.++$step.']. Checking for using required traits...');
        if (! $this->isUsedTranslatableTrait()) {
            return;
        }

        $this->line(PHP_EOL.'['.++$step.']. Checking for declaring translatables attributes...');
        if (! $this->hasTranslatableAttributes()) {
            return;
        }

        $this->line(PHP_EOL.'['.++$step.']. Checking the compatibility of data types...');
        if (! $this->isCompatibledDataType()) {
            return;
        }

        $this->line(PHP_EOL.'['.++$step.']. Checking the compatibility of value...');
        if (! $this->isCompatibledDataValue()) {
            $this->info(PHP_EOL.'FINAL RESULT >>> OK. Now, your model is a translatable, but some values in database ​​have yet to determine the locale.');

            return;
        }

        $this->info(PHP_EOL.'FINAL RESULT >>> Congratulations!. Everything is good. Now, your model is a translatable.');
    }

    /**
     * Check if eloquent model used Translatable trait
     *
     * @return boolean
     */
    protected function isUsedTranslatableTrait()
    {
        $usedTraits = class_uses_recursive($this->class_name);

        if (array_key_exists(Translatable::class, $usedTraits)) {
            $this->info(PHP_EOL.'Good. Your model used required trait.');

            return true;
        }

        $this->info(PHP_EOL.'Failed. Your model is not yet using required trait.');

        return false;
    }

    /**
     * Check if eloquent model has translatable attributes
     *
     * @return boolean
     */
    protected function hasTranslatableAttributes()
    {
        $model = new $this->class_name;

        if (! empty($model->getTranslatableAttributes())) {
            $this->info(PHP_EOL.'Good. Your model has declared translatables attributes.');

            return true;
        }

        $this->info(PHP_EOL.'Not good. Your model has not yet declared translatables attributes.');

        return false;
    }

    /**
     * Check if translatable columns in table is compatibled data type
     *
     * @return boolean
     */
    protected function isCompatibledDataType()
    {
        $model = new $this->class_name;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            $this->info(PHP_EOL.'Sorry! Table "' .$table. '" of your model doesn\'t exists. Processing stop here');

            return false;
        }

        $column_info = DB::select(DB::raw('SHOW COLUMNS FROM '.$table));
        $isSuccess   = true;

        $type_info = array_reduce($column_info, function($carry, $column) use ($model, &$isSuccess) {
            $name = $column->Field;
            $type = preg_replace('/^(\w+).*$/', '${1}', $column->Type);

            if ($model->isTranslatableAttribute($name)) {
                $carry[$name] = [
                    'column'   => $name,
                    'type'     => $type,
                    'shoud_be' => 'text | longtext',
                ];

                if ($type == 'text' || $type == 'longtext') {
                    $carry[$name]['result'] = 'true';
                } else {
                    $carry[$name]['result'] = 'false';
                    $isSuccess = $isSuccess && false;
                }
            }

            return $carry;
        }, []);

        $this->table(['Column', 'Type', 'Shoud be', 'Matched'], $type_info);

        if ($isSuccess) {
            $this->info(PHP_EOL.'Good. The attributes in your model already have compatible data types.');
        } else {
            $this->info(PHP_EOL.'Not good. May be some your translatable attributes have not yet compatible type for storing translations.');
        }

        return $isSuccess;
    }

    /**
     * Check if values of translatable columns in table is compatibled
     *
     * @return boolean
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
            $this->info(PHP_EOL.'Passed. Your table have not any record now.');

            return true;
        }

        foreach ($records as $record) {
            foreach ($verifyFields as $field) {
                $value = json_decode($record->{$field}, true);

                if (json_last_error() !== JSON_ERROR_NONE && !is_array($value)) {
                    $needRepair[$record->{$keyName}][$field] = json_encode([
                        app()->getLocale() => $record->{$field}
                    ], JSON_UNESCAPED_UNICODE);
                }
            }
        }

        if (! empty($needRepair)) {
            $this->info(PHP_EOL.'Not good. Perhaps a few values in fields ["'.implode('", "', $verifyFields).'"] ​​have yet to determine the locale.');

            if ($this->confirm('So! Do you want to assign these values to default locale ('.app()->getLocale().')', true)) {
                foreach ($needRepair as $id => $updates) {
                    DB::table($table)->where($keyName, $id)->update($updates);
                }

                return true;
            }

            return false;
        }

        $this->info(PHP_EOL.'Good. All your values in fields ["'.implode('", "', $verifyFields).'"] may have been translated.');

        return true;
    }
}
