<?php

namespace Neos\Folder\Domain\Repository;

/*
 * This file is part of the Neos.Folder package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\ContentRepository\Exception as NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Folder\Domain\Service\Diacritics;
use Neos\Folder\Domain\Service\FolderContext;
use Neos\Folder\Domain\Service\FolderProvider;

/**
 * A repository for Folders
 *
 * @Flow\Scope("singleton")
 * @package "Neos.Folder"
 * @api
 */
class FolderRepository extends NodeDataRepository
{
    /*
     * key values for folder data
     */
    final public const NAME_KEY = 'name';
    final public const IDENTIFIER_KEY = 'identifier';
    final public const PATH_KEY = 'path';
    final public const TITLE_PATH_KEY = 'titlePath';
    final public const NODE_TYPE_KEY = 'nodeType';
    final public const INDEX_KEY = 'index';
    final public const VARIANTS_KEY = 'variants';
    final public const TITLE_KEY = 'title';
    final public const DIMENSIONS_KEY = 'dimensions';
    final public const PROPERTY_KEY = 'properties';
    final public const ASSOCIATIONS_KEY = 'associations';
    final public const CHILDREN_KEY = 'children';

    final public const DEFAULT_PROPERTIES = [
        self::TITLE_KEY => ['type' => 'string'],
        self::TITLE_PATH_KEY => ['type' => 'string'],
        self::ASSOCIATIONS_KEY => ['type' => 'references'],
    ];

    /**
     * @Flow\InjectConfiguration(package="Neos.Folder", path="defaults.titlePropertyKey")
     * @var string $titlePropertyKey Folder name
     */
    protected string $titlePropertyKey;

    /**
     * @Flow\InjectConfiguration(package="Neos.Folder", path="defaults.titlePathPropertyKey")
     * @var string $titlePathPropertyKey Folder name
     */
    protected string $titlePathPropertyKey;

    /**
     * @Flow\InjectConfiguration(package="Neos.Folder", path="defaults.associationsPropertyKey")
     * @var string $associationsPropertyKey Folder associations
     */
    protected string $associationsPropertyKey;

    /**
     * @Flow\InjectConfiguration(package="Neos.Folder", path="defaults.nodeType")
     * @var string nodeTypeName
     */
    protected string $defaultNodeTypeName;

    /**
     * @Flow\Inject
     * @var ContextFactory
     */
    protected ContextFactory $contextFactory;

    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @var Workspace
     */
    protected Workspace $workspace;

    /**
     * @return Workspace current workspace for request(s): 'live'
     */
    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    /**
     * Executed by Flow Object Framework
     *
     * @return void
     * @throws IllegalObjectTypeException
     * @api
     */
    public function initializeObject(): void
    {
        $this->context = $this->contextFactory->create(['workspaceName' => 'live']);
        $this->workspace = $this->context->getWorkspace();
    }

    /**
     * Add a folder to the folder system
     *
     * @param string $path Folder path to add. Note: path segments are set to property "title"
     * @param string $nodeTypeName Node type (default: Neos.Folder:Folder)
     * @param array $dimensions
     * @param bool $recursive Recursive operation on creation
     *
     * @return NodeData added folder node
     * @throws NodeException
     * @api
     */
    public function addFolder(string $path, string $nodeTypeName, array $dimensions, bool $recursive = false): NodeData
    {
        $nodeType = $this->getNodeTypeByName($nodeTypeName);
        if ($nodeTypeName !== '' && $nodeTypeName !== $nodeType->getName()) {
            throw new NodeException("Node type \"$nodeTypeName\" invalid.", 1676547040);
        }
        // test path valid and folder don't exist
        $providedFolder = (new FolderProvider())->new($path, $dimensions, true);
        if (!empty($providedFolder->node)) {
            throw new NodeException("Folder \"$path\" exists.", 1676547042);
        }
        // test if parent exists
        $parentPath = NodePaths::getParentPath(Diacritics::path($path));
        $folderNode = $this->findOneByPath($parentPath, $this->workspace, $dimensions);
        if (!$recursive && empty($folderNode)) {
            $dimensionString = FolderContext::dimensionString($dimensions);
            throw new NodeException(
                "Parent folder \"$parentPath\" dimensions \"$dimensionString\" not found.", 1676547044);
        }
        $parentFolderNode = $this->findOneByPath('/', $this->workspace);
        $folderPath = '';
        foreach (explode('/', ltrim($path, '/')) as $title) {
            $folderPath = NodePaths::addNodePathSegment($folderPath, Diacritics::path($title));
            $folderNode = $this->findOneByPath($folderPath, $this->workspace, $dimensions);
            if (!($folderNode instanceof NodeData)) {
                $nodeName = $this->_uniqueNodeName($parentFolderNode, $title, $dimensions);
                $folderNode = $parentFolderNode->createNodeData($nodeName, $nodeType, null, $this->workspace, $dimensions);
                $folderNode->setProperty($this->titlePropertyKey, $title ?: '');
                $folderNode->setProperty($this->titlePathPropertyKey, $this->_titlePath($folderNode, $title, $dimensions));
                $folderNode->setProperty($this->associationsPropertyKey, []);

                $this->setTitleAndTitlePath($folderNode, $title, $dimensions);
            }
            $parentFolderNode = $folderNode;
        }
        $this->persist();
        return $parentFolderNode;
    }

