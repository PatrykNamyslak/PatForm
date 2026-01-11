<?php
namespace PatrykNamyslak\FormBuilder;

enum RequestMethod:string{
    case POST = "POST";
    case GET = "GET";
    case PATCH = "PATCH";
    case DELETE = "DELETE";
}