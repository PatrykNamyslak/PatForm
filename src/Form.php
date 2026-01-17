<?php
namespace PatrykNamyslak\FormBuilder;

use Exception;
use PatrykNamyslak\Patbase;


class Form{
    /** This will be an array of objects for all of the columns*/
    private array $tableStructure;
    private(set) array $fieldNames;
    private string $action;
    private string $method;
    private bool $htmx = false;
    private bool $wrapField = false;

    /**
     * 
     * @param \PatrykNamyslak\Patbase $databaseConnection
     * @param string $table This is the table name for which the input fields will be fetched from, the input fields will be the columns from the table
     */
    public function __construct(protected Patbase $databaseConnection, protected string $table){
        $query = "DESCRIBE {$table};";
        try{
            $stmt = $databaseConnection->connection->query($query);
            $stmt->setFetchMode(\PDO::FETCH_OBJ);
            $this->tableStructure = $stmt->fetchAll();
            $this->fieldNames = array_column($this->tableStructure, "Field");
            return;
        }catch(Exception $e){
            echo "Form Builder Failed \n\n";
            return;
            // echo $e;
        }
    }


    /**
     * Turn an array of regular field names into placeholders that are ready for prepared statements.
     * @param array $fieldNames Database table field names that will be used in a prepared statement
     * @return string
     */
    public function createPlaceholdersFromArray(array $fieldNames): string{
        foreach($fieldNames as &$placeholder){
            $placeholder = ":" . $placeholder;
        }
        return implode(",", $fieldNames);
    }


    /**
     * Adds a div surrounding the input and its label, this is if you want to use flexbox or a grid layout for the form.
     * @return static
     */
    public function wrapFields(){
        $this->wrapField = true;
        return $this;
    }

    public function submit(array $formData){
        $placeholders = $this->createPlaceholdersFromArray($this->fieldNames);
        foreach($this->tableStructure as $column){
            $formData[$column->Field] = match($column->Type){
                "json" => json_encode(explode(",", $formData[$column->Field])),
                default => $formData[$column->Field],
            };
        }
        $columnNames = implode(",", $this->fieldNames);
        $query = "INSERT INTO `{$this->table}` ({$columnNames}) VALUES($placeholders);";
        try{
            $this->databaseConnection->prepare($query, $formData)->execute();
            echo "Form submitted!";
            return;
        }catch(Exception $e){
            echo $e;
            echo "An error has occurred";
            return;
        }
    }


    /**
     * Sets where the form should send data.
     * @param string $destination URI or URL
     */
    public function action(string $destination){
        $this->action = $destination;
        return $this;
    }

    public function method(RequestMethod|string $RequestMethod){
        if (is_string($RequestMethod) and !in_array($RequestMethod, array_column(RequestMethod::cases(), "value"))){
            throw new Exception("The RequestMethod was not set as the value provided is invalid");
        }
        $this->method = match(true){
            $RequestMethod instanceof RequestMethod => $RequestMethod->value,
            default => $RequestMethod,
        };
        return $this;
    }
    /**
     * Makes the form use HTMX for the request
     * @return void
     */
    public function htmx(){
        $this->htmx = true;
    }

    /**
     * Renders the form
     */
    public function render(string $formTitle, bool $renderLabels = true){
        ?>
        <h2><?= $formTitle ?></h2>
        <form action="<?= $this->action ?>" method="<?= $this->method ?>">
        <?php
        foreach ($this->tableStructure as $column):
            if ($this->wrapField): ?>
                <div>
            <?php
            endif;
            // Skip Auto incremented columns
            if (Column::isAutoIncrement(column: $column)){
                continue;
            }
            $Input = new Input;
            $Input
            ->type($column->Type)
            ->dataTypeExpectedByDatabase($column->Type)
            ->name($column->Field)
            ->values($column->Type)
            ->default($column->Default)
            ->required(Column::isNullable($column) === false);

            // Render the input field
            if($renderLabels){
                $Input
                ->label()
                ->renderLabel();
            }
            // handle a field that accepts multiple inputs
            if ($Input->getTypeInString() === "json"){
                $Input->json()->textField();
            }else{
                match($Input->type){
                    InputType::TEXT_AREA => $Input->textArea(),
                    InputType::TEXT => $Input->textField(),
                    InputType::DROPDOWN => $Input->dropdown(),
                };
            }
            if ($this->wrapField): ?>
                </div>
            <?php
            endif;
        endforeach;
        ?>
        <button type="submit">Submit</button>
        </form>
        <?php
    }
}