    /**
     * Get NodeType by node type name. Fallback is configuration `Neos.Folder.defaults.nodeType` (standard
     * configuration is `Neos.Folder:Folder`). Checks if NodeType of type nodeTypeName has the demanded
     * default properties.
     *
     * @param ?string $nodeTypeName on empty: returns default node type
     *
     * @return NodeType from node type name
     * @throws NodeException
     * @api
     */
    public function getNodeTypeByName(?string $nodeTypeName): NodeType
    {
        $nodeType = $this->nodeTypeManager->getNodeType($nodeTypeName);
        if (empty($nodeType) || $nodeTypeName !== $nodeType->getName()) {
            $nodeType = $this->nodeTypeManager->getNodeType($this->defaultNodeTypeName);
        }
        $diff = array_udiff_uassoc(self::DEFAULT_PROPERTIES, $nodeType->getProperties(),
            function (mixed $va, mixed $vb) {
                return strcmp(serialize($va), serialize($vb));
            },
            function (mixed $ka, mixed $kb) {
                return strcmp($ka, $kb);
            }
        );
        if (!empty($diff)) {
            $nodeTypeName = $nodeType->getName();
            throw new NodeException("NodeType \"$nodeTypeName\" missing default folder properties.", 1677404051);
        }
        return $nodeType;
    }

    /**
     * Get unique node name from $title
     *
     * @param NodeData $parentNode
     * @param string $title
     * @param array $dimensions
     *
     * @return string
     * @throws NodeException
     */
    protected function _uniqueNodeName(NodeData $parentNode, string $title, array $dimensions): string
    {
        // retrieve child folders and sort by name
        $childFolderNodes = $this->findByParentAndNodeType($parentNode->getPath(), '', $this->workspace, $dimensions);
        $childFolderNames = [];
        foreach ($childFolderNodes as $childFolderNode) {
            $childFolderNames[$childFolderNode->getName()] = $childFolderNode;
        }
        ksort($childFolderNames);
        $initialNodeName = $nodeName = Diacritics::path($title);
        // assure nodeName and title are unique at target
        foreach ($childFolderNames as $childFolderName => $childFolderNode) {
            if ($childFolderNode->getProperty(self::TITLE_KEY) === $title) {
                throw new InvalidArgumentException('Folder with same title exists', 1677237583);
            }
            $i = 1;
            while ($nodeName === $childFolderName) {
                $nodeName = sprintf('%s-%03d', $initialNodeName, $i++);
            }
        }
        return $nodeName;
    }

    /**
     * flush and persist
     *
     * @api
     */
    public function persist(): void
    {
        $this->context->getFirstLevelNodeCache()->flush();
        $this->persistEntities();
    }

