<?php

declare(strict_types=1);

namespace Neos\Fusion\Tests\Unit\Migrations;

use Neos\Fusion\Migrations\FusionMigrationTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use PHPUnit\Framework\TestCase;

class FusionMigrationTest extends TestCase
{
    use FusionMigrationTrait;

    /**
     * @var array
     */
    protected $targetPackageData;

    public function setUp(): void
    {
        $this->targetPackageData = [];
        $this->eelReplacementOperations = [];
    }

    /** @test */
    public function doesNoReplacementsIfPackageDoesNotContainFusionFiles(): void
    {
        $stream = vfsStream::setup('fusion', null, $expectedStructure = [
            "Target.Package" => [
                'Configuration' => [
                    'Settings.yaml' => <<<'YAML'
                    Target.Package:
                      someEelExpression: ${someVariable}
                    YAML,
                ],
                'Resources' => [
                    'Private' => [
                        'SomeTemplate.html' => 'I contain also ${someVariable} but am not fusion'
                    ]
                ],
                'Readme.md' => 'My superb package!'
            ]
        ]);

        $this->targetPackageData = [
            'path' => 'vfs://fusion/Target.Package'
        ];

        $this->replaceEelExpression('/someVariable/', 'newVariable');

        $this->applyEelFusionOperations();

        $structureVisitor = new vfsStreamStructureVisitor();
        $structureVisitor->visit($stream->getChild('Target.Package'));
        $newStructure = $structureVisitor->getStructure();

        self::assertEquals(
            $expectedStructure,
            $newStructure
        );
    }

    /** @test */
    public function doesNoReplacementsIfNoFusionFilesNeedAdjustments(): void
    {
        $stream = vfsStream::setup('fusion', null, $expectedStructure = [
            "Target.Package" => [
                'Resources' => [
                    'Private' => [
                        'Fusion' => [
                            'SomeComponent.fusion' => <<<'Fusion'
                            prototype(Neos.Fusion.Test:Value) < prototype(Neos.Fusion:Value) {
                                value = ${anotherVariable}
                            }
                            Fusion
                        ]
                    ]
                ],
            ]
        ]);

        $this->targetPackageData = [
            'path' => 'vfs://fusion/Target.Package'
        ];

        $this->replaceEelExpression('/someVariable/', 'newVariable');

        $this->applyEelFusionOperations();

        $structureVisitor = new vfsStreamStructureVisitor();
        $structureVisitor->visit($stream->getChild('Target.Package'));
        $newStructure = $structureVisitor->getStructure();

        self::assertEquals(
            $expectedStructure,
            $newStructure
        );
    }

    /** @test */
    public function doesReplacementInAllFusionFilesOfPackage(): void
    {
        $stream = vfsStream::setup('fusion', null, [
            "Target.Package" => [
                'Resources' => [
                    'Private' => [
                        'Fusion' => [
                            'SomeComponent.fusion' => <<<'Fusion'
                            prototype(Neos.Fusion.Test:Value) < prototype(Neos.Fusion:Value) {
                                value = ${someVariable}
                            }
                            Fusion
                        ]
                    ]
                ],
            ]
        ]);

        $this->targetPackageData = [
            'path' => 'vfs://fusion/Target.Package'
        ];

        $this->replaceEelExpression('/someVariable/', 'newVariable');

        $this->applyEelFusionOperations();

        $structureVisitor = new vfsStreamStructureVisitor();
        $structureVisitor->visit($stream->getChild('Target.Package'));
        $newStructure = $structureVisitor->getStructure();

        $expectedStructure = [
            "Target.Package" => [
                'Resources' => [
                    'Private' => [
                        'Fusion' => [
                            'SomeComponent.fusion' => <<<'Fusion'
                            prototype(Neos.Fusion.Test:Value) < prototype(Neos.Fusion:Value) {
                                value = ${newVariable}
                            }
                            Fusion
                        ]
                    ]
                ],
            ]
        ];

        self::assertEquals(
            $expectedStructure,
            $newStructure
        );
    }
}
