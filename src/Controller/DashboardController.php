<?php

namespace App\Controller;

use App\Entity\ThreatReport;
use App\Repository\ThreatReportRepository;
use App\Repository\FetchRunRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        Request $request,
        ThreatReportRepository $reportRepo,
        FetchRunRepository $runRepo
    ): Response {
        $latestRun = $runRepo->findLatestCompletedRun();
        
        $reports = [];
        $stats = [
            'critical_vulns' => 0,
            'malware_samples' => 0,
            'otx_pulses' => 0,
        ];

        // Fetch query parameters for filtering
        $source = $request->query->get('source');
        $severity = $request->query->get('severity');
        $q = $request->query->get('q');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if ($latestRun) {
            // 1. Calculate Summary Banner stats for the latest run
            $stats['critical_vulns'] = $reportRepo->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.fetchRun = :run')
                ->andWhere('r.source IN (:sources)')
                ->andWhere('r.severity LIKE :critical OR r.severity LIKE :high OR r.severity = :highWord')
                ->setParameter('run', $latestRun)
                ->setParameter('sources', [ThreatReport::SOURCE_CISA_KEV, ThreatReport::SOURCE_NVD])
                ->setParameter('critical', '%Critical%')
                ->setParameter('high', '%High%')
                ->setParameter('highWord', 'High')
                ->getQuery()
                ->getSingleScalarResult();

            $stats['malware_samples'] = $reportRepo->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.fetchRun = :run')
                ->andWhere('r.source = :mb')
                ->setParameter('run', $latestRun)
                ->setParameter('mb', ThreatReport::SOURCE_MALWARE_BAZAAR)
                ->getQuery()
                ->getSingleScalarResult();

            $stats['otx_pulses'] = $reportRepo->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.fetchRun = :run')
                ->andWhere('r.source = :otx')
                ->setParameter('run', $latestRun)
                ->setParameter('otx', ThreatReport::SOURCE_OTX)
                ->getQuery()
                ->getSingleScalarResult();

            // 2. Build Query for filtered Threat Reports
            $qb = $reportRepo->createQueryBuilder('r')
                ->where('r.fetchRun = :run')
                ->setParameter('run', $latestRun);

            // Filter: Source
            if (!empty($source)) {
                $qb->andWhere('r.source = :sourceFilter')
                   ->setParameter('sourceFilter', $source);
            }

            // Filter: Severity
            if (!empty($severity)) {
                if ($severity === 'High/Critical') {
                    $qb->andWhere('r.severity LIKE :sevCrit OR r.severity LIKE :sevHigh OR r.severity = :highWord')
                       ->setParameter('sevCrit', '%Critical%')
                       ->setParameter('sevHigh', '%High%')
                       ->setParameter('highWord', 'High');
                } else {
                    $qb->andWhere('r.severity LIKE :sevFilter')
                       ->setParameter('sevFilter', '%' . $severity . '%');
                }
            }

            // Filter: Free Text Search
            if (!empty($q)) {
                $qb->andWhere('r.title LIKE :searchQ OR r.description LIKE :searchQ OR r.externalId LIKE :searchQ')
                   ->setParameter('searchQ', '%' . $q . '%');
            }

            // Filter: Date Range
            if (!empty($startDate)) {
                $qb->andWhere('r.publishedAt >= :startDate')
                   ->setParameter('startDate', new \DateTime($startDate . ' 00:00:00'));
            }
            if (!empty($endDate)) {
                $qb->andWhere('r.publishedAt <= :endDate')
                   ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'));
            }

            // Order by Severity Custom Case, then Publish Date DESC
            $qb->addSelect("CASE 
                WHEN r.severity LIKE '%Critical%' THEN 1 
                WHEN r.severity LIKE '%High%' OR r.severity = 'High' THEN 2 
                WHEN r.severity LIKE '%Medium%' THEN 3 
                ELSE 4 END as HIDDEN severityOrder")
               ->orderBy('severityOrder', 'ASC')
               ->addOrderBy('r.publishedAt', 'DESC');

            $reports = $qb->getQuery()->getResult();
        }

        return $this->render('dashboard.html.twig', [
            'latestRun' => $latestRun,
            'reports' => $reports,
            'stats' => $stats,
            'filters' => [
                'source' => $source ?? '',
                'severity' => $severity ?? '',
                'q' => $q ?? '',
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
            ]
        ]);
    }

    #[Route('/report/{id}', name: 'app_report_detail')]
    public function detail(int $id, ThreatReportRepository $reportRepo): Response
    {
        $report = $reportRepo->find($id);
        if (!$report) {
            throw $this->createNotFoundException('Threat report not found');
        }

        return $this->render('detail.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/history', name: 'app_history')]
    public function history(FetchRunRepository $runRepo): Response
    {
        $runs = $runRepo->findBy([], ['ranAt' => 'DESC']);

        return $this->render('history.html.twig', [
            'runs' => $runs,
        ]);
    }
}
