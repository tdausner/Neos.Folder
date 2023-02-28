<?php

namespace Neos\Folder\Controller;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Error;
use InvalidArgumentException;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\ContentRepository\Exception as NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Session\Exception;
use Neos\Flow\Session\Exception\SessionNotStartedException;
use Neos\Flow\Session\SessionInterface;
use Neos\Folder\Domain\Repository\FolderRepository;
use Neos\Folder\Domain\Service\FolderContext;
use Neos\Folder\Domain\Service\FolderProvider;

/**
 * Controller for Folder Services
 *
 * Dimensions values are determined
 *  - from the session if $_SERVER['QUERY_STRING'] is empty
 *  - from $_SERVER['QUERY_STRING'] parameters
 *
 *    Query string `<parameter_1>=<value_1>&<parameter_2>=<value_2>...` is transformed to dimensions array
 *
 *     ```php
 *     [
 *       <parameter_1> => <value_1>,
 *       <parameter_2> => <value_2>
 *       ...
 *     ]
 *     ```
 *
 * @package "Neos.Folder"
 */
class FolderServiceController extends ActionController
{
    /**
     * @Flow\InjectConfiguration(package="Neos.Folder", path="defaults.adoptOnEmpty")
     * @var bool $adoptOnEmpty auto adopt on empty folder get request (see function getFolderTreeAction())
     */
    protected bool $adoptOnEmpty;

    /**
     * @var array
     */
    protected $supportedMediaTypes = ['application/json'];

    /**
     * @var string
     */
    protected $defaultViewObjectName = JsonView::class;

    /**
     * @Flow\Inject
     * @var FolderRepository
     */
    protected FolderRepository $folderRepository;

    /**
     * @Flow\Inject
     * @var SessionInterface
     */
    protected SessionInterface $currentSession;

    /**
     * @Flow\Inject
     * @var FolderContext
     */
    protected FolderContext $folderContext;

