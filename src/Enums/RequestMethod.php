<?php
namespace PatrykNamyslak\PatForm\Enums;

enum RequestMethod:string{
    case POST = "POST";
    case GET = "GET";
    case PATCH = "PATCH";
    case DELETE = "DELETE";
}