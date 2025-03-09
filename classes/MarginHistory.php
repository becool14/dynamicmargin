<?php

class MarginHistory extends ObjectModel
{
    public $id_margin_history;
    public $margin_value;
    public $previous_value;
    public $date_add;
    public $id_employee;

    public static $definition = [
        'table' => 'dynamic_margin_history',
        'primary' => 'id_margin_history',
        'fields' => [
            'margin_value' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'previous_value' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
        ],
    ];
}