    /**
     *  Make titlePath for $folderNode
     *
     * @param NodeData $folderNode
     * @param string $title
     * @param array $dimensions
     *
     * @return string
     */
    protected function _titlePath(NodeData $folderNode, string $title, array $dimensions): string
    {
        $parentPath = $folderNode->getParentPath();
        $parentFolder = $this->findOneByPath($parentPath, $this->workspace, $dimensions);
        $titlePath = (!empty($parentFolder) && $parentFolder->hasProperty(FolderRepository::TITLE_PATH_KEY))
            ? $parentFolder->getProperty(FolderRepository::TITLE_PATH_KEY) : $parentPath;
        return NodePaths::addNodePathSegment($titlePath, $title);
    }


    /**
     * Set title and titlePath at folder
     *
     * @param NodeData $folderNode
     * @param string $title
     * @param array $dimensions
     *
     * @return void
     * @throws NodeException
     * @api
     */
    public function setTitleAndTitlePath(NodeData $folderNode, string $title, array $dimensions): void
    {
        $childFolderNodes = $this->findByParentAndNodeType($folderNode->getParentPath(), '', $this->workspace, $dimensions);
        foreach ($childFolderNodes as $childFolderNode) {
            if ($folderNode !== $childFolderNode && $title === $childFolderNode->getProperty(self::TITLE_KEY)) {
                throw new InvalidArgumentException('Folder with same title exists', 1677237583);
            }
        }
        $this->_setTitleAndTitlePath($folderNode, $title, $dimensions);
    }

    /**
     * @param NodeData $folderNode
     * @param string $title
     * @param array $dimensions
     *
     * @return void
     * @throws NodeException
     */
    protected function _setTitleAndTitlePath(NodeData $folderNode, string $title, array $dimensions): void
    {
        empty($title) && $title = $folderNode->getName();
        $folderNode->setProperty(self::TITLE_KEY, $title);
        $folderNode->setProperty(self::TITLE_PATH_KEY, $this->_titlePath($folderNode, $title, $dimensions));
        $childNodes = $this->findByParentAndNodeType($folderNode->getPath(), '', $this->workspace, $dimensions);
        foreach ($childNodes as $childNode) {
            $this->_setTitleAndTitlePath($childNode, $childNode->getProperty(self::TITLE_KEY), $dimensions);
        }
    }

    /**
     * Collect all folder entries matching to $bases (default: all)
     *
     * @param FolderProvider $providedFolder
     * @param array $dimensions
     * @param int $sortMode
     * @param bool $titlePathExtra
     *
     * @return array folder node tree of <token>
     * @throws NodeException
     * @api
     */
    public function getFolderTree(FolderProvider $providedFolder, array $dimensions = [], int $sortMode = SORT_NATURAL,
        bool $titlePathExtra = false): array
    {
        $rootPathDepth = $providedFolder->path === '/' ? 1 : count(explode('/', $providedFolder->titlePath));
        $folderData[$providedFolder->titlePath] = [$rootPathDepth, $providedFolder];
        $childNodes = $this->findByParentAndNodeTypeRecursively($providedFolder->path, '', $this->workspace, $dimensions);
        foreach ($childNodes as $childNode) {
            $providedFolder = (new FolderProvider())->new($childNode);
            $pathDepth = count(explode('/', $providedFolder->titlePath));
            $folderData[$providedFolder->titlePath] = [$pathDepth, $providedFolder];
        }
        if ($sortMode === SORT_STRING || $sortMode === SORT_NATURAL) {
            $sortMode += SORT_FLAG_CASE;
        }
        ksort($folderData, $sortMode);
        return $this->_buildFolderTree($folderData, $dimensions, $titlePathExtra, $rootPathDepth)[0];
    }

