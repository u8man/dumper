<?php

use Manuylenko\Dumper\Dumper;

if (! function_exists('dump')) {
    /**
     * Выводит дамп данных
     * @return void
     */
    function dump() {
        if (func_num_args() > 0) {
            $dumper = new Dumper(true);

            foreach (func_get_args() as $value) {
                $dumper->dump($value);
            }
        }
    }
}

if (! function_exists('dump_ex')) {
    /**
     * Выводит дамп данных и завершает выполнение скрипта
     * @return void
     */
    function dump_ex() {
        call_user_func_array('dump', func_get_args());
        exit;
    }
}

