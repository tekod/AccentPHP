<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\ArrayUtils\Collection;


/**
 * Testing Accent\AccentCore\ArrayUtils\Collection
 */

class Test__Collection extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'ArrayUtils / Collection test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';



    public function TestBuild() {
        // build empty collection
        $C= new Collection;
        $this->assertTrue(empty($C->ToArray()));
        // build predefined collection
        $C2= new Collection(array('a','b'));
        $this->assertIdentical(count($C2->ToArray()), 2);
    }


    public function TestClear() {
        // build predefined collection
        $C= new Collection(array('a','b'));
        $this->assertIdentical(count($C->ToArray()), 2);
        // clear collection and count again
        $C->Clear();
        $this->assertIdentical(count($C->ToArray()), 0);
    }


    public function TestImport() {

        $C= new Collection;
        // import 2 elements in empty collection
        $C->Import(array('x','y'));
        $this->assertIdentical(count($C->ToArray()), 2);
        // import another element in non-empty collection
        $C->Import(array('z'));
        $this->assertIdentical(count($C->ToArray()), 1);   // each import must overwrite existing items
    }


    public function TestInvoke() {

        $C= new Collection(array('a','b','c'));
        // call object as function and specify key
        $this->assertIdentical($C(0), 'a');
        $this->assertIdentical($C(2), 'c');
        $this->assertIdentical($C(3), null);
    }


    public function TestAssociativeKeys() {

        $C= new Collection(array('name'=>'Nikola'));
        $this->assertIdentical($C(0), null);
        $this->assertIdentical($C('name'), 'Nikola');
    }


    public function TestIterator() {

        $C= new Collection(array(1,2,3));
        // fetch all items in loop using foreach statement
        $Sum= 0;
        foreach ($C as $c) {
            $Sum += $c;
        }
        $this->assertIdentical($Sum, 6);
        // fetch from empty collection
        $C2= new Collection;
        foreach ($C2 as $c2) {
            $this->fail('Iterating inside of empty collection: ('.$c2.')');
        }
    }


    public function TestCount() {

        $C= new Collection;
        $this->assertIdentical($C->Count(), 0);
        $C->Import(array(5,6,7));
        $this->assertIdentical($C->Count(), 3);
    }


    public function TestIsEmpty() {

        $C= new Collection;
        $this->assertIdentical($C->IsEmpty(), true);
        $C->Import(array(5,6));
        $this->assertIdentical($C->IsEmpty(), false);
    }


    public function TestToJSON() {

        $C= new Collection(array('a','b'));
        $this->assertIdentical($C->ToJSON(), '["a","b"]');
        // test again with empty collection
        $C2= new Collection;
        $this->assertIdentical($C2->ToJSON(), '[]');
    }


    public function TestGetSet() {

        $C= new Collection;
        $this->assertIdentical($C->Get(1), null);
        // now set value
        $C->Set(1, 'one');
        $this->assertIdentical($C->Get(1), 'one');
        // overwrite
        $C->Set(1, 'two');
        $this->assertIdentical($C->Get(1), 'two');
        // validate count
        $this->assertIdentical(count($C->ToArray()), 1);
    }


    public function TestAppend() {

        $C= new Collection(array('a','b','x'=>'c'));
        // next appened element should have index 2
        $C->Append('z');
        $this->assertIdentical($C(2), 'z');
    }


    public function TestRemove() {

        $C= new Collection(array('a','b','c'));
        $C->Remove(1);
        $this->assertIdentical($C->Count(), 2);
        $this->assertIdentical($C(1), null);
        // remove non-existant element should not produce any error
        $C->Remove(99);
        $this->assertIdentical($C->Count(), 2);
    }


    public function TestRemoveByValue() {

        $C= new Collection(array('a','b','c'));
        $C->RemoveByValue('b');
        $this->assertIdentical($C->Count(), 2);
        $this->assertIdentical($C(1), null);
        // remove non-existant element should not produce any error
        $C->RemoveByValue('xyz');
        $this->assertIdentical($C->Count(), 2);
    }


    public function TestPushPop() {

        $C= new Collection;
        $this->assertIdentical($C->Pop(), null);  // pop from empty collection should not produce error
        $C->Push('a');  // push in empty collection
        $C->Push('b');  // push in non-empty collection
        $this->assertIdentical($C->ToArray(), array('a','b'));
        $this->assertIdentical($C->Pop(), 'b');
        $this->assertIdentical($C->Pop(), 'a');
    }


    public function TestShiftUnshift() {

        $C= new Collection;
        $this->assertIdentical($C->Shift(), null);  // pop from empty collection should not raise error
        $C->Unshift('a');  // add in empty collection
        $C->Unshift('b');  // add in non-empty collection
        $this->assertIdentical($C->ToArray(), array('b','a'));
        $this->assertIdentical($C->Shift(), 'b');
        $this->assertIdentical($C->Shift(), 'a');
    }


    public function TestHasKey() {

        $C= new Collection;
        $this->assertIdentical($C->HasKey('b'), false);   // searching in empty collection should not produce error
        $C->Import(array(
            'a'=> 'aaaaaa',
            'b'=> function(){die('Accent/ArrayUtils/Test_Collection/TestHasKey has triggered value instead of just checking key.');},
        ));
        $this->assertIdentical($C->HasKey('b'), true);
        $this->assertIdentical($C->HasKey(0), false);
        $this->assertIdentical($C->HasKey('bbbbb'), false);
    }


    public function TestHasValue() {

        $C= new Collection;
        $this->assertIdentical($C->HasValue('x'), false);   // searching in empty collection should not produce error
        $C->Import(array(
            'a'=> 'x',
            'b'=> 'y',
        ));
        $this->assertIdentical($C->HasValue('b'), false);   // 'b' is key
        $this->assertIdentical($C->HasValue('x'), true);
        $this->assertIdentical($C->HasValue(0), false);
        $this->assertIdentical($C->HasValue(true), false);
        $this->assertIdentical($C->HasValue(false), false);
        $this->assertIdentical($C->HasValue(null), false);
    }


    public function TestIndexOf() {

        $C= new Collection;
        $this->assertIdentical($C->IndexOf('b'), false);
        $C->Import(array(
            'a'=> 'x',
            'b'=> 'y',
            9  => 999,
        ));
        $this->assertIdentical($C->IndexOf('y'), 'b');  // associative key
        $this->assertIdentical($C->IndexOf('Y'), false);  // case sensitive
        $this->assertIdentical($C->IndexOf(999), 9);  // numeric key
        $this->assertIdentical($C->IndexOf(0), false);  // non-existant key
        $this->assertIdentical($C->IndexOf('999'), false);  // associative key (strict var type)
    }


    public function TestGetAllKeys() {

        $C= new Collection;
        $this->assertIdentical($C->GetAllKeys(), array());
        $C->Import(array(
            'a'=> 'x',
            'b'=> 'y',
        ));
        $this->assertIdentical($C->GetAllKeys(), array(0=>'a', 1=>'b'));
    }

    public function TestGetAllValues() {

        $C= new Collection;
        $this->assertIdentical($C->GetAllValues(), array());
        $C->Import(array(
            'a'=> 'x',
            'b'=> 'y',
        ));
        $this->assertIdentical($C->GetAllValues(), array(0=>'x', 1=>'y'));
    }


    public function TestGetChunked() {

        $C= new Collection;
        $this->assertIdentical($C->GetChunked(3), array());
        $C->Import(array(1,2,3,4,5,6,7,8));
        $this->assertIdentical($C->GetChunked(3), array(array(1,2,3),array(4,5,6),array(7,8)));
        $this->assertIdentical($C->GetChunked(9), array(array(1,2,3,4,5,6,7,8)));
        $this->assertIdentical($C->GetChunked(1), array(array(1),array(2),array(3),array(4),array(5),array(6),array(7),array(8)));
        $this->assertIdentical($C->GetChunked('m'), array(array(1),array(2),array(3),array(4),array(5),array(6),array(7),array(8)));
    }


    public function TestSliceGetSliced() {

        $C= new Collection;
        $this->assertIdentical($C->GetSliced(2), array());      // behaviour on empty collection
        $C->Import(array(1,2,3,4,5,6));
        $this->assertIdentical($C->GetSliced(2), array(2=>3,3=>4,4=>5,5=>6));  // positive offset, null length
        $this->assertIdentical($C->GetSliced(2, 2), array(2=>3,3=>4));  // positive offset, positive length
        $this->assertIdentical($C->GetSliced(2, -1), array(2=>3,3=>4,4=>5));  // positive offset, negative length
        $this->assertIdentical($C->GetSliced(-2), array(4=>5,5=>6));  // negative offset, null length
        $this->assertIdentical($C->GetSliced(-2, 1), array(4=>5));  // negative offset, positive length
        $this->assertIdentical($C->GetSliced(-3, -1), array(3=>4,4=>5));  // negative offset, negative length
        $this->assertIdentical($C->GetSliced(23), array());  // offset out of range
        // test Slice(), it calling GetSliced internally and store result in collection
        $C->Slice(-3, -1);
        $this->assertIdentical($C->ToArray(), array(3=>4,4=>5));
    }


    public function TestSplice() {

        $C= new Collection;
        // behaviour on empty collection
        $C->Splice(2, 2, array('x'));
        $this->assertIdentical($C->ToArray(), array('x')); // it is added to collection
        // positive offset, null length
        $C->Import(array(1,2,3,4,5,6));
        $C->Splice(2, null, 'x');
        $this->assertIdentical($C->ToArray(), array(0=>1,1=>2,2=>'x'));
        // positive offset, positive length
        $C->Import(array(1,2,3,4,5,6));
        $C->Splice(2, 2, 'x');
        $this->assertIdentical($C->ToArray(), array(0=>1,1=>2,2=>'x',3=>5,4=>6));  // keys are renumbered
        // positive offset, negative length
        $C->Import(array(1,2,3,4,5,6));
        $C->Splice(2, -1, 'x');
        $this->assertIdentical($C->ToArray(), array(0=>1,1=>2,2=>'x',3=>6));
        // negative offset, null length
        $C->Import(array(1,2,3,4,5,6));
        $C->Splice(-2, null, 'x');
        $this->assertIdentical($C->ToArray(), array(0=>1,1=>2,2=>3,3=>4,4=>'x'));
        // negative offset, positive length
        $C->Import(array(1,2,3,4,5,6));
        $C->Splice(-2, 1, 'x');
        $this->assertIdentical($C->ToArray(), array(0=>1,1=>2,2=>3,3=>4,4=>'x',5=>6));
        // negative offset, negative length
        $C->Import(array(1,2,3,4,5,6));
        $C->Splice(-3, -1, 'x');
        $this->assertIdentical($C->ToArray(), array(0=>1,1=>2,2=>3,3=>'x',4=>6));
    }


    public function TestMergeGetMerged() {

        $C= new Collection;
        // merging with empty collection
        $this->assertIdentical($C->GetMerged(array('a')), array('a'));
        // merging with collections of 2 elements
        $C->Import(array('a','b'));
        $this->assertIdentical($C->GetMerged(array('a')), array('a','b','a'));
        // multiple
        $this->assertIdentical($C->GetMerged(array('x'),array('y'),array(1,2)), array('a','b','x','y',1,2));
        // test Merge(), it calling GetMerged internally and store result in collection
        $C->Merge(array(5,3));
        $this->assertIdentical($C->ToArray(), array(0=>'a', 1=>'b', 2=>5, 3=>3));
    }


    public function TestPadGetPadded() {

        $C= new Collection;
        // padding empty collection
        $this->assertIdentical($C->GetPadded(3, 'a'), array('a','a','a'));
        // padding collection of 2 elements
        $C->Import(array('a','b'));
        $this->assertIdentical($C->GetPadded(4, 'x'), array('a','b','x','x'));
        // padding too long collection should not truncate it
        $C->Import(array('a','b'));
        $this->assertIdentical($C->GetPadded(1, 'x'), array('a','b'));
        // padding at begining of collection
        $C->Import(array('a','b'));
        $this->assertIdentical($C->GetPadded(4, 'x', false), array('x','x','a','b'));
        // test Pad(), it calling GetPadded internally and store result in collection
        $C->Import(array('a','b'));
        $C->Pad(4, 'm');
        $this->assertIdentical($C->ToArray(), array(0=>'a', 1=>'b', 2=>'m', 3=>'m'));
    }


    public function TestSort() {

        $C= new Collection;
        // natural sort
        $C->Import(array('b','a','c'));
        $C->Sort();
        $this->assertIdentical($C->ToArray(), array(1=>'a', 0=>'b', 2=>'c'));   // keys are preserved
        // sort by keys
        $C->Import(array(4=>'a', 2=>'c', 7=>'b'));
        $C->Sort(true);
        $this->assertIdentical($C->ToArray(), array(2=>'c', 4=>'a', 7=>'b'));
        // sort using user-defined function
        $UFL= function($a, $b){return strlen($a) < strlen($b) ? -1 : 1;};  // sort by length of string
        $C->Import(array('aaa', 'c', 'bbbbbb'));
        $C->Sort(false, $UFL);
        $this->assertIdentical($C->ToArray(), array(1=>'c', 0=>'aaa', 2=>'bbbbbb'));
        // sort by keys using user-defined function
        $UFK= function($a, $b) {return $a % 2 === $b % 2 ? 0 : ($a % 2 < $b % 2 ? -1 : 1);}; // sort keys with event numbers first
        $C->Import(array('a','b','c','d','e'));
        $C->Sort(true, $UFK);
        $this->assertIdentical($C->ToArray(), array( 0=>'a', 2=>'c', 4=>'e', 1=>'b', 3=>'d'));
    }


    public function TestMap() {

        $C= new Collection;
        // map on empty collection
        $C->Map('strtolower');
        $this->assertIdentical($C->ToArray(), array());
        // apply callback to non-empty collection
        $C->Import(array('a','B','C'));
        $C->Map('strtolower');
        $this->assertIdentical($C->ToArray(), array('a','b','c'));
    }


    public function TestFilter() {

        $C= new Collection;
        $CB= function($v) {return $v <> 'b';};   // remove all "b" chars
        // filter empty collection
        $C->Filter($CB);
        $this->assertIdentical($C->ToArray(), array());
        // apply callback to non-empty collection
        $C->Import(array('a','b','c','a','b','c'));
        $C->Filter($CB);
        $this->assertIdentical($C->ToArray(), array(0=>'a', 2=>'c', 3=>'a', 5=>'c'));
    }


    public function TestGetSplit() {

        $C= new Collection;
        $CB= function($k,$v) {return is_numeric($v);};   // distinct numeric from string values
        // split empty collection
        $this->assertIdentical($C->GetSplit($CB), array(array(),array()));
        // split non-empty collection
        $C->Import(array('a','b',4,1,'x',5));
        $this->assertIdentical($C->GetSplit($CB), array(array(2=>4,3=>1,5=>5),array(0=>'a',1=>'b',4=>'x')));
    }


    public function TestDottedNotation() {

        $C= new Collection;
        // should return default values
        $this->assertIdentical($C->GetDotted('A', 'DEF'), 'DEF');
        $this->assertIdentical($C->GetDotted('A.B', 'DEFB'), 'DEFB');
        // set value with missing parents
        $C->SetDotted('X.Y.Z', '####');
        $this->assertIdentical($C->GetDotted('X.Y.Z'), '####');
        $this->assertIdentical($C->GetDotted('X.Y'), ['Z'=>'####']);
        $this->assertIdentical($C->GetDotted('X'), ['Y'=>['Z'=>'####']]);
        // overwrite & append
        $C->SetDotted('X.Y.Z', 'K1');
        $C->SetDotted('X.Y.Z2', 'K2');
        $this->assertIdentical($C->GetDotted('X.Y'), ['Z'=>'K1', 'Z2'=>'K2']);
    }


    public function TestModified() {

        $C= new Collection;
        // construction without supplied array is not in modified state
        $this->assertIdentical($C->ModifiedGet(), false);
        // modify collection
        $C->Append('a');
        $this->assertIdentical($C->ModifiedGet(), true);
        // reset flag
        $C->ModifiedClear();
        $this->assertIdentical($C->ModifiedGet(), false);
        // Clear() must set modified flag
        $C->ModifiedClear();
        $C->Clear();
        $this->assertIdentical($C->ModifiedGet(), true);
        // importing must se modified flag
        $C->ModifiedClear();
        $C->Import(array(1,2,3));
        $this->assertIdentical($C->ModifiedGet(), true);
        // and all other setters....
    }

}
