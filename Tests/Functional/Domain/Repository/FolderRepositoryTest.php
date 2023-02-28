<?php

namespace Neos\Folders\Tests\Functional\Domain\Repository;

/*
 * This file is part of the Neos.Folders package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Exception\NodeConfigurationException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;

use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Folders\Domain\Repository\FolderRepository;

/**
 * Functional test case.
 */
class FolderRepositoryTest extends FunctionalTestCase
{
    protected const TEST_FOLDERS = [
        ["/folders-test", null],
        ["/folders-test/level-one", null],
        ["/folders-test/level-one/level-one-one", [
            "fee" => "fyi",
            "foe" => "foo",
            "bar" => "baz",
        ]],
        ["/folders-test/level-two", null],
        ["/folders-test/level-two/level-two-one", null],
        ["/folders-test/level-two/level-two-two", null],
    ];
    protected const FOLDER_TYPE = 'Neos.Folders:Folder';

    /**
     * @var ContextFactoryInterface
     */
    protected ContextFactoryInterface $contextFactory;
    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @var NodeDataRepository
     */
    protected NodeDataRepository $nodeDataRepository;

    /**
     * This method is called before the first test of this test class is run.
     */
    public function setUp(): void
    {
        parent::setUp();
        $workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
        $workspaceRepository->add(new Workspace('folder-test'));
        $this->contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        $this->context = $this->contextFactory->create(['workspaceName' => 'folder-test']);
        $this->nodeDataRepository = new NodeDataRepository();
    }

    /**
     * @throws NodeTypeNotFoundException
     * @throws NodeConfigurationException
     */
    public function testCreateOrFindFolderNode(): void
    {
        $folderRepository = $this->objectManager->get(FolderRepository::class);
        $rootPath = self::TEST_FOLDERS[0][0];

        $folder = $folderRepository->createOrFindFolderNodeByPath($rootPath, self::FOLDER_TYPE);
    }

    /**
     * @ depends testCreateOrFindFolderNode
     * @test
     */
    public function testFindOneFolderByPath(): void
    {
        $rootPath = self::TEST_FOLDERS[0][0];
    }
/*
    public function testFindFoldersByParentPath(string $path): void
    {
    }

    public function testNodeTypeByName(?string $nodeTypeName): void
    {
    }


    public function testMoveFolder(NodeData $folderNode, string $destinationPath): void
    {
    }

    public function testRemoveFolder(NodeData $folderNode): void
    {
    }

    public function testGetFolderTree(string $types): void
    {
    }

    public function testClearAllProperties(NodeData $folderNode): void
    {
    }

    public function testSetProperties(NodeData $folderNode, array $properties): void
    {
    }
*/
}
