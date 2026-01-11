<?php
namespace PatrykNamyslak\FormBuilder;

class Table{

    /**
     * Checks if a column is nullable.
     * @param object $column
     * @return bool
     */
    public static function isColumnNullable(object $column){
        return match($column->Null){
            "YES" => true,
            "NO" => false,
        };
    }
}