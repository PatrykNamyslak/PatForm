<?php
namespace PatrykNamyslak\PatForm;

use PatrykNamyslak\PatForm\Enums\InputType;
use PatrykNamyslak\PatForm\Support\Schema;

class Input{
    private(set) InputType $type;
    private ?string $label = null;
    /**
     * The type that is used for the column i.e enum, text or varchar
     * @var string
     */
    private string $columnTypeInString;
    private string $name;
    private ?string $defaultValue = null;
    private int $maxLength = 255;
    private ?array $possibleValues = null;
    private bool $acceptMultipleValues = false;
    private bool $required = false;
    private string $dataTypeExpectedByDatabase;


    // Specific field types
    private bool $json = false;
    private bool $unix = false;
    private bool $date = false;
    private bool $boolean = false;
    // End of field types

    private const PLACEHOLDER_TEXT_FOR_JSON_FIELD = "Seperate using commas E.g One,two,three";

    // Chainable methods
    public function default(string|null $value): static{
        $this->defaultValue = $value;
        return $this;
    }

    public function json(): static{
        $this->json = true;
        return $this;
    }

    public function boolean(): static{
        $this->boolean = true;
        return $this;
    }

    public function date(): static{
        $this->date = true;
        return $this;
    }

    /**
     * Mark the input as a unix timestamp field
     * @return static
     */
    public function unix(){
        $this->unix = true;
        return $this;
    }

    public function dataTypeExpectedByDatabase(string $value): static{
        $this->dataTypeExpectedByDatabase = $value;
        return $this;
    }
    /**
     * Set the default values from a string
     * @param string $stringifiedValues This is usually mixed in with the type part of a columns structure i.e enum('1','2','3','4')
     */
    public function values(string $stringifiedValues): static{
        // Find where the values start
        $openingBracket = strpos(haystack: $stringifiedValues, needle: "(");
        $possibleValues = substr(string: $stringifiedValues, offset: $openingBracket);
        $possibleValues = trim(string: $possibleValues, characters: "()");
        $possibleValues = explode(separator: ",", string: $possibleValues);

        // Remove single quotes
        foreach($possibleValues as &$value){
            $value = trim($value, "'");
        }
        $this->possibleValues = $possibleValues;
        return $this;
    }

    public function length(int $value){
        $this->maxLength = $value;
        return $this;
    }

    /**
     * Sets the name of the input field
     * @param string $value Name of the column that the input field is being generated for
     * @return static
     */
    public function name(string $value){
        $this->name = $value;
        return $this;
    }

    /**
     * Sets the input fields label, either uses a given label name or the name of the field
     * @param ?string $value
     * @return static
     */
    public function label(?string $value = null){
        $this->label = str_replace("_", " ", $value ?? $this->name);
        return $this;
    }
    public function required(bool $isRequired = true){
        if ($isRequired){
            $this->required = true;
        }
        return $this;
    }

    /**
     * Checks whether the input field expects unix timestamp.
     * @return bool
     */
    public function expectsUnix(): bool{
        return $this->columnTypeInString === "bigint" and $this->unix;
    }

    /**
     * A check to make the input of type checkbox if the table schema expects a boolean value
     * @return bool
     */
    public function expectsBoolean(){
        $booleanTypes = ["boolean", "bool", "tinyint"];
        return in_array($this->columnTypeInString, $booleanTypes) and $this->boolean;
    }
    /**
     * Checks if the input expects a password.
     * @return bool
     */
    public function expectsPassword(){
        return str_contains($this->name, "password");
    }
    /**
     * Checks if the input expects a date.
     * @return bool
     */
    public function expectsDate(): bool{
        // Column types that expect date format
        return in_array(strtoupper($this->columnTypeInString), Schema::DATE_TYPES);
    }

    /**
     * Check if the table schema accepts `only two distinct` options.
     * * Used to assign input type of `InputType::Radio` in `Input::type()`
     * @return bool
     */
    public function expectsChoiceBetweenTwoOptions(): bool{
        return ($this->columnTypeInString === "enum") and (count($this->possibleValues) === 2);
    }

    /**
     * Set the inputs type by using `InputType::enum`
     * @param string $type Passed from column structure from Form::prepareFields()
     * @return static
     */
    public function type(string $type){
        // extract the type by trimming the values. i.e enum(1,2,3,4) will be just enum. $values is (1,2,3,4) so we are using an intersect method to remove it.
        if(str_contains($type, "(")){
            $type = strtolower(
                string: substr(
                    string: $type,
                    offset: 0,
                    length: strpos(haystack: $type, needle: "(")
                    )
                );
        }
        $this->columnTypeInString = $type;
        
        $this->type = match(true){
            $this->expectsChoiceBetweenTwoOptions() => InputType::RADIO,
            $this->expectsPassword() => InputType::PASSWORD,
            $this->expectsUnix(), $this->expectsDate() => InputType::DATE,
            $this->expectsBoolean() => InputType::CHECKBOX,
            default => match($type){
                "float", "decimal", "double", "int", "smallint", "mediumint", "bigint", "tinyint" => InputType::NUMBER,
                "varchar", "json" => InputType::TEXT,
                "text", "longtext" => InputType::TEXT_AREA,
                "enum" => InputType::DROPDOWN,
                default => InputType::TEXT,
            },
        };
        return $this;
    }

