<?php
/**
 *
 * Unit tests or ExpandableBehavior
 */
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
/**
 * User test Model class class
 */
class ExpandableUser extends AppModel {
    public $name = 'User';
    public $actsAs = array(
        'Expandable.Expandable' => array(
            'with' => 'UserExpand',
            'encode_json' => true,
            'encode_csv' => array('states'),
            'encode_date' => array(
                'birthdate' => 'Y-m-d',
                'birthday' => 'm/d',
            ),
            'restricted_keys' => array('password', 'password_confirm'),
        ),
        'Containable'
    );
    public $hasMany = array('Expandable.UserExpand');
    public $recursive = -1;
}
/**
 * ExpandableTest class
 *
 * @package       Cake.Test.Case.Model.Behavior
 */
class ExpandableBehaviorTest extends CakeTestCase {

    /**
     * Fixtures associated with this test case
     *
     * @var array
     */
    public $fixtures = array(
        'plugin.expandable.user',
        'plugin.expandable.user_expand',
    );

    /**
     * Method executed before each test
     *
     */
    public function setUp() {
        parent::setUp();
        $this->User = ClassRegistry::init('Expandable.ExpandableUser');
        $this->User->Behaviors->attach('Expandable.Expandable');
    }

    /**
     * Method executed after each test
     *
     */
    public function tearDown() {
        unset($this->User);
        parent::tearDown();
    }

    /**
     * testContainments method
     *
     * @return void
     */
    public function testAggregateFunctionality() {
        $user = $this->User->find('first');
        $userInit = $user;
        $user['ExpandableUser']['extraField1'] = 'extraValue1';
        $user['ExpandableUser']['extraField2'] = true;
        $user['ExpandableUser']['extraField3'] = false;
        $user['ExpandableUser']['extraField5'] = null;
        $user['ExpandableUser']['extraField6'] = '';
        // restricted
        $user['ExpandableUser']['password_confirm'] = 'Shouldnt be here';
        // date fields
        $user['ExpandableUser']['birthdate'] = array('year' => '1979', 'month' => '12', 'day' => '31');
        $user['ExpandableUser']['birthday'] = array('month' => '12', 'day' => '31');
        // CSV fields
        $user['ExpandableUser']['states'] = array('NY', 'CA', 'OH', 'KY', 'CT');
        // misc array
        $miscJsArray = array('one', 'two', 3, 4, true, false, null, '');
        $user['ExpandableUser']['miscJsArray'] = $miscJsArray;
        $miscJsObject = array('one' =>  'One', 'two' => 'Two', 3 => 3, 4 => 4, 'true' => true, 'false' => false, 'null' => null, '');
        $user['ExpandableUser']['miscJsObject'] = $miscJsObject;

        $this->User->create(false);
        $saved = $this->User->save($user);
        $this->assertFalse(empty($saved));
        // now if we find that record again, it wont have the expands
        // because recursive = -1, and no contains
        $userWithoutExpand = $this->User->find('first');
        $this->assertEquals($userInit, $userWithoutExpand);
        // but if we repeat the find with a contains (or recursive = 1)
        $userWithExpand = $this->User->find('first', array('contain' => 'UserExpand'));
        $this->assertNotEquals($userInit, $userWithExpand);
        // now we can test the values directly on the User model results
        $this->assertEquals('extraValue1', $userWithExpand['ExpandableUser']['extraField1']);
        $this->assertEquals(true, $userWithExpand['ExpandableUser']['extraField2']);
        $this->assertEquals(false, $userWithExpand['ExpandableUser']['extraField3']);
        $this->assertEquals(null, $userWithExpand['ExpandableUser']['extraField5']);
        $this->assertEquals('', $userWithExpand['ExpandableUser']['extraField6']);
        // restricted
        $this->assertTrue(empty($userWithExpand['ExpandableUser']['password_confirm']));
        // date fields
        $this->assertEquals('1979-12-31', $userWithExpand['ExpandableUser']['birthdate']);
        $this->assertEquals('12/31', $userWithExpand['ExpandableUser']['birthday']);
        // CSV fields
        $this->assertEquals('NY,CA,OH,KY,CT', $userWithExpand['ExpandableUser']['states']);
        // misc array
        $this->assertEquals($miscJsArray, $userWithExpand['ExpandableUser']['miscJsArray']);
        $this->assertEquals($miscJsObject, $userWithExpand['ExpandableUser']['miscJsObject']);
        // the hasMany relationship is passed through... but we are removing
        // for simpler full-array tesing
        $this->assertFalse(empty($userWithExpand['UserExpand']));
        unset($userWithExpand['UserExpand']);
        // convert a few transformed values
        $user['ExpandableUser']['extraField2'] = 1; // transformed on save?
        $user['ExpandableUser']['birthdate'] = '1979-12-31';
        $user['ExpandableUser']['birthday'] = '12/31';
        $user['ExpandableUser']['states'] = implode(',', $user['ExpandableUser']['states']);
        unset($user['ExpandableUser']['password_confirm']);
        $this->assertEquals($user, $userWithExpand);
    }
}
