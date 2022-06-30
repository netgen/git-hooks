<?php

declare(strict_types=1);

namespace Netgen\GitHooks\Action;

use CaptainHook\App\Config;
use CaptainHook\App\Console\IO;
use SebastianFeldmann\Cli\Processor\ProcOpen as Processor;
use SebastianFeldmann\Git\Repository;
use function escapeshellarg;
use function preg_match;

final class PHPStan extends Action
{
    protected const ERROR_MESSAGE = 'Committed PHP code did not pass phpstan inspection. Please check the output for errors.';

    protected function doExecute(Config $config, IO $io, Repository $repository, Config\Action $action): void
    {
        $changedPHPFiles = $repository->getIndexOperator()->getStagedFilesOfType('php');
        if (empty($changedPHPFiles)) {
            return;
        }

        $excludedFiles = $action->getOptions()->get('excluded_files');

        $io->write('Running phpstan on files:', true, IO::VERBOSE);
        foreach ($changedPHPFiles as $file) {
            if ($this->shouldSkipFileCheck($file, $excludedFiles)) {
                continue;
            }

            $result = $this->analyzeFile($file, $config, $action);

            $io->write($result['output'], true);

            if ($result['success'] !== true) {
                $this->throwError($action, $io);
            }
        }
    }

    protected function shouldSkipFileCheck(string $file, array $excludedFiles): bool
    {
        foreach ($excludedFiles as $excludedFile) {
            // File definition using regexp
            if ($excludedFile[0] === '/') {
                if (preg_match($excludedFile, $file)) {
                    return true;
                }

                continue;
            }
            if ($excludedFile === $file) {
                return true;
            }
        }

        return false;
    }

    protected function analyzeFile(string $file, Config $config, Config\Action $action): array
    {
        $process = new Processor();
        $phpstanPath = $action->getOptions()->get('phpstan_path') ?? 'vendor/bin/phpstan';
        $level = $action->getOptions()->get('level') ?? 8;
        $result = $process->run($config->getPhpPath() . ' ' . $phpstanPath . ' analyse --level='.$level. ' ' . escapeshellarg($file));

        return [
            'success' => $result->isSuccessful(),
            'output' => $result->getStdOut(),
        ];
    }
}
