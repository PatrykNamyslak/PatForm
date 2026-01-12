<?php
// This is a test script

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once "vendor/autoload.php";
use PatrykNamyslak\FormBuilder\Form;
use PatrykNamyslak\Patbase;


$databaseConnection = new Patbase(database_name: "bite_sized_projects", username: "root", password: "root");
$form = new Form(databaseConnection: $databaseConnection, table: "resume_projects");
$form->action("/")->method("POST");


match($_SERVER['REQUEST_METHOD']){
    "GET" => $form->render(),
    // You can use this if you want to make same page submissions
    "POST" => $form->submit(formData: $_POST),
};

?>