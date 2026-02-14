<?php
namespace PatrykNamyslak\PatForm;

use Carbon\Carbon;
use DateTime;
use Exception;
use PatrykNamyslak\Patbase;
use PatrykNamyslak\PatForm\Enums\ColumnProperty;
use PatrykNamyslak\PatForm\Enums\HtmxSwapMode;
use PatrykNamyslak\PatForm\Enums\InputType;
use PatrykNamyslak\PatForm\Enums\RequestMethod;
use PatrykNamyslak\PatForm\Support\Column;
use RuntimeException;
use Throwable;

session_start();

class Form{
    /**
     * @var \PatrykNamyslak\PatForm\Input[]
     */
    protected array $inputFields = [];
    /** An array of objects with all of the columns and their structure i.e $tableStructure[0]->Field is the name of the column, reference: ../TableStructureDocumentation.txt*/
    private(set) array $tableStructure;
    private(set) array $fieldNames;
    private string $action;
    private string $method;
    private bool $wrapField = false;
    private(set) bool $htmx = false;
    public bool $htmxWasInjected = false;
    private(set) ?string $htmxResponseTarget = NULL;
    private(set) ?HtmxSwapMode $htmxSwapMode = NULL;
    private(set) ?bool $htmxRenderResponseTarget = NULL;
    private bool $csrf = true;
    private ?string $timestampFormat = null;
    private(set) string $submitButtonText = "Submit";


