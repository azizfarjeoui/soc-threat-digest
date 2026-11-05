<?php

namespace App\Command;

use App\Service\ThreatDigestManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-threat-digest',
    description: 'Fetch threat intelligence from configured sources and save to database.',
)]
class FetchThreatDigestCommand extends Command
{
    private ThreatDigestManager $manager;

    public function __construct(ThreatDigestManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Filter by specific source (e.g. CISA_KEV, NVD, etc.)')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Number of past days to query', 7);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceOption = $input->getOption('source');
        $days = (int) $input->getOption('days');

        $io->title('Threat Intelligence Fetch Pipeline');

        $since = new \DateTime(sprintf('-%d days', $days));
        $io->info(sprintf('Querying threat reports updated/published since: %s', $since->format('Y-m-d H:i:s')));

        // Start FetchRun tracking
        $run = $this->manager->startFetchRun();
        $io->text(sprintf('Started FetchRun ID: <comment>%d</comment>', $run->getId()));

        $sourcesToFetch = $this->manager->getEnabledSourceCodes();
        if ($sourceOption) {
            $upperSource = strtoupper($sourceOption);
            if (!in_array($upperSource, $this->manager->getRegisteredSourceCodes(), true)) {
                $io->error(sprintf('Source "%s" is not registered or supported.', $sourceOption));
                return Command::INVALID;
            }
            $sourcesToFetch = [$upperSource];
        }

        if (empty($sourcesToFetch)) {
            $io->warning('No enabled threat sources are available.');
            $this->manager->completeFetchRun($run, []);
            return Command::SUCCESS;
        }

        $io->progressStart(count($sourcesToFetch));

        foreach ($sourcesToFetch as $source) {
            $io->text(sprintf("\nFetching from: <info>%s</info>...", $source));
            $this->manager->fetchSource($run, $source, $since);
            $io->progressAdvance();
        }

        $io->progressFinish();

        $this->manager->completeFetchRun($run, $sourcesToFetch);

        // Print Summary
        $io->section('Fetch Summary');
        $io->text(sprintf('Status: <info>%s</info>', $run->getStatus()));
        $io->text(sprintf('New Reports Saved: <info>%d</info>', $run->getNewReportsCount()));

        $errors = $run->getErrors();
        if (!empty($errors)) {
            $io->section('Errors Encountered');
            foreach ($errors as $src => $err) {
                $io->warning(sprintf('%s: %s', $src, $err));
            }
        }

        $io->success('Threat digest fetch completed.');

        return Command::SUCCESS;
    }
}
