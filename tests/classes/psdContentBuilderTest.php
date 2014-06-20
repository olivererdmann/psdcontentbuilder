<?php
/**
 * Test-cases for psdNContentBuilder.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 20.06.2014
 */


class psdContentBuilderTest  extends \PHPUnit_Framework_TestCase
{

    use Xpmock\TestCaseTrait;

    protected $testInclude = '/../fixtures/contentbuilder_test.yaml';


    /**
     * Test the parameterBag-feature which replaces variables in YAML-Files.
     *
     * @return void
     */
    public function testParameterBag()
    {

        $variables = [
            'test_name'  => 'Contentbuilder Test',
            'test_key'   => 'short_title',
            'test_child' => '{name: Test Article, class: article, remote_id: test_article}'
        ];

        $contentBuilder = new psdContentBuilder();
        $contentBuilder->parameterBag->add($variables);
        $contentBuilder->loadFromFile(__DIR__ . $this->testInclude);

        $structure = $contentBuilder->getStructure();

        $item = $structure['content'][0];

        $this->assertEquals($item['name'], $variables['test_name']);
        $this->assertArrayHasKey($variables['test_key'], $item);
        $this->assertEquals($item['children']['name'], 'Test Article');
        $this->assertEquals($item['children']['remote_id'], 'test_article');


    }


}
