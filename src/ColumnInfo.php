<?php
namespace PatrykNamyslak\FormBuilder;

/**
 * Stores the names of the properties assigned by MYSQL from the describe query for the tableStructure in the Form::__construct()
 */
enum ColumnInfo:string{
    case NAME = "Field";
    case TYPE = "Type";
    case NULL = "Null";
    case KEY = "Key";
    case DEFAULT = "Default";
    case EXTRA = "Extra";
}