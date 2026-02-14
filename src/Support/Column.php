<?php
namespace PatrykNamyslak\PatForm\Support;

use PatrykNamyslak\PatForm\Enums\ColumnProperty;

abstract class Column{


    /**
     * Checks if a column is nullable.
     * @param object $column
     * @return bool
     */
    public static function isNullable(object $column): bool{
        return match($column->Null){
            "YES" => true,
            "NO" => false,
        };
    }

    /**
     * Checks whether a `$column` is an `auto_increment` field.
     * @param object $column
     * @return bool
     */
    public static function isAutoIncrement(object $column): bool{
        return strtolower($column->{ColumnProperty::EXTRA->value}) === "auto_increment";
    }


    /**
     * Checks for unix flag on the column
     * @param object $column
     * @return bool
     */
    public static function expectsUnix(object $column){
        return Column::hasFlag(column: $column, flag: "unix");
    }

    public static function expectsPassword(object $column){
        return str_contains(strtolower($column->{ColumnProperty::NAME->value}), "password");
    }

    public static function expectsDate(object $column){
        $isDateColumn = in_array(strtoupper($column->{ColumnProperty::TYPE->value}), Schema::DATE_TYPES);
        return Column::isUnix($column) || $isDateColumn;
    }
    public static function isUnix(object $column){
        return Column::hasFlag(column: $column, flag: "unix") && ($column->{ColumnProperty::TYPE->value} === Schema::BIGINT);
    }

    public static function hasFlag(object $column, string $flag){
        // Casting string to the comment value to prevent a Depreciation error caused by null values being passed into strtolower
        return str_contains(strtolower((string) $column->{ColumnProperty::COMMENTS->value}), "[$flag]");
    }

    /**
     * Check whether a column is expecting JSON data
     * @param object $column
     * @return bool
     */
    public static function expectsJSON(object $column): bool{
        $isJsonColumn = str_contains($column->{ColumnProperty::TYPE->value}, "json");
        return $isJsonColumn;
    }

    /**
     * Checks for a boolean flag in the comments section of the column
     * @param object $column
     * @return bool
     */
    public static function expectsBoolean(object $column): bool{
        return self::hasFlag($column, "[boolean]");
    }
}