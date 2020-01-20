<?php namespace Accent\Test;

/**
 * Using coverage checking forcing developers to cover all public methods in testing class.
 *
 * That ensures that after inital develop/test cycle further addition of new public methods
 * will dispatch warning notice if developer forgot to wrote tests for them.
 */

class CoverageChecker {


    // internal properties
    protected $Class;
    protected $Methods;
    protected $Checked;


    /**
     * Contructor.
     *
     * @param string $Class  FQCN
     * @param int $Depth  ignore methods from parents further then $Depth
     * @param array $Ignore  list of methods that should not be monitored
     */
    public function __construct($Class, $Depth, $Ignore) {

        // store specified class
        $this->Class= $Class;

        // prepare list of methods
        $this->Methods= $this->CalcListOfMethods($Class, $Depth, $Ignore);

        // prepare checkings buffer
        $this->Checked= [];
    }


    protected function CalcListOfMethods($Class, $Depth, $Ignore) {

        // get list of public methods
        $Methods= get_class_methods($this->Class);

        // remove methods from too far ancestors
        while($Class !== false && $Depth-- > 0) {
            $Class= get_parent_class($Class);
        };
        if ($Class) {
            $Methods= array_diff($Methods, get_class_methods($Class));
        }

        // make lowercased index
        $Methods= array_combine(array_map('strtolower', $Methods), $Methods);

        // exclude specified methods from list
        return array_diff_key(
            $Methods,
            array_flip(array_map('strtolower', $Ignore))
        );
    }


    /**
     * Mark specified method as checked.
     *
     * @param string $Method  method name
     */
    public function Check($Method) {

        $this->Checked[strtolower($Method)]= $Method;
    }


    /**
     * Return list of public methods (lowercased) of target class.
     *
     * @return array
     */
    public function GetMethods() {

        return $this->Methods;
    }


    /**
     * Calculate and return list of non-checked public methods.
     *
     * @return array
     */
    public function GetUnchecked() {

        return array_diff_key($this->Methods, $this->Checked);
    }


    /**
     * Calculate and return list of checks for non-existant methods.
     *
     * @return array
     */
    public function GetMisses() {

        return array_diff_key($this->Checked, $this->Methods);
    }


    /**
     * Returns checked class.
     *
     * @return string
     */
    public function GetClass() {

        return $this->Class;
    }

}

?>