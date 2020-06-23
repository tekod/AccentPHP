<?php namespace Accent\AccentCore\ArrayUtils;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * ArrayCollection represents array of arbitrary items
 * featured with common functionalities for manipulating them.
 */


class Collection implements \IteratorAggregate {


    /**
     * Internal buffer containing items.
     * @var array
     */
    protected $Buffer;


    /**
     * Flag indicating that collection is modified.
     * @var boolean
     */
    protected $Modified;


    /**
     * Constructor
	 *
	 * @param array $Collection
     */
    public function __construct($Collection=array()) {

        $this->Import($Collection);
        $this->Modified= !empty($Collection);
    }


    /**
     * This method is called by all setters to perform on-fly normalization before inserting value into collection.
     *
     * @param mixed $Value
     * @return mixed
     */
    protected function Normalize($Value) {

        return $Value;
    }


    /**
     * Clears the collection.
     *
     * @return self
     */
    public function Clear() {

        $this->Buffer= array();
        $this->Modified= true;
        return $this;
    }


    /**
     * Set whole collection in one call.
     * Previous content will be deleted.
     *
     * @param array $Array
     * @return self
     */
    public function Import($Array) {

        $this->Buffer= array_map(array($this,'Normalize'), (array)$Array);
        $this->Modified= true;
        return $this;
    }


    /**
     * Magic method, called if object was called as a function.
     * Returns specified element of collection, same as this->Get(Key).
     *
     * @param int|string  key of item in collection
     * @return mixed
     */
    public function __invoke($Key) {

        return $this->Get($Key);
    }


    /**
     * Implementation for IteratorAggregate interface.
     * Used for foreach loops.
     *
     * @return \ArrayIterator
     */
    public function getIterator() {

        return new \ArrayIterator($this->Buffer);
    }


    /**
     * Returns the number of items in the collection.
     *
     * @return int
     */
    public function Count() {

        return count($this->Buffer);
    }


    /**
     * Checks is the collection empty.
     *
     * @return boolean
     */
    public function IsEmpty() {

        return empty($this->Buffer);
    }

    /**
     * Returns whole collection as array.
     *
     * @return array
     */
    public function ToArray() {

        return $this->Buffer;
    }


    /**
     * Synonim for ToArray().
     *
     * @return array
     */
    public function AsArray() {

        return $this->Buffer;
    }


    /**
     * Returns whole collection packed as JSON string.
     *
     * @return string
     */
    public function ToJSON() {

        return json_encode($this->Buffer, JSON_UNESCAPED_UNICODE, 512);
    }


//---------------------------------------------------------------------------
//
//             Methods for manipulating with individual items
//
//---------------------------------------------------------------------------


    /**
     * Returns specified item of collection
     * or null if item not exist.
     *
     * @param int|string $Key  index or key of item
     * @param mixed $DefaultValue  default value
     * @return mixed
     */
    public function Get($Key, $DefaultValue=null) {

        return isset($this->Buffer[$Key])
            ? $this->Buffer[$Key]
            : $DefaultValue;
    }


    /**
     * Puts or adds item to the collection at specified position.
     *
     * @param int|string $Key  index or key of item
     * @param mixed $Value  value to store in collection
     * @return self  chaining this object
     */
    public function Set($Key, $Value) {

        $this->Buffer[$Key]= $this->Normalize($Value);
        $this->Modified= true;
        return $this;
    }


    /**
     * Adds item to the end of collection.
     *
     * @param mixed $Value
     * @return self  chaining this object
     */
    public function Append($Value) {

        $this->Buffer[]= $this->Normalize($Value);
        $this->Modified= true;
        return $this;
    }


    /**
     * Deletes item at specified key (index) from the collection.
     *
     * @param int|string $Key
     * @return self  chaining this object
     */
    public function Remove($Key) {

        unset($this->Buffer[$Key]);
        $this->Modified= true;
        return $this;
    }

    /**
     * Deletes item specified by its value from the collection.
     * Searching for value is strict by type.
     *
     * @param mixed $Value   value of item to remove
     * @return self
     */
    public function RemoveByValue($Value) {

        $Key= array_search($Value, $this->Buffer, true);

        if ($Key !== false) {
            unset($this->Buffer[$Key]);
            $this->Modified= true;
        }

        return $this;
    }


    /**
     * Adds item to the end of collection. Same as method Append().
     *
     * @param mixed $Value
     * @return self  chaining this object
     */
    public function Push($Value) {

        array_push($this->Buffer, $this->Normalize($Value));
        $this->Modified= true;
        return $this;
    }


    /**
     * Remove last item from the collection and returns its value.
     *
     * @return mixed
     */
    public function Pop() {

        $Result= array_pop($this->Buffer);
        $this->Modified= true;
        return $Result;
    }


