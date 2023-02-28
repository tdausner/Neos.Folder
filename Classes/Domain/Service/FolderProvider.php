<?php

namespace Neos\Folder\Domain\Service;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use InvalidArgumentException;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\ContentRepository\Exception as NodeException;
use Neos\Flow\Validation\Validator\UuidValidator;
use Neos\Folder\Domain\Repository\FolderRepository;
use Neos\Neos\Domain\Service\SiteService;

/**
 * @package "Neos.Folder"
 */
class FolderProvider
{
    /**
     * regular expression for node path
     */
    final public const PATTERN_MATCH_PATH = '/^(\/([a-z0-9](-[a-z0-9])*)*)+$/';

    /**
     * @param ?NodeData $node
     */
    public ?NodeData $node;
    /**
     * @param ?string $identifier
     */
    public ?string $identifier;
    /**
     * @param ?string $path
     */
    public ?string $path;
    /**
     * @param ?string $title folder title (-> variants)
     */
    public ?string $title;
    /**
     * @param string $titlePath path made from titles (-> variants)
     */
    public ?string $titlePath;

    /**
     * initialize folder node properties
     */
    public function __construct()
    {
        $this->node = null;
        $this->identifier = '';
        $this->path = '';
        $this->title = '';
        $this->titlePath = '';
    }

    /**
     * Provide folder node properties from <token> parameter represented by NodeData|path|identifier
     *
     * Returns FolderProvider, throws
     *  - NodeNotFoundException on <token> Node not found AND <testFolder> == false
     *  - InvalidArgumentException on <token> is '/' or '/sites'
     *
     * @param NodeData|string $token NodeData | path | identifier
     * @param array $dimensions
     * @param bool $testFolder true: don't throw exception on folder not found
     *
     * @return FolderProvider
     * @throws NodeException
     * @throws InvalidArgumentException
     */
    public function new(NodeData|string $token, array $dimensions = [], bool $testFolder = false): FolderProvider
    {
        if ($token instanceof NodeData) {
            $this->node = $token;
            $this->identifier = $this->node->getIdentifier();
            $this->path = $this->node->getPath();
        } else {
            $contentFactory = new ContextFactory();
            $context = $contentFactory->create(['workspaceName' => 'live', 'dimensions' => $dimensions]);
            $this->node = null;
            if (preg_match(UuidValidator::PATTERN_MATCH_UUID, $token)) {
                // token is identifier
                $this->node = ($context->getNodeByIdentifier($token))?->getNodeData();
                $this->identifier = $token;
                $this->path = ($this->node)?->getPath();
            } elseif (preg_match(self::PATTERN_MATCH_PATH, Diacritics::path($token))) {
                // token is path
                $this->node = ($context->getNode(Diacritics::path($token)))?->getNodeData();
                $this->identifier = ($this->node)?->getIdentifier();
                $this->path = Diacritics::path($token);
            }
        }
        if (!$testFolder && empty($this->node)) {
            throw new NodeException(sprintf('Folder token "%s" dimensions "%s" not found', $token, FolderContext::dimensionString($dimensions)), 1676631409);
        }
        if (!empty($this->node)) {
            if ($this->path === '/' || str_starts_with($this->path, SiteService::SITES_ROOT_PATH)) {
                throw new InvalidArgumentException("Folder token \"$this->path ($this->identifier)\" invalid.", 1676388552);
            }
            try {
                $this->title = $this->node->getProperty(FolderRepository::TITLE_KEY);
                $this->titlePath = $this->node->getProperty(FolderRepository::TITLE_PATH_KEY);
            } catch (NodeException) {
                // properties not found, set default values for properties
                $this->title = $this->node->getName();
                $this->titlePath = $this->path;
            }
        }
        return $this;
    }

}
