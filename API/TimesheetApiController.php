<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\API;

use App\API\BaseApiController;
use App\Entity\Timesheet;
use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TagRepository;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use App\Utils\SearchTerm;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use FOS\RestBundle\View\View;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use FOS\RestBundle\View\ViewHandlerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints;

#[Route(path: '/approval/timesheets')]
#[IsGranted('API')]
#[OA\Tag(name: 'Timesheet')]
final class TimesheetApiController extends BaseApiController
{
    public const GROUPS_ENTITY = ['Default', 'Entity', 'Timesheet', 'Timesheet_Entity', 'Not_Expanded'];
    public const GROUPS_ENTITY_FULL = ['Default', 'Entity', 'Timesheet', 'Timesheet_Entity', 'Expanded'];
    public const GROUPS_FORM = ['Default', 'Entity', 'Timesheet', 'Not_Expanded'];
    public const GROUPS_COLLECTION = ['Default', 'Collection', 'Timesheet', 'Not_Expanded'];
    public const GROUPS_COLLECTION_FULL = ['Default', 'Collection', 'Timesheet', 'Expanded'];

    public function __construct(
        private readonly ViewHandlerInterface $viewHandler,
        private readonly TimesheetRepository $repository,
        private readonly TagRepository $tagRepository,
        private readonly ApprovalRepository $approvalRepository
    ) {
    }

    /**
     * Fetch timesheets
     */
    #[IsGranted(new Expression("is_granted('view_own_timesheet') or is_granted('view_other_timesheet')"))]
    #[OA\Response(response: 200, description: 'Returns a collection of timesheets. The datetime fields are given in the users local time including the timezone offset (ISO-8601).', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TimesheetCollection')))]
    #[Route(methods: ['GET'], path: '', name: 'approval_get_timesheets')]
    #[Rest\QueryParam(name: 'user', requirements: '\d+|all', strict: true, nullable: true, description: "User ID to filter timesheets. Needs permission 'view_other_timesheet', pass 'all' to fetch data for all user (default: current user)")]
    #[Rest\QueryParam(name: 'users', map: true, requirements: '\d+', strict: true, nullable: true, default: [], description: 'List of user IDs to filter, e.g.: users[]=1&users[]=2 (ignored if user=all)')]
    #[Rest\QueryParam(name: 'customer', requirements: '\d+', strict: true, nullable: true, description: 'Customer ID to filter timesheets')]
    #[Rest\QueryParam(name: 'customers', map: true, requirements: '\d+', strict: true, nullable: true, default: [], description: 'List of customer IDs to filter, e.g.: customers[]=1&customers[]=2')]
    #[Rest\QueryParam(name: 'project', requirements: '\d+', strict: true, nullable: true, description: 'Project ID to filter timesheets')]
    #[Rest\QueryParam(name: 'projects', map: true, requirements: '\d+', strict: true, nullable: true, default: [], description: 'List of project IDs to filter, e.g.: projects[]=1&projects[]=2')]
    #[Rest\QueryParam(name: 'activity', requirements: '\d+', strict: true, nullable: true, description: 'Activity ID to filter timesheets')]
    #[Rest\QueryParam(name: 'activities', map: true, requirements: '\d+', strict: true, nullable: true, default: [], description: 'List of activity IDs to filter, e.g.: activities[]=1&activities[]=2')]
    #[Rest\QueryParam(name: 'page', requirements: '\d+', strict: true, nullable: true, description: 'The page to display, renders a 404 if not found (default: 1)')]
    #[Rest\QueryParam(name: 'size', requirements: '\d+', strict: true, nullable: true, description: 'The amount of entries for each page (default: 50, max: 500)')]
    #[Rest\QueryParam(name: 'tags', map: true, strict: true, nullable: true, default: [], description: 'List of tag names, e.g. tags[]=bar&tags[]=foo')]
    #[Rest\QueryParam(name: 'orderBy', requirements: 'id|begin|end|rate', strict: true, nullable: true, description: 'The field by which results will be ordered. Allowed values: id, begin, end, rate (default: begin)')]
    #[Rest\QueryParam(name: 'order', requirements: 'ASC|DESC', strict: true, nullable: true, description: 'The result order. Allowed values: ASC, DESC (default: DESC)')]
    #[Rest\QueryParam(name: 'begin', requirements: [new Constraints\DateTime(format: 'Y-m-d\TH:i:s')], strict: true, nullable: true, description: 'Only records started at or after this date-time will be included (format: HTML5 datetime-local, e.g. YYYY-MM-DDThh:mm:ss)')]
    #[Rest\QueryParam(name: 'end', requirements: [new Constraints\DateTime(format: 'Y-m-d\TH:i:s')], strict: true, nullable: true, description: 'Only records started at or before this date-time will be included (format: HTML5 datetime-local, e.g. YYYY-MM-DDThh:mm:ss)')]
    #[Rest\QueryParam(name: 'exported', requirements: '0|1', strict: true, nullable: true, description: 'Use this flag if you want to filter for export state. Allowed values: 0=not exported, 1=exported (default: all)')]
    #[Rest\QueryParam(name: 'active', requirements: '0|1', strict: true, nullable: true, description: 'Filter for running/active records. Allowed values: 0=stopped, 1=active (default: all)')]
    #[Rest\QueryParam(name: 'billable', requirements: '0|1', strict: true, nullable: true, description: 'Filter for non-/billable records. Allowed values: 0=non-billable, 1=billable (default: all)')]
    #[Rest\QueryParam(name: 'full', requirements: '0|1|true|false', strict: true, nullable: true, description: 'Allows to fetch full objects including subresources. Allowed values: 0|1|false|true (default: false)')]
    #[Rest\QueryParam(name: 'approved', requirements: '0|1|true|false', strict: true, nullable: true, description: 'Filter by approval status. Allowed values: 0|1|false|true (default: all)')]
    #[Rest\QueryParam(name: 'term', description: 'Free search term', nullable: true)]
    #[Rest\QueryParam(name: 'modified_after', requirements: [new Constraints\DateTime(format: 'Y-m-d\TH:i:s')], strict: true, nullable: true, description: 'Only records changed after this date will be included. You need to pass in a UTC date-time, as this field is stored in UTC (format: HTML5 datetime-local, e.g. YYYY-MM-DDThh:mm:ss)')]
    public function cgetAction(ParamFetcherInterface $paramFetcher, CustomerRepository $customerRepository, ProjectRepository $projectRepository, ActivityRepository $activityRepository, UserRepository $userRepository): Response
    {
        $query = new TimesheetQuery(false);
        $this->prepareQuery($query, $paramFetcher);

        $seeAll = $this->applyUserFilters($query, $paramFetcher, $userRepository);
        $this->applyUserFallback($query, $seeAll);

        $this->applyCustomerFilters($query, $paramFetcher, $customerRepository);
        $this->applyProjectFilters($query, $paramFetcher, $projectRepository);
        $this->applyActivityFilters($query, $paramFetcher, $activityRepository);
        $this->applyTagFilters($query, $paramFetcher);
        $this->applyDateFilters($query, $paramFetcher);
        $this->applyStateFilters($query, $paramFetcher);
        $this->applySearchFilters($query, $paramFetcher);
        $this->applyModifiedAfter($query, $paramFetcher);

        [$data, $results, $approvalMap] = $this->applyApprovalFilter($query, $paramFetcher);

        $view = new View($results, Response::HTTP_OK);
        $this->applyCollectionGroups($view, $paramFetcher);
        $this->applyPaginationHeaders($view, $data);
        $response = $this->viewHandler->handle($view);

        return $this->addApprovalToJsonResponse($response, $results, $approvalMap);
    }

