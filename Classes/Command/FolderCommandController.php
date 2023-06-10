<?php

namespace Neos\Folder\Command;

/*
 * This file is part of the Neos.Folder package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Error;
use InvalidArgumentException;
use Neos\ContentRepository\Exception as NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Persistence\Exception as FlowException;
use Neos\Flow\Security\Exception;
use Neos\Flow\Security\Exception\InvalidPrivilegeException;
use Neos\Folder\Domain\Repository\FolderRepository;
use Neos\Folder\Domain\Service\Diacritics;
use Neos\Folder\Domain\Service\FolderContext;
use Neos\Folder\Domain\Service\FolderProvider;
use Neos\Neos\Controller\Exception\NodeNotFoundException;
use Neos\Neos\Domain\Service\SiteService;

/**
 * @Flow\Scope("singleton")
 * @package "Neos.Folder"
 */
class FolderCommandController extends CommandController
{
    /**
     * unix return error codes
     */
    final protected const ENOENT = 2;
    final protected const EEXISTS = 17;
    final protected const EINVAL = 22;

    /**
     * @Flow\Inject
     * @var FolderRepository
     */
    protected FolderRepository $folderRepository;

    /**
     * @Flow\Inject
     * @var FolderProvider
     */
    protected FolderProvider $folderProvider;

    /**
     * @Flow\Inject
     * @var FolderContext
     */
    protected FolderContext $folderContext;