    /**
     * Adds item at the beginning of the collection.
     *
     * @param mixed $Value
     * @return self  chaining this object
     */
    public function UnShift($Value) {

        array_unshift($this->Buffer, $this->Normalize($Value));
        $this->Modified= true;
        return $this;
    }


    /**
     * Remove first item of the collection and returns its value.
     *
     * @return mixed
     */
    public function Shift() {

        $Result= array_shift($this->Buffer);
        $this->Modified= true;
        return $Result;
    }


    /**
     * Get element specified using "dotted notation".
     *
     * @param string $Key
     * @param mixed $DefaultValue
     * @return mixed
     */
    public function GetDotted($Key, $DefaultValue=null) {

        $Pointer= &$this->Buffer;
        foreach(explode('.', $Key) as $K) {
            if (!isset($Pointer[$K])) {
                return $DefaultValue;
            }
            $Pointer= &$Pointer[$K];
        }
        return $Pointer;
    }


    /**
     * Put element specified using "dotted notation".
     *
     * @param int|string $Key  index or key of item
     * @param mixed $Value  value to store in collection
     * @return self  chaining this object
     */
    public function SetDotted($Key, $Value) {

        $Pointer= &$this->Buffer;
        foreach(explode('.', $Key) as $K) {
            $Pointer= &$Pointer[$K];
        }
        $Pointer= $this->Normalize($Value);

        $this->Modified= true;
        return $this;
    }



//---------------------------------------------------------------------------
//
//                Methods for searching through collection
//
//---------------------------------------------------------------------------



    /**
     * Checks is specified key (index) defined in the collection.
     *
     * @param int|string $Key
     * @return boolean
     */
    public function HasKey($Key) {

        return isset($this->Buffer[$Key]);
    }


    /**
     * Checks is specified value contained anywhere in the collection.
     * Searching is strict by type.
     *
     * @param mixed $Value
     * @return boolean
     */
    public function HasValue($Value) {

        return array_search($Value, $this->Buffer, true) !== false;
    }


    /**
     * Searches for a item with specified value and returns its key (index),
     * or false if such item not found.
     * Searching is strict by type.
     *
     * @param mixed $Value
     * @return int|string|false
     */
    public function IndexOf($Value) {

        return array_search($Value, $this->Buffer, true);
    }



//---------------------------------------------------------------------------
//
//                   Handling "Modified" flag
//
//---------------------------------------------------------------------------


    /**
     * Reset "modified" flag.
     *
     * @return self
     */
    public function ModifiedClear() {

        $this->Modified= false;
        return $this;
    }


    /**
     * Returns state of "modified" flag.
     *
     * @return bool
     */
    public function ModifiedGet() {

        return $this->Modified;
    }



//---------------------------------------------------------------------------
//
//                          Utilities
//
//---------------------------------------------------------------------------


    /**
     * Returns all keys (indexes) of the collection.
     *
     * @return array
     */
    public function GetAllKeys() {

        return array_keys($this->Buffer);
    }


    /**
     * Returns all values of the collection.
     * Keys are renumbered from zero.
     *
     * @return array
     */
    public function GetAllValues() {

        return array_values($this->Buffer);
    }


    /**
     * Returns array of slices of collection, each of specified maximum length.
     *
     * @param int $Length
     * @param boolean $PreserveKeys
     * @return array
     */
    public function GetChunked($Length, $PreserveKeys=false) {

        return array_chunk($this->Buffer, max(1,intval($Length)), $PreserveKeys);
    }


    /**
     * Returns part of collection, starting at position $Offset and containing $Length items.
     * Values in collection are not modified.
     * Keys of resulted array are preserved.
     *
     * @param int $Offset  count how many items to skip before slice,
     *                      negative number for counting from the end of collection
     * @param int $Length  count how many items to copy in result,
     *                      negative number for stopping at position from the end of collection
     *                      null to return all items to the end of the collection
     * @return array
     */
    public function GetSliced($Offset, $Length=null) {

        return array_slice($this->Buffer, $Offset, $Length, true);
    }


    /**
     * Similar to GetSlice() but imports result in the collection.
	 *
	 * @param int $Offset
	 * @param int $Length
	 * @return self
     */
    public function Slice($Offset, $Length=null) {

        $this->Buffer= $this->GetSliced($Offset, $Length);
        $this->Modified= true;
        return $this;
    }



