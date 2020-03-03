<?php

/**
 * Yuriy Manuylenko
 */

namespace Manuylenko\Dumper;

use Closure;
use ReflectionFunction;
use ReflectionObject;

class Dumper
{
    /**
     * Кодировка по умолчанию для многобайтовых функций
	 *
     * @var string
     */
    private $charset = 'UTF-8';

    /**
     * Максимальная длинна отображаемых символов при выводе строки
     *  0 - Снимает ограничения
	 *
     * @var int
     */
    private $maxlength;

    /**
     * Указывает как отображать содержимое объектов и массивов
     * true - развернуто, false - свернуто
	 *
     * @var bool
     */
    private $deployed;


    /**
     * Конструктор
	 *
     * @param bool $deployed
     * @param int $maxlength
     */
    public function __construct($deployed = true, $maxlength = 100)
    {
        $this->deployed = $deployed;
        $this->maxlength = $maxlength;
    }

    /**
     * Выводит дамп данных
	 *
     * @param mixed $value
     * @return void
     */
    public function dump($value)
    {
        $head = '';
        static $loaded = false;

        if (! $loaded) {
            $head .= $this->loadResources();
            $loaded = true;
        }

        echo $head.'<div class="t_dump">'.$this->resolve($value).'</div>'.PHP_EOL;
    }

    /**
     * Оборачивает содержимое переменной в html теги
	 *
     * @param mixed $value
     * @param bool $wrap
	 *
     * @return string
     */
    protected function resolve($value, $wrap = true)
    {
        switch (strtolower(gettype($value))) {
            case 'null': $out = $this->dumpNull(); break;
            case 'boolean': $out = $this->dumpBoolean($value); break;
            case 'integer':
            case 'double': $out = $this->dumpNumber($value); break;
            case 'string': $out = $this->dumpString($value); break;
            case 'array': $out = $this->dumpArray($value); break;
            case 'object': $out = $this->dumpObject($value); break;
            case 'resource': $out = $this->dumpResource($value); break;
            default: $out = $this->dumpUnknown();
        }

        if ($wrap) {
            $out = '<div>'.$out.'</div>';
        }

        return $out;
    }

    /**
     * Ничего
	 *
     * @return string
     */
    protected function dumpNull()
    {
        return '<span class="td-n">null</span>';
    }

    /**
     * Булевое значение
	 *
     * @param bool $bool
	 *
     * @return string
     */
    protected function dumpBoolean($bool)
    {
        return '<span class="td-b">'.($bool ? 'true' : 'false').'</span>';
    }

    /**
     * Число
	 *
     * @param double|float|int $number
	 *
     * @return string
     */
    protected function dumpNumber($number)
    {
        return '<span class="td-di">'.(is_float($number) ? 'float' : 'int').': '.strval($number).'</span>';
    }

    /**
     * Строка
	 *
     * @param string $string
	 *
     * @return string
     */
    protected function dumpString($string)
    {
        $length = mb_strlen($string, $this->charset);
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $this->charset);

        if ($this->maxlength !== 0 && $length > $this->maxlength) {
            $croppedString = mb_substr($string, 0, $this->maxlength - 1, $this->charset);

            $idn = $this->getIdNesting();

            $out = '<span class="td-s" id="td-ibs'.$idn.'" data-string="'.$string.'">';
            $out .= '"'.$croppedString.'" <a onclick="td_showFullString('.$idn.')">...</a>';
            $out .= '</span>';
        }
        else {
            $out = '<span class="td-s">"'.$string.'"</span>';
        }

        if ($length > 0) {
            $out .= ' ('.strval($length).')';
        }

