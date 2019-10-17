<?php namespace Accent\Security\Password\Test;

use Accent\Test\AccentTestCase;
use Accent\Security\Password\Password;
use Accent\Security\Random\Random;


class Test__Password extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Password service test';

    // title of testing group
    const TEST_GROUP= 'Security';


    protected function Build($Options=array()) {

        $DefOptions= array(
            'DefaultHashAlgo'=> '$2a$09',
            'Services'=> array(
                'Random'=> new Random,
            ),
        );
        return new Password($DefOptions + $Options);
    }


    // TESTS:

    public function TestGeneratingPassword() {

        $P= $this->Build();

        // create password
        $Password= $P->Create(8);

        // create it's hash
        $Hash= $P->Hash($Password);

        // it must not be ready for rehash
        $this->assertFalse($P->NeedToRehash($Hash));

        // verify
        $Confirm= $P->Verify($Password, $Hash);
        $this->assertTrue($Confirm);

        // verify wrong password
        $Confirm= $P->Verify($Password.'x', $Hash);
        $this->assertFalse($Confirm);

        // verify wrong hash
        $Confirm= $P->Verify($Password, substr($Hash,-1).'s');
        $this->assertFalse($Confirm);

    }

}


?>