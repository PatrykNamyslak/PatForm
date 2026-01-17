<?php
namespace PatrykNamyslak\FormBuilder;

class Column{

    /**
     * Checks if a column is nullable.
     * @param object $column
     * @return bool
     */
    public static function isNullable(object $column){
        return match($column->Null){
            "YES" => true,
            "NO" => false,
        };
    }
    public static function isAutoIncrement(object $column){
        return strtolower($column->Extra) === "auto_increment";
    }
}