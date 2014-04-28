<?php
/**
 * Test-cases for psdNodeBuilder.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 24.04.2014
 */


class psdNodeBuilderTest  extends \PHPUnit_Framework_TestCase
{

    use Xpmock\TestCaseTrait;

    protected $languages = ['ger-DE', 'ger-CH', 'ger-AT'];

    protected $testInclude = '/../fixtures/nodebuilder_test.yaml';

    /**
     * Tests the construction of psdNodeBuilderNodeInfo with multiple provided languages.
     *
     * @return void
     */
    public function testGetObjectNameWithPattern()
    {

        $cases = [
            [
                'pattern' => '<name>',
                'structure' => [
                    'name' => 'Object Name'
                ],
                'expected'  => 'Object Name'
            ],
            [
                'pattern'   => '<name|other>',
                'structure' => [
                    'name' => 'Object Name'
                ],
                'expected'  => 'Object Name'
            ],

            [
                'pattern'   => '<name|other>',
                'structure' => [
                    'other' => 'Other Name'
                ],
                'expected'  => 'Other Name'
            ],
            [
                'pattern'   => '<subject> are <fields|streets> and the sky is <sky>',
                'structure' => [
                    'subject' => 'Fields',
                    'fields'  => 'green',
                    'sky'     => 'blue'
                ],
                'expected'  => 'Fields are green and the sky is blue'
            ],
            [
                'pattern'   => '<subject> are <fields|streets> and the sky is <sky>',
                'structure' => [
                    'subject' => 'Streets',
                    'streets' => 'grey',
                    'sky'     => 'golden'
                ],
                'expected'  => 'Streets are grey and the sky is golden'
            ],

        ];

        $builder = $this->mockNodeBuilder();

        foreach ($cases as $case) {

            $result = psdTestUtils::invokeHiddenMethod(
                $builder,
                'getObjectNameWithPattern',
                [$case['pattern'], $case['structure']]
            );

            $this->assertEquals($case['expected'], $result);

        }

    }


    /**
     * Test protected function getCreateNodeInfo().
     *
     * @return void
     */
    public function testGetCreateNodeInfo()
    {

        $contentBuilder = new psdContentBuilder();
        $contentBuilder->loadFromFile(__DIR__ . $this->testInclude);

        $structure   = $contentBuilder->getStructure();
        $nodeBuilder = $this->mockNodeBuilder();

        $fakeDTBuilders = [
            'ezboolean'    => null,
            'xrowmetadata' => null
        ];

        psdTestUtils::setHiddenProperty($nodeBuilder, 'contentBuilder', $contentBuilder);
        psdTestUtils::setHiddenProperty($nodeBuilder, 'dataTypeBuilders', $fakeDTBuilders);

        $info = psdTestUtils::invokeHiddenMethod($nodeBuilder, 'getCreateNodeInfo', $structure['content']);

        // Name comes from short_title
        $this->assertEquals('Short Test', $info->name);


        // Has exactly 1 child with remote-id 'test:data'
        $this->assertEquals(count($info->children), 1);
        $this->assertEquals($info->children[0]['remote_id'], 'test:data');

        // Test post-publish fields.
        $this->assertEquals($info->postPublishFields, ['layout']);

        // Test class-identifier in options.
        $this->assertEquals($info->options['class_identifier'], 'frontpage');

        // Test Custom Fields.
        $this->assertArrayHasKey('has_navigation', $info->customFields['ger-DE']);
        $this->assertArrayHasKey('metadata', $info->customFields['ger-DE']);


        // Test fields
        $this->assertEquals('Test Content CH', $info->fields['ger-CH']['name']);
        $this->assertEquals('Test AT', $info->fields['ger-AT']['short_title']);

    }


    /**
     * Creates a mocked instance of psdNodeBuilder.
     * Omits calling the constructor and initializes the builder's properties with defaults.
     * Protected and private class-members are set public for this instance.
     *
     * @return psdNodeBuilder
     */
    protected function mockNodeBuilder()
    {

        $builder = $this->mock('psdNodeBuilder')->new();

        psdTestUtils::setHiddenProperty($builder, 'languages', $this->languages);
        psdTestUtils::setHiddenProperty($builder, 'defaultLanguage', 'ger-DE');
        psdTestUtils::setHiddenProperty($builder, 'systemLanguages', $this->languages);

        return $builder;

    }


}
