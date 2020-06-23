<?php namespace Accent\AccentCore\ArrayUtils;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Mapper is object responsible for transforming/adapting/customizing
 * associative arrays between two sub-systems with different naming strategies.
 *
 * For example there is common situation that developer have to implement
 * storing some entity (object or array of values) to database but keys of
 * array are not compatible with field names in database table.
 * Table is already in use by other systems and entity is part of already
 * finished, well tested and proved project.
 * Mapper can be injected between them to handle this issue.
 * Entity should send values to mapper first and then result of mapping send
 * to database, and in opposite direction data fetched from database should
 * pass through mapper before analyzing them.
 *
 * Objects utilizing mapper offers to developers possibility to customize such
 * importing and exporting data without need to modify object itself.
 * All that developer need to do is to provide its own map in object's constructor.
 * And even more, instead of map developer can inject its own instantied class
 * (descendant of Mapper) and put some business logic in so multiple values can
 * be split and joined, modified or removed from array.
 *
 * Terms:
 *  - "map" here indicates simple array of "from"=>"to" pairs.
 *  - "mapping" indicates process of translating array from "standard" to "customized" struct.
 *  - "remapping" indicates opposite process, from "customized" to "standard" struct.
 *
 * Beside simple altering array keys this class has several advanced features:
 *  - it can be instructed to remove particular keys from result
 *  - it can be instructed to move particular keys to secondary array
 *  - it can be instructed that all unknown keys move to secondary array or to delete them
 *
 * Rules used for this particular class are:
 *  - keys that are mapped as boolean false will be removed from result
 *  - keys that are mapped as empty string will be moved to secondary buffer
 *  - all unknown keys are preserved by default
 */

//use \Accent\AccentCore\Component;


class Mapper {


    protected $Map;
    protected $ReMap;


    /**
     * Constructor.
     *
     * @param array $Map
     */
    public function __construct($Map) {

        // export map to protected variables
        $this->Map= $Map;
        $this->ReMap= array_flip(array_filter($Map));
    }


    /**
     * Pack supplied associative array following according to rules.
     *
     * @param array $Values  input array of values
     * @param int $Purgatory  where to send values from unmapped keys: 1=Primary, 2=Secondary, 0=delete
     * @param bool $AddMissingKeys  set all mapped keys without supplied value to null
     * @param bool $ReturnBothBuffers  should return both buffers as array(0=>Pri., 1=>Sec.)
     * @return array|false  Primary buffer or both buffers
     */
    public function MapArray($Values, $Purgatory=1, $AddMissingKeys=true, $ReturnBothBuffers=false) {

        if (!is_array($Values)) {
            return false;
        }
        $Primary= array();
        $Secondary= array();
        // first process all expected keys
        foreach ($this->Map as $k => $v) {
            // is it instructed for removing from result?
            if ($v === false) {
                unset($Values[$k]);
                continue;
            }
            // is it instructed for redirecting to secondary buffer?
            if ($v === '' && isset($Values[$k])) {
                $Secondary[$k]= $Values[$k];
                unset($Values[$k]);
                continue;
            }
            // using option to re-create missing keys guarantee at least null value at result
            if ($AddMissingKeys) {
                $Primary[$v]= null;
            }
            // if key found send its value to result
            if (isset($Values[$k])) {
                $Primary[$v]= $Values[$k];
                unset($Values[$k]);
            }
        }
        // where to send unmapped keys?
        switch ($Purgatory) {
            case 1: $Primary += $Values; break;
            case 2: $Secondary += $Values; break;
        }
        // return resulting array(s)
        return $ReturnBothBuffers
            ? array($Primary, $Secondary)
            : $Primary;
    }


