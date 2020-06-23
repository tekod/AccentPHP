<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\ArrayUtils\Mapper;


/**
 * Testing Accent\AccentCore\ArrayUtils\Mapper
 */

class Test__Mapper extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'ArrayUtils / Mapper';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    public function TestSimpleTransform() {

        $Map= array(
            'id'=> 'uId',
            'user'=> 'Username',
            'pass'=> 'Password',
        );
        $Origin= array(
            'id'=> 4,
            'user'=> 'Ana',
            'pass'=> 'xyz',
        );
        $Customized= array(
            'uId'=> 4,
            'Username'=> 'Ana',
            'Password'=> 'xyz',
        );
        $Mapper= new Mapper($Map);
        // transform array
        $Returned= $Mapper->MapArray($Origin);
        $this->assertEqual($Returned, $Customized);
        // reverse
        $Reversed= $Mapper->ReMapArray($Returned);
        $this->assertEqual($Reversed, $Origin);
    }


    public function TestRemovingKeys() {

        $Map= array(
            'id'=> false,
            'user'=> 'Username',
            'pass'=> 'Password',
        );
        $Origin= array(
            'id'=> 4,
            'user'=> 'Ana',
            'pass'=> 'xyz',
        );
        $Customized= array(
            'Username'=> 'Ana',
            'Password'=> 'xyz',
        );
        $Mapper= new Mapper($Map);
        // transform array
        $Returned= $Mapper->MapArray($Origin);
        $this->assertEqual($Returned, $Customized);
        // reverse
        $Reversed= $Mapper->ReMapArray($Returned);
        unset($Origin['id']);
        $this->assertEqual($Reversed, $Origin);
    }


    public function TestPurgatoryParameter() {

        $Map= array(
            'Name'=> 'name',
        );
        $Mapper= new Mapper($Map);
        // transform array with Purgatory=delete
        $Origin= array(
            'Name'=> 'Miro',
            'City'=> 'Novi Sad',
        );
        $Returned= $Mapper->MapArray($Origin, 0);
        $Expected= array(
            'name'=> 'Miro',
        );
        $this->assertEqual($Returned, $Expected);
        // check in secondary array ($ReturnBothBuffers=true)
        $Returned= $Mapper->MapArray($Origin, 0, true, true);
        $Expected= array($Expected, array());
        $this->assertEqual($Returned, $Expected);
        // reverse
        $Origin= array(
            'name'=> 'Miro',
            'city'=> 'Novi Sad',
        );
        $Returned= $Mapper->ReMapArray($Origin, 0);
        $Expected= array(
            'Name'=> 'Miro',
        );
        $this->assertEqual($Returned, $Expected);
        // check in secondary array ($ReturnBothBuffers=true)
        $Returned= $Mapper->ReMapArray($Origin, 0, true, true);
        $Expected= array($Expected, array()); // must be empty

        // transform array with Purgatory=Primary
        $Origin= array(
            'Name'=> 'Miro',
            'City'=> 'Novi Sad',
        );
        $Returned= $Mapper->MapArray($Origin, 1);
        $Expected= array(
            'name'=> 'Miro',
            'City'=> 'Novi Sad',
        );
        $this->assertEqual($Returned, $Expected);
        // check in secondary array ($ReturnBothBuffers=true)
        $Returned= $Mapper->MapArray($Origin, 1, true, true);
        $Expected= array($Expected, array()); // must be empty
        $this->assertEqual($Returned, $Expected);
        // reverse
        $Origin= array(
            'name'=> 'Miro',
            'city'=> 'Novi Sad',
        );
        $Returned= $Mapper->ReMapArray($Origin, 1);
        $Expected= array(
            'Name'=> 'Miro',
            'city'=> 'Novi Sad',
        );
        $this->assertEqual($Returned, $Expected);
        // check in secondary array ($ReturnBothBuffers=true)
        $Returned= $Mapper->ReMapArray($Origin, 1, true, true);
        $Expected= array($Expected, array()); // must be empty

        // transform array with Purgatory=Secondary
        $Origin= array(
            'Name'=> 'Miro',
            'City'=> 'Novi Sad',
        );
        $Returned= $Mapper->MapArray($Origin, 2);
        $Expected= array(
            'name'=> 'Miro',
        );
        $this->assertEqual($Returned, $Expected);
        // check in secondary array ($ReturnBothBuffers=true)
        $Returned= $Mapper->MapArray($Origin, 2, true, true);
        $Expected= array($Expected, array('City'=> 'Novi Sad'));
        $this->assertEqual($Returned, $Expected);
        // reverse
        $Origin= array(
            'name'=> 'Miro',
            'city'=> 'Novi Sad',
        );
        $Returned= $Mapper->ReMapArray($Origin, 2);
        $Expected= array(
            'Name'=> 'Miro',
        );
        $this->assertEqual($Returned, $Expected);
        // check in secondary array ($ReturnBothBuffers=true)
        $Returned= $Mapper->ReMapArray($Origin, 2, true, true);
        $Expected= array($Expected, array('city'=> 'Novi Sad'));
    }


    public function TestAddingMissingKeys() {

        $Map= array(
            'Day'=> 'Day',
            'Month'=> 'Month',
            'Year'=> 'Year',
        );
        $Mapper= new Mapper($Map);
        // transform array using AddMissingKeys=true
        $Origin= array(
            'Year'=> 2008,
        );
        $Returned= $Mapper->MapArray($Origin, 0, true);
        $Expected= array(
            'Day'=> null,
            'Month'=> null,
            'Year'=> 2008,
        );
        $this->assertEqual($Returned, $Expected);
        // reverse
        $Origin= array(
            'Month'=> 3,
            'Year'=> 1999,
        );
        $Returned= $Mapper->ReMapArray($Origin, 0, true);
        $Expected= array(
            'Day'=> null,
            'Month'=> 3,
            'Year'=> 1999,
        );
        $this->assertEqual($Returned, $Expected);
        // transform array using AddMissingKeys=false
        $Origin= array(
            'Year'=> 2008,
        );
        $Returned= $Mapper->MapArray($Origin, 0, false);
        $Expected= $Origin;
        $this->assertEqual($Returned, $Expected);
        // reverse
        $Origin= array(
            'Year'=> 1999,
        );
        $Returned= $Mapper->ReMapArray($Origin, 0, false);
        $Expected= $Origin;
        $this->assertEqual($Returned, $Expected);
    }


    public function Test2dArrays() {

        $Map= array(
            'City'=> 'city',
            'Country'=> 'country',
        );
        $Mapper= new Mapper($Map);
        $Origin= array(
            array('City'=>'Tokyo','Country'=>'Japan'),
            array('City'=>'Oslo','Country'=>'Norway'),
            array('City'=>'Madrid','Country'=>'Spain'),
        );
        $Returned= $Mapper->MapArray2D($Origin);
        $Expected= array(
            array('city'=>'Tokyo','country'=>'Japan'),
            array('city'=>'Oslo','country'=>'Norway'),
            array('city'=>'Madrid','country'=>'Spain'),
        );
        $this->assertEqual($Returned, $Expected);
        // reverse
        $Reversed= $Mapper->ReMapArray2D($Returned);
        $Expected= $Origin;
        $this->assertEqual($Reversed, $Expected);
    }


    public function TestSingleKeys() {

        $Map= array(
            'City'=> 'city',
            'Country'=> 'country',
        );
        $Mapper= new Mapper($Map);
        // test MapKey()
        $Returned= $Mapper->MapKey('City');
        $Expected= 'city';
        $this->assertEqual($Returned, $Expected);
        // test ReMapKey()
        $Returned= $Mapper->ReMapKey('city');
        $Expected= 'City';
        $this->assertEqual($Returned, $Expected);
        // test MapKey() with unknown key, with passing option
        $Returned= $Mapper->MapKey('abc', true);
        $Expected= 'abc';
        $this->assertEqual($Returned, $Expected);
        // test MapKey() with unknown key, without passing option
        $Returned= $Mapper->MapKey('abc', false);
        $Expected= null;
        $this->assertEqual($Returned, $Expected);
    }

}


