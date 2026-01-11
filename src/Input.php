<?php
namespace PatrykNamyslak\FormBuilder;

class Input{
    private(set) InputType $type;
    private ?string $label = null;
    /**
     * The type that is used for the column i.e enum, text or varchar
     * @var string
     */
    private string $typeInString;
    private string $name;
    private ?string $defaultValue = null;
    private int $maxLength = 255;
    private ?array $values = null;
    private bool $acceptMultipleValues = false;
    private bool $required = false;

    private string $dataTypeExpectedByDatabase;


    // Chainable methods
    public function default(string|null $value){
        $this->defaultValue = $value;
        return $this;
    }
    public function dataTypeExpectedByDatabase(string $value){
        $this->dataTypeExpectedByDatabase = $value;
        return $this;
    }
    /**
     * Summary of values
     * @param string $stringifiedValues This is usually mixed in with the type part of a columns structure i.e enum('1','2','3','4')
     */
    public function values(string $stringifiedValues){
        $charactersToTrim = ["enum", "set", "(", ")", "'"];
        $enumValues = "";
        foreach($charactersToTrim as $char){
            $enumValues = trim($stringifiedValues, $char);
        }
        $enumValues = explode(",", $enumValues);
        $this->values = $enumValues;
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
        $this->typeInString = $type;
        $this->type = match($type){
            "int", "smallint", "mediumint", "tinyint", "bigint" => InputType::NUMBER,
            "varchar", "json" => InputType::TEXT,
            "text", "longtext" => InputType::TEXT_AREA,
            "enum" => InputType::DROPDOWN,
            "boolean" => InputType::RADIO,
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
     * @return static
     */
    function renderLabel(){
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
    public function textArea(){
        ?>
        <textarea 
        name="<?= $this->name ?>" 
        <?= $this->renderRequiredAttribute() ?> 
        maxlength="<?= $this->maxLength ?>"
        >
        <?= $this->defaultValue ?>
        </textarea>
        <?php
    }

    public function dropdown(){
        
    }

    public function textField(){
        ?>
        <input 
        type="text" 
        name="<?= $this->name ?>" 
        maxlength="<?= $this->maxLength ?>" 
        <?php $this->renderMultipleAttribute() ?>
        <?php $this->renderRequiredAttribute() ?>
        >
        <?php
    }

    /**
     * This functions pure purpose is to check if there should be a required mark on an input field or not include it
     * @return void
     */
    public function renderRequiredAttribute(){
        if($this->required){
            echo "required";
        }
    }

    public function renderMultipleAttribute(){
        if($this->acceptMultipleValues){
            echo "multiple";
        }
    }

    public function getTypeInString(){
        return $this->typeInString;
    }
}