<?php
declare(strict_types=1);

namespace Synapse\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
{
    /**
     * Init method
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'username' => 'admin',
                'password' => '$2y$10$abcdefghijklmnopqrstuv',
                'email' => 'admin@example.com',
                'active' => true,
                'created' => '2024-01-01 08:00:00',
                'modified' => '2024-01-01 08:00:00',
            ],
            [
                'id' => 2,
                'username' => 'user1',
                'password' => '$2y$10$abcdefghijklmnopqrstuv',
                'email' => 'user1@example.com',
                'active' => true,
                'created' => '2024-01-01 08:30:00',
                'modified' => '2024-01-01 08:30:00',
            ],
        ];
        parent::init();
    }
}
