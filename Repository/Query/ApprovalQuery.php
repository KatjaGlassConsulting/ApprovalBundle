<?php
namespace KimaiPlugin\ApprovalBundle\Repository\Query;

use App\Entity\User;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\DateRangeTrait;
use App\Repository\Query\DateRangeInterface;

class ApprovalQuery extends BaseQuery implements DateRangeInterface
{
    use DateRangeTrait;
    public const APPROVAL_ORDER_ALLOWED = ['user', 'week', 'status'];

    /**
     * @var array<User>
     */
    public array $users = [];
    private array $weeks = [];
    private array $statuses = [];

    public function __construct()
    {
        $this->setDefaults([
            'order' => self::ORDER_ASC,
            'orderBy' => 'week',
        ]);

        $this->setAllowedOrderColumns(self::APPROVAL_ORDER_ALLOWED);
    }

    public function getStatus(): array
    {
        return $this->statuses;
    }

    public function setStatus(array $statuses): void
    {
        $this->statuses = $statuses;
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return array_values($this->users);
    }
}