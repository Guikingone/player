<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Player\Tests;

use Blackfire\Player\Parser;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\VisitStep;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParsingSeparatedScenario()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
scenario Test 1
    set env "prod"
    endpoint 'http://toto.com'

    # A comment
    visit url('/blog/')
        expect "prod" == env

scenario Test2
    reload
EOF
);
        $this->assertCount(2, $scenarioSet);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('Test 1', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $scenario->getVariables());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('Test2', $scenario->getKey());
        $this->assertInstanceOf(ReloadStep::class, $scenario->getBlockStep());
        $this->assertEquals([], $scenario->getVariables());
    }

    public function testParsingGlobalConfiguration()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set env "prod"
endpoint 'http://toto.com'

scenario Test 1
    # A comment
    visit url('/blog/')
        header "Accept-Language: en-US"
        samples 10
        expect "prod" == env

scenario Test2
    reload
EOF
        );
        $this->assertCount(2, $scenarioSet);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('Test 1', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $scenario->getVariables());
        $this->assertEquals([
            '"Accept-Language: en-US"',
        ], $scenario->getBlockStep()->getHeaders());
        $this->assertEquals(10, $scenario->getBlockStep()->getSamples());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('Test2', $scenario->getKey());
        $this->assertInstanceOf(ReloadStep::class, $scenario->getBlockStep());
        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $scenario->getVariables());
    }

    public function testWarmupStepConfig()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
scenario Test 1
    # A comment
    visit url('/blog/')
        warmup true

scenario Test 2
    # A comment
    visit url('/blog/')
        warmup false

scenario Test 3
    # A comment
    visit url('/blog/')
        warmup 'auto'
EOF
        );

        $this->assertCount(3, $scenarioSet);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('true', $scenario->getBlockStep()->getWarmup());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('false', $scenario->getBlockStep()->getWarmup());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[2];
        $this->assertEquals('\'auto\'', $scenario->getBlockStep()->getWarmup());
    }

    /**
     * @dataProvider provideDocSamples
     */
    public function testDocSamples($input)
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse($input);

        $this->assertInstanceOf(ScenarioSet::class, $scenarioSet);
    }

    public function provideDocSamples()
    {
        yield [<<<'EOF'
scenario
    name "Scenario Name"
    endpoint "http://example.com/"

    visit url('/')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        expect status_code() == 200

    visit url('/blog/')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        expect status_code() == 200

    click link('Read more')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
# This is a comment
scenario
    # Comment are ignored
    visit url('/')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        method 'POST'
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        method 'PUT'
        body '{ "title": "New Title" }'
EOF
        ];

        yield [<<<'EOF'
scenario
    click link("Add a blog post")
EOF
        ];

        yield [<<<'EOF'
scenario
    submit button("Submit")
        param title 'Happy Scraping'
        param content 'Scraping with Blackfire Player is so easy!'

        # File Upload:
        # the path is relative to the current .bkf file
        # the name parameter is optional
        param image file('relative/path/to/image.png', 'blackfire.png')
EOF
        ];

        yield [<<<'EOF'
scenario
    submit button("Submit")
        param title fake('sentence', 5)
        param content join(fake('paragraphs', 3), "\n\n")
EOF
        ];

        yield [<<<'EOF'
scenario
    visit "redirect.php"
        expect status_code() == 302
        expect header('Location') == '/redirected.php'
EOF
        ];

        yield [<<<'EOF'
scenario
    visit "redirect.php"
        expect status_code() == 302
        expect header('Location') == '/redirected.php'

    follow
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    follow_redirects true
EOF
        ];

        yield [<<<'EOF'
scenario
    visit "redirect.php"
        follow_redirects
EOF
        ];

        yield [<<<'EOF'
group login
    visit url('/login')
        expect status_code() == 200

    submit button('Login')
        param user 'admin'
        param password 'admin'
EOF
        ];

        // Adapted
        yield [<<<'EOF'
load "Player/Tests/fixtures/bkf/group/group.bkf"

scenario
    name "Scenario Name"

    include homepage

    visit url('/admin')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        header "Accept-Language: en-US"
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        header 'User-Agent: ' ~ fake('firefox')
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        auth "username:password"
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        wait 10000
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        wait fake('numberBetween', 1000, 3000)
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        method 'POST'
        param foo "bar"
        json true
EOF
        ];

        yield [<<<'EOF'
scenario
    auth "username:password"
    header "Accept-Language: en-US"
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        header "Accept-Language: false"
        auth false
EOF
        ];

        yield [<<<'EOF'
scenario
    name "Scenario Name"
     # Use the environment name (or UUID) you're targeting or false to disable
    blackfire "Environment name"
EOF
        ];

        yield [<<<'EOF'
scenario
    name "Scenario Name"
    # Use the environment name (or UUID) you're targeting or false to disable
    blackfire true
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        expect status_code() == 200
        set latest_post_title css(".post h2").first()
        set latest_post_href css(".post h2 a").first().attr("href")
        set latest_posts css(".post h2 a").extract('_text', 'href')
        set age header("Age")
        set content_type header("Content-Type")
        set token regex('/name="_token" value="([^"]+)"/')
EOF
        ];

        // Adapted
        yield [<<<'EOF'
set api_username "user"
set api_password "password"

scenario
    name "Scenario name"
    auth api_username ~ ':' ~ api_password
    set profile_uuid 'zzzz'

    visit url('/profiles' ~ profile_uuid)
        expect status_code() == 200
        set sql_queries json('arguments."sql.pdo.queries".keys(@)')
        set store_url json("_links.store.href")

    visit url(store_url)
        method 'POST'
        body '{ "foo": "batman" }'
        expect status_code() == 200
EOF
        ];
    }
}