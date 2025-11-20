<?php
declare(strict_types=1);

namespace Synapse\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AuthorsFixture
 */
class AuthorsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'bio' => 'A prolific writer',
                'created' => '2024-01-01 09:00:00',
                'modified' => '2024-01-01 09:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'bio' => 'Tech enthusiast',
                'created' => '2024-01-01 09:30:00',
                'modified' => '2024-01-01 09:30:00',
            ],
        ];
        parent::init();
    }
}
