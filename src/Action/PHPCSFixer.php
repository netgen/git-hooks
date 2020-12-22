<?php

declare(strict_types=1);

namespace Netgen\GitHooks\Action;

use CaptainHook\App\Config;
use CaptainHook\App\Console\IO;
use SebastianFeldmann\Cli\Processor\ProcOpen as Processor;
use SebastianFeldmann\Git\Repository;
use function escapeshellarg;
use function preg_match;

final class PHPCSFixer extends Action
{
    protected const ERROR_MESSAGE = 'Committed PHP code did not pass php-cs-fixer inspection. Please check the output for suggested diff.';

    protected function doExecute(Config $config, IO $io, Repository $repository, Config\Action $action): void
    {
        $changedPHPFiles = $repository->getIndexOperator()->getStagedFilesOfType('php');
        if (empty($changedPHPFiles)) {
            return;
        }

        $allowedFiles = $action->getOptions()->get('allowed_files');

        $io->write('Running php-cs-fixer on files:', true, IO::VERBOSE);
        foreach ($changedPHPFiles as $file) {
            if ($this->shouldSkipFileCheck($file, $allowedFiles)) {
                continue;
            }

            $result = $this->fixFile($file);

            $io->write($result['output'], true);

            if ($result['success'] !== true) {
                $this->throwError($action, $io);
            }
        }
    }

    protected function shouldSkipFileCheck(string $file, array $allowedList): bool
    {
        foreach ($allowedList as $allowedFile) {
            // File definition using regexp
            if ($allowedFile[0] === '/') {
                if (preg_match($allowedFile, $file)) {
                    return true;
                }

                continue;
            }

            if ($allowedFile === $file) {
                return true;
            }
        }

        return false;
    }

    protected function fixFile($file): array
    {
        $process = new Processor();
        $result = $process->run('php-cs-fixer fix --dry-run --diff ' . escapeshellarg($file));

        return [
            'success' => $result->isSuccessful(),
            'output' => $result->getStdOut(),
        ];
    }
}
