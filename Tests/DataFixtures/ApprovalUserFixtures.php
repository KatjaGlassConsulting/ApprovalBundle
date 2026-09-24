<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\DataFixtures;

use App\Entity\Team;
use App\Entity\User;
use App\Tests\DataFixtures\TestFixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Creates the three actors documentation.md describes: a user who submits a week, the teamlead
 * who approves or denies it and an admin who may overrule an already approved week.
 *
 * Submitter and teamlead are put into one team, otherwise the teamlead has no permission to
 * see the submitter's timesheets.
 *
 * Passwords are never verified in tests - AbstractControllerBaseTestCase::loginUser()
 * injects the security token directly - so a placeholder hash is enough.
 */
final class ApprovalUserFixtures implements TestFixture
{
    public const USERNAME_SUBMITTER = 'approval_submitter';
    public const USERNAME_APPROVER = 'approval_approver';
    public const USERNAME_ADMIN = 'approval_admin';

    /**
     * @return array{0: User, 1: User, 2: User, 3: Team}
     */
    public function load(ObjectManager $manager): array
    {
        $submitter = $this->createUser(self::USERNAME_SUBMITTER, 'Sam Submitter', [User::ROLE_USER]);
        $approver = $this->createUser(self::USERNAME_APPROVER, 'Alex Approver', [User::ROLE_TEAMLEAD]);
        // only ROLE_SUPER_ADMIN carries "view_all_approval", see ApprovalExtension::prepend()
        $admin = $this->createUser(self::USERNAME_ADMIN, 'Andy Admin', [User::ROLE_SUPER_ADMIN]);

        $manager->persist($submitter);
        $manager->persist($approver);
        $manager->persist($admin);

        $team = new Team('Approval test team');
        $team->addTeamlead($approver);
        $team->addUser($submitter);
        $manager->persist($team);

        $manager->flush();

        return [$submitter, $approver, $admin, $team];
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(string $username, string $alias, array $roles): User
    {
        $user = new User();
        $user->setUserIdentifier($username);
        $user->setEmail($username . '@example.com');
        $user->setAlias($alias);
        $user->setPassword('not-used-in-tests');
        $user->setEnabled(true);
        $user->setRoles($roles);
        $user->setRegisteredAt(new \DateTime('2024-01-01 08:00:00'));

        // otherwise the first request is redirected to the setup wizard
        foreach (User::WIZARDS as $wizard) {
            $user->setWizardAsSeen($wizard);
        }

        return $user;
    }
}