    public function acceptMultipleValues(){
        $this->acceptMultipleValues = true;
        return $this;
    }

    // Non chainable methods, these must be at the end of the chain!
    /**
     * Renders the label
     */
    function renderLabel(): static{
        if ($this->label){
            ?>
            <label for="<?= $this->name ?>"><?= ucwords($this->label) ?>:</label>
            <?php
        }
        return $this;
    }

    /**
     * Returns a textarea html element while also respecting database maximums
     */
    public function textArea(?string $placeholder = NULL){
        $placeholder = $this->createInputPlaceholder($placeholder);
        ?>
        <textarea 
        name="<?= $this->name ?>" 
        placeholder="<?= $placeholder ?>"
        <?= $this->renderRequiredAttribute() ?> 
        maxlength="<?= $this->maxLength ?>" 
        value="<?= $this->defaultValue ?>"
        ></textarea>
        <?php
    }

    public function passwordField(?string $placeholder = NULL){
        $placeholder = $this->createInputPlaceholder($placeholder);
        ?>
        <input type="password" name="<?= $this->name ?>" 
        placeholder="<?= $placeholder ?>" 
        <?php
        $this->renderRequiredAttribute();
        ?>
        >
        <?php
    }

    /**
     * Check whether the passed `$value` is the default for the field and adds a given attribute, i.e `selected` for `dropdowns` or `checked` for `radio buttons`.
     * @param string|int $value
     * @return bool|string
     */
    protected function isDefaultValue(string|int $value, string $attribute = "selected"): bool|string{
        return match(true){
            $value === $this->defaultValue => $attribute,
            default => false,
        };
    }
    public function dropdown(): void{
        ?>
        <select name="<?= $this->name ?>" 
        <?php $this->renderRequiredAttribute(); ?>
        >
        <option selected disabled>Please choose an option</option>
        <?php
        foreach($this->possibleValues as $value):
            ?>
            <option value="<?= $value ?>" <?= $this->isDefaultValue($value) ?>><?= $this->prettyPrint($value) ?></option>
            <?php
        endforeach;
        ?>
        </select>
        <?php
    }

    public function radio(){
        foreach($this->possibleValues as $option): ?>
            <div>
                <span><?= $option ?></span>
                <input 
                type="radio" 
                name="<?= $this->name ?>" 
                value="<?= $option ?>" 
                <?= $this->isDefaultValue($option, attribute: "checked") ?>
                > 
            </div>
        <?php
        endforeach;
    }

    public function textField(?string $placeholder = null): void{
        $placeholder = $this->createInputPlaceholder($placeholder);
        ?>
        <input 
        type="text" 
        placeholder="<?= $placeholder ?>"
        name="<?= $this->name ?>" 
        maxlength="<?= $this->maxLength ?>" 
        value="<?= $this->defaultValue ?>" 
        <?php $this->renderRequiredAttribute() ?> 
        >
        <?php
    }

    public function checkBox(){
        ?>
        <input type="checkbox"
        name="<?= $this->name ?>" 
        value="<?= $this->defaultValue ?? "false" ?>"
        >
        <?php
    }

    public function datePicker(){
        ?>
        <input type="date" 
        name="<?= $this->name ?>" 
        <?php $this->renderRequiredAttribute() ?>
        >
        <?php
    }

    public function numberField(?string $placeholder = NULL){
        $placeholder = $this->createInputPlaceholder($placeholder);
        ?>
        <input type="number" 
        placeholder="<?= $placeholder ?>"
        name="<?= $this->name ?>" 
        max="<?= $this->maxLength ?>" 
        value="<?= $this->defaultValue ?>" 
        <?php $this->renderRequiredAttribute() ?>
        >
        <?php
    }

    /**
     * This functions pure purpose is to check if there should be a required mark on an input field or not include it
     * @return void
     */
    public function renderRequiredAttribute(): void{
        if($this->required){
            echo "required";
        }
    }

    public function renderMultipleAttribute(): void{
        if($this->acceptMultipleValues){
            echo "multiple";
        }
    }

    public function getColumnTypeInString(): string{
        return $this->columnTypeInString;
    }

    private function createInputPlaceholder(?string $placeholder = NULL): string{
        return match(true){
            // if the field is a json field append a guide for data insertion (Show what is expected)
            $this->json => ($placeholder ?? $this->prettyPrint($this->name)) . ": " . self::PLACEHOLDER_TEXT_FOR_JSON_FIELD,
            isset($placeholder) => $placeholder,
            default => ucfirst(str_replace("_", " ", $this->name)),
        };
    }

    /**
     * Makes the text look pretty :P
     * @param string $text
     * @return string
     */
    public function prettyPrint(string $text): string{
        $text = str_replace(["_"], " ", $text);
        return ucwords($text);
    }
}