    /**
     * Reversed transformation of supplied associative array, according to rules.
     *
     * @param array $Values  input array of values
     * @param int $Purgatory  where to send values from unmapped keys: 1=Primary, 2=Secondary, 0=delete
     * @param bool $AddMissingKeys  set all mapped keys without supplied value to null
     * @param bool $ReturnBothBuffers  should return both buffers as array(0=>Pri., 1=>Sec.)
     * @return array|false  Primary buffer or both buffers
     */
    public function ReMapArray($Values, $Purgatory=1, $AddMissingKeys=true, $ReturnBothBuffers=false) {

        if (!is_array($Values)) {
            return false;
        }
        $Primary= array();
        $Secondary= array();
        // first process all expected keys
        foreach ($this->Map as $k => $v) {
            // is it instructed for removing from result?
            if ($v === false) {
                unset($Values[$k]);
                continue;
            }
            // is it instructed for redirecting to secondary buffer?
            if ($v === '' && isset($Values[$k])) {
                $Secondary[$k]= $Values[$k];
                unset($Values[$k]);
                continue;
            }
            // using option to re-create missing keys guarantee at least null value at result
            if ($AddMissingKeys) {
                $Primary[$k]= null;
            }
            // if key found send its value to result
            if (isset($Values[$v])) {
                $Primary[$k]= $Values[$v];
                unset($Values[$v]);
            }
        }
        // where to send unmapped keys?
        switch ($Purgatory) {
            case 1: $Primary += $Values; break;
            case 2: $Secondary= $Values; break;
        }
        // return resulting array(s)
        return $ReturnBothBuffers
            ? array($Primary, $Secondary)
            : $Primary;
    }


    /**
     * Perform same transformation as MapArray but on array of arrays.
     * Keys of main array are preserved.
	 *
	 * @param array $Values
	 * @param int $Purgatory
	 * @param bool $AddMissingKeys
	 * @param bool $ReturnBothBuffers
	 * @return array|false
     */
    public function MapArray2D($Values, $Purgatory=1, $AddMissingKeys=true, $ReturnBothBuffers=false) {

        if (!is_array($Values)) {
            return false;
        }
        foreach ($Values as &$RowData) {
            // using reference is safe because $Values is copy of supplied array
            if (!is_array($RowData)) {
                return false;
            }
            $RowData= $this->MapArray($RowData, $Purgatory, $AddMissingKeys, $ReturnBothBuffers);
        }
        return $Values;
    }


    /**
     * Perform same transformation as ReMapArray but on array of arrays.
     * Keys of main array are preserved.
	 *
	 * @param array $Values
	 * @param int $Purgatory
	 * @param bool $AddMissingKeys
	 * @param bool $ReturnBothBuffers
	 * @return array|false
     */
    public function ReMapArray2D($Values, $Purgatory=1, $AddMissingKeys=true, $ReturnBothBuffers=false) {

        if (!is_array($Values)) {
            return false;
        }
        foreach ($Values as &$RowData) {
            // using reference is safe because $Values is copy of supplied array
            if (!is_array($RowData)) {
                return false;
            }
            $RowData= $this->ReMapArray($RowData, $Purgatory, $AddMissingKeys, $ReturnBothBuffers);
        }
        return $Values;
    }


    /**
     * Return map itself.
     *
     * @return array
     */
    public function GetMap() {

        return $this->Map;
    }


    /**
     * Get mapped name for specified key.
     *
     * @param string $Key  requested key
     * @param bool $Pass  return unmodified key if it not exist instead of null
     * @return string|null
     */
    public function MapKey($Key, $Pass=false) {

        return isset($this->Map[$Key])
            ? $this->Map[$Key]
            : ($Pass ? $Key : null);
    }


    /**
     * Get original name for specified renamed key.
     *
     * @param string $MappedKey  requested key
     * @param bool $Pass  return unmodified key if it not exist instead of null
     * @return string|null
     */
    public function ReMapKey($MappedKey, $Pass=false) {

        return isset($this->ReMap[$MappedKey])
            ? $this->ReMap[$MappedKey]
            : ($Pass ? $MappedKey : null);
    }

}
