<?php
declare(strict_types=1);

namespace Synapse\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ArticlesFixture
 */
class ArticlesFixture extends TestFixture
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
                'title' => 'First Article',
                'body' => 'This is the content of the first article.',
                'author_id' => 1,
                'published' => true,
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'title' => 'Second Article',
                'body' => 'This is the content of the second article.',
                'author_id' => 2,
                'published' => true,
                'created' => '2024-01-02 11:00:00',
                'modified' => '2024-01-02 11:00:00',
            ],
            [
                'id' => 3,
                'title' => 'Draft Article',
                'body' => 'This article is not published yet.',
                'author_id' => 1,
                'published' => false,
                'created' => '2024-01-03 12:00:00',
                'modified' => '2024-01-03 12:00:00',
            ],
        ];
        parent::init();
    }
}
