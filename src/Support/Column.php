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
        return str_contains($column->{ColumnProperty::NAME->value}, "password");
    }

    public static function expectsDate(object $column){
        $isDateColumn = in_array(strtoupper($column->{ColumnProperty::TYPE->value}), Schema::DATE_TYPES);
        return Column::isUnix($column) or $isDateColumn;
    }
    public static function isUnix(object $column){
        return Column::hasFlag(column: $column, flag: "unix") and ($column->{ColumnProperty::TYPE->value} === Schema::BIGINT);
    }

    public static function hasFlag(object $column, string $flag){
        return str_contains(strtolower($column->{ColumnProperty::COMMENTS->value}), "[$flag]");
    }

    /**
     * For a json column you can set an `optional` `[json]` flag in the `comments` section of the column schema, this is added for `consistency` and is `NOT` required.
     * @param object $column
     * @return bool `true` `ONLY` if the column is of type `json` in the `table schema`, `OR` if its of type `json` and the `flag is set`. Returns `false` If the column has the `[json]` flag set but is `NOT` of type json.
     */
    public static function expectsJSON(object $column): bool{
        $flagIsSet = Column::hasFlag($column, "json");
        $isJsonColumn = str_contains($column->{ColumnProperty::TYPE->value}, "json");
        return $isJsonColumn or ($isJsonColumn and $flagIsSet);
    }

    /**
     * Checks for a boolean flag in the comments section of the column
     * @param object $column
     * @return bool
     */
    public static function expectsBoolean(object $column): bool{
        return str_contains(strtolower($column->{ColumnProperty::COMMENTS->value}), "[boolean]");
    }
}