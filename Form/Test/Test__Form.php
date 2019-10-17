<?php namespace Accent\Form\Test;

/**
 * Testing form service.
 */

use Accent\Test\AccentTestCase;
use Accent\Form\Form;
use Accent\Form\ValueTransform\NumberToLocalNumberTransform;
use Accent\Form\ValueTransform\DateTimeToYMDTransform;
use Accent\Form\ValueTransform\IntegerToYMDTransform;
use Accent\Form\ValueTransform\KeyValueTransform;


class Test__Form extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Form service test';

    // title of testing group
    const TEST_GROUP= 'Form';


    protected $ComplexStructure= array(
        'Name'=> 'Forma',
        'Controls'=> array(
            'Name'=> array(
                'Type'=>'Text',
            ),
            'Email'=> array(
                'Type'=> 'Text',
            ),
            'Department'=> array(
                'Type'=> 'Select',
                'List'=> array('Management','Sales','Tech.support'),
            ),
            'Message'=> array(
                'Type'=>'Textarea',
            ),
            'Agree'=> array(
                'Type'=> 'Checkbox',
                'Value'=> 'Y',
            ),
            'Computer'=> array(
                'Type'=> 'CheckboxGroup',
                'List'=> array(1=>'Desktop', 2=>'Laptop', 3=>'Tablet'),
            ),
            'Genre'=> array(
                'Type'=> 'RadioGroup',
                'List'=> array(1=>'male',2=>'female'),
            ),
            'Now'=> array(
                'Type'=> 'String',
            ),
            'Upload'=> array(
                'Type'=> 'File',
            ),
            'Year'=> array(
                'Type'=> 'NumberRangeSelect',
                'Range'=> array(2015, 2019),
            ),
            'Birthday'=> array(
                'Type'=> 'Date',
                'Format'=> 'd.m.Y',
            ),
            'Expired'=> array(
                'Type'=> 'Date3',
                'YearRange'=> array(2010, 2015),
            ),
            'Password'=> array(
                'Type'=> 'Password',
            ),
        ),
        'Buttons'=> array(
            'Add'=> array(
                'Label'=> 'Send',
                'Type'=> 'submit',
            ),
        ),
    );


    protected $TestValues= array(
        'Name'=> 'Chuck Norris',
        'Email'=> 'gmail@chucknorris.com',
        'Department'=> '1', // 1=>Sales
        'Message'=> 'Are you hurt?',
        'Agree'=> 'Y',
        'Computer'=> array(1,3),
        'Genre'=> 2,
        'Upload'=> '',
        'Year'=> 2016,
        'Birthday'=> '1864-10-01',
        'Expired'=> '2011-05-24',
        'Password'=> 'admin',
    );



    protected function BuildServices() {

        $UTF= new \Accent\AccentCore\UTF\UTF;
        $Validator= new \Accent\AccentCore\Filter\Validator(array('Services'=>array('UTF'=>$UTF)));
        $Localization= new \Accent\Localization\Localization(array(
            'DefaultLang'=> 'sr',
            'LangAliases'=> array(
                '@'=> 'sr',  // use non-english language to test formating float number
            ),
            'LoaderConfigs'=> array( // prepare configuration for loader which will be invoked
                'php1'=> array(         // first PHP loader
                    'LoaderClass'=> 'PHP',        // or YML, INI, JSON, XML, DB
                    'Directories'=> array(   // list of full paths where to search for books
                        __DIR__.'/lang',
                    ),
                ),
            ),
            'DefaultLoader'=> 'php1', // which loader to use for books not listed in BookLoader
            'Services'=> array(
                'ArrayUtils'=> new \Accent\AccentCore\ArrayUtils\ArrayUtils,
                'UTF'=> $UTF,
                'Event'=> new \Accent\AccentCore\Event\EventService,
            ),
        ));
        $Sanitizer= new \Accent\AccentCore\Filter\Sanitizer(array('Services'=>array(
            'UTF'=> $UTF,
            'Localization'=> $Localization,
        )));
        return array(
            'Sanitizer'=> $Sanitizer,
            'Validator'=> $Validator,
            'UTF'=> $UTF,
            'Localization'=> $Localization,
        );
    }


    protected function Build($NewOptions=array()) {

        return new Form($NewOptions + array(
            'Action'=> 'Test.php',
            'Services'=> $this->BuildServices(),
        ));
    }


    protected function ReduceWhitespaces($str) {
        return preg_replace('/\s+/', ' ', $str);
    }


    protected function GenerateStructure($SoloControl=null) {

        $Struc= $this->ComplexStructure;
        if ($SoloControl !== null) {
            $Struc['Controls']= array($SoloControl=>$Struc['Controls'][$SoloControl]);
        }
        return $Struc;
    }



    // TESTS:



    public function TestRenderTextControl() {
        
        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Name'));
        $HTML= $F->Render();
        $Expected= '<input type="text" name="Name" id="Forma_Name" class="fcText" />';
        $this->assertTrue(strpos($HTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }

    public function TestRenderTextareaControl() {
        // this will also test including attributes from DefaultOptions.Attributes
        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Message'));
        $HTML= $F->Render();
        $Expected= '<textarea name="Message" id="Forma_Message" class="fcTextarea" cols="10" rows="4"></textarea>';
        $this->assertTrue(strpos($HTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }


    public function TestRenderSelectControl() {
        // this will also test including attributes from LoadStructure
        $F= $this->Build();
        $Struc= $this->GenerateStructure('Department');
        $Struc['Controls']['Department']['Attributes']= array('tabindex'=>4);
        $F->LoadStructure($Struc);
        $HTML= $F->Render();
        $Expected= '<select name="Department" id="Forma_Department" class="fcSelect" size="1" tabindex="4"> <option value="0" selected="selected">Management</option> <option value="1">Sales</option> <option value="2">Tech.support</option></select>';
        $rwHTML= $this->ReduceWhitespaces($HTML);
        $this->assertTrue(strpos($rwHTML, $Expected) !== false, 'FOUND: "'.$rwHTML.'"');
    }


    public function TestRenderCheckboxControl() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Agree'));
        $HTML= $F->Render();
        $Expected= '<input type="checkbox" name="Agree" id="Forma_Agree" class="fcCheckbox" value="Y" />';
        $this->assertTrue(strpos($HTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }


    public function TestRenderCheckboxGroupControl() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Computer'));
        $HTML= $F->Render();
        $rwHTML= $this->ReduceWhitespaces($HTML);
        $Expected= '<ul class="fcGroup"><li><label><input type="checkbox" name="Computer[]" class="fcCheckboxGroup" value="1" />Desktop</label></li><li><label><input type="checkbox" name="Computer[]" class="fcCheckboxGroup" value="2" />Laptop</label></li><li><label><input type="checkbox" name="Computer[]" class="fcCheckboxGroup" value="3" />Tablet</label></li></ul>';
        $this->assertTrue(strpos($rwHTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }


    public function TestRenderRadioGroupControl() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Genre'));
        $HTML= $F->Render();
        $rwHTML= $this->ReduceWhitespaces($HTML);
        $Expected= '<ul class="fcGroup"><li><label><input type="radio" name="Genre" class="fcRadioGroup" value="1" />male</label></li><li><label><input type="radio" name="Genre" class="fcRadioGroup" value="2" />female</label></li></ul>';
        $this->assertTrue(strpos($rwHTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }


    public function TestRenderStringControl() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Now'));
        $HTML= $F->Render();
        $Expected= '<td></td>';
        $this->assertTrue(strpos($HTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }


    public function TestRenderFileControl() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Upload'));
        $HTML= $F->Render();
        $Expected= '<input type="file" name="Upload" id="Forma_Upload" class="fcFile" />';
        $this->assertTrue(strpos($HTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }


    public function TestRenderNumberRangeSelectControl() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Year'));
        $HTML= $F->Render();
        $Expected= '<select name="Year" id="Forma_Year" class="fcNumberRangeSelect" size="1"> <option value="1990" selected="selected">1990</option>';
        $rwHTML= $this->ReduceWhitespaces($HTML);
        $this->assertTrue(strpos($rwHTML, $Expected) !== false, 'FOUND: "'.$rwHTML.'"');
    }


    public function TestRenderDateControl() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Birthday'));
        $HTML= $F->Render();
        $Expected= '<input type="text" name="Birthday" id="Forma_Birthday" class="fcDate" value="01.01.0001" />';
        $this->assertTrue(strpos($HTML, $Expected) !== false, 'FOUND: "'.$HTML.'"');
    }


    public function TestRenderDate3Control() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure('Expired'));
        $HTML= $F->Render();
        $Expected= '</select><select name="Expired_m" id="Forma_Expired_m" class="fcDate3" size="1"> <option value="1" selected="selected">1</option> <option value="2">2</option>';
        $rwHTML= $this->ReduceWhitespaces($HTML);
        $this->assertTrue(strpos($rwHTML, $Expected) !== false, 'FOUND: "'.$rwHTML.'"');
    }


    public function TestValueAssignation() {

        // assign by SetValues
        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure());
        $F->SetValues($this->TestValues);
        $Result= $F->GetValues();
        $this->assertEqual($this->TestValues, $Result);
        // rebuild and assign by SetInitValues
        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure());
        $F->SetInitValues($this->TestValues);
        $Result= $F->GetValues(); // some controls must be empty, they are unsuccessfull
        $this->assertFalse(isset($Result['Name']));
        $this->assertTrue(array_key_exists('Expired',$Result)); // complex control are always succ.
        // now assign again by SetValues
        $F->SetValues($this->TestValues);
        $Result= $F->GetValues(); // now values must be present
        $this->assertEqual($this->TestValues, $Result);
        // rebuild and assign by SetHttpValues
        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure());
        // prepare HTTP variables, they are not always same as form-data
        $HttpSubmission= $this->TestValues + array(
            'Expired_d'=>24, 'Expired_m'=>5, 'Expired_y'=>2011,
        );
        $HttpSubmission['Birthday']= '01.10.1864';
        $F->SetHttpValues($HttpSubmission);
        $Result= $F->GetValues();
        $this->assertEqual($this->TestValues, $Result);
    }


    public function TestVerticalRenderer() {

        $F= $this->Build(array('Renderer'=>'VerticalRenderer'));
        $F->LoadStructure($this->GenerateStructure());
        $HTML= $F->Render();
        $Expected= '<div class="fVertSeparator"></div> <div id="Forma_Agree_row"> <input type="checkbox" name="Agree" id="Forma_Agree" class="fcCheckbox" value="Y" />&nbsp;<label for="Forma_Agree">Agree</label> </div><div class="fVertSeparator"></div>';
        $rwHTML= $this->ReduceWhitespaces($HTML);
        $this->assertTrue(strpos($rwHTML, $Expected) !== false, 'FOUND: "'.$rwHTML.'"');
    }


    public function TestSanitization() {

        $Tests= array(
            array('I', '2unlimited', 2),
            array('CL', 'Admin', 'admin'),
            array('@', 'dir%2Fsub+dir', 'dir/sub dir'),
            array('Len:4', 'qwertz', 'qwer'),
            array('Pad:4:0:L', '24', '0024'),
            array('Range:3..5', '1', 3),
            array('Alpha', '2unlimited', 'unlimited'),
            array('Alnum', '2-unlimited', '2unlimited'),
            array('Digits', '4x4', '44'),
            array('FileName', '../../etc/passwd', '....etcpasswd'),
            array('FileName|Len:8', '../../etc/passwd', '....etcp'),
            array('Local|Float', '1.024,010', 1024.01),
        );
        foreach($Tests as $Test) {
            $F= $this->Build();
            $Struc= $this->GenerateStructure('Name');
            $Struc['Controls']['Name']['Sanitize']= $Test[0];
            $F->LoadStructure($Struc);
            $F->SetHttpValues(array('Name'=>$Test[1]));
            $Result= $F->GetValues();
            $this->assertTrue(
                $Result['Name'] === $Test[2],
                'Test("'.$Test[0].'") = {'.var_export($Result['Name'],true).'}'
            );
        }
    }


    public function TestValidation() {

        $Tests= array(
            // dont need to test EVERY validator, this is testing of Form service
            array('Email', 'chucknorris.com', false),
            array('Email', 'gmail@chucknorris.com', true),
            array('InRange:3..5', '2', false),
            array('InRange:3..5', '3', true),
            array('Max:4', '4', true),
            array('Max:4', '5', false),
            array('In:a,b,c', 'a', true),
            array('In:a,b,c', 'd', false),
            array('Len:4', 'abcd', true),
            array('Len:4', 'abc', false),
            array('LenMax:4', 'abcd', true),
            array('LenMax:4', '', true),
            array('LenRange:3..5', '', false),
            array('FileName', 'chucknorris', true),
            array('FileName', 'gmail@chucknorris', false),
        );
        foreach($Tests as $Test) {
            $F= $this->Build();
            $Struc= $this->GenerateStructure('Name');
            $Struc['Controls']['Name']['Validate']= $Test[0];
            $F->LoadStructure($Struc);
            $F->SetHttpValues(array('Name'=>$Test[1]));
            $Result= $F->Validate();
            $this->assertTrue(
                $Result === $Test[2],
                'Test("'.$Test[0].'" - "'.$Test[1].'") = {'.var_export($Result,true).'}'
            );
        }
    }


    public function TestIsSubmitted() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure());
        $F->SetInitValues(array(
            'Name'=> 'Chuck Norris',
        ));
        $F->SetHttpValues(array(
            'Name'=> 'Batman',
        ));
        $this->assertEqual($F->IsSubmitted(), false);
        $F->SetHttpValues(array(
            'Name'=> 'Batman',
            '_FormName'=> 'Forma',
        ));
        $this->assertEqual($F->IsSubmitted(), true);
    }


    public function TestIntegerToYMDTransform() {

        $Timestamp= mktime(0,0,0,3,24,1999);

        // test transformation specified as class name
        $F= $this->Build();
        $Struc= $this->GenerateStructure('Name');
        $Struc['Controls']['Name']['Transform']= 'IntegerToYMDTransform';
        $F->LoadStructure($Struc);
        $F->SetValues(array('Name'=> $Timestamp));
        $Values= $F->GetValues();
        $this->assertEqual($Values['Name'], $Timestamp);
        $this->assertEqual($F->GetControl('Name')->RenderValue(), '1999.03.24');

        // inject instantied transformer and change format
        $Struc['Controls']['Name']['Transform']= new IntegerToYMDTransform(array(
            'Format'=> 'd.m.Y',
        ));
        $F->LoadStructure($Struc);
        $F->SetValues(array('Name' => $Timestamp));
        $Values= $F->GetValues();
        $this->assertEqual($Values['Name'], $Timestamp);
        $this->assertEqual($F->GetControl('Name')->RenderValue(), '24.03.1999');
    }


    public function TestNumberToLocalNumberTransform() {

        $Float= 1234567.2451;
        // test transformation specified as class name
        $F= $this->Build();
        $Struc= $this->GenerateStructure('Name');
        $Struc['Controls']['Name']['Transform']= 'NumberToLocalNumberTransform';
        $F->LoadStructure($Struc);
        $F->SetValues(array('Name'=> $Float));
        $Values= $F->GetValues();
        $this->assertEqual($Values['Name'], 1234567.25);
        // last 2 decimals are lost during first transformation,
        // source for second transformation is value from first one.
        $this->assertEqual($F->GetControl('Name')->RenderValue(), '1.234.567,25');

        // inject instantied transformer and change default options
        $Struc['Controls']['Name']['Transform']= new NumberToLocalNumberTransform(array(
            'Lang'=> 'en',
            'Decimals'=> 1,
            'Services'=> $this->BuildServices()
        ));
        $F->LoadStructure($Struc);
        $F->SetValues(array('Name' => $Float));
        $Values= $F->GetValues();
        $this->assertEqual($Values['Name'], 1234567.2);
        $this->assertEqual($F->GetControl('Name')->RenderValue(), '1,234,567.2');
    }


    public function TestDateTimeToYMDTransform() {

        // test transformation specified as class name
        $F= $this->Build();
        $Struc= $this->GenerateStructure('Name');
        $Struc['Controls']['Name']['Transform']= 'DateTimeToYMDTransform';
        $F->LoadStructure($Struc);
        $F->SetValues(array('Name'=> '1999-03-24'));
        $Values= $F->GetValues();
        $this->assertEqual($Values['Name'], '1999-03-24 00:00:00');
        $this->assertEqual($F->GetControl('Name')->RenderValue(), '1999.03.24');

        // inject instantied transformer and change format
        $Struc['Controls']['Name']['Transform']= new DateTimeToYMDTransform(array(
            'Format'=> 'd.m.Y',
        ));
        $F->LoadStructure($Struc);
        $F->SetValues(array('Name' => '1999-03-24'));
        $Values= $F->GetValues();
        $this->assertEqual($Values['Name'], '1999-03-24 00:00:00');
        $this->assertEqual($F->GetControl('Name')->RenderValue(), '24.03.1999');
    }


    public function TestKeyValueTransform() {

        $Arr= array(1=>'one', 2=>'two', 10=>'ten', 100=>'hundred');
        $Tests= array(
            array(2, "two"),
            array(10, "ten"),
            array(100, "hundred")
        );
        // usage with class name is not possible becouse parameters are required
        $F= $this->Build();
        $Struc= $this->GenerateStructure('Name');
        $Struc['Controls']['Name']['Transform']= new KeyValueTransform(array(
            'Translations'=> $Arr,
        ));
        $F->LoadStructure($Struc);
        foreach($Tests as $Test) {
            $F->SetValues(array('Name' => $Test[0]));
            $Values= $F->GetValues();
            $this->assertEqual($Values['Name'], $Test[0]);
            $this->assertEqual($F->GetControl('Name')->RenderValue(), $Test[1]);
        }
    }


    public function TestDiff() {

        $F= $this->Build();
        $F->LoadStructure($this->GenerateStructure());
        $F->DeleteControl('Upload');
        $F->DeleteControl('Year');
        $F->SetInitValues(array(
            'Name'=> 'Chuck Norris',
            'Email'=> '',
            'Department'=> '1',
            //'Message'=> '',
            'Agree'=> 'Y',
            'Computer'=> array(2),
            'Genre'=> 1,
        ));
        $F->SetHttpValues(array(
            'Name'=> 'Chuck Norris',            // unmodified
            'Email'=> 'gmail@chucknorris.com',  // empty -> text
            'Department'=> '',                  // text -> empty
            'Message'=> 'Are you hurt?',        // null -> text
            //'Agree'=> 'Y',                    // text -> null
            'Computer'=> array(2,3),            // changed array of checkbox-group
            'Genre'=> 2,                        // changed radio-group
        ));
        $Result= $F->Diff();
        $Expected= "Email='gmail@chucknorris.com', Department='', Message='Are you hurt?', "
            ."Agree='No', Computer='Laptop,Tablet', Genre='female'";
        $this->assertEqual($Result, $Expected);
    }



    public function TestTestingRealSubmitWholeForm() {

    }


}


?>