    /**
     * Get folder information:
     *
     *  - path, identifier, name, sorting index, variants
     *     - dimension, properties (title, titlePath, associations)
     *  - children
     *
     * @param array $folderData
     * @param array $dimensions
     * @param bool $titlePathExtra
     * @param int $depth
     *
     * @return array folder tree
     * @throws NodeException
     */
    protected function _buildFolderTree(array &$folderData, array $dimensions, bool $titlePathExtra, int $depth): array
    {
        $folderTree = [];
        while ([$pathDepth, $providedFolder] = current($folderData)) {
            if ($pathDepth != $depth) {
                break;
            }
            next($folderData);
            $temp = [];
            $temp[self::PATH_KEY] = $providedFolder->path;
            $temp[self::IDENTIFIER_KEY] = $providedFolder->identifier;
            $temp[self::NAME_KEY] = $providedFolder->node->getName() ?: '(root)';
            $titlePathExtra && $temp[self::TITLE_PATH_KEY] = $providedFolder->titlePath;
            $temp[self::NODE_TYPE_KEY] = $providedFolder->node->getNodeType()->getName();
            $temp[self::INDEX_KEY] = $providedFolder->node->getIndex();

            $folderVariants = $this->findByIdentifierWithoutReduce($providedFolder->identifier, $this->workspace);
            $temp[self::VARIANTS_KEY] = [];
            foreach ($folderVariants as $folderVariant) {
                $folderDimensions = $folderVariant->getDimensionValues();
                ksort($folderDimensions);
                if (empty($dimensions) || serialize($folderDimensions) === serialize($dimensions)) {
                    $temp[self::VARIANTS_KEY][] = [
                        self::DIMENSIONS_KEY => $folderDimensions,
                        self::PROPERTY_KEY => $folderVariant->getProperties(),
                    ];
                }
            }
            $temp[self::CHILDREN_KEY] = $this->_buildFolderTree($folderData, $dimensions, $titlePathExtra, $depth + 1);
            $folderTree[] = $temp;
        }
        return $folderTree;
    }

    /**
     * Remove a folder
     *
     * @param string $token Folder to remove
     * @param array $dimensions
     * @param bool $recursive true: remove recursively
     *
     * @return void
     * @throws NodeException
     * @api
     */
    public function removeFolder(string $token, array $dimensions, bool $recursive = false): void
    {
        $providedFolder = (new FolderProvider())->new($token, $dimensions);
        $childFolderNodes = $this->findByParentAndNodeType($providedFolder->path, '', $this->workspace, $dimensions);
        if (!$recursive && !empty($childFolderNodes)) {
            $dimension = FolderContext::dimensionString($dimensions);
            throw new NodeException("Folder \"$token\" dimensions \"$dimension\" not empty.", 1676996656);
        }
        $this->_remove($providedFolder, empty($dimensions), $recursive);
        $this->persist();
    }

    /**
     * Perform the removal of a folder
     *
     * @param FolderProvider $providedFolder
     * @param bool $allDimensions
     * @param bool $recursive
     *
     * @return void
     * @throws NodeException
     */
    protected function _remove(FolderProvider $providedFolder, bool $allDimensions, bool $recursive): void
    {
        if ($recursive) {
            $childNodes = $this->findByParentWithoutReduce($providedFolder->path, $this->workspace);
            foreach ($childNodes as $childNode) {
                $this->_remove((new FolderProvider())->new($childNode), $allDimensions, true);
            }
        }
        $providedDimensions = $providedFolder->node->getDimensionValues();
        $folderVariants = $this->findByIdentifierWithoutReduce($providedFolder->identifier, $this->workspace);
        foreach ($folderVariants as $folderVariant) {
            $variantDimensions = $folderVariant->getDimensionValues();
            if ($allDimensions
                || empty($variantDimensions) && empty($providedDimensions)
                || serialize($variantDimensions) === serialize($providedDimensions)) {
                $this->persistenceManager->remove($folderVariant);
            }
        }
    }

    /**
     * Move a folder
     *
     * Move a folder (and sub folders) identified by <token> to folder identified by <target>.
     * Folder titles and titlePath are kept. It is not possible to move a folder if a folder
     * with same title exists at <target>. The folder Node's path name may change.
     *
     * @param string $token Folder path or identifier to move
     * @param string $target Target path or identifier
     * @param array $dimensions Dimension: any of Neos.ContentRepository.contentDimensions
     *
     * @return string folder node path to moved folder
     * @throws NodeException
     * @api
     */
    public function moveFolder(string $token, string $target, array $dimensions): string
    {
        $providedSourceFolder = (new FolderProvider())->new($token, $dimensions);
        $providedTargetFolder = (new FolderProvider())->new($target, $dimensions);
        if (str_starts_with($providedTargetFolder->titlePath, $providedSourceFolder->titlePath)) {
            throw new InvalidArgumentException(
                sprintf($providedTargetFolder->titlePath === $providedSourceFolder->titlePath
                    ? 'Source token "%s" and target token "%s" are identical.'
                    : 'Source token "%s" is ancestor of target token "%s".', $token, $target), 1677233927);
        }
        if ($providedTargetFolder->path === $providedSourceFolder->node->getParentPath()) {
            throw new InvalidArgumentException(sprintf('Target token "%s" is parent of source token "%s".', $target, $token), 1677233930);
        }

        $nodeName = $this->_uniqueNodeName($providedTargetFolder->node, $providedSourceFolder->title, $dimensions);
        $newPath = NodePaths::addNodePathSegment($providedTargetFolder->path, $nodeName);
        $providedSourceFolder->node->setPath($newPath);

        $this->persist();
        $this->setTitleAndTitlePath($providedSourceFolder->node, $providedSourceFolder->title, $dimensions);
        $this->persist();
        return $newPath;
    }

