<?php
/**
 * Test-cases for psdNodeBuilderNodeInfo.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 24.04.2014
 */


class psdNodeBuilderNodeInfoTest_OFF  extends \PHPUnit_Framework_TestCase
{

    use Xpmock\TestCaseTrait;


    protected $script;


    public function setUp()
    {

        $scriptSettings                   = array();
        $scriptSettings['description']    = 'Tests psdNodeBuilder';
        $scriptSettings['use-session']    = false;
        $scriptSettings['use-modules']    = true;
        $scriptSettings['use-extensions'] = true;
        $scriptSettings['site-access']    = 'prosieben_admin'; // @TODO: get valid test-siteaccess

        $this->script = eZScript::instance($scriptSettings);
        $this->script->startup();

        $config             = '';
        $argumentConfig     = '';
        $optionHelp         = false;
        $arguments          = false;
        $useStandardOptions = true;

        $options = $this->script->getOptions($config, $argumentConfig, $optionHelp, $arguments, $useStandardOptions);
        $this->script->initialize();

    }

    public function tearDown()
    {

        $this->script->shutdown();

    }

    /**
     * Tests the construction of psdNodeBuilderNodeInfo with multiple provided languages.
     *
     */
    public function testConstructor()
    {

        $this->addPrioritizedLanguage('ger-DE');
        $this->addPrioritizedLanguage('ger-AT');
        $this->addPrioritizedLanguage('ger-CH');

        // Test Constructor from priorized languages.
        $info = new psdNodeBuilderNodeInfo();
        $this->assertConstructor($info);

        // Test Constructor from provided langugages.
        $info = new psdNodeBuilderNodeInfo(['ger-DE', 'ger-AT', 'ger-CH']);
        $this->assertConstructor($info);

    }


    /**
     * Asserts the constructor by checking fields and customFields for each provided language.
     *
     * @param psdNodeBuilderNodeInfo $info Initialized info-structure.
     * @return void
     */
    protected function assertConstructor($info)
    {

        $this->assertTrue(true, is_array($info->fields['ger-DE']) && empty($info->fields['ger-DE']));
        $this->assertTrue(true, is_array($info->fields['ger-AT']) && empty($info->fields['ger-AT']));
        $this->assertTrue(true, is_array($info->fields['ger-CH']) && empty($info->fields['ger-CH']));

        $this->assertTrue(true, is_array($info->customFields['ger-DE']) && empty($info->fields['ger-DE']));
        $this->assertTrue(true, is_array($info->customFields['ger-AT']) && empty($info->fields['ger-AT']));
        $this->assertTrue(true, is_array($info->customFields['ger-CH']) && empty($info->fields['ger-CH']));

    }


    /**
     * Mocks eZContentLanguage::setPrioritizedLanguage() but without DB-connection.
     *
     * @param string $locale Locale to add, this is also used as name.
     * @return void
     */
    protected function addPrioritizedLanguage($locale)
    {
        if (!isset($GLOBALS['eZContentLanguagePrioritizedLanguages'])) {
            $GLOBALS['eZContentLanguagePrioritizedLanguages'] = [];
        }

        $id = count($GLOBALS['eZContentLanguagePrioritizedLanguages']);

        $GLOBALS['eZContentLanguagePrioritizedLanguages'][] = new eZContentLanguage(
            ['id' => $id, 'locale' => $locale, 'name' => $locale, 'disabled' => 0]
        );

    }


    /**
     * Tests psdNodeBuilderNodeInfo->addFieldWithLanguage().
     *
     * @return void
     */
    public function testAddFieldWithLanguage()
    {

        // Mocks eZLocale::currentLocaleCode();
        $GLOBALS["eZLocaleStringDefault"] = 'ger-DE';

        $info = new psdNodeBuilderNodeInfo(['ger-DE', 'ger-AT', 'ger-CH']);

        // Test add Field with custom language.
        $info->addFieldWithLanguage('ch-field', 'ch value', 'ger-CH');
        $this->assertEquals('ch value', $info->fields['ger-CH']['ch-field']);

        // Test add Field with default language.
        $info->addFieldWithLanguage('de-field', 'de value');
        $this->assertEquals('de value', $info->fields['ger-DE']['de-field']);

        // Test default language (first language added).
        $this->assertEquals('ger-CH', $info->options['language']);

    }


    /**
     * Tests psdNodeBuilderNodeInfo->addCustomFieldWithLanguage().
     *
     * @return void
     */
    public function testAddCustomFieldWithLanguage()
    {

        $info = new psdNodeBuilderNodeInfo(['ger-DE', 'ger-AT', 'ger-CH']);

        // Test add Field with default language.
        $info->addCustomFieldWithLanguage('de-field', 'de value');
        $this->assertEquals('de value', $info->customFields['ger-DE']['de-field']);

        // Test add Field with custom language.
        $info->addCustomFieldWithLanguage('ch-field', 'ch value', 'ger-CH');
        $this->assertEquals('ch value', $info->customFields['ger-CH']['ch-field']);

    }


    /**
     * Tests psdNodeBuilderNodeInfo->setPostPublishFields().
     *
     * @return void
     */
    public function setPostPublishFields()
    {

        $info = new psdNodeBuilderNodeInfo(['ger-DE', 'ger-AT', 'ger-CH']);

        $value = 'string-value';
        $expected = [$value];

        $info->setPostPublishFields($value);
        $this->assertEquals($expected, $info->postPublishFields);

        $value = ['array-value'];
        $info->setPostPublishFields($value);
        $this->assertEquals($value, $info->postPublishFields);
    }

}
