<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoSeedCommandTest extends ApiTestCase
{
    public function testSeedingAgainReplacesTheDemoWorkspaceWithAFreshCopy(): void
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'delete', 'type' => 'tasks', 'id' => 't4']]]);

        $command = new CommandTester((new Application(self::$kernel))->find('app:demo:seed'));
        $command->execute([]);

        $command->assertCommandIsSuccessful();
        $this->signIn();
        $tasks = $this->getJson('/api/bootstrap')['data']['tasks'];
        self::assertCount(48, $tasks);
        self::assertContains('t4', array_column($tasks, 'id'));
    }

    /** The demo Workspace is the demo Members' one, whatever it was renamed to. */
    public function testSeedingAgainReplacesARenamedDemoWorkspace(): void
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'set', 'type' => 'ws', 'data' => ['name' => 'Gr8r Labs', 'url' => 'gr8r-labs', 'c' => '#5A67D8', 'brand' => true]]]]);
        self::assertResponseStatusCodeSame(200);

        $command = new CommandTester((new Application(self::$kernel))->find('app:demo:seed'));
        $command->execute([]);

        $command->assertCommandIsSuccessful();
        $this->signIn();
        self::assertSame(['name' => 'Gr8r Studio', 'url' => 'gr8rstudio', 'c' => '#1D1C1A', 'brand' => true], $this->getJson('/api/bootstrap')['data']['ws']);
    }
}