    /**
     * Replace a portion of the collection with something else.
     * Offset and Length designates which items will be replaced by Replacements.
     * Note that count of replaced and replacements not need to be same.
     * Numeric keys will be renumbered.
     *
     * @param int $Offset  count how many items to skip before splice,
     *                      negative number for counting from the end of collection
     * @param int $Length  count how many items to replace,
     *                      negative number for stopping at position from the end of collection
     *                      null to return all items to the end of the collection
     * @param int|string|array $Replacements  array or single item to insert into collection
     * @return self
     */
    public function Splice($Offset, $Length, $Replacements) {

        if ($Length === null) {
            $Length= count($this->Buffer);
        }
        $Replacements= is_array($Replacements)
            ? array_map(array($this,'Normalize'), $Replacements)
            : $this->Normalize($Replacements);
        array_splice($this->Buffer, $Offset, $Length, $Replacements);
        $this->Modified= true;
        return $this;
    }


    /**
     * Returns array built by merging collection and all supplied arrays.
     * Items with associative keys will overwrite items in previous array.
     * Items with numeric keys will be renumbered and append to end of result.
     * Merging is not recursive.
     * Values in collection are not modified.
     *
     * @param array $Array1, $Array2, $Array3, $Array4
     * @return array
     */
    public function GetMerged($Array1, $Array2=array(), $Array3=array(), $Array4=array()) {

        return array_merge(
            $this->Buffer,
            array_map(array($this,'Normalize'), $Array1),
            array_map(array($this,'Normalize'), $Array2),
            array_map(array($this,'Normalize'), $Array3),
            array_map(array($this,'Normalize'), $Array4)
        );
    }


    /**
     * Similar to GetMerged() but imports result in the collection.
     */
    public function Merge($Array1, $Array2=array(), $Array3=array(), $Array4=array()) {

        $this->Buffer= $this->GetMerged($Array1, $Array2, $Array3, $Array4);
        $this->Modified= true;
        return $this;
    }


    /**
     * Returns array padded to specified length with specified value.
     * Associative keys will be preserved, numeric keys will be renumbered.
     * Note that resulting array will NOT be sliced if specified $Length is already
     * smaller then length of collection.
     * Values in collection are not modified.
     *
     * @param int $Length  total length of resulting array
     * @param mixed $Value  value to set in all additional items
     * @param boolean $AtEnd  true to append new items, false to prepend
     * @return array
     */
    public function GetPadded($Length, $Value, $AtEnd=true) {

        return array_pad($this->Buffer, $AtEnd ? $Length : -$Length, $this->Normalize($Value));
    }


    /**
     * Similar to GetPadded() but imports result in the collection.
	 *
	 * @param int $Length
	 * @param mixed $Value
	 * @param bool $AtEnd
	 * @return self
     */
    public function Pad($Length, $Value, $AtEnd=true) {

        $this->Buffer= $this->GetPadded($Length, $Value, $AtEnd);
        $this->Modified= true;
        return $this;
    }


    /**
     * Sorts items in collection according to callback.
     * Callback should return [-1|0|1]. See: usort().
     * If callback not defined collection will be sorted by natcasesort().
     * Keys of resulted array are preserved.
     *
     * @param boolean $SortByKeys
     * @param callable $Callback
     * @return self
     */
    public function Sort($SortByKeys=false, $Callback=null) {

        if ($Callback === null) {
            if ($SortByKeys) {
                ksort($this->Buffer);
            } else {
                natcasesort($this->Buffer);
            }
        } else {
            if ($SortByKeys) {
                uksort($this->Buffer, $Callback);
            } else {
                uasort($this->Buffer, $Callback);
            }
        }
        $this->Modified= true;
        return $this;
    }


    /**
     * Applies callback to each item in the collection.
     * For callback signature see: array_map().
     *
     * @param callable $Callback
     * @return self
     */
    public function Map($Callback) {

        $this->Buffer= array_map($Callback, $this->Buffer);
        $this->Modified= true;
        return $this;
    }

    /**
     * Removes all items from collection that are not approved by callback.
     * Callback must return boolean, see: array_filter().
     * Keys of resulted array are preserved.
     *
     * @param callable $Callback
     * @return self
     */
    public function Filter($Callback) {

        $this->Buffer= array_filter($this->Buffer, $Callback);
        $this->Modified= true;
        return $this;
    }


    /**
     * Splits collection in two arrays according to callback.
     * Callback must return boolean, similar to array_filter().
     * Items resolved as "true" will be sent to first array and others to second array.
     * Values in collection are not modified.
     * Keys of resulted array are preserved.
     *
     * @param callable $Callback
     * @return array  array of both arrays
     */
    public function GetSplit($Callback) {

        $Result1= $Result2= array();
        foreach ($this->Buffer as $Key => $Value) {
            if ($Callback($Key, $Value)) {
                $Result1[$Key]= $Value;
            } else {
                $Result2[$Key]= $Value;
            }
        }
        return array($Result1, $Result2);
    }

}


