<?php
namespace PatrykNamyslak\PatForm\Enums;

enum InputType{

    /**
     * JSON Columns can have the type of TEXT and NUMBER
     */

    /** Varchar Columns */
    case TEXT;
    // Short Text, Medium Text and Long Text columns
    case TEXT_AREA;
    // Integer column
    case NUMBER;
    // Enum / Set columns
    case DROPDOWN;
    // Timestamp columns
    case DATE;
    // Boolean columns
    case RADIO;
    case PASSWORD;
    case CHECKBOX;

}