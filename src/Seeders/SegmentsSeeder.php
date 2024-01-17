<?php

namespace Crm\RempMailerModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Seeders\SegmentsTrait;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
    use SegmentsTrait;

    private $segmentGroupsRepository;

    private $segmentsRepository;

    public function __construct(
        SegmentGroupsRepository $segmentGroupsRepository,
        SegmentsRepository $segmentsRepository
    ) {
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $this->seedSegment(
            $output,
            'Unsubscribe inactive users from newsletters list',
            'unsubscribe_inactive_users_from_newsletters_list',
            <<<SQL
SELECT %fields%
FROM %table%

WHERE %where%
  AND %table%.active=1
  AND %table%.role='user'
  AND %table%.deleted_at IS NULL
  AND %table%.created_at < NOW() - INTERVAL 1 YEAR
  AND users.id NOT IN (SELECT user_id FROM subscriptions WHERE subscriptions.end_time > NOW() - INTERVAL 30 DAY)


GROUP BY %table%.id
SQL,
            null,
            'users',
            'users.id, users.email'
        );
    }
}