        return $out;
    }

    /**
     * Массив
	 *
     * @param array $array
	 *
     * @return string
     */
    protected function dumpArray(array $array)
    {
        $out = '';
        $count = count($array);

        static $list = [];

        if (in_array($array, $list)) {
            $out .= '<span class="td-a">Array</span> ('.strval($count).') [ <span class="td-rc">* RECURSION *</span> ]';
        }
        else {
            if ($count > 0) {
                $idn = $this->getIdNesting();

                $out .= '<span class="td-a">Array</span> ('.$count.')';
                $out .= ' [<a onclick="td_showOrHideBlock('.$idn.')" id="td-ibtn'.$idn.'" class="'.($this->deployed ? 'td-open' : 'td-close').'"></a>';
                $out .= '<div class="td-bx'.($this->deployed ? ' td-show' : '').'" id="td-ibx'.$idn.'">';

                array_push($list, $array);

                foreach ($array as $key => $value) {
                    $out .= '<div>[';
                    $out .= \is_int($key) ? '<span class="td-di">'.$key.'</span>' : '<span class="td-s">\''.$key.'\'</span>';
                    $out .= '] => '.$this->resolve($value, false).'</div>';
                }

                array_pop($list);

                $out .= '</div>';
                $out .= ']';
            }
            else {
                $out .= '<span class="td-a">Array</span> (0) []';
            }
        }

        return $out;
    }

    /**
     * Объект
	 *
     * @param object $object
	 *
     * @return string
     */
    protected function dumpObject($object)
    {
        $reflection = $object instanceof Closure ? new ReflectionFunction($object) : new ReflectionObject($object);

        static $list = [];

        $out = '<span class="td-o">'.get_class($object).'</span> <span class="td-h">#'.$this->getObjectId($object).'</span>';

        if (in_array($object, $list)) {
            $out .= ' { <span class="td-rc">* RECURSION *</span> }';
        }
        else {
            $idn = $this->getIdNesting();

            $out .= ' {<a onclick="td_showOrHideBlock('.$idn.')" id="td-ibtn'.$idn.'" class="'.($this->deployed ? 'td-open' : 'td-close').'"></a>';
            $out .= '<div class="td-bx'.($this->deployed ? ' td-show' : '').'" id="td-ibx'.$idn.'">';

            array_push($list, $object);

            switch (true) {
                case $reflection instanceof ReflectionObject:
                    $out .= $this->reflectionObject($reflection, $object);
                    break;
                case $reflection instanceof ReflectionFunction:
                    $out .= $this->reflectionFunction($reflection);
                    break;
            }

            array_pop($list);

            $out .= '</div>';
            $out .= '}';
        }

        return $out;
    }

    /**
     * Разбирает объекты
	 *
     * @param ReflectionObject $reflection
     * @param object $object
	 *
     * @return string
     */
    protected function reflectionObject($reflection, $object)
    {
        $out = '';

        foreach ($reflection->getProperties() as $property) {
            $modifier = '';

            switch (true) {
                case ! $property->isDefault(): $modifier = '='; break; // Динамическое свойство
                case $property->isPublic(): $modifier = '+'; break; // Публичное свойство
                case $property->isPrivate(): $modifier = '-'; break; // Приватное свойство
                case $property->isProtected(): $modifier = '#'; break; // Защищенное свойство
            }

            // Модификатор доступа и статическое ли свойство
            $out .= '<div>'.($property->isStatic() ? '<span class="td-m">('.$modifier.')</span>' : '(<span class="td-m">'.$modifier.'</span>)');

            // Открываем доступ к свойству, для получения его значения
            $property->setAccessible(true);

            $out .= ' <span class="td-p">'.$property->getName().'</span>: '. $this->resolve($property->getValue($object), false);
            $out .= '</div>';
        }

        return $out;
    }

    /**
     * Разбирает функции
	 *
     * @param ReflectionFunction $reflection
	 *
     * @return string
     */
    protected function reflectionFunction($reflection)
    {
        // Имя файла
        $out = '<div><span class="td-p">file</span>: '.$reflection->getFileName().'</div>';

        // Номер строки
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();
        $lines = $startLine < $endLine ? '<span class="td-di">'.$startLine.'</span> - <span class="td-di">'.$endLine.'</span>' : $startLine;
        $out .= '<div><span class="td-p">line'.(strpos($lines, '-') ? 's' : '').'</span>: '.$lines.'</div>';

        // Параметры функции
        $out .= $this->variables($reflection->getParameters(), 'parameters');

        // Статические переменные
        $out .= $this->variables($reflection->getStaticVariables(), 'use');

        // Возвращаеемый тип
        if ($reflection->hasReturnType()) {
            $out .= '<div><span class="td-p">return</span>: <span class="td-o">'.$reflection->getReturnType().'</span></div>';
        }

        return $out;
    }

    /**
     * Разбираем массив переменных
	 *
     * @param array $variables
     * @param string $name
	 *
     * @return string
     */
    protected function variables(array $variables, $name)
    {
        $out = '';

        if (($count = count($variables)) > 0) {
            $idn = $this->getIdNesting();

            $out .= '<div><span class="td-p">'.$name.'</span>: ';
            $out .= '(<a onclick="td_showOrHideBlock('.$idn.')" id="td-ibtn'.$idn.'" class="td-close"></a>';
            $out .= '<div class="td-bx" id="td-ibx'.$idn.'">';

            switch ($name) {
                case 'parameters':
                    foreach ($variables as $param) {
                        $defaultValue = $param->isDefaultValueAvailable() ? ' = '.$this->resolve($param->getDefaultValue(), false) : '';
                        $out .= '<div><span class="td-p">$'.$param->getName().'</span>'.$defaultValue.'</div>';
                    }
                    break;
                case 'use':
                    foreach ($variables as $key => $value) {
                        $out .= '<div><span class="td-p">$'.$key.'</span> = '.$this->resolve($value, false).'</div>';
                    }
            }

            $out .= '</div>';
            $out .= ')';
            $out .= '</div>';
        }

        return $out;
    }

    /**
     * Ресурс
	 *
     * @param resource $resource
	 *
     * @return string
     */
    protected function dumpResource($resource)
    {
        $idn = $this->getIdNesting();

        $out = '<span class="td-rs">Resource</span> ';
        $out .= '{<a onclick="td_showOrHideBlock('.$idn.')" id="td-ibtn'.$idn.'" class="'.($this->deployed ? 'td-open' : 'td-close').'"></a>';
        $out .= '<div class="td-bx'.($this->deployed ? ' td-show' : '').'" id="td-ibx'.$idn.'">';

        // Тип ресурса
        $out .= '<div><span class="td-p">type</span>: <span class="td-o">'.get_resource_type($resource).'</span></div>';

        $out .= '</div>';
        $out .= '}';

        return $out;
    }

    /**
     * Неизвестный тип
	 *
     * @return string
     */
    protected function dumpUnknown()
    {
        return '<span class="td-u">Неизвестное значение</span>';
    }

    /**
     * Загружает ресурсы
	 *
     * @return string
     */
    protected function loadResources()
    {
        $res = '';

        $res .= join(PHP_EOL, array('<style>', trim(file_get_contents(__DIR__.'/Dumper/res/style.css')), '</style>', ''));
        $res .= join(PHP_EOL, array('<script>', trim(file_get_contents(__DIR__.'/Dumper/res/script.js')), '</script>', ''));

        return $res;
    }

    /**
     * Получает ID вложенности
	 *
     * @return string
     */
    protected function getIdNesting()
    {
        static $nesting = 1;
        return strval($nesting++);
    }

    /**
     * Получает идентификатор объекта в зависимости от текущей версии php
	 *
     * @param object $object
	 *
     * @return int|string
     */
    protected function getObjectId($object)
    {
        return version_compare('7.2', phpversion(), '<=') ? spl_object_id($object) : substr(spl_object_hash($object), -4);
    }
}