    /**
     * Add a folder to parent folder identified by token. If dimensions from query string
     * is "none" a folder without dimensions is added.
     *
     * uriPattern: neos/folder/add/{parent}/{title}(/{nodeTypeName})
     *  - `parent`: existing parent folder path or identifier
     *  - `title`: title of new folder. The Node name is generated from the title
     *  - `nodeTypeName`: optional node type name. If omitted the node type
     *    name is taken from configuration `Neos.Folder.defaults.nodeType`
     *
     * @param string $parent
     * @param string $title
     * @param string $nodeTypeName
     */
    public function addAction(string $parent, string $title, string $nodeTypeName = ''): void
    {
        try {
            $dimensions = $this->_validateSessionAndGetDimensions(true);
            $this->folderRepository->addFolder("$parent/$title", $nodeTypeName, $dimensions);
            $this->view->assign('value', ['ok' => 'folder added']);
        } catch (NodeException|SessionNotStartedException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * validate session and calculate dimensions
     *
     * @return array validated dimensions
     * @throws InvalidArgumentException
     * @throws SessionNotStartedException
     */
    protected function _validateSessionAndGetDimensions(bool $noDimensionsPermitted = false): array
    {
        try {
            $this->currentSession->isStarted() || throw new SessionNotStartedException();
            $this->currentSession->canBeResumed() && $this->currentSession->resume();
            if ($noDimensionsPermitted && $_SERVER['QUERY_STRING'] === 'none') {
                $dimensions = [];
            } else {
                $dimensions =  empty($_SERVER['QUERY_STRING'])
                    ? NodePaths::explodeContextPath($this->currentSession->getData('lastVisitedNode'))['dimensions']
                    : $this->folderContext->verifyAndConvertDimensions($_SERVER['QUERY_STRING']);
            }
            return $dimensions;
        } catch (SessionNotStartedException) {
            throw new SessionNotStartedException('No session', 1676315840);
        }
    }

    /**
     * Handle exception
     *
     * @param Error|Exception|IllegalObjectTypeException|InvalidArgumentException|NodeException $exception
     */
    protected function _exception(Error|Exception|IllegalObjectTypeException|InvalidArgumentException|NodeException $exception): void
    {
        $this->response->setStatusCode($exception instanceof SessionNotStartedException ? 401 : 400); // Unauthorized : Bad request
        $this->view->assign('value', ['error' => [$exception->getCode(), $exception->getMessage()]]);
    }

    /**
     * Get folder tree information
     *
     * uriPattern: neos/folder/get/{token}(/{sortMode})?<dimensions>
     *  - `token`: folder path or identifier
     *  - `sortMode`: `SORT_REGULAR | SORT_NUMERIC | SORT_STRING | SORT_LOCALE_STRING | SORT_NATURAL`.
     *    On sort-modes `SORT_STRING|SORT_NATURAL` the flag `SORT_FLAG_CASE` is set (case-insensitive sort).
     *    Default sort mode `SORT_NATURAL` is defined in `Routes.yaml`.
     *
     * On request to a non-existing folder AND `Neos.Folder.defaults.adoptOnEmpty: true` the requested
     * folder tree is adopted from the default dimension to the session's dimensions.
     *
     * @param string $token Folder path or identifier
     * @param string $sortMode Sort mode
     *
     */
    public function getTreeAction(string $token, string $sortMode): void
    {
        try {
            $targetDimensions = $this->_validateSessionAndGetDimensions();
            try {
                $providedFolder = (new FolderProvider())->new($token, $targetDimensions);
            } catch (NodeException $exception) {
                if ($this->adoptOnEmpty && $exception->getCode() === 1676631409) {
                    $sourceDimensions = $this->folderContext->getDefaultDimensions();
                    $adoptedFolder = $this->folderContext->adoptFolder($token, $sourceDimensions, $targetDimensions);
                    $providedFolder = (new FolderProvider())->new($adoptedFolder);
                    $this->folderRepository->persist();
                } else {
                    throw new NodeException('Cannot adopt.', 1676315820);
                }
            }
            $folderTree = $this->folderRepository->getFolderTree($providedFolder, $targetDimensions, constant($sortMode));
            empty($folderTree) && throw new NodeException(sprintf('Folder for token "%s" has no variant for dimensions "%s")',$token, FolderContext::dimensionString($targetDimensions)),1676315838);
            $this->view->assign('value', $folderTree);
        } catch (InvalidArgumentException|NodeException|SessionNotStartedException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Set title at folder identified by token.
     *
     * uriPattern: neos/folder/get/{token}(/{title})
     *  - token folder path or identifier
     *  - title new title
     *
     * On empty title the title is set to the folder node's name. The titlePath
     * for the folder node and children is adjusted recursively.
     *
     * @param string $token
     * @param string $title
     */
    public function titleAction(string $token, string $title): void
    {
        try {
            $dimensions = $this->_validateSessionAndGetDimensions();
            $folderNode = (new FolderProvider())->new($token, $dimensions)->node;
            $this->folderRepository->setTitleAndTitlePath($folderNode, $title, $dimensions);
            $this->folderRepository->persist();
            $this->view->assign('value', ['ok' => "folder title set to \"$title\""]);
        } catch (SessionNotStartedException|NodeException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Remove folder identified by token.
     *
     * uriPattern: neos/folder/remove/{token}
     *  - token folder path or identifier
     *
     * @param string $token
     * @param bool $recursive
     */
    public function removeAction(string $token, bool $recursive): void
    {
        try {
            $dimensions = $this->_validateSessionAndGetDimensions();
            $this->folderRepository->removeFolder($token, $dimensions, $recursive);
            $this->view->assign('value', ['ok' => 'folder(s) removed']);
        } catch (InvalidArgumentException|NodeException|SessionNotStartedException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Adopt folder(s) identified by token.
     *  - source dimensions are default dimensions (language=en_US on a standard Neos installation)
     *  - target dimensions are taken from session
     *
     * uriPattern: neos/folder/adopt/{token}(/{recursive})
     *  - token folder path or identifier
     *  - recursive is optional to evoke adopt of a folder tree
     *
     * @param string $token
     * @param bool $recursive
     */
    public function adoptAction(string $token, bool $recursive): void
    {
        try {
            $sourceDimensions = $this->folderContext->getDefaultDimensions();
            $targetDimensions = $this->_validateSessionAndGetDimensions();
            $this->folderContext->adoptFolder($token, $sourceDimensions, $targetDimensions, $recursive);
            $this->view->assign('value', ['ok' => 'folder(s) adopted']);
        } catch (InvalidArgumentException|NodeException|SessionNotStartedException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Move a folder
     *
     * Move o folder identified by <token> to folder identified by <target>.
     *
     * uriPattern: neos/folder/move/{token}/{target}
     *  - token: folder path or identifier
     *  - target: folder path or identifier of target = new parent folder
     *
     * @param string $token
     * @param string $target
     */
    public function moveAction(string $token, string $target): void
    {
        try {
            $dimensions = $this->_validateSessionAndGetDimensions();
            $newPath = $this->folderRepository->moveFolder($token, $target, $dimensions);
            $this->view->assign('value', ['ok' => $newPath]);
        } catch (NodeException|SessionNotStartedException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Set properties of a folder
     *
     * Set properties at a folder. Standard folder properties (title, titlePath, associations) are
     * excluded. Clears properties on option <--reset> included and <properties> empty ('').
     *
     * uriPattern: neos/folder/property/{token}(/{propertyString})
     *  - token: folder path or identifier
     *  - propertyString: json encoded property string
     *
     * @param string $token Folder path or identifier to update properties
     * @param string $propertyString Folder properties (JSON formatted string)
     */
    public function propertyAction(string $token, string $propertyString): void
    {
        try {
            $dimensions = $this->_validateSessionAndGetDimensions();
            if (empty($propertyString)) {
                $this->folderRepository->clearAllProperties($token, $dimensions);
            } else {
                $this->folderRepository->setProperties($token, $propertyString, $dimensions);
            }
            $this->view->assign('value', ['ok' => 'properties ' . (empty($propertyString) ? 'cleared' : 'set')]);
        } catch (InvalidArgumentException|NodeException|SessionNotStartedException $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * Set or clear association
     *
     * Set or clear association to <token> folder into <target> folder. Arguments work
     * like setting a symbolic link: "ln -s <token> <target>" or removing it: "rm <target>"
     *
     * uriPattern: neos/folder/associate/{token}/{target}(/{remove})
     *  - token: folder path or identifier
     *  - target: folder path or identifier of target = new parent folder
     *  - remove: on true dissociate <token> from <target>
     *
     * @param string $token Folder path or identifier (folder to associate)
     * @param string $target Target path or identifier (where to set association)
     * @param bool $remove true: dissociate <token> from <target>
     */
    public function associateAction(string $token, string $target, bool $remove): void
    {
        try {
            $dimensions = $this->_validateSessionAndGetDimensions();
            $this->folderRepository->associate($token, $target, $dimensions, $remove);
            $this->view->assign('value', ['ok' => 'association ' . ($remove ? 'cleared' : 'set')]);
        } catch (InvalidArgumentException|NodeException|SessionNotStartedException $exception) {
            $this->_exception($exception);
        }
    }

}
