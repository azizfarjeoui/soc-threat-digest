<?php

namespace App\Controller;

use App\Entity\ThreatSource;
use App\Entity\User;
use App\Repository\FetchRunRepository;
use App\Repository\ThreatReportRepository;
use App\Repository\ThreatSourceRepository;
use App\Repository\UserRepository;
use App\Service\ThreatDigestManager;
use App\Service\ThreatSourceCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ThreatSourceRepository $sourceRepository,
        private ThreatReportRepository $reportRepository,
        private FetchRunRepository $runRepository,
        private UserRepository $userRepository,
        private ThreatDigestManager $digestManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        $this->seedDefaultSources();

        $sources = $this->sourceRepository->findBy([], ['name' => 'ASC']);
        $reportCounts = $this->reportRepository->countGroupedBySource();
        $existingCodes = array_map(static fn (ThreatSource $s) => $s->getCode(), $sources);
        $availableToAdd = array_diff_key(ThreatSourceCatalog::defaults(), array_flip($existingCodes));

        return $this->render('admin/index.html.twig', [
            'sources' => $sources,
            'reportCounts' => $reportCounts,
            'availableToAdd' => $availableToAdd,
            'registeredCodes' => $this->digestManager->getRegisteredSourceCodes(),
            'users' => $this->userRepository->findBy([], ['email' => 'ASC']),
            'latestRun' => $this->runRepository->findLatestCompletedRun(),
            'totalReports' => $this->reportRepository->count([]),
            'totalRuns' => $this->runRepository->count([]),
        ]);
    }

    #[Route('/sources', name: 'app_admin_source_add', methods: ['POST'])]
    public function addSource(Request $request): Response
    {
        $this->assertCsrf($request, 'admin_source_add');

        $code = strtoupper(trim((string) $request->request->get('code')));
        $catalog = ThreatSourceCatalog::defaults();

        if (!isset($catalog[$code])) {
            $this->addFlash('danger', 'Unknown source code. Only built-in fetchers can be added.');
            return $this->redirectToRoute('app_admin');
        }

        if ($this->sourceRepository->findOneBy(['code' => $code])) {
            $this->addFlash('warning', 'That source is already configured.');
            return $this->redirectToRoute('app_admin');
        }

        $name = trim((string) $request->request->get('name'));
        $apiUrl = trim((string) $request->request->get('api_url'));
        $apiKey = trim((string) $request->request->get('api_key'));

        $source = new ThreatSource();
        $source->setCode($code);
        $source->setName($name !== '' ? $name : $catalog[$code]['name']);
        $source->setApiUrl($apiUrl !== '' ? $apiUrl : $catalog[$code]['apiUrl']);
        $source->setApiKey($apiKey !== '' ? $apiKey : null);
        $source->setEnabled(true);
        $source->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Source %s added.', $source->getName()));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/sources/{id}/update', name: 'app_admin_source_update', methods: ['POST'])]
    public function updateSource(Request $request, ThreatSource $source): Response
    {
        $this->assertCsrf($request, 'admin_source_update_' . $source->getId());

        $name = trim((string) $request->request->get('name'));
        $apiUrl = trim((string) $request->request->get('api_url'));
        $apiKey = trim((string) $request->request->get('api_key'));

        if ($name === '' || $apiUrl === '') {
            $this->addFlash('danger', 'Name and API URL are required.');
            return $this->redirectToRoute('app_admin');
        }

        $source->setName($name);
        $source->setApiUrl($apiUrl);
        if ($apiKey !== '') {
            $source->setApiKey($apiKey);
        }
        $source->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Source %s updated.', $source->getName()));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/sources/{id}/toggle', name: 'app_admin_source_toggle', methods: ['POST'])]
    public function toggleSource(Request $request, ThreatSource $source): Response
    {
        $this->assertCsrf($request, 'admin_source_toggle_' . $source->getId());

        $source->setEnabled(!$source->isEnabled());
        $source->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', sprintf(
            'Source %s is now %s.',
            $source->getName(),
            $source->isEnabled() ? 'enabled' : 'disabled'
        ));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/sources/{id}/delete', name: 'app_admin_source_delete', methods: ['POST'])]
    public function deleteSource(Request $request, ThreatSource $source): Response
    {
        $this->assertCsrf($request, 'admin_source_delete_' . $source->getId());

        $purge = $request->request->getBoolean('purge_reports');
        $code = $source->getCode();
        $name = $source->getName();
        $removedReports = 0;

        if ($purge && $code) {
            $removedReports = $this->reportRepository->deleteBySource($code);
        }

        $this->entityManager->remove($source);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf(
            'Source %s removed.%s',
            $name,
            $purge ? sprintf(' %d related reports deleted.', $removedReports) : ''
        ));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/fetch', name: 'app_admin_fetch', methods: ['POST'])]
    public function fetchNow(Request $request): Response
    {
        $this->assertCsrf($request, 'admin_fetch');

        $days = max(1, min(30, (int) $request->request->get('days', 7)));
        $sourceOption = strtoupper(trim((string) $request->request->get('source')));

        $sourcesToFetch = $this->digestManager->getEnabledSourceCodes();
        if ($sourceOption !== '') {
            if (!in_array($sourceOption, $this->digestManager->getRegisteredSourceCodes(), true)) {
                $this->addFlash('danger', 'That source has no fetcher registered.');
                return $this->redirectToRoute('app_admin');
            }
            $sourcesToFetch = [$sourceOption];
        }

        if ($sourcesToFetch === []) {
            $this->addFlash('warning', 'No enabled sources to fetch.');
            return $this->redirectToRoute('app_admin');
        }

        $since = new \DateTime(sprintf('-%d days', $days));
        $run = $this->digestManager->startFetchRun();

        foreach ($sourcesToFetch as $source) {
            $this->digestManager->fetchSource($run, $source, $since);
        }

        $this->digestManager->completeFetchRun($run, $sourcesToFetch);

        $this->addFlash('success', sprintf(
            'Fetch run #%d finished with status %s (%d new reports).',
            $run->getId(),
            $run->getStatus(),
            $run->getNewReportsCount()
        ));

        $errors = $run->getErrors() ?? [];
        if ($errors !== []) {
            foreach ($errors as $src => $error) {
                $this->addFlash('warning', sprintf('%s: %s', $src, $error));
            }
        }

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/users', name: 'app_admin_user_add', methods: ['POST'])]
    public function addUser(Request $request): Response
    {
        $this->assertCsrf($request, 'admin_user_add');

        $email = trim((string) $request->request->get('email'));
        $password = (string) $request->request->get('password');
        $makeAdmin = $request->request->getBoolean('is_admin');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->addFlash('danger', 'Valid email and a password of at least 8 characters are required.');
            return $this->redirectToRoute('app_admin');
        }

        if ($this->userRepository->findOneBy(['email' => $email])) {
            $this->addFlash('danger', 'A user with that email already exists.');
            return $this->redirectToRoute('app_admin');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($makeAdmin ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('User %s created.', $email));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/users/{id}/role', name: 'app_admin_user_role', methods: ['POST'])]
    public function toggleUserRole(Request $request, User $user): Response
    {
        $this->assertCsrf($request, 'admin_user_role_' . $user->getId());

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'You cannot change your own admin role.');
            return $this->redirectToRoute('app_admin');
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        if ($isAdmin && $this->countAdmins() <= 1) {
            $this->addFlash('danger', 'Cannot remove the last admin.');
            return $this->redirectToRoute('app_admin');
        }

        $user->setRoles($isAdmin ? [] : ['ROLE_ADMIN']);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf(
            '%s is now %s.',
            $user->getEmail(),
            in_array('ROLE_ADMIN', $user->getRoles(), true) ? 'an admin' : 'an analyst'
        ));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, User $user): Response
    {
        $this->assertCsrf($request, 'admin_user_delete_' . $user->getId());

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_admin');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true) && $this->countAdmins() <= 1) {
            $this->addFlash('danger', 'Cannot delete the last admin.');
            return $this->redirectToRoute('app_admin');
        }

        $email = $user->getEmail();
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('User %s deleted.', $email));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/reports/purge', name: 'app_admin_reports_purge', methods: ['POST'])]
    public function purgeReports(Request $request): Response
    {
        $this->assertCsrf($request, 'admin_reports_purge');

        $source = strtoupper(trim((string) $request->request->get('source')));
        if ($source === '') {
            $this->addFlash('danger', 'Select a source to purge.');
            return $this->redirectToRoute('app_admin');
        }

        $removed = $this->reportRepository->deleteBySource($source);
        $this->addFlash('success', sprintf('Deleted %d reports from %s.', $removed, $source));

        return $this->redirectToRoute('app_admin');
    }

    private function seedDefaultSources(): void
    {
        $changed = false;
        foreach (ThreatSourceCatalog::defaults() as $code => $meta) {
            if ($this->sourceRepository->findOneBy(['code' => $code])) {
                continue;
            }

            $source = new ThreatSource();
            $source->setCode($code);
            $source->setName($meta['name']);
            $source->setApiUrl($meta['apiUrl']);
            $source->setEnabled(true);
            $source->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($source);
            $changed = true;
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function countAdmins(): int
    {
        $count = 0;
        foreach ($this->userRepository->findAll() as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $count++;
            }
        }

        return $count;
    }
}
