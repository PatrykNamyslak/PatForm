<?php
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

// This is a test script showing off how to use the form builder


require_once "vendor/autoload.php";
use PatrykNamyslak\PatForm\Form;
use PatrykNamyslak\Patbase;


$databaseConnection = new Patbase(database: "bite_sized_projects", username: "root", password: "root");

$form = new Form(databaseConnection: $databaseConnection, table: "patform_example");

$form->action("/")->method("POST")->wrapFields()->htmx()->prepareFields();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Builder</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
    match($_SERVER['REQUEST_METHOD']){
        "GET" => $form->render(formTitle: "PatForm Example"),
        // You can use this if you want to make same page submissions
        "POST" => $form->submit(formData: $_POST),
    };
    ?>
</body>
</html>