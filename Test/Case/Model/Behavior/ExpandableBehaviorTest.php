<?php
/**
 *
 * Unit tests or ExpandableBehavior
 */
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('ExpandableBehavior', 'Expandable.Model/Behavior');
/**
 * User test Model class class
 */
class ExpandableUser extends AppModel {
    public $name = 'User';
    public $actsAs = array(
        'Expandable.TestExpandable' => array(
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
 * UserExpand test Model class
 */
class UserExpand extends AppModel {
    public $name = 'UserExpand';
    public $belongsTo = ['ExpandableUser'];
    public $validate = [
        'key' => [
            'notblank' => [
                'rule' => ['notBlank'],
                'required' => 'create',
                'allowEmpty' => false,
                'message' => 'Key must not be blank'
            ],
        ],
    ];
}

/**
 * Profile test Model class (for testing nested model validation)
 */
class Profile extends AppModel {
    public $name = 'Profile';
    public $belongsTo = ['ExpandableUser'];
    public $validate = [
        'bio' => [
            'notblank' => [
                'rule' => ['notBlank'],
                'required' => 'create',
                'allowEmpty' => false,
                'message' => 'Bio is required'
            ],
        ],
    ];
}

// This is used to more easily/thoroughly test protected ExpandableBehavior methods
class TestExpandableBehavior extends ExpandableBehavior
{
    public function prepareEavData(Model $Model)
    {
        return $this->_prepareEavData($Model);
    }
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
        'plugin.expandable.profile',
    );

    /**
     * The ExpandableUser model instance
     *
     * @var ExpandableUser
     */
    public $ExpandableUser;

    /**
     * Method executed before each test
     *
     */
    public function setUp() {
        parent::setUp();
        $this->ExpandableUser = ClassRegistry::init('Expandable.ExpandableUser');
    }

    /**
     * Method executed after each test
     *
     */
    public function tearDown() {
        unset($this->ExpandableUser);
        parent::tearDown();
    }

    /**
     * testContainments method
     *
     * @return void
     */
    public function testAggregateFunctionality() {
        $user = $this->ExpandableUser->find('first');
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

        $this->ExpandableUser->create(false);
        $saved = $this->ExpandableUser->save($user);
        $this->assertFalse(empty($saved));
        // now if we find that record again, it wont have the expands
        // because recursive = -1, and no contains
        $userWithoutExpand = $this->ExpandableUser->find('first');
        $this->assertEquals($userInit, $userWithoutExpand);
        // but if we repeat the find with a contains (or recursive = 1)
        $userWithExpand = $this->ExpandableUser->find('first', array('contain' => 'UserExpand'));
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

    /**
     * Test that prepareEavData returns the correct EAV data when valid data is present
     *
     * @return void
     */
    public function testPrepareEavDataWithValidData()
    {
        $this->ExpandableUser->data = [
            'ExpandableUser' => [
                'id' => '1',
                'name' => 'Test',
                'extra_field' => 'value',
                'password' => 'secret'
            ]
        ];

        $result = $this->ExpandableUser->Behaviors->TestExpandable->prepareEavData($this->ExpandableUser);

        // Verify we only got one EAV record
        $this->assertCount(1, $result);

        // Verify the extra field was properly set
        $this->assertEquals('extra_field', $result[0]['key']);
        $this->assertEquals('value', $result[0]['value']);

        // Verify the original data structure remains unchanged
        $this->assertArrayHasKey('password', $this->ExpandableUser->data['ExpandableUser'], 'Original data should retain all fields');
        $this->assertEquals('secret', $this->ExpandableUser->data['ExpandableUser']['password'], 'Original password value should be preserved');
    }

    /**
     * Test that prepareEavData returns empty array when no EAV-eligible fields are present
     *
     * @return void
     */
    public function testPrepareEavDataWithNoExtraFields()
    {
        // Set up data with no extra fields
        $this->ExpandableUser->data = [
            'ExpandableUser' => [
                'id' => '1',
                'name' => 'Test'
            ]
        ];

        // EAV data should not be returned because there are no extra fields
        $result = $this->ExpandableUser->Behaviors->TestExpandable->prepareEavData($this->ExpandableUser);
        $this->assertEmpty($result);
    }

    /**
     * Test that prepareEavData properly handles associated model data.
     * Verifies that:
     * - Fields that are part of the schema are ignored
     * - Associated model data is ignored
     * - Only custom fields not in schema are included in EAV data
     *
     * @return void
     */
    public function testPrepareEavDataWithAssociatedModels()
    {
        // Set up data with associated models
        $this->ExpandableUser->belongsTo = ['Category'];
        $this->ExpandableUser->hasMany = ['Comment'];

        // Set up data with associated model data
        $this->ExpandableUser->data = [
            'ExpandableUser' => [
                'id' => '1',
                'name' => 'Test',
                'profile_id' => '123', // This should be ignored (part of schema)
                'comment_id' => '456', // This should be ignored (part of schema)
                'custom_field' => 'value', // This should be included (NOT part of schema)
            ],
            'Profile' => [ // This should be ignored
                'id' => '2',
                'name' => 'Test Profile'
            ],
            'Comment' => [ // This should be ignored
                [
                    'id' => '1',
                    'text' => 'Test Comment 1'
                ],
                [
                    'id' => '2',
                    'text' => 'Test Comment 2'
                ]
            ]
        ];

        // EAV data should only contain custom_field because it's not part of the schema,
        // is not restricted, and is not associated with a model
        $result = $this->ExpandableUser->Behaviors->TestExpandable->prepareEavData($this->ExpandableUser);
        $this->assertCount(1, $result);
        $this->assertEquals('custom_field', $result[0]['key']);
        $this->assertEquals('value', $result[0]['value']);
    }

    /**
     * Test that prepareEavData returns empty array when schema is not set in behavior settings
     *
     * @return void
     */
    public function testPrepareEavDataWithNoSchema()
    {
        // Set up data with extra fields
        $this->ExpandableUser->data = [
            'ExpandableUser' => [
                'id' => '1',
                'name' => 'Test',
                'custom_field' => 'value' // Normally this would be included in EAV data
            ]
        ];

        // Temporarily remove schema from behavior settings
        $originalSettings = $this->ExpandableUser->Behaviors->TestExpandable->settings[$this->ExpandableUser->alias];
        unset($this->ExpandableUser->Behaviors->TestExpandable->settings[$this->ExpandableUser->alias]['schema']);

        // EAV data should not be returned because schema is not set
        $result = $this->ExpandableUser->Behaviors->TestExpandable->prepareEavData($this->ExpandableUser);
        $this->assertEmpty($result);

        // Restore original settings
        $this->ExpandableUser->Behaviors->TestExpandable->settings[$this->ExpandableUser->alias] = $originalSettings;
    }

    /**
     * Test validation when saving with no validation errors in either base model or EAV data
     *
     * @return void
     */
    public function testSaveWithNoValidationErrors()
    {
        // Set up test data with valid base model and EAV fields
        $data = [
            'ExpandableUser' => [
                'name' => 'Valid Name',
                'custom_field' => 'Valid Value'
            ]
        ];

        // Mock UserExpand model
        $this->ExpandableUser->UserExpand = $this->getMockForModel(
            'Expandable.UserExpand',
            ['validates', 'save']
        );

        // Expect validation to be called once with all fields
        $this->ExpandableUser->UserExpand
            ->expects($this->once())
            ->method('validates')
            ->willReturn(true);

        // Expect save to be called with validate => false
        $this->ExpandableUser->UserExpand
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->anything(),
                $this->equalTo(['validate' => false])
            )
            ->willReturn(true);

        // Perform the test
        $result = $this->ExpandableUser->save($data);
        $this->assertNotEmpty($result);
        $this->assertEmpty($this->ExpandableUser->validationErrors);
    }

    /**
     * Test validation when saving with base model validation errors and valid EAV field/value
     *
     * @return void
     */
    public function testSaveWithBaseModelValidationErrors()
    {
        // Add a validation rule to the base model
        $this->ExpandableUser->validate = [
            'name' => [
                'rule' => 'notBlank',
                'message' => 'Name is required'
            ]
        ];

        // Set up test data with invalid base model field but valid EAV field
        $data = [
            'ExpandableUser' => [
                'name' => '', // Invalid
                'custom_field' => 'Valid Value'
            ]
        ];

        // Mock UserExpand model
        $this->ExpandableUser->UserExpand = $this->getMockForModel(
            'Expandable.UserExpand',
            ['validates', 'save']
        );

        // Expect validation to be called even though base model validation failed
        $this->ExpandableUser->UserExpand
            ->expects($this->once())
            ->method('validates')
            ->willReturn(true);

        // Save should not be called since base model validation failed
        $this->ExpandableUser->UserExpand
            ->expects($this->never())
            ->method('save');

        // Perform the test
        $result = $this->ExpandableUser->save($data);
        $this->assertFalse($result);
        $this->assertEquals(1, count($this->ExpandableUser->validationErrors));
        $this->assertArrayHasKey('name', $this->ExpandableUser->validationErrors);
        $this->assertArrayNotHasKey('custom_field', $this->ExpandableUser->validationErrors);
    }

    /**
     * Test validation when saving with EAV value field validation errors
     *
     * @return void
     */
    public function testSaveWithEavValueValidationErrors()
    {
        // Set up test data with valid base model field but invalid EAV value
        $data = [
            'ExpandableUser' => [
                'name' => 'Valid Name',
                'custom_field' => '' // Empty value will trigger validation error
            ]
        ];

        // Mock UserExpand model
        $this->ExpandableUser->UserExpand = $this->getMockForModel(
            'Expandable.UserExpand',
            ['save']
        );

        // Set up validation rules for this specific test
        $this->ExpandableUser->UserExpand->validate = [
            'key' => [
                'notblank' => [
                    'rule' => ['notBlank'],
                    'required' => 'create',
                    'allowEmpty' => false,
                    'message' => 'Key must not be blank'
                ],
            ],
            'value' => [
                'notblank' => [
                    'rule' => ['notBlank'],
                    'required' => 'create',
                    'allowEmpty' => false,
                    'message' => 'Value must not be blank'
                ],
            ],
        ];

        // Save should not be called since validation will fail
        $this->ExpandableUser->UserExpand
            ->expects($this->never())
            ->method('save');

        // Perform the test
        $result = $this->ExpandableUser->save($data);
        $this->assertFalse($result);
        $this->assertEquals(1, count($this->ExpandableUser->validationErrors));
        $this->assertArrayHasKey('custom_field', $this->ExpandableUser->validationErrors);
        $this->assertEquals(
            ['Value must not be blank'],
            $this->ExpandableUser->validationErrors['custom_field']
        );
    }

    /**
     * Test validation when saving with EAV meta field validation errors
     *
     * @return void
     */
    public function testSaveWithEavMetaValidationErrors()
    {
        // Set up test data with valid base model field but invalid EAV key
        $data = [
            'ExpandableUser' => [
                'name' => 'Valid Name',
                '' => 'Valid Value' // Empty key should trigger meta validation error
            ]
        ];

        // Mock UserExpand model
        $this->ExpandableUser->UserExpand = $this->getMockForModel(
            'Expandable.UserExpand',
            ['save']
        );

        // Save should not be called since validation failed
        $this->ExpandableUser->UserExpand
            ->expects($this->never())
            ->method('save');

        // Perform the test
        $result = $this->ExpandableUser->save($data);
        $this->assertFalse($result);
        $this->assertEquals(1, count($this->ExpandableUser->validationErrors));
        $this->assertArrayHasKey('_system', $this->ExpandableUser->validationErrors);
        $this->assertEquals(
            ['An unexpected error occurred. Please contact support if this persists.'],
            $this->ExpandableUser->validationErrors['_system']
        );
    }

    /**
     * Test validation when saving with both base model and EAV value/meta data validation errors
     *
     * @return void
     */
    public function testSaveWithBothValidationErrors()
    {
        // Set up validation rule for the base model
        $this->ExpandableUser->validate = [
            'name' => [
                'rule' => 'notBlank',
                'message' => 'Name is required'
            ]
        ];

        // Set up test data with invalid base model and EAV fields
        $data = [
            'ExpandableUser' => [
                'name' => '', // Invalid
                'custom_field' => '', // Invalid
                '' => 'Valid Value' // Invalid
            ]
        ];

        // Mock UserExpand model
        $this->ExpandableUser->UserExpand = $this->getMockForModel(
            'Expandable.UserExpand',
            ['save']
        );

        // Set up EAV model's validation rules for this test
        $this->ExpandableUser->UserExpand->validate = [
            'key' => [
                'notblank' => [
                    'rule' => ['notBlank'],
                    'required' => 'create',
                    'allowEmpty' => false,
                    'message' => 'Key must not be blank'
                ],
            ],
            'value' => [
                'notblank' => [
                    'rule' => ['notBlank'],
                    'required' => 'create',
                    'allowEmpty' => false,
                    'message' => 'Value must not be blank'
                ],
            ],
        ];

        // Save should not be called since validation failed
        $this->ExpandableUser->UserExpand
            ->expects($this->never())
            ->method('save');

        $result = $this->ExpandableUser->save($data);
        $this->assertFalse($result);
        $this->assertEquals(3, count($this->ExpandableUser->validationErrors));
        $this->assertArrayHasKey('name', $this->ExpandableUser->validationErrors);
        $this->assertArrayHasKey('custom_field', $this->ExpandableUser->validationErrors);
        $this->assertEquals(
            ['Name is required'],
            $this->ExpandableUser->validationErrors['name']
        );
        $this->assertEquals(
            ['Value must not be blank'],
            $this->ExpandableUser->validationErrors['custom_field']
        );
        $this->assertEquals(
            ['An unexpected error occurred. Please contact support if this persists.'],
            $this->ExpandableUser->validationErrors['_system']
        );
    }

    /**
     * Test validation when saving with multiple EAV fields having validation errors
     *
     * @return void
     */
    public function testSaveWithMultipleEavValidationErrors()
    {
        // Set up test data with multiple invalid EAV fields
        $data = [
            'ExpandableUser' => [
                'name' => 'Valid Name',
                'custom_field1' => '', // Invalid
                'custom_field2' => '' // Invalid
            ]
        ];

        // Mock UserExpand model
        $this->ExpandableUser->UserExpand = $this->getMockForModel(
            'Expandable.UserExpand',
            ['validates', 'save']
        );

        // Counter to track validation calls
        $validationCount = 0;

        // Expect validation to be called for each field
        $this->ExpandableUser->UserExpand
            ->expects($this->exactly(2))
            ->method('validates')
            ->will($this->returnCallback(function() use (&$validationCount) {
                $validationCount++;
                $this->ExpandableUser->UserExpand->validationErrors = [
                    'value' => ['Value must not be blank']
                ];
                return false;
            }));

        // Save should not be called since validation failed
        $this->ExpandableUser->UserExpand
            ->expects($this->never())
            ->method('save');

        $result = $this->ExpandableUser->save($data);
        $this->assertFalse($result);
        $this->assertArrayHasKey('custom_field1', $this->ExpandableUser->validationErrors);
        $this->assertArrayHasKey('custom_field2', $this->ExpandableUser->validationErrors);
        $this->assertEquals(
            ['Value must not be blank'],
            $this->ExpandableUser->validationErrors['custom_field1']
        );
        $this->assertEquals(
            ['Value must not be blank'],
            $this->ExpandableUser->validationErrors['custom_field2']
        );
    }

    /**
     * Test validation when saving with nested model validation errors
     *
     * @return void
     */
    public function testSaveWithNestedModelValidationErrors()
    {
        // Set up belongsTo relationship
        $this->ExpandableUser->hasOne = [
            'Profile' => [
                'className' => 'Profile',
                'foreignKey' => 'user_id',
            ]
        ];

        // Mock Profile model to track save attempts
        $this->ExpandableUser->Profile = ClassRegistry::init('Expandable.Profile');
        $this->ExpandableUser->Profile->bindModel([
            'belongsTo' => [
                'ExpandableUser' => [
                    'className' => 'Expandable.ExpandableUser',
                    'foreignKey' => 'user_id'
                ]
            ]
        ]);

        // Set up test data with valid base model and EAV fields, but invalid nested model data
        $data = [
            'ExpandableUser' => [
                'name' => 'Test Valid Name',
                'login' => 'test_user_1',
                'custom_field' => 'Valid Value',
            ],
            'Profile' => [
                'bio' => '' // Empty bio should trigger notBlank validation error
            ]
        ];

        // Test that the data is not saved
        $result = $this->ExpandableUser->saveAssociated($data, [
            'validate' => true,
            'deep' => true,
            'atomic' => true
        ]);
        $this->assertFalse($result);
        $this->assertArrayHasKey('bio', $this->ExpandableUser->Profile->validationErrors);
        $this->assertArrayNotHasKey('custom_field', $this->ExpandableUser->validationErrors);
        $this->assertNotEmpty($this->ExpandableUser->find('first', ['conditions' => ['id' => $this->ExpandableUser->id]]));
        $this->assertEmpty($this->ExpandableUser->Profile->find('first', ['conditions' => ['user_id' => $this->ExpandableUser->id]]));
        $this->assertEmpty($this->ExpandableUser->UserExpand->find('first', ['conditions' => ['user_id' => $this->ExpandableUser->id]]));

        // Test that the data is saved
        $data = [
            'ExpandableUser' => [
                'name' => 'Test Valid Name 2',
                'email' => 'test2@example.com',
                'is_active' => true,
                'custom_field' => 'Valid Value',
            ],
            'Profile' => [
                'bio' => 'Valid Bio'
            ]
        ];
        $this->ExpandableUser->create(); // Create a new record
        $result = $this->ExpandableUser->saveAssociated($data, [
            'validate' => true,
            'deep' => true,
            'atomic' => true
        ]);
        $this->assertNotEmpty($result);
        $this->assertNotEmpty($this->ExpandableUser->Profile->find('first', ['conditions' => ['user_id' => $this->ExpandableUser->id]]));
        $this->assertNotEmpty($this->ExpandableUser->UserExpand->find('first', ['conditions' => ['user_id' => $this->ExpandableUser->id]]));
        $this->assertNotEmpty($this->ExpandableUser->find('first', ['conditions' => ['id' => $this->ExpandableUser->id]]));
    }

    /**
     * Test that afterSave properly saves EAV data with correct structure and relationships
     *
     * @return void
     */
    public function testAfterSaveEavData()
    {
        // Set up test data with various field types
        $data = [
            'ExpandableUser' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'is_active' => true,
                'custom_string' => 'string value',
                'custom_number' => 123,
                'custom_boolean' => true,
                'custom_array' => ['one', 'two', 'three'],
                'custom_object' => ['key' => 'value'],
                'custom_date' => '2024-01-01'
            ]
        ];

        // Save the data
        $result = $this->ExpandableUser->save($data);
        $this->assertNotEmpty($result);

        // Verify the base model data was saved
        $saved = $this->ExpandableUser->find('first', [
            'contain' => ['UserExpand'],
            'conditions' => ['ExpandableUser.id' => $this->ExpandableUser->id]
        ]);
        $this->assertEquals('Test User', $saved['ExpandableUser']['name']);
        $this->assertEquals('test@example.com', $saved['ExpandableUser']['email']);
        $this->assertTrue($saved['ExpandableUser']['is_active']);

        // Verify EAV data was saved with correct structure
        $this->assertNotEmpty($saved['UserExpand']);

        // Expected EAV data
        $expectedEav = [
            'custom_string' => 'string value',
            'custom_number' => '123',
            'custom_boolean' => 'true', // Boolean values are stored as "true"/"false" strings
            'custom_array' => json_encode(['one', 'two', 'three']),
            'custom_object' => json_encode(['key' => 'value']),
            'custom_date' => '2024-01-01'
        ];

        // Verify each EAV record
        foreach ($saved['UserExpand'] as $eav) {
            $this->assertArrayHasKey('key', $eav);
            $this->assertArrayHasKey('value', $eav);
            $this->assertArrayHasKey('user_id', $eav);
            $this->assertEquals($this->ExpandableUser->id, $eav['user_id']);

            // Verify the value matches what we expect
            $this->assertArrayHasKey($eav['key'], $expectedEav);
            $this->assertEquals($expectedEav[$eav['key']], $eav['value']);
        }

        // Test updating existing EAV data
        $updateData = [
            'ExpandableUser' => [
                'id' => $this->ExpandableUser->id,
                'custom_string' => 'updated value',
                'custom_number' => 456
            ]
        ];

        $result = $this->ExpandableUser->save($updateData);
        $this->assertNotEmpty($result);

        // Find the updated record
        $updated = $this->ExpandableUser->find('first', [
            'contain' => ['UserExpand'],
            'conditions' => ['ExpandableUser.id' => $this->ExpandableUser->id]
        ]);

        // Verify the EAV data was updated
        foreach ($updated['UserExpand'] as $eav) {
            if ($eav['key'] === 'custom_string') {
                $this->assertEquals('updated value', $eav['value']);
            } elseif ($eav['key'] === 'custom_number') {
                $this->assertEquals('456', $eav['value']);
            }
        }
    }
}
