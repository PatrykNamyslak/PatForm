<?php
namespace PatrykNamyslak\FormBuilder;


/**
 * For further documentation Reference: https://htmx.org/attributes/hx-swap/
 */
enum HtmxSwapMode: string{
    case innerHTML = "innerHTML";
    case outerHTML = "outerHTML";
    case textContent = "textContent";
    case beforebegin = "beforebegin";
    case afterbegin = "afterbegin";
    case beforeend = "beforeend";
    case afterend = "afterend";
    case delete = "delete";
    case none = "none";
}