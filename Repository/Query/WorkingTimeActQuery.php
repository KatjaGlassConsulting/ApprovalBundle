<?php

namespace KimaiPlugin\ApprovalBundle\Repository\Query;

use App\Repository\Query\BaseQuery;
use App\Repository\Query\DateRangeInterface;
use App\Repository\Query\DateRangeTrait;


class WorkingTimeActQuery extends BaseQuery implements DateRangeInterface
{
    use DateRangeTrait;
    public const ORDER_ALLOWED = ['user', 'average_6months', 'average_24weeks'];

    /**
     * @var array<User>
     */
    private array $users = [];

    public function __construct()
    {
        $this->setDefaults([
            'order' => self::ORDER_ASC,
            'orderBy' => 'user',
        ]);

        $this->setAllowedOrderColumns(self::ORDER_ALLOWED);
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return array_values($this->users);
    }

    public function setUsers(array $users): void
    {
        $this->users = $users;
    }
}
