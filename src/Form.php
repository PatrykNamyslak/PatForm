<?php
namespace PatrykNamyslak\PatForm;

use Carbon\Carbon;
use DateTime;
use Exception;
use PatrykNamyslak\Builders\HtmlElement;
use PatrykNamyslak\Patbase;
use PatrykNamyslak\PatForm\Enums\ColumnProperty;
use PatrykNamyslak\PatForm\Enums\HtmxSwapMode;
use PatrykNamyslak\PatForm\Enums\InputType;
use PatrykNamyslak\PatForm\Enums\RequestMethod;
use PatrykNamyslak\PatForm\Exceptions\FeatureNotImplemented;
use PatrykNamyslak\PatForm\Support\Column;
use RuntimeException;
use Throwable;
session_start();

class Form{
    /**
     * @var Input[]
     */
    protected array $inputFields = [];
    /** An array of objects with all of the columns and their structure i.e $tableStructure[0]->Field is the name of the column, reference: ../TableStructureDocumentation.txt*/
    private(set) array $tableStructure;
    private(set) ?array $fieldsToRender = NULL;
    private string $action;
    private string $method;
    private bool $wrapField = false;
    private(set) bool $htmx = false;
    public static bool $htmxWasInjected = false;
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
    public function __construct(protected Patbase $databaseConnection, protected string $table, protected ?HtmlElement $wrapperElement = NULL){
        // Accept alphanumeric characters (letters and numbers) and underscores, `table-name` would be invalid
        if (!preg_match(pattern: '/^[a-zA-Z0-9_]+$/', subject: $table)) {
            throw new \InvalidArgumentException("Invalid table name: {$table}");
        }
        $query = "SHOW FULL COLUMNS FROM `{$this->table}`;";
        try{
            $stmt = $databaseConnection->connection->query($query);
            $stmt->setFetchMode(\PDO::FETCH_OBJ);
            $this->tableStructure = $stmt->fetchAll();
            
            $this->wrapperElement = match(true){
                isset($this->wrapperElement) => $this->wrapperElement,
                default => new HtmlElement(),
            };
            return;
        }catch(Throwable $e){
            throw new RuntimeException("Form Builder Failed");
            // echo $e;
        }
    }

    /**
     * Create an instance by providing the structure of each column
     * @return self
     */
    public static function fromArray(): self{
        throw new FeatureNotImplemented;
    }

    public static function fromSchema(string $schema): void{
        throw new FeatureNotImplemented;
    }
    public static function fromMigrationSchema(string $schema): void{
        throw new FeatureNotImplemented;
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
        $placeholders = $this->createPlaceholdersFromArray($this->getFieldNames());
        foreach($this->tableStructure as $column){
            $formData[$column->Field] = match($column->Type){
                "json" => json_encode(explode(",", $formData[$column->Field])),
                default => $formData[$column->Field],
            };
        }
        // Add backticks to prevent a column name being the same as an SQL operator
        $backtickedFieldNames = array_map(fn($field) => "`$field`", $this->getFieldNames());
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

    protected function getFieldNames(): array{
        return array_column(array: $this->fieldsToRender ?? $this->tableStructure, column_key: ColumnProperty::NAME->value);
    }

    public function requiredOnly(): static{
        // $ts = table structure
        $ts = $this->tableStructure;
        // An array of column objects that will be used for form generation
        $fieldsToUse = [];
        foreach ($ts as $column){
            if (!Column::isNullable($column)){
                $fieldsToUse[] = $column;
            }
        }
        $this->fields($fieldsToUse);
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
        $ts = $this->tableStructure;
        $currentlySetFieldNames = $this->getFieldNames();
        foreach($columnNames as $columnName){
            $key = array_search($columnName, $currentlySetFieldNames);
            if ($key !== false){
                unset($ts[$key]);
            }
        }
        $this->fields($ts);
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
        $currentlySetFieldNames = $this->getFieldNames();
        // Check if the columns are in the table structure
        if (empty(array_diff($columnNames, $currentlySetFieldNames))){
            $columnsToRender = [];
            foreach($columnNames as $columnName){
                $position = array_search($columnName, $currentlySetFieldNames);
                $columnsToRender[] = $this->tableStructure[$position];
            }
        }else{
            throw new Exception("Invalid column names provided.: " . implode(separator: ",", array: array_diff($columnNames, $currentlySetFieldNames)));
        }
        $this->fields($columnsToRender);
        return $this;
    }

    /**
     * Add extra columns to use, ideal for chaining after an onlyUse() call.å
     * @return static
     */
    public function alsoUse(array $columnNames): static{
        foreach($columnNames as $columnName){
            // Search for the object key by getting its location in the array column of field name
            $objectKey = array_search($columnName, array_column($this->tableStructure, ColumnProperty::NAME->value));
            $this->fieldsToRender[] = $this->tableStructure[$objectKey];
        }
        return $this;
    }

    public static function wasHtmxInjected(): bool{
        return self::$htmxWasInjected;
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
    public function validateCsrfToken(string $token): bool{
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
    public function timestampFormat(string $format = self::DEFAULT_TIMESTAMP_FORMAT): void{
        $this->timestampFormat = $format;
    }
    protected function isValidDateFormat(string $date){
        return DateTime::createFromFormat($this->timestampFormat, datetime: $date) instanceof DateTime;
    }
    /**
     * Set the fields / columns that will be rendered
     * @param object[] $columns
     * @return static
     */
    protected function fields(array $columns): static{
        $this->fieldsToRender = $columns;
        return $this;
    }

    public function prepareFields(): void{
        $columns = match(true){
            $this->fieldsToRender !== [] => $this->fieldsToRender,
            default => $this->tableStructure,
        };

        foreach ($columns as $column):
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
    public function render(string $formTitle, bool $renderLabels = true): void{
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
            if (!self::wasHtmxInjected()): ?>
                <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
            <?php
            self::$htmxWasInjected = true;
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
                <?= $this->wrapperElement->render(delayEndTag: true) ?>
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
                <?= $this->wrapperElement->renderEndTag() ?>
            <?php
            endif;
        endforeach;
        ?>
        <button type="submit"><?= $this->submitButtonText ?></button>
        </form>
        <?php
    }
}