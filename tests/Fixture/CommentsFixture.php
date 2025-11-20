<?php
declare(strict_types=1);

namespace Synapse\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * CommentsFixture
 */
class CommentsFixture extends TestFixture
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
                'article_id' => 1,
                'user_id' => 1,
                'content' => 'Great article!',
                'created' => '2024-01-01 11:00:00',
                'modified' => '2024-01-01 11:00:00',
            ],
            [
                'id' => 2,
                'article_id' => 1,
                'user_id' => 2,
                'content' => 'Very informative.',
                'created' => '2024-01-01 12:00:00',
                'modified' => '2024-01-01 12:00:00',
            ],
            [
                'id' => 3,
                'article_id' => 2,
                'user_id' => 1,
                'content' => 'Thanks for sharing.',
                'created' => '2024-01-02 13:00:00',
                'modified' => '2024-01-02 13:00:00',
            ],
        ];
        parent::init();
    }
}
