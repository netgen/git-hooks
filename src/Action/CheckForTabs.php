<?php

declare(strict_types=1);

namespace Netgen\GitHooks\Action;

use CaptainHook\App\Config;
use CaptainHook\App\Console\IO;
use SebastianFeldmann\Git\Repository;
use Symfony\Component\Process\Process;
use function array_diff;
use function implode;

final class CheckForTabs extends Action
{
    protected const ERROR_MESSAGE = 'You have tabs in your code!';

    protected function doExecute(Config $config, IO $io, Repository $repository, Config\Action $action): void
    {
        $files = $this->getChangedFiles($action, $repository);
        if (empty($files)) {
            return;
        }

        $arguments = array_merge(
            [
                'grep',
                '\t',
            ],
            $files
        );

        $process = new Process($arguments);

        $process->run();
        $process->wait();

        if ($process->getOutput()) {
            $io->writeError("<error>{$process->getOutput()}</error>");
            $this->throwError($action, $io);
        }
    }

    private function getChangedFiles(Config\Action $action, Repository $repository): array
    {
        $excludedFiles = $action->getOptions()->get('excluded_files');
        $extensions = $action->getOptions()->get('extensions', ['php']);

        $changedFiles = [];
        foreach ($extensions as $extension) {
            $changedFiles = [...$changedFiles, ...$repository->getIndexOperator()->getStagedFilesOfType($extension)];
        }

        return array_diff($changedFiles, $excludedFiles);
    }
}
