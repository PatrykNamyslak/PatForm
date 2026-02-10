```PHP
$databaseConnection = new Patbase(database_name: "bite_sized_projects", username: "root", password: "root");

$form = new Form(databaseConnection: $databaseConnection, table: "patform_example");

$form->action("/")->method("POST")->wrapFields()->htmx()->prepareFields();

// Here is a code snippet on how you can handle same page submissions
match($_SERVER['REQUEST_METHOD']){
    "GET" => $form->render(),
    // You can use this if you want to make same page submissions
    "POST" => $form->submit(formData: $_POST),
};
```