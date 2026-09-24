<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Toolbox;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * SecurityTool decides whose approvals the current user gets to see, which is the
 * distinction documentation.md draws between the three roles:
 *
 *   - "Users - Submit for Approval": their own weeks
 *   - "Teamleads": their own weeks plus the weeks of their team members
 *   - "Admins - Overview & Overrule": any approval
 *
 * @covers \KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool
 */
class SecurityToolTest extends TestCase
{
    /**
     * @param array<User> $allUsers returned by the UserRepository, only relevant for admins
     */
    private function createSut(User $currentUser, bool $viewAll, bool $viewTeam, array $allUsers = []): SecurityTool
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($currentUser);
        $security->method('isGranted')->willReturnCallback(
            fn (mixed $attribute): bool => match ($attribute) {
                'view_all_approval' => $viewAll,
                'view_team_approval' => $viewTeam,
                default => false,
            }
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findAll')->willReturn($allUsers);

        return new SecurityTool($security, $userRepository);
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUserIdentifier($username);
        $user->setAlias(ucfirst($username));
        $user->setEnabled(true);

        return $user;
    }

    /**
     * @param array<User> $users
     * @return array<string>
     */
    private static function usernames(array $users): array
    {
        return array_map(fn (User $user): string => $user->getUserIdentifier(), $users);
    }

    public function testGetUserReturnsTheAuthenticatedUser(): void
    {
        $user = $this->createUser('sam');

        self::assertSame($user, $this->createSut($user, false, false)->getUser());
    }

    public function testPlainUserOnlySeesThemselves(): void
    {
        $sam = $this->createUser('sam');
        $other = $this->createUser('other');

        $sut = $this->createSut($sam, false, false, [$sam, $other]);

        self::assertFalse($sut->canViewAllApprovals());
        self::assertFalse($sut->canViewTeamApprovals());
        self::assertSame(['sam'], self::usernames($sut->getUsers()));
    }

    public function testTeamleadSeesTheirWholeTeam(): void
    {
        $lead = $this->createUser('lead');
        $memberB = $this->createUser('bob');
        $memberA = $this->createUser('alice');

        $team = new Team('Team of the lead');
        $team->addTeamlead($lead);
        $team->addUser($memberB);
        $team->addUser($memberA);

        $sut = $this->createSut($lead, false, true);

        self::assertTrue($sut->canViewTeamApprovals());
        // the teamlead is part of the team and the list is sorted by username
        self::assertSame(['alice', 'bob', 'lead'], self::usernames($sut->getUsers()));
    }

    public function testTeamleadOfOneTeamWhoIsOnlyAMemberOfAnotherSeesOnlyTheirOwnTeam(): void
    {
        // documentation.md: "A teamlead can also be a team member of a different team"
        $lead = $this->createUser('lead');
        $ownMember = $this->createUser('alice');
        $foreignLead = $this->createUser('zoe');
        $foreignMember = $this->createUser('bob');

        $ownTeam = new Team('Own team');
        $ownTeam->addTeamlead($lead);
        $ownTeam->addUser($ownMember);

        $foreignTeam = new Team('Foreign team');
        $foreignTeam->addTeamlead($foreignLead);
        $foreignTeam->addUser($lead);
        $foreignTeam->addUser($foreignMember);

        $sut = $this->createSut($lead, false, true);

        self::assertSame(['alice', 'lead'], self::usernames($sut->getUsers()));
    }

    public function testTeamleadWithoutATeamStillSeesThemselves(): void
    {
        $lead = $this->createUser('lead');

        self::assertSame(['lead'], self::usernames($this->createSut($lead, false, true)->getUsers()));
    }

    public function testAdminSeesEveryUser(): void
    {
        $admin = $this->createUser('admin');
        $charlie = $this->createUser('charlie');
        $alice = $this->createUser('alice');

        $sut = $this->createSut($admin, true, true, [$charlie, $admin, $alice]);

        self::assertTrue($sut->canViewAllApprovals());
        self::assertSame(['admin', 'alice', 'charlie'], self::usernames($sut->getUsers()));
    }

    public function testSuperAdminsSystemAccountsAndDisabledUsersAreNeverListed(): void
    {
        $admin = $this->createUser('admin');

        $disabled = $this->createUser('disabled');
        $disabled->setEnabled(false);

        $superAdmin = $this->createUser('superadmin');
        $superAdmin->setSuperAdmin(true);

        $systemAccount = $this->createUser('system');
        $systemAccount->setSystemAccount(true);

        $sut = $this->createSut($admin, true, true, [$admin, $disabled, $superAdmin, $systemAccount]);

        self::assertSame(['admin'], self::usernames($sut->getUsers()));
    }
}
