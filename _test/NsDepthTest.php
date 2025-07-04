<?php

namespace dokuwiki\plugin\advanced\test;

use DokuWikiTest;

/**
 * tests for the advanced plugin
 *
 * @group plugin_advanced
 * @group plugins
 */
class NsDepthTest extends DokuWikiTest
{
    /**
     * @return array (searchParams [ns, recursive, type], expect)
     */
    public function providerParams()
    {
        return [
            [['', false, 'pages'], 1],
            [['', false, 'media'], 1],
            [['', true, 'pages'], 0],
            [['', true, 'media'], 0],
            [['test', false, 'pages'], 2],
            [['test', false, 'media'], 1],
            [['test', true, 'pages'], 0],
            [['test', true, 'media'], 0],
            [['test/test1/test2/test3/test4', false, 'pages'], 6],
            [['test/test1/test2/test3/test4', false, 'media'], 1],
            [['test/test1/test2/test3/test4', true, 'pages'], 0],
            [['test/test1/test2/test3/test4', true, 'media'], 0],
        ];
    }

    /**
     * @dataProvider providerParams
     *
     * @param array $searchParams
     * @param int $expect
     * @return void
     */
    public function testNsDepth($searchParams, $expect)
    {
        $this->assertEquals($expect, \admin_plugin_advanced_export::getSearchDepth($searchParams[0], $searchParams[1], $searchParams[2]));
    }
}
