<?php
/**
 * Test-cases for psdIncludeYamlFunctionHandler.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since  27.02.14
 */


class psdIncludeYamlFunctionHandlerTest  extends \PHPUnit_Framework_TestCase
{

    use Xpmock\TestCaseTrait;

    /**
     * @var string
     */
    protected $testFile = '/../../fixtures/include_test.yaml';

    /**
     * @var string
     */
    protected $testInclude = '/../../fixtures/include_test/include_file.yaml';

    /**
     * Explicit function-handlers provided to the post-processor. This overrides the settings in psdcontentbuilder.ini.
     *
     * @var array
     */
    protected $functionHandlers = [
        'include' => 'psdIncludeYamlFunctionHandler'
    ];

    /**
     * @var psdContentBuilder
     */
    protected $contentBuilder;

    /**
     * Include handler to be tested against. Created by setUp()-method.
     * @var psdIncludeYamlFunctionHandler
     */
    protected $includeHandler;


    /**
     * SetUp, called before every test.
     *
     * @return void
     */
    protected function setUp()
    {

        $this->includeHandler = new psdIncludeYamlFunctionHandler();
        $this->contentBuilder = new \psdContentBuilder(__DIR__.$this->testFile);

        $this->contentBuilder->loadPostProcessor($this->functionHandlers);
        $this->includeHandler->builder = $this->contentBuilder;
    }


    /**
     * Tests if the Include-Handler fails if a string is passed instead of an array.
     *
     * Expects to throw an exception.
     *
     * @return void
     */
    public function testIncludeHandlerWrongParameter()
    {

        try {
            $this->includeHandler->apply('include', 'file');
            $this->fail('Exception should be thrown: parameters must not be string.');
        } catch (\Exception $e) {
            $this->assertContains('Include file not defined. Use the "file"-key.', $e->getMessage());
        }

    }


    /**
     * Tests if the handler fails when passing a valid array with a non-existent filename.
     *
     * Expected to fail.
     *
     * @return void
     */
    public function testResolveIncludeFilenameNotExists()
    {

        $path = __DIR__.'/../../fixtures/does-not-exist.yaml';

        try {
            $this->includeHandler->apply('include', array('file' => $path));
            $this->fail('Exception should be thrown: file does not exists.');
        } catch (\Exception $e) {
            $this->assertContains('Include file not found or not readable', $e->getMessage());
        }

    }


    /**
     * Tests if the handler succeeds if an existing file is passed in a valid array.
     * The handler just loads and parses the YAML, without evaluating functions.
     *
     * Expected to succeed.
     *
     * @return void
     */
    public function testResolveIncludeFilenameExists()
    {

        //  - function: include
        //    file:     include_test/include_file.yaml
        $expected = [
            [
                'function' => 'include',
                'file'     => 'include_test/include_file.yaml'
            ]
        ];

        $result = $this->includeHandler->apply('include', array('file' => __DIR__.$this->testFile));

        // Only check the content-key and compare. This time, the yaml-function is not resolved
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals($result['content'], $expected);

    }


    /**
     * Tests the actual include-function by evaluating function-handles with the help of the Content-Builder.
     * This test also validates working merge-keys across includes.
     * We test against the included and merged content-structure.
     *
     * Expects the content-key to contain the items of the merge-key.
     *
     * @return void
     */
    public function testIncludeSuccess()
    {

        // - children:
        //     - name: Include Child 1
        //     - name: Include Child 2
        //     - name: Include Child 3
        //   name: Include File
        $expected = [
            [
                'children' => [
                    ['name' => 'Include Child 1'],
                    ['name' => 'Include Child 2'],
                    ['name' => 'Include Child 3'],
                ],
                'name'     => 'Include File'
            ]
        ];

        $this->contentBuilder->loadFromFile(__DIR__.$this->testFile);
        $result = $this->contentBuilder->postProcess($this->contentBuilder->getStructure());

        // Only check the content-key and compare. This time, the yaml-function is not resolved
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals($result['content'], $expected);

    }
}