    private const DEFAULT_TIMESTAMP_FORMAT = "H:i:s d-m-Y";
    public const INVALID_CSRF = "Invalid CSRF Token.";
    /**
     * 
     * @param \PatrykNamyslak\Patbase $databaseConnection
     * @param string $table This is the table name for which the input fields will be fetched from, the input fields will be the columns from the table
     */
    public function __construct(protected Patbase $databaseConnection, protected string $table){
        $this->table = str_replace([" ", "-"], "_", trim($table));
        $query = "SHOW FULL COLUMNS FROM `{$this->table}`;";
        try{
            $stmt = $databaseConnection->connection->query($query);
            $stmt->setFetchMode(\PDO::FETCH_OBJ);
            $this->tableStructure = $stmt->fetchAll();
            $this->fieldNames = array_column($this->tableStructure, column_key: ColumnProperty::NAME->value);
            return;
        }catch(Throwable $e){
            throw new RuntimeException("Form Builder Failed");
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

    

    protected function beforeSubmit(array $formData){}
    protected function afterSubmit(array $formData){}
    /**
     * Default form submission
     * @param array $formData
     * @return void
     */
    public function submit(array $formData): void{
        if (!$this->validateCsrfToken($formData["csrf_token"])){
            exit(self::INVALID_CSRF);
        }
        unset($formData["csrf_token"]);
        $placeholders = $this->createPlaceholdersFromArray($this->fieldNames);
        foreach($this->tableStructure as $column){
            $formData[$column->Field] = match($column->Type){
                "json" => json_encode(explode(",", $formData[$column->Field])),
                default => $formData[$column->Field],
            };
        }
        // Add backticks to prevent a column name being the same as an SQL operator
        $backtickedFieldNames = array_map(fn($field) => "`$field`", $this->fieldNames);
        $columnNames = implode(",", $backtickedFieldNames);
        $query = "INSERT INTO `{$this->table}` ({$columnNames}) VALUES($placeholders);";
        try{
            $this->databaseConnection->prepare($query, $formData)->execute();
            echo "Form submitted!";
            return;
        }catch(Exception $e){
            echo "An error has occurred while attempting to submit the form!";
            return;
            // echo $e;
        }
    }

    /**
     * Sets where the form should send data.
     * @param string $destination URI or URL
     */
    public function action(string $destination): static{
        $this->action = $destination;
        return $this;
    }

    public function submitButtonText(string $value): static{
        $this->submitButtonText = $value;
        return $this;
    }


    public function method(RequestMethod|string $RequestMethod): static{
        // Make sure it is a valid method by making it the exact same format as in the RequestMethod::Enum
        if (is_string($RequestMethod)){
            $RequestMethod = strtoupper($RequestMethod);
        }
        if (is_string($RequestMethod) && !in_array($RequestMethod, array_column(RequestMethod::cases(), "value"))){
            throw new Exception("The RequestMethod was not set as the value provided is invalid");
        }
        $this->method = match(true){
            $RequestMethod instanceof RequestMethod => $RequestMethod->value,
            default => $RequestMethod,
        };
        return $this;
    }

    /**
     * Pass an array of column names that are in the target table that the form is being generated from to remove them from the final form, this can cause errors if the database does not have default values for these columns upon form submission or you don't handle form submission correctly by modifying the submit functionality.
     * @return static
     */
    public function omitFields(array $columnNames): static{
        if ($columnNames === []){
            throw new Exception('$columnNames cannot be an empty array!');
        }
        $ts = &$this->tableStructure;
        $currentlySetFieldNames = &$this->fieldNames;
        foreach($columnNames as $columnName){
            $key = array_search($columnName, $currentlySetFieldNames);
            if ($key !== false){
                unset($ts[$key]);
                unset($currentlySetFieldNames[$key]);
            }
        }
        return $this;
    }

    /**
     * Only uses the fields provided - `Be warned that upon form submission there could be an error if the database doesnt have a default value for the omitted columns`
     * @param array $columnNames
     * @throws Exception
     * @return static
     */
    public function onlyUse(array $columnNames): static{
        if ($columnNames === []){
            throw new Exception('$columnNames Cannot be an empty array!');
        }
        $currentlySetFieldNames = array_column($this->tableStructure, ColumnProperty::NAME->value);
        // Check if the columns are in the table structure
        if (empty(array_diff($columnNames, $currentlySetFieldNames))){
            $newTableStructure = [];
            foreach($columnNames as $columnName){
                $position = array_search($columnName, $currentlySetFieldNames);
                $newTableStructure[] = $this->tableStructure[$position];
            }
        }else{
            throw new Exception("Invalid column names provided.: " . implode(separator: ",", array: array_diff($columnNames, $currentlySetFieldNames)));
        }
        $this->tableStructure = $newTableStructure;
        $this->fieldNames(array_column($this->tableStructure, ColumnProperty::NAME->value));
        return $this;
    }


    public function noCsrf(): static{
        $this->csrf = false;
        return $this;
    }
    private function createCsrfToken(): string{
        return bin2hex(random_bytes(32));
    }
    private function setCsrfToken(): void{
        $_SESSION["csrf_token"] = $this->createCsrfToken();
    }
    /**
     * Returns the currently set CSRF token and if there is none set, it sets it, then returns it.
     * @return string
     */
    public function csrfToken(): string{
        if (!$_SESSION["csrf_token"]){
            $this->setCSRFToken();
        }
        return $_SESSION["csrf_token"];
    }
    public function validateCsrfToken(string $token){
        return $token === $this->csrfToken();
    }

    /**
     * Makes the form use `HTMX` for the request.
     * @param string $responseTarget This needs to be a valid CSS selector, i.e ".response" or "nearest .response" for htmx to be able to locate your element
     * @param mixed $renderResponseElement This defaults to an element called .response and ignores the users set responseTargetElement
     * @return static
     */
    public function htmx(string $responseTargetElement = "this", bool $renderResponseElement = true,  HtmxSwapMode $swapMode = HtmxSwapMode::innerHTML): static{
        $this->htmx = true;
        $this->htmxRenderResponseTarget = $renderResponseElement;
        // Defaults to .response if the user wants the form to default to its own response element / use the rendered one
        $this->htmxResponseTarget = match($this->htmxRenderResponseTarget){
            true => ".response",
            false => $responseTargetElement,
        };
        $this->htmxSwapMode = $swapMode;
        return $this;
    }


    /**
     * Set the default timestampFormat for specific formats
     * @param string $format Defaults to `DEFAULT_TIMESTAMP_FORMAT`
     * @return void
     */
    public function timestampFormat(string $format = self::DEFAULT_TIMESTAMP_FORMAT){
        $this->timestampFormat = $format;
    }
    protected function isValidDateFormat(string $date){
        return DateTime::createFromFormat($this->timestampFormat, datetime: $date) instanceof DateTime;
    }
    /**
     * Used for updating the `fieldNames` property stored in the object instance after filtering fields either using `$this->onlyUse()` or `$this->omitFields()`
     * @param array $names
     * @return static
     */
    private function fieldNames(array $names){
        if ($names !== []){
            $this->fieldNames = $names;
        }
        return $this;
    }

    public function prepareFields(){
        foreach ($this->tableStructure as $column):
            // Skip Auto incremented columns
            if (Column::isAutoIncrement(column: $column)){
                continue;
            }
            $input = new Input;
            // Handle specific edge cased fields
            match(true){
                Column::expectsJSON($column) => $input->json(),
                Column::expectsUnix($column) => $input->unix()->date(),
                Column::expectsBoolean($column) => $input->boolean(),
                Column::expectsDate($column) => $input->date(),
                default => null,
            };
            // Build the input field
            $input
            ->dataTypeExpectedByDatabase($column->{ColumnProperty::TYPE->value})
            ->name($column->{ColumnProperty::NAME->value})
            ->values($column->{ColumnProperty::TYPE->value})
            ->type($column->{ColumnProperty::TYPE->value})
            ->default($column->{ColumnProperty::DEFAULT->value})
            ->required(Column::isNullable($column) === false);
            
            // Store the input fields
            $this->inputFields[] = $input;
        endforeach;
    }

    /**
     * Renders the form
     */
    public function render(string $formTitle, bool $renderLabels = true){
        ?>
        <h2><?= $formTitle ?></h2>
        <?php
        if ($this->htmx):
            //  Render default response element
            if ($this->htmxRenderResponseTarget): ?>
            <div class="response"></div>
            <?php
            endif;
            // Inject htmx dependency
            if (!$this->htmxWasInjected): ?>
                <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
            <?php
            endif;
            ?>
            <form hx-<?= $this->method ?>="<?= $this->action ?>" hx-swap="<?= $this->htmxSwapMode->value ?>" hx-target="<?= $this->htmxResponseTarget ?>">
        <?php
        else: ?>
            <form action="<?= $this->action ?>" method="<?= $this->method ?>">
        <?php
        endif;
        if ($this->csrf): ?>
        <input type="hidden" name="csrf_token" value="<?= $this->csrfToken() ?>">
        <?php
        endif;
        foreach ($this->inputFields as $input):
            if ($this->wrapField): ?>
                <div>
            <?php
            endif;

            // Render the input field
            if($renderLabels){
                $input
                ->label()
                ->renderLabel();
            }
            match($input->type){
                    InputType::TEXT => $input->textField(),
                    InputType::PASSWORD => $input->passwordField(),
                    InputType::TEXT_AREA => $input->textArea(),
                    InputType::DROPDOWN => $input->dropdown(),
                    InputType::RADIO => $input->radio(),
                    InputType::NUMBER => $input->numberField(),
                    InputType::DATE => $input->datePicker(),
                    InputType::CHECKBOX => $input->checkBox(),
                };
            if ($this->wrapField): ?>
                </div>
            <?php
            endif;
        endforeach;
        ?>
        <button type="submit"><?= $this->submitButtonText ?></button>
        </form>
        <?php
    }
}