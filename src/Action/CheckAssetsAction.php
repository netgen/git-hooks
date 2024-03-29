<?php

declare(strict_types=1);

namespace Netgen\GitHooks\Action;

use CaptainHook\App\Config;
use CaptainHook\App\Console\IO;
use SebastianFeldmann\Git\Repository;

use function count;
use function in_array;
use function str_contains;

/**
 * @deprecated Will be removed in 3.0, since assets are not committed to the repository any more.
 */
final class CheckAssetsAction extends Action
{
    protected string $defaultErrorMessage = "I'm sorry, Dave. I'm afraid I can't do that. Please build production assets before committing changes";

    protected function doExecute(Config $config, IO $io, Repository $repository, Config\Action $action): void
    {
        $changedSassFiles = $repository->getIndexOperator()->getStagedFilesOfType('scss');
        $changedJSFiles = $repository->getIndexOperator()->getStagedFilesOfType('js');
        if (count($changedSassFiles) === 0 && count($changedJSFiles) === 0) {
            return;
        }

        if ($this->allChangedFilesExcluded($changedSassFiles, $changedJSFiles, $action)) {
            return;
        }

        $changedCssFiles = $repository->getIndexOperator()->getStagedFilesOfType('css');

        $cssBuildFound = $this->checkCssBuildFiles($changedSassFiles, $changedCssFiles);
        $jsBuildFound = $this->checkJsBuildFiles($changedJSFiles);

        if (!$this->checkAssetsConfigFiles($repository)) {
            $this->throwError($action, $io);
        }

        if (!$cssBuildFound || !$jsBuildFound) {
            $this->throwError($action, $io);
        }
    }

    /**
     * @param string[] $changedSassFiles
     * @param string[] $changedJSFiles
     */
    private function allChangedFilesExcluded(array $changedSassFiles, array $changedJSFiles, Config\Action $action): bool
    {
        /** @var string[] $excludedFiles */
        $excludedFiles = $action->getOptions()->get('excluded_files') ?? [];

        foreach ($changedSassFiles as $changedSassFile) {
            if (!in_array($changedSassFile, $excludedFiles, true)) {
                return false;
            }
        }

        foreach ($changedJSFiles as $changedJSFile) {
            if (!in_array($changedJSFile, $excludedFiles, true)) {
                return false;
            }
        }

        return true;
    }

    private function checkAssetsConfigFiles(Repository $repository): bool
    {
        $changedJsonFiles = $repository->getIndexOperator()->getStagedFilesOfType('json');

        $entrypointFound = false;
        $manifestFound = false;
        foreach ($changedJsonFiles as $jsonFile) {
            if (str_contains($jsonFile, 'build/entrypoints.json')) {
                $entrypointFound = true;

                continue;
            }

            if (str_contains($jsonFile, 'build/manifest.json')) {
                $manifestFound = true;
            }
        }

        return $entrypointFound && $manifestFound;
    }

    /**
     * @param string[] $changedSassFiles
     * @param string[] $changedCssFiles
     */
    private function checkCssBuildFiles(array $changedSassFiles, array $changedCssFiles): bool
    {
        $cssBuildFound = count($changedSassFiles) === 0;

        foreach ($changedCssFiles as $cssFile) {
            if (str_contains($cssFile, 'build')) {
                continue;
            }

            $cssBuildFound = true;
        }

        return $cssBuildFound;
    }

    /**
     * @param string[] $changedJSFiles
     */
    private function checkJsBuildFiles(array $changedJSFiles): bool
    {
        $jsBuildFound = count($changedJSFiles) === 0;
        foreach ($changedJSFiles as $changedJSFile) {
            if (str_contains($changedJSFile, 'build')) {
                $jsBuildFound = true;

                break;
            }
        }

        return $jsBuildFound;
    }
}
