<?php

namespace Neos\Folder\Domain\Service;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use InvalidArgumentException;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Service\ConfigurationContentDimensionPresetSource;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\ContentRepository\Exception as CrException;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Exception;
use Neos\Flow\Security\Exception\InvalidPrivilegeException;
use Neos\Folder\Domain\Repository\FolderRepository;

/**
 * Context for Neos Folders
 *
 * @Flow\Scope("singleton")
 * @package "Neos.Folder"
 * @api
 */
class FolderContext extends FolderRepository
{
    /**
     * Convert json dimension string to dimension array and compared to $this->subGraphDimensions
     *
     * - any contentDimension name must be unique
     * - contentDimension value is an array of strings (or whatsoever is defined in Neos.ContentRepository.contentDimensions)
     *
     * dimension value string has format:
     *
     *  <dimension name1>=<dimensions value>{,<dimension name2>=<dimensions value>}
     *
     * @param string $value
     *
     * @return array dimensions
     * @throws InvalidArgumentException
     * @api
     */
    public function verifyAndConvertDimensions(string $value): array
    {
        empty($value) && throw new InvalidArgumentException('Empty dimension(s)', 1676315508);
        $dimensions = NodePaths::parseDimensionValueStringToArray($value);
        $dimensionsToTest = [];
        foreach ($dimensions as $dimensionName => $dimensionValue) {
            $dimensionsToTest[$dimensionName] = join(',', $dimensionValue);
        }
        if ((new ConfigurationContentDimensionPresetSource())->isPresetCombinationAllowedByConstraints($dimensionsToTest)) {
            ksort($dimensions);
        } else {
            throw new InvalidArgumentException('Invalid dimension(s) "' . self::dimensionString($dimensions) . '"', 1676315236);
        }
        return $dimensions;
    }

    /**
     * Get default dimensions
     *
     * @return array default dimensions
     * @api
     */
    public function getDefaultDimensions(): array
    {
        return $this->context->getDimensions();
    }

    /**
     * Adopt a folderNode having source dimensions to target dimensions
     *
     * @param string $token
     * @param array $sourceDimensions
     * @param array $targetDimensions
     * @param bool $recursive
     *
     * @return NodeData adopted folder node
     * @throws NodeException
     * @throws Exception
     * @throws InvalidPrivilegeException
     * @throws CrException
     * @api
     */
    public function adoptFolder(string $token, array $sourceDimensions, array $targetDimensions, bool $recursive = true): NodeData
    {
        $providedSourceFolder = (new FolderProvider())->new($token, $sourceDimensions);
        $sourcePath = $providedSourceFolder->path;
        $sourceNode = $providedSourceFolder->node;
        $sourceDimensions = $sourceNode->getDimensionValues();
        if (!empty($sourceDimensions) && !empty($this->findOneByPath($sourcePath, $sourceNode->getWorkspace(), $targetDimensions))) {
            $dimension = self::dimensionString($targetDimensions);
            throw new NodeException("Variant \"$dimension\" of folder \"$sourcePath\" exists.", 1676996654);
        }
        $sourceContext = $this->contextFactory->create([
            'workspaceName' => $sourceNode->getWorkspace()->getName(),
            'dimensions' => $sourceDimensions
        ]);
        $targetContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => $targetDimensions,
        ]);
        $sourceNode = $sourceContext->getNodeByIdentifier($providedSourceFolder->identifier);
        $targetNode = $targetContext->adoptNode($sourceNode, $recursive)->getNodeData();
        $this->setTitleAndTitlePath($targetNode, $sourceNode->getProperty(self::TITLE_KEY), $targetDimensions);
        $this->persist();
        return $targetNode;
    }

    /**
     * @param array $dimensions
     *
     * @return string dimensions converted to string
     */
    public static function dimensionString(array $dimensions): string
    {
        $dimensionStrings = [];
        foreach ($dimensions as $dimensionName => $dimensionValues) {
            $dimensionStrings[] = $dimensionName . '=' . join(',', $dimensionValues);
        }
        return join('&', $dimensionStrings);
    }
}
