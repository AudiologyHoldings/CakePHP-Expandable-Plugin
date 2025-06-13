<?php
/**
 * Profile Fixture (for testing nested model validation)
 */
class ProfileFixture extends CakeTestFixture {
    public $fields = [
        'id' => ['type' => 'integer', 'null' => false, 'default' => null, 'key' => 'primary'],
        'expandable_user_id' => ['type' => 'integer', 'null' => false, 'default' => null],
        'bio' => ['type' => 'text', 'null' => true, 'default' => null],
        'created' => ['type' => 'datetime', 'null' => true, 'default' => null],
        'modified' => ['type' => 'datetime', 'null' => true, 'default' => null],
        'indexes' => [
            'PRIMARY' => ['column' => 'id', 'unique' => true],
        ],
    ];

    public $records = [
        [
            'id' => 1,
            'expandable_user_id' => 1,
            'bio' => 'Test bio for user 1',
            'created' => '2024-01-01 00:00:00',
            'modified' => '2024-01-01 00:00:00'
        ],
    ];
} 