    /**
     * Set or clear properties at a folder
     *
     * Set properties at a folder. Standard folder properties (title, titlePath, associations) are
     * excluded. Clears properties on option <--reset> included and <properties> empty ('').
     *
     * @param string $token Folder path or identifier to move
     * @param string $propertyString Json encoded properties
     * @param array $dimensions Dimension: any of Neos.ContentRepository.contentDimensions
     * @param bool $reset true: reset properties before set of new properties
     *
     * @return void
     * @throws NodeException
     * @api
     */
    public function setProperties(string $token, string $propertyString, array $dimensions, bool $reset = false): void
    {
        ($reset) && $this->clearAllProperties($token, $dimensions);
        $properties = $this->_validProperties((array)json_decode($propertyString));
        $providedFolder = (new FolderProvider())->new($token, $dimensions);
        foreach ($properties as $propertyName => $propertyValue) {
            $providedFolder->node->setProperty($propertyName, $propertyValue);
        }
        $this->persist();
    }

    /**
     * @param array $propertiesIn
     *
     * @return array
     */
    protected function _validProperties(array $propertiesIn): array
    {
        $propertiesOut = [];
        foreach (array_keys($propertiesIn) as $propertyName) {
            if (!in_array($propertyName, [self::TITLE_KEY, self::TITLE_PATH_KEY, $this->associationsPropertyKey])) {
                $propertiesOut[$propertyName] = $propertiesIn[$propertyName];
            }
        }
        return $propertiesOut;
    }

    /**
     * Clear properties at folder
     *
     * @param string $token Folder path or identifier to move
     * @param array $dimensions Dimension: any of Neos.ContentRepository.contentDimensions
     *
     * @return void
     * @throws NodeException
     * @api
     */
    public function clearAllProperties(string $token, array $dimensions): void
    {
        $providedFolder = (new FolderProvider())->new($token, $dimensions);
        $properties = $this->_validProperties($providedFolder->node->getProperties());
        array_walk($properties, function ($property, $propertyName) use ($providedFolder) {
            $providedFolder->node->removeProperty($propertyName);
        });
    }

    /**
     * Set or clear association
     *
     * Set or clear association to <token> folder into <target> folder. Arguments work
     * like setting a symbolic link: "ln -s <token> <target>" or removing it: "rm <target>"
     *
     * @param string $token Folder path or identifier (folder to associate)
     * @param string $target Target path or identifier (where to set association)
     * @param array $dimensions Dimension: any of Neos.ContentRepository.contentDimensions
     * @param bool $remove true: dissociate (remove) <token> from <target>
     *
     * @throws NodeException
     * @api
     */
    public function associate(string $token, string $target, array $dimensions, bool $remove = false): void
    {
        $providedSourceFolder = (new FolderProvider())->new($token, $dimensions);
        $providedTargetFolder = (new FolderProvider())->new($target, $dimensions);
        if (is_null($associations = $providedTargetFolder->node->getProperty($this->associationsPropertyKey))) {
            $associations = [];
        }
        if ($remove) {
            $associations = array_diff($associations, [$providedSourceFolder->identifier]);
        } elseif (!in_array($providedSourceFolder->identifier, $associations)) {
            $associations[] = $providedSourceFolder->identifier;
        }
        $providedTargetFolder->node->setProperty($this->associationsPropertyKey, $associations);
        $this->persist();
    }

}