    private function applyUserFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher, UserRepository $userRepository): bool
    {
        $seeAll = false;

        if ($this->isGranted('view_other_timesheet')) {
            /** @var array<int> $users */
            $users = $paramFetcher->get('users');
            $userId = $paramFetcher->get('user');

            if ('all' === $userId) {
                $seeAll = true;
            } elseif (\is_string($userId) && $userId !== '') {
                $users[] = (int) $userId;
            }

            if (!$seeAll) {
                foreach ($userRepository->findByIds($users) as $user) {
                    $query->addUser($user);
                }
            }
        }

        return $seeAll;
    }

    private function applyUserFallback(TimesheetQuery $query, bool $seeAll): void
    {
        if ($seeAll) {
            $query->setUser(null);

            return;
        }

        if (!$query->hasUsers()) {
            $query->setUser($this->getUser());
        }
    }

    private function applyCustomerFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher, CustomerRepository $customerRepository): void
    {
        /** @var array<int> $customers */
        $customers = $paramFetcher->get('customers');
        $customer = $paramFetcher->get('customer');
        if (\is_string($customer) && $customer !== '') {
            $customers[] = $customer;
        }

        foreach (array_unique($customers) as $customerId) {
            $customer = $customerRepository->find($customerId);
            if ($customer === null) {
                throw $this->createNotFoundException('Unknown customer: ' . $customerId);
            }
            $query->addCustomer($customer);
        }
    }

    private function applyProjectFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher, ProjectRepository $projectRepository): void
    {
        /** @var array<int> $projects */
        $projects = $paramFetcher->get('projects');
        $project = $paramFetcher->get('project');
        if (\is_string($project) && $project !== '') {
            $projects[] = $project;
        }

        foreach (array_unique($projects) as $projectId) {
            $project = $projectRepository->find($projectId);
            if ($project === null) {
                throw $this->createNotFoundException('Unknown project: ' . $project);
            }
            $query->addProject($project);
        }
    }

    private function applyActivityFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher, ActivityRepository $activityRepository): void
    {
        /** @var array<int> $activities */
        $activities = $paramFetcher->get('activities');
        $activity = $paramFetcher->get('activity');
        if (\is_string($activity) && $activity !== '') {
            $activities[] = $activity;
        }

        foreach (array_unique($activities) as $activityId) {
            $activity = $activityRepository->find($activityId);
            if ($activity === null) {
                throw $this->createNotFoundException('Unknown activity: ' . $activity);
            }
            $query->addActivity($activity);
        }
    }

    private function applyTagFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher): void
    {
        /** @var array<string> $tags */
        $tags = $paramFetcher->get('tags');
        if (!\is_array($tags) || \count($tags) === 0) {
            return;
        }

        $tagsByName = $this->tagRepository->findTagsByName($tags, true);
        if (\count($tagsByName) === 0) {
            throw new BadRequestHttpException('Given tags were not found');
        }
        foreach ($tagsByName as $tag) {
            $query->addTag($tag);
        }
    }

    private function applyDateFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher): void
    {
        $factory = $this->getDateTimeFactory();

        $begin = $paramFetcher->get('begin');
        if (\is_string($begin) && $begin !== '') {
            $query->setBegin($factory->createDateTime($begin));
        }

        $end = $paramFetcher->get('end');
        if (\is_string($end) && $end !== '') {
            $query->setEnd($factory->createDateTime($end));
        }
    }

    private function applyStateFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher): void
    {
        $active = $paramFetcher->get('active');
        if (\is_string($active) && $active !== '') {
            $active = (int) $active;
            if ($active === 1) {
                $query->setState(TimesheetQuery::STATE_RUNNING);
            } elseif ($active === 0) {
                $query->setState(TimesheetQuery::STATE_STOPPED);
            }
        }

        $billable = $paramFetcher->get('billable');
        if (\is_string($billable) && $billable !== '') {
            $billable = (int) $billable;
            if ($billable === 1) {
                $query->setBillable(true);
            } elseif ($billable === 0) {
                $query->setBillable(false);
            }
        }

        $exported = $paramFetcher->get('exported');
        if (\is_string($exported) && $exported !== '') {
            $exported = (int) $exported;
            if ($exported === 1) {
                $query->setExported(TimesheetQuery::STATE_EXPORTED);
            } elseif ($exported === 0) {
                $query->setExported(TimesheetQuery::STATE_NOT_EXPORTED);
            }
        }
    }

    private function applySearchFilters(TimesheetQuery $query, ParamFetcherInterface $paramFetcher): void
    {
        $term = $paramFetcher->get('term');
        if (\is_string($term) && $term !== '') {
            $query->setSearchTerm(new SearchTerm($term));
        }
    }

    private function applyModifiedAfter(TimesheetQuery $query, ParamFetcherInterface $paramFetcher): void
    {
        $modifiedAfter = $paramFetcher->get('modified_after');
        if (\is_string($modifiedAfter)) {
            $query->setModifiedAfter(new \DateTimeImmutable($modifiedAfter, new \DateTimeZone('UTC')));
        }
    }

    private function applyCollectionGroups(View $view, ParamFetcherInterface $paramFetcher): void
    {
        $full = $paramFetcher->get('full');
        if ($full === '1' || $full === 'true') {
            $view->getContext()->setGroups(self::GROUPS_COLLECTION_FULL);

            return;
        }

        $view->getContext()->setGroups(self::GROUPS_COLLECTION);
    }

    private function applyPaginationHeaders(View $view, Pagerfanta $data): void
    {
        $view->setHeader('X-Page', (string) $data->getCurrentPage());
        $view->setHeader('X-Total-Count', (string) $data->getNbResults());
        $view->setHeader('X-Total-Pages', (string) $data->getNbPages());
        $view->setHeader('X-Per-Page', (string) $data->getMaxPerPage());
    }

    private function applyApprovalFilter(TimesheetQuery $query, ParamFetcherInterface $paramFetcher): array
    {
        $approved = $this->getApprovedFilter($paramFetcher);
        if ($approved === null) {
            $data = $this->repository->getPagerfantaForQuery($query);
            $results = (array) $data->getCurrentPageResults();
            $approvalMap = $this->buildApprovalMap($results);

            return [$data, $results, $approvalMap];
        }

        $timesheets = $this->repository->getTimesheetsForQuery($query);
        $approvalMap = $this->buildApprovalMap($timesheets);
        $filtered = array_values(array_filter(
            $timesheets,
            function (Timesheet $timesheet) use ($approvalMap, $approved): bool {
                return ($approvalMap[$timesheet->getId()] ?? false) === $approved;
            }
        ));

        $pager = $this->createPagerfanta($filtered, $paramFetcher);
        $results = (array) $pager->getCurrentPageResults();

        return [$pager, $results, $approvalMap];
    }

    private function getApprovedFilter(ParamFetcherInterface $paramFetcher): ?bool
    {
        $approved = $paramFetcher->get('approved');
        if (!\is_string($approved) || $approved === '') {
            return null;
        }

        if ($approved === '1' || $approved === 'true') {
            return true;
        }

        if ($approved === '0' || $approved === 'false') {
            return false;
        }

        return null;
    }

    private function createPagerfanta(array $timesheets, ParamFetcherInterface $paramFetcher): Pagerfanta
    {
        $pager = new Pagerfanta(new ArrayAdapter($timesheets));
        $pager->setMaxPerPage($this->getPageSize($paramFetcher));
        $pager->setCurrentPage($this->getPageNumber($paramFetcher));

        return $pager;
    }

    private function getPageNumber(ParamFetcherInterface $paramFetcher): int
    {
        $page = $paramFetcher->get('page');
        if (\is_string($page) && $page !== '') {
            return max(1, (int) $page);
        }

        return 1;
    }

    private function getPageSize(ParamFetcherInterface $paramFetcher): int
    {
        $size = $paramFetcher->get('size');
        if (\is_string($size) && $size !== '') {
            return min(500, max(1, (int) $size));
        }

        return 50;
    }

    private function buildApprovalMap(array $timesheets): array
    {
        if ($timesheets === []) {
            return [];
        }

        $userIds = [];
        $minDate = null;
        $maxDate = null;

        foreach ($timesheets as $timesheet) {
            if (!$timesheet instanceof Timesheet) {
                continue;
            }

            $userIds[] = $timesheet->getUser()->getId();
            $date = $timesheet->getBegin();
            $dateOnly = new \DateTimeImmutable($date->format('Y-m-d'));

            if ($minDate === null || $dateOnly < $minDate) {
                $minDate = $dateOnly;
            }

            if ($maxDate === null || $dateOnly > $maxDate) {
                $maxDate = $dateOnly;
            }
        }

        if ($minDate === null || $maxDate === null) {
            return [];
        }

        $userIds = array_values(array_unique($userIds));
        $approvals = $this->approvalRepository->findApprovedForUsersAndDateRange($userIds, $minDate, $maxDate);

        $rangesByUser = [];
        foreach ($approvals as $approval) {
            $userId = $approval->getUser()->getId();
            $rangesByUser[$userId][] = [
                $approval->getStartDate()->format('Y-m-d'),
                $approval->getEndDate()->format('Y-m-d'),
            ];
        }

        $approvalMap = [];
        foreach ($timesheets as $timesheet) {
            if (!$timesheet instanceof Timesheet) {
                continue;
            }

            $timesheetId = $timesheet->getId();
            $userId = $timesheet->getUser()->getId();
            $date = $timesheet->getBegin()->format('Y-m-d');

            $approved = false;
            foreach ($rangesByUser[$userId] ?? [] as [$startDate, $endDate]) {
                if ($date >= $startDate && $date <= $endDate) {
                    $approved = true;
                    break;
                }
            }

            $approvalMap[$timesheetId] = $approved;
        }

        return $approvalMap;
    }

    private function addApprovalToJsonResponse(Response $response, array $results, array $approvalMap): Response
    {
        if ($results === []) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'json')) {
            return $response;
        }

        $content = $response->getContent();
        if (!\is_string($content) || $content === '') {
            return $response;
        }

        try {
            $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $response;
        }

        if (!\is_array($payload)) {
            return $response;
        }

        foreach ($payload as $index => &$item) {
            if (!isset($results[$index]) || !$results[$index] instanceof Timesheet || !\is_array($item)) {
                continue;
            }

            $timesheetId = $results[$index]->getId();
            $item['approved'] = $approvalMap[$timesheetId] ?? false;
        }
        unset($item);

        try {
            $response->setContent(json_encode($payload, \JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return $response;
        }

        return $response;
    }
}