    /**
     * Add a folder
     *
     * Add a folder to the folder system. The argument <path> is required (instead of <token>). The path
     * comprises folder titles which can be natural language strings (including upper case, blanks and Umlauts).
     *
     * @param string $path Folder path to add. Path segment names are set to property "title".
     * @param string $nodeTypeName Node type (on empty: Neos.Folder:Folder).
     * @param string $dimension Leave empty ('') for no dimensions..
     * @param bool $recursive Recursive operation on creation.
     *
     * @throws StopCommandException
     */
    public function addCommand(string $path, string $nodeTypeName, string $dimension, bool $recursive = false): void
    {
        try {
            $dimensions = empty($dimension) ? [] : $this->folderContext->verifyAndConvertDimensions($dimension);
            $this->folderRepository->addFolder($path, $nodeTypeName, $dimensions, $recursive);
        } catch (Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Handle exception
     *
     * @param Error|FlowException|InvalidArgumentException|NodeException|NodeNotFoundException $exception
     *
     * @return void
     * @throws StopCommandException
     */
    protected function _exception(
        Error|FlowException|InvalidArgumentException|NodeException|NodeNotFoundException $exception
    ): void {
        $exitCodes = [
            1676547042 => self::EEXISTS,
            1676547044 => self::ENOENT,
        ];
        $exitCode = array_keys($exitCodes, $exception->getCode());
        $this->outputLine("\033[31m%'010d\033[0m %s", [$exception->getCode(), $exception->getMessage()]);
        $this->quit(empty($exitCode) ? self::EINVAL : $exitCode[0]);
    }

    /**
     * Set title at folder
     *
     * Set title at folder identified by <token> (path or identifier). <title> can be a natural
     * language strings (including upper case, blanks and Umlauts). On empty title the node name is
     * set to the title.
     *
     * @param string $token Folder path or identifier for new title
     * @param string $title New title for folder
     * @param string $dimension The folder's dimension(s)
     *
     * @return void
     * @throws StopCommandException
     */
    public function titleCommand(string $token, string $title, string $dimension): void
    {
        try {
            $dimensions = $this->folderContext->verifyAndConvertDimensions($dimension);
            $providedFolder = (new FolderProvider())->new($token, $dimensions);
            $this->folderRepository->setTitleAndTitlePath($providedFolder->node, $title, $dimensions);
            $this->folderRepository->persist();
        } catch (Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Remove a folder or folder structure
     *
     * Remove a folder or folder structure from folder system. On empty ('') <dimension> a folder
     * without dimensions can be removed.
     *
     * @param string $token Folder path or identifier to remove
     * @param string $dimension Dimension
     * @param bool $recursive true: remove recursively
     *
     * @throws StopCommandException
     */
    public function removeCommand(string $token, string $dimension, bool $recursive = false): void
    {
        try {
            $dimensions = empty($dimension) ? [] : $this->folderContext->verifyAndConvertDimensions($dimension);
            $this->folderRepository->removeFolder($token, $dimensions, $recursive);
        } catch (Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Adopt an existing folder by new dimensions
     *
     * Adopt an existing folder by new dimensions if new-dimension variant for folder does not exist.
     *
     * @param string $token Folder path or identifier as start point for adopt of new dimensions
     * @param string $sourceDimension Dimension of existing folder
     * @param string $targetDimension Dimension for folder to adopt
     * @param bool $recursive true: adopt recursively
     *
     * @throws StopCommandException
     */
    public function adoptCommand(string $token, string $sourceDimension, string $targetDimension, bool $recursive = false): void
    {
        try {
            $sourceDimensions = empty($sourceDimension) ? [] : $this->folderContext->verifyAndConvertDimensions(
                $sourceDimension
            );
            $targetDimensions = $this->folderContext->verifyAndConvertDimensions($targetDimension);
            $this->folderContext->adoptFolder($token, $sourceDimensions, $targetDimensions, $recursive);
        } catch (Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Move a folder
     *
     * Move a folder (and sub folders) identified by <token> to folder identified by <target>.
     * Folder <b>titles</b> and <b>titlePath</b> are kept. It is not possible to move a folder if a folder
     * with same title exists at <target>. The folder Node's path name may change.
     *
     * @param string $token Folder path or identifier to move
     * @param string $target Target path or identifier
     * @param string $dimension Dimension
     *
     * @throws StopCommandException
     */
    public function moveCommand(string $token, string $target, string $dimension): void
    {
        try {
            $dimensions = $this->folderContext->verifyAndConvertDimensions($dimension);
            $newPath = $this->folderRepository->moveFolder($token, $target, $dimensions);
            $this->outputLine('Folder moved to "%s"', [$newPath]);
        } catch (Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Set or clear properties at a folder
     *
     * Set properties at a folder. Standard folder properties (title, titlePath, associations) are
     * excluded. Clears properties on option <--reset> included and <properties> empty ('').
     *
     * @param string $token Folder path or identifier to update properties
     * @param string $properties Folder properties (JSON formatted string)
     * @param string $dimension Dimension
     * @param bool $reset Reset all folder properties before property insertion
     *
     * @throws StopCommandException
     */
    public function propertyCommand(string $token, string $properties, string $dimension, bool $reset = false): void
    {
        try {
            $dimensions = $this->folderContext->verifyAndConvertDimensions($dimension);
            $this->folderRepository->setProperties($token, $properties, $dimensions, $reset);
        } catch (Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Set or clear association
     *
     * Set or clear association to <token> folder into <target> folder. Arguments work
     * like setting a symbolic link: "ln -s <token> <target>" or removing it: "rm <target>"
     *
     * @param string $token Folder path or identifier (folder to associate)
     * @param string $target Target path or identifier (where to set association)
     * @param string $dimension Dimension
     * @param bool $remove true: dissociate <token> from <target>
     *
     * @throws StopCommandException
     */
    public function associateCommand(string $token, string $target, string $dimension, bool $remove = false): void
    {
        try {
            $dimensions = $this->folderContext->verifyAndConvertDimensions($dimension);
            $this->folderRepository->associate($token, $target, $dimensions, $remove);
        } catch (Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Show root folders
     *
     * Show root folders with dimensions
     *
     * @return void
     */
    public function rootCommand(): void
    {
        $workspace = $this->folderRepository->getWorkspace();
        $childNodes = $this->folderRepository->findByParentWithoutReduce('/', $workspace);
        $len = 0;
        $info = [];
        foreach ($childNodes as $childNode) {
            $folderPath = $childNode->getPath();
            if ($folderPath !== SiteService::SITES_ROOT_PATH) {
                $folderVariants = $this->folderRepository->findByIdentifierWithoutReduce(
                    $childNode->getIdentifier(),
                    $workspace
                );
                $len = max($len, strlen($folderPath));
                foreach ($folderVariants as $folderVariant) {
                    $info[$folderPath][] = $folderVariant->getDimensionValues();
                }
            }
        }
        ksort($info, SORT_NATURAL);
        foreach ($info as $folderPath => $variants) {
            $folderPath = str_pad($folderPath, $len);
            $folderVariants = [];
            foreach ($variants as $variant) {
                $folderVariants[] = FolderContext::dimensionString($variant);
            }
            $this->outputLine('%s  %s', [$folderPath, join(' ', $folderVariants)]);
        }
    }

    /**
     * List folder structure
     *
     * List folders path names from folder system. On sort-modes SORT_STRING|SORT_NATURAL
     * the flag SORT_FLAG_CASE is set (case-insensitive sort).
     *
     * @param string $token Folder path or identifier
     * @param string $dimension Dimensions (ampersand separated list of). On empty ('') takes default dimension(s). Special: "all".
     * @param string $sortMode Sort mode (SORT_REGULAR | SORT_NUMERIC | SORT_STRING | SORT_LOCALE_STRING | SORT_NATURAL => default)
     *
     * @return void
     * @throws StopCommandException
     */
    public function listCommand(string $token, string $dimension, string $sortMode = 'SORT_NATURAL'): void
    {
        $dimensions = [];
        if ($dimension === 'all') {
            $folderTree = $this->_getFolderTree($token, $dimension, $sortMode);
            if (!empty($folderTree)) {
                $folderList = $this->_restructureFoldersForList($folderTree, $dimensions);
                $this->_formatFolderList($folderList, $dimensions);
            }
        } else {
            $dimensionValues = empty($dimension)
                ? [FolderContext::dimensionString($this->folderContext->getDefaultDimensions())]
                : explode('&', $dimension);
            while ($dimensionValue = current($dimensionValues)) {
                $folderTree = $this->_getFolderTree($token, $dimensionValue, $sortMode);
                if (!empty($folderTree)) {
                    $folderList = $this->_restructureFoldersForList($folderTree);
                    $this->_formatFolderList($folderList, []);
                }
                next($dimensionValues);
                if (!empty(current($dimensionValues))) {
                    $this->outputLine();
                }
            }
        }
    }

    /**
     * Workhorse method for list and export commands. Checks $token and $dimension.
     *
     * @param string $token Folder path or identifier
     * @param string $dimension Dimension. Special case: "all" takes all dimensions
     * @param string $sortMode Sort mode (SORT_REGULAR | SORT_NUMERIC | SORT_STRING | SORT_LOCALE_STRING | SORT_NATURAL => default)
     *
     * @return array folder tree
     * @throws StopCommandException
     */
    protected function _getFolderTree(string $token, string $dimension, string $sortMode): array
    {
        $folderTree = [];
        try {
            $dimensions = [];
            if ($dimension !== 'all') {
                empty($dimensions) && $dimensions = $this->folderContext->verifyAndConvertDimensions($dimension);
                empty($dimensions) && $dimensions = $this->folderContext->getDefaultDimensions();
            }
            $providedFolder = (new FolderProvider())->new($token, $dimensions);
            $folderTree = $this->folderRepository->getFolderTree($providedFolder, $dimensions, constant($sortMode));
            if (empty($folderTree)) {
                $this->outputLine('Folder tree \<%s> empty.', [$providedFolder->path]);
                $this->quit(self::ENOENT);
            }
        } catch (Error|Exception|InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
        return $folderTree;
    }

    /**
     * Format folder properties (path, titlePath, nodeType, identifier) recursively
     *
     * @param array $folderTree
     * @param array $dimensions
     * @param int $depth
     *
     * @return array
     */
    protected function _restructureFoldersForList(array $folderTree, array &$dimensions = [], int $depth = 0): array
    {
        $folderList = [];
        foreach ($folderTree[FolderRepository::VARIANTS_KEY] as $index => $variant) {
            $dimensionString = $this->folderContext->dimensionString($variant[FolderRepository::DIMENSIONS_KEY]);
            $dimensions[$dimensionString] = $index + 1;
            $name = $index > 0 ? '->' : $folderTree[FolderRepository::NAME_KEY];
            $titlePath = $variant[FolderRepository::PROPERTY_KEY][FolderRepository::TITLE_PATH_KEY];
            $nodeType = $folderTree[FolderRepository::NODE_TYPE_KEY];
            $identifier = $variant[FolderRepository::IDENTIFIER_KEY];
            $folderList[] = [$depth, $dimensionString, [$name, $titlePath, $identifier, $nodeType]];
        }
        if (key_exists(FolderRepository::CHILDREN_KEY, $folderTree)) {
            foreach ($folderTree[FolderRepository::CHILDREN_KEY] as $folderChild) {
                $childList = $this->_restructureFoldersForList($folderChild, $dimensions, $depth + 1);
                $folderList = [...$folderList, ...$childList];
            }
        }
        return $folderList;
    }

    /**
     * Pretty print folder list
     *
     * @param array $folderList
     * @param array $dimensions
     * @return void
     */
    protected function _formatFolderList(array $folderList, array $dimensions): void
    {
        $paddings = [''];
        $colors = [0, 31, 36, 33];
        $columnWidth = [0, 0, 0, 0];
        array_unshift($folderList, [0, '', ['name', 'titlePath', 'identifier', 'nodeType']]);
        $numDimensions = count($dimensions);
        $offset = $numDimensions <= 9 ? 2 : 3;
        foreach ($folderList as [$padSize, $dimensionString, $columns]) {
            foreach ($columns as $index => $column) {
                $len = is_string($column) ? Diacritics::strlen($column) : strlen((string)$column);
                $index < 1 && $len += $padSize;
                $index == 1 && !empty($dimensions) && $len += $offset;
                $columnWidth[$index] = max($len, $columnWidth[$index]);
            }
            $paddings[] = str_pad('', $padSize);
        }
        $dashes = [0, ''];
        foreach ($columnWidth as $index => $value) {
            $dashes[2][$index] = str_pad('', $value, '-');
        }
        array_splice($folderList, 1, 0, [$dashes]);
        foreach ($folderList as $row => [$depth, $dimensionString, $columns]) {
            $out = [];
            foreach ($columns as $index => $column) {
                $width = $columnWidth[$index];
                $dimensionNumber = '';
                if ($row > 1 && $index == 1 && $numDimensions > 1) {
                    $dimensionIndex = $dimensions[$dimensionString];
                    $dimensionNumber = "\033[32m$dimensionIndex ";
                    $width -= $offset;
                }
                $padding = ($index < 1) ? $paddings[$row] : '';
                $out[] = $dimensionNumber . sprintf("\033[%dm%s\033[0m", $colors[$index],
                        Diacritics::str_pad("$padding$column", $width, $row > 1 && $index < 2 ? '.' : ' ')
                    );
            }
            $this->outputLine(join(' ', $out));
        }
        foreach ($dimensions as $dimension => $index) {
            $this->outputLine("\033[32m%d\033[0m %s", [$index, $dimension]);
        }
    }

    /**
     * Export folders
     *
     * Export folders from folder system in JSON format
     *
     * @param string $token path or identifier of folder tree to export
     * @param string $dimension Dimension. Special case: "all" takes all dimensions on export
     * @param string $sortMode Sort mode (SORT_REGULAR | SORT_NUMERIC | SORT_STRING | SORT_LOCALE_STRING | SORT_NATURAL => default)
     * @param bool $pretty Pretty-print output
     *
     * @throws StopCommandException
     */
    public function exportCommand(string $token, string $dimension, string $sortMode = 'SORT_NATURAL', bool $pretty = false): void
    {
        $folderTree = $this->_getFolderTree($token, $dimension, $sortMode);
        if (!empty($folderTree)) {
            $this->outputLine(
                '%s',
                [json_encode($folderTree, $pretty ? JSON_INVALID_UTF8_IGNORE + JSON_ERROR_UTF8 + JSON_PRETTY_PRINT : 0)]
            );
        }
    }

    /**
     * Import folders
     *
     * Import folders to folder system from file (JSON format)
     *
     * @param string $file File to import folders from (JSON format)
     * @param bool $reset Reset: remove all folder types defined in import file (recursively)
     *
     * @throws StopCommandException
     * @throws InvalidPrivilegeException
     */
    public function importCommand(string $file, bool $reset = false): void
    {
        try {
            $folderSource = json_decode(file_get_contents($file), JSON_OBJECT_AS_ARRAY);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(
                    sprintf('Invalid JSON folder file "%s", JSON error: %s', $file, json_last_error_msg()), 1677263000
                );
            }
            if ($reset) {
                try {
                    foreach ($folderSource[FolderRepository::VARIANTS_KEY] as $variant) {
                        $this->folderRepository->removeFolder(
                            $folderSource[FolderRepository::PATH_KEY],
                            $variant[FolderRepository::DIMENSIONS_KEY],
                            true
                        );
                    }
                } catch (NodeException) {
                } // is thrown on folder tree empty
            }
            $this->_import($folderSource);
        } catch (InvalidArgumentException|InvalidPrivilegeException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * @param mixed $folderSource
     *
     * @throws InvalidArgumentException
     * @throws NodeException
     */
    protected function _import(array $folderSource): void
    {
        $validKeys = [
            FolderRepository::PATH_KEY,
            FolderRepository::NAME_KEY,
            FolderRepository::NODE_TYPE_KEY,
            FolderRepository::VARIANTS_KEY,
            FolderRepository::CHILDREN_KEY,
        ];
        if (count($delta = array_diff($validKeys, array_keys($folderSource))) > 0) {
            throw new InvalidArgumentException(
                'Invalid file properties: ' . \Neos\Flow\var_dump($delta, '', true),
                1677264338
            );
        }
        foreach ($folderSource[FolderRepository::VARIANTS_KEY] as $variant) {
            $dimensions = $variant[FolderRepository::DIMENSIONS_KEY];
            $properties = $variant[FolderRepository::PROPERTY_KEY];

            $folderNode = $this->folderRepository->addFolder(
                $folderSource[FolderRepository::PATH_KEY],
                $folderSource[FolderRepository::NODE_TYPE_KEY],
                $dimensions
            );

            foreach ($properties as $propertyName => $propertyValue) {
                $folderNode->setProperty($propertyName, $propertyValue);
            }
            $this->folderRepository->persist();

        }
        foreach ($folderSource[FolderRepository::CHILDREN_KEY] as $child) {
            $this->_import($child);
        }
    }
}
