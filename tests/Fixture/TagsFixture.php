<?php
declare(strict_types=1);

namespace Synapse\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TagsFixture
 */
class TagsFixture extends TestFixture
{
    /**
     * Init method
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'CakePHP',
                'created' => '2024-01-01 08:00:00',
                'modified' => '2024-01-01 08:00:00',
            ],
            [
                'id' => 2,
                'name' => 'PHP',
                'created' => '2024-01-01 08:15:00',
                'modified' => '2024-01-01 08:15:00',
            ],
            [
                'id' => 3,
                'name' => 'Testing',
                'created' => '2024-01-01 08:30:00',
                'modified' => '2024-01-01 08:30:00',
            ],
        ];
        parent::init();
    }
}
