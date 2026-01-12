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

    public function submit(array $formData){
        $placeholders = $this->createPlaceholdersFromArray($this->fieldNames);
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
     * Renders the form
     */
    public function render(bool $renderLabels = true){
        ?>
        <form action="<?= $this->action ?>" method="<?= $this->method ?>">
        <?php
        foreach ($this->tableStructure as $column){
            $Input = new Input;
            $Input
            ->type($column->Type)
            ->dataTypeExpectedByDatabase($column->Type)
            ->name($column->Field)
            ->values($column->Type)
            ->default($column->Default)
            ->required(Table::isColumnNullable($column) === false);

            // Render the input field
            if($renderLabels){
                $Input
                ->label()
                ->renderLabel();
            }
            // handle a field that accepts multiple inputs
            if ($Input->getTypeInString() === "json"){
                $Input->acceptMultipleValues()->textField();
            }else{
                match($Input->type){
                    InputType::TEXT_AREA => $Input->textArea(),
                    InputType::TEXT => $Input->textField(),
                    InputType::DROPDOWN => $Input->dropdown(),
                };
            }
        }
        ?>
        <button type="submit">Submit</button>
        </form>
        <?